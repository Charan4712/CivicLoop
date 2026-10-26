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

<!-- CIVICLOOP UI REFRESH -->
<style id="civicloop-ui-refresh">
:root {
    --cl-primary: #2563eb;
    --cl-primary-dark: #1d4ed8;
    --cl-ink: #172033;
    --cl-muted: #64748b;
    --cl-border: #e2e8f0;
    --cl-surface: #ffffff;
    --cl-bg: #f4f7fc;
    --cl-radius: 16px;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    background: var(--cl-bg);
    color: var(--cl-ink);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                 Roboto, Helvetica, Arial, sans-serif;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}
header, nav, main, footer {
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}
header, nav { padding-left: 24px; padding-right: 24px; }
main { padding: 24px; }
h1, h2, h3, h4 {
    color: var(--cl-ink);
    line-height: 1.25;
    letter-spacing: -0.025em;
}
h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); }
h2 { font-size: clamp(1.4rem, 3vw, 1.9rem); margin-top: 30px; }
h3 { font-size: 1.12rem; }
p { color: var(--cl-muted); }
a { color: var(--cl-primary); text-underline-offset: 3px; }
button, input[type="submit"], .button, a.button {
    transition: background .18s ease, transform .18s ease,
                box-shadow .18s ease;
}
button, input[type="submit"] {
    cursor: pointer;
    border: 0;
    border-radius: 10px;
    padding: 11px 17px;
    background: var(--cl-primary);
    color: white;
    font-weight: 650;
}
button:hover, input[type="submit"]:hover {
    background: var(--cl-primary-dark);
    box-shadow: 0 5px 14px rgba(37, 99, 235, .18);
}
input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]),
select, textarea {
    max-width: 100%;
    min-height: 44px;
    border: 1px solid #d7dfeb;
    border-radius: 10px;
    padding: 10px 12px;
    background: white;
    color: var(--cl-ink);
    font: inherit;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
}
textarea { min-height: 120px; resize: vertical; }
input:focus, select:focus, textarea:focus {
    border-color: var(--cl-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}
.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin: 24px 0 32px;
}
.stat, .chart-card, .issue-card, .card, .form-card,
.analytics > div {
    background: var(--cl-surface);
    border: 1px solid var(--cl-border);
    border-radius: var(--cl-radius);
    box-shadow: 0 5px 20px rgba(15, 23, 42, .035);
}
.stat { padding: 22px 24px; }
.stat span { display: block; color: var(--cl-muted); font-size: .9rem; }
.stat strong { display: block; margin-top: 7px; font-size: 2rem; color: var(--cl-ink); }
.analytics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin: 22px 0 32px;
}
.chart-card, .form-card { padding: 22px; }
.chart-description { font-size: .9rem; }
.bar-track {
    overflow: hidden;
    border-radius: 999px;
    background: #eaf0f8;
    min-height: 9px;
}
.bar-fill { min-height: 9px; border-radius: 999px; }
.bar-label { gap: 10px; margin-bottom: 7px; }
.bar-label strong { font-variant-numeric: tabular-nums; }
.issue-card {
    padding: 22px;
    margin: 16px 0;
    transition: box-shadow .18s ease, transform .18s ease;
}
.issue-card:hover {
    box-shadow: 0 10px 28px rgba(15, 23, 42, .07);
    transform: translateY(-2px);
}
.filter-form, form {
    gap: 12px;
}
.filter-form {
    padding: 18px;
    margin: 18px 0 26px;
    background: white;
    border: 1px solid var(--cl-border);
    border-radius: var(--cl-radius);
}
.badge, .status-badge, .priority-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 999px;
    padding: 5px 11px;
    font-size: .8rem;
    font-weight: 700;
}
table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}
th, td { padding: 12px 14px; border-bottom: 1px solid var(--cl-border); text-align: left; }
th { background: #f8fafc; color: #475569; font-size: .82rem; }
tr:last-child td { border-bottom: 0; }
hr { border: 0; border-top: 1px solid var(--cl-border); margin: 24px 0; }
:focus-visible { outline: 3px solid rgba(37, 99, 235, .35); outline-offset: 3px; }
@media (max-width: 760px) {
    main { padding: 16px; }
    header, nav { padding-left: 16px; padding-right: 16px; }
    .stats { grid-template-columns: 1fr; gap: 12px; }
    .stat { padding: 17px 19px; }
    .analytics { grid-template-columns: 1fr; gap: 14px; }
    .chart-card, .form-card { padding: 17px; }
    .filter-form { display: flex; flex-direction: column; }
    .filter-form input, .filter-form select, .filter-form button { width: 100%; }
    table { display: block; overflow-x: auto; white-space: nowrap; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
}
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

