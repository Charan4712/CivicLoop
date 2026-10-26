<?php
require_once "db.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $issue_id = (int)($_POST["issue_id"] ?? 0);
    $new_status = $_POST["status"] ?? "";

    $allowedStatuses = [
        "Reported",
        "Acknowledged",
        "In Progress",
        "Resolved"
    ];

    if (
        $issue_id < 1 ||
        !in_array($new_status, $allowedStatuses, true)
    ) {
        $error = "Invalid issue or status.";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT status FROM issues
                 WHERE issue_id = ?
                 AND parent_issue_id IS NULL
                 FOR UPDATE"
            );
            $stmt->bind_param("i", $issue_id);
            $stmt->execute();
            $stmt->bind_result($old_status);

            if (!$stmt->fetch()) {
                $stmt->close();
                throw new Exception("Issue not found.");
            }
            $stmt->close();

            if ($old_status !== $new_status) {
                $stmt = $conn->prepare(
                    "UPDATE issues
                     SET status = ?,
                         resolved_at = CASE
                             WHEN ? = 'Resolved'
                             THEN CURRENT_TIMESTAMP
                             ELSE NULL
                         END
                     WHERE issue_id = ?
                     AND parent_issue_id IS NULL"
                );
                $stmt->bind_param(
                    "ssi",
                    $new_status,
                    $new_status,
                    $issue_id
                );
                $stmt->execute();
                $stmt->close();

                $note = "Status changed through Manage Issues";

                $stmt = $conn->prepare(
                    "INSERT INTO issue_history
                     (issue_id, old_status, new_status, note)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "isss",
                    $issue_id,
                    $old_status,
                    $new_status,
                    $note
                );
                $stmt->execute();
                $stmt->close();

                $message = "Status updated and history recorded.";
            } else {
                $message = "Status is already " . $new_status . ".";
            }

            $conn->commit();

        } catch (Throwable $e) {
            $conn->rollback();
            error_log("CivicLoop: " . $e->getMessage());
            $error = "Could not update the issue. Please try again.";
        }
    }
}

$sql = "SELECT i.issue_id, i.title, i.status, i.priority,
               a.area_name, c.category_name
        FROM issues i
        JOIN areas a ON i.area_id = a.area_id
        JOIN categories c ON i.category_id = c.category_id
        WHERE i.parent_issue_id IS NULL
        ORDER BY i.created_at DESC";

$issues = $conn->query($sql);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Issues | CivicLoop</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #f3f6fa;
    color: #172033;
}
header {
    background: #10243a;
    color: white;
    padding: 22px 7%;
}
header h1 { margin: 0 0 8px; }
header a { color: #c6d5e5; text-decoration: none; }
main { max-width: 950px; margin: 35px auto; padding: 0 20px; }
.card {
    background: white;
    padding: 22px;
    margin-bottom: 16px;
    border-radius: 12px;
    box-shadow: 0 3px 14px #1720330b;
}
.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}
.meta { color: #64748b; font-size: 14px; }
select, button {
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
}
button {
    background: #0f766e;
    color: white;
    border: 0;
    cursor: pointer;
}
.success { color: #166534; }
.error { color: #b91c1c; }
</style>
</head>
<body>
<header>
    <h1>CivicLoop</h1>
    <a href="index.php">← Dashboard</a>
</header>

<main>
    <h2>Manage Community Issues</h2>
    <p>Update the progress of reported problems.</p>

    <?php if ($message !== ""): ?>
        <p class="success"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php while ($issue = $issues->fetch_assoc()): ?>
        <section class="card">
            <div class="row">
                <div>
                    <h3><?= e($issue["title"]) ?></h3>
                    <p class="meta">
                        Report #<?= (int)$issue["issue_id"] ?> ·
                        <?= e($issue["category_name"]) ?> ·
                        <?= e($issue["area_name"]) ?>
                    </p>
                    <p>Priority: <?= e($issue["priority"]) ?></p>
                    <p>Current status:
                        <strong><?= e($issue["status"]) ?></strong>
                    </p>
                </div>

                <form method="POST" action="manage.php">
                    <input type="hidden" name="issue_id"
                           value="<?= (int)$issue["issue_id"] ?>">

                    <label for="status-<?= (int)$issue["issue_id"] ?>">
                        New status
                    </label>

                    <select id="status-<?= (int)$issue["issue_id"] ?>"
                            name="status">
                        <?php foreach (
                            ["Reported", "Acknowledged", "In Progress", "Resolved"]
                            as $status
                        ): ?>
                            <option value="<?= e($status) ?>"
                                <?= $issue["status"] === $status ? "selected" : "" ?>>
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">Update</button>
                </form>
            </div>
        </section>
    <?php endwhile; ?>
</main>
</body>
</html>
<?php $conn->close(); ?>

