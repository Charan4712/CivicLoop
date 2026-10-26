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










<style id="civicloop-final-ui">
:root {
  --ink:#17243b; --muted:#65758d; --line:#e2e8f0;
  --bg:#f4f7fb; --brand:#0f766e;
}
* { box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
  margin:0!important; width:100%!important; max-width:none!important;
  background:var(--bg)!important; color:var(--ink);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  font-size:16px; line-height:1.6;
}
body > header {
  display:block!important; position:relative!important;
  width:100%!important; max-width:none!important;
  margin:0!important; padding:28px max(32px,calc((100% - 1380px)/2))!important;
  background:linear-gradient(115deg,#101d35,#183b60)!important;
  color:#fff!important; box-shadow:0 5px 22px #10233f20;
}
body > header h1, body > header h2,
body > header p, body > header a {
  color:#fff!important;
}
body > header h1 {font-size:32px!important;font-weight:800!important;margin:0 0 4px!important;}
body > header p {color:#cfdaea!important;}
body > header nav {width:100%!important;max-width:none!important;margin:18px 0 0!important;padding:0!important;}
body > header nav a {display:inline-block;padding:10px 15px;border-radius:9px;text-decoration:none!important;}
body > header nav a:hover {background:#ffffff1a;}
body > main {
  display:block!important;
  width:calc(100% - 64px)!important;
  max-width:1380px!important;
  min-width:0!important;
  margin:38px auto 70px!important;
  padding:0!important;
}
body > main > h2:first-child {font-size:34px!important;font-weight:800!important;margin:0 0 8px!important;}
body > main > p:first-of-type {margin:0 0 25px!important;color:var(--muted);}
body > main h2 {font-size:25px!important;margin:34px 0 10px!important;}
.stats {display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:22px!important;margin:26px 0 40px!important;}
.stat {position:relative;background:#fff!important;padding:28px!important;border:1px solid var(--line)!important;border-radius:18px!important;box-shadow:0 8px 28px #172b4508!important;}
.stat:before {content:"";position:absolute;top:0;left:0;right:0;height:4px;background:#2563eb;border-radius:18px 18px 0 0;}
.stat:nth-child(2):before {background:#d97706;}
.stat:nth-child(3):before {background:#0f9488;}
.stat span {color:var(--muted)!important;font-size:14px!important;font-weight:600;}
.stat strong {display:block;font-size:40px!important;font-weight:800!important;margin-top:8px;color:#10233f!important;}
.analytics {display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:24px!important;margin:24px 0 40px!important;}
.chart-card,.analytics>div {min-width:0;background:#fff!important;padding:28px!important;border:1px solid var(--line)!important;border-radius:18px!important;box-shadow:0 8px 28px #172b4508!important;}
.chart-card h3 {font-size:19px!important;margin-top:0;}
.chart-description {color:var(--muted)!important;margin-bottom:24px;}
.bar-row {margin:18px 0!important;}
.bar-label {display:flex;justify-content:space-between;gap:12px;margin-bottom:8px;}
.bar-label strong {white-space:nowrap;}
.bar-track {height:10px!important;border-radius:99px!important;background:#eaf0f7!important;overflow:hidden;}
.bar-fill {height:10px!important;border-radius:99px!important;}
.filter-form {display:grid!important;grid-template-columns:minmax(220px,2fr) repeat(4,minmax(125px,1fr)) auto auto!important;gap:12px!important;align-items:center;padding:22px!important;background:#fff!important;border:1px solid var(--line)!important;border-radius:16px!important;box-shadow:0 8px 28px #172b4508!important;margin:20px 0 26px!important;}
.filter-form input,.filter-form select {width:100%!important;min-width:0!important;height:46px!important;padding:9px 12px!important;font-size:13px!important;}
input:not([type=checkbox]):not([type=radio]),select,textarea {max-width:100%;min-height:44px;padding:10px 12px;border:1px solid #d5dfec;border-radius:10px;background:#fff;color:var(--ink);font:inherit;}
button,input[type=submit] {min-height:44px;padding:11px 17px;border:0;border-radius:10px!important;background:var(--brand)!important;color:white!important;font-weight:700;cursor:pointer;}
button:hover,input[type=submit]:hover {background:#115e59!important;}
.reset-button {display:inline-flex;align-items:center;justify-content:center;padding:10px 15px;border:1px solid var(--line);border-radius:10px;background:#f8fafc;text-decoration:none;}
.issue-card,.card,.form-card {background:#fff!important;border:1px solid var(--line)!important;border-radius:18px!important;padding:26px!important;margin:18px 0!important;box-shadow:0 8px 28px #172b4508!important;}
table {width:100%;border-collapse:collapse;background:white;border-radius:12px;overflow:hidden;}
th,td {padding:13px 15px;border-bottom:1px solid var(--line);text-align:left;}
th {background:#f8fafc;color:#475569;}
@media(max-width:1100px) {
 body > main {width:calc(100% - 40px)!important;}
 .filter-form {grid-template-columns:repeat(3,minmax(0,1fr))!important;}
}
@media(max-width:700px) {
 body > header {padding:22px 18px!important;}
 body > header nav a {padding:8px 9px;font-size:13px;}
 body > main {width:calc(100% - 28px)!important;margin:24px auto 40px!important;}
 .stats,.analytics {grid-template-columns:1fr!important;gap:14px!important;}
 .stat,.chart-card,.analytics>div,.issue-card,.card,.form-card {padding:19px!important;}
 .stat strong {font-size:34px!important;}
 .filter-form {grid-template-columns:1fr!important;padding:15px!important;}
 .filter-form input,.filter-form select,.filter-form button,.reset-button {width:100%;}
 table {display:block;overflow-x:auto;}
}
@media(prefers-reduced-motion:reduce) {html{scroll-behavior:auto}*{transition:none!important;}}
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

