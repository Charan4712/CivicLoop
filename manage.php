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




<style id="civicloop-premium-v2">
:root {
  --brand: #0f766e;
  --brand-dark: #115e59;
  --navy: #0f172a;
  --text: #17243b;
  --muted: #64748b;
  --line: #e2e8f0;
  --bg: #f3f6fb;
  --white: #ffffff;
  --shadow: 0 8px 30px rgba(15,23,42,.045);
}
html { scroll-behavior: smooth; }
body {
  margin: 0;
  background: var(--bg) !important;
  color: var(--text);
  font-family: -apple-system, BlinkMacSystemFont,
    "Segoe UI", Inter, sans-serif;
  font-size: 15px;
  line-height: 1.6;
}
body > header {
  max-width: none !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 28px max(36px, calc((100vw - 1320px)/2)) !important;
  background: linear-gradient(115deg,#0b172d,#173a5d) !important;
  color: white !important;
  box-shadow: 0 5px 18px rgba(15,23,42,.12);
}
body > header h1, body > header h2,
body > header p, body > header a {
  color: white !important;
}
body > header nav {
  max-width: none !important;
  padding: 0 !important;
  margin: 18px 0 0 !important;
}
body > header nav a {
  display: inline-block;
  text-decoration: none;
  padding: 9px 14px;
  margin-right: 5px;
  border-radius: 9px;
  transition: background .2s ease;
}
body > header nav a:hover {
  background: rgba(255,255,255,.13);
}
body > main, main {
  width: min(1320px, calc(100% - 64px)) !important;
  max-width: 1320px !important;
  margin: 38px auto 70px !important;
  padding: 0 !important;
}
main > h2:first-child {
  font-size: clamp(26px,3vw,36px) !important;
  font-weight: 800;
  letter-spacing: -.04em;
  margin-bottom: 8px;
}
main h2 {
  font-size: 23px;
  letter-spacing: -.025em;
  margin-top: 35px;
}
main > p {
  color: var(--muted);
  margin-bottom: 24px;
}
.stats {
  display: grid !important;
  grid-template-columns: repeat(3,minmax(0,1fr)) !important;
  gap: 22px !important;
  margin: 26px 0 40px !important;
}
.stat {
  position: relative;
  overflow: hidden;
  padding: 28px 30px !important;
  border: 1px solid var(--line) !important;
  border-radius: 18px !important;
  background: white !important;
  box-shadow: var(--shadow) !important;
  transition: transform .2s ease, box-shadow .2s ease;
}
.stat::before {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: #2563eb;
}
.stat:nth-child(2)::before { background: #f59e0b; }
.stat:nth-child(3)::before { background: #0d9488; }
.stat:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 32px rgba(15,23,42,.09) !important;
}
.stat span {
  font-size: 14px !important;
  color: var(--muted) !important;
  font-weight: 600;
}
.stat strong {
  display: block;
  margin-top: 10px;
  font-size: clamp(30px,3vw,42px) !important;
  line-height: 1.2;
  font-weight: 800;
  color: var(--navy) !important;
}
.analytics {
  display: grid !important;
  grid-template-columns: repeat(2,minmax(0,1fr)) !important;
  gap: 24px !important;
  margin: 25px 0 40px !important;
}
.chart-card {
  padding: 30px !important;
  border-radius: 18px !important;
  background: white !important;
  border: 1px solid var(--line) !important;
  box-shadow: var(--shadow) !important;
}
.chart-card h3 {
  font-size: 19px !important;
  margin-top: 0;
}
.chart-description {
  color: var(--muted);
  margin-bottom: 28px;
}
.bar-row { margin: 19px 0 !important; }
.bar-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 9px;
  font-size: 14px;
}
.bar-label strong {
  font-weight: 700;
  color: var(--navy);
}
.bar-track {
  height: 11px !important;
  background: #edf2f8 !important;
  border-radius: 99px !important;
  overflow: hidden;
}
.bar-fill {
  height: 11px !important;
  border-radius: 99px !important;
  background: var(--brand);
}
.filter-form {
  display: grid !important;
  grid-template-columns: minmax(220px,2fr)
    repeat(4,minmax(125px,1fr)) auto auto !important;
  gap: 12px !important;
  align-items: center;
  padding: 22px !important;
  border: 1px solid var(--line) !important;
  border-radius: 18px !important;
  background: white !important;
  box-shadow: var(--shadow);
  margin-bottom: 26px !important;
}
.filter-form input,
.filter-form select {
  width: 100% !important;
  min-width: 0 !important;
  height: 46px !important;
  padding: 9px 12px !important;
  font-size: 13px !important;
}
button, input[type="submit"],
.filter-form button {
  min-height: 44px;
  border: 0;
  border-radius: 10px !important;
  background: var(--brand) !important;
  color: white !important;
  font-weight: 700;
  cursor: pointer;
  padding: 10px 18px;
}
button:hover, input[type="submit"]:hover,
.filter-form button:hover {
  background: var(--brand-dark) !important;
}
.reset-button {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 10px 15px !important;
  border-radius: 10px;
  text-decoration: none;
  border: 1px solid var(--line);
  color: var(--text);
  background: #f8fafc;
}
.issue-card {
  background: white !important;
  padding: 27px 30px !important;
  margin: 18px 0 !important;
  border-radius: 18px !important;
  border: 1px solid var(--line) !important;
  box-shadow: var(--shadow) !important;
  transition: transform .2s ease,box-shadow .2s ease;
}
.issue-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(15,23,42,.08) !important;
}
.issue-card h3 {
  font-size: 18px;
  font-weight: 750;
}
.status-badge, .priority-badge, .badge {
  border-radius: 999px !important;
  font-size: 12px !important;
  font-weight: 700 !important;
}
form input, form select, form textarea {
  border-radius: 10px;
}
footer {
  text-align: center;
  color: var(--muted);
}
@media (max-width: 1150px) {
  .filter-form {
    grid-template-columns: repeat(3,minmax(0,1fr)) !important;
  }
}
@media (max-width: 760px) {
  body > header {
    padding: 22px 18px !important;
  }
  body > header nav a {
    padding: 8px 9px;
    font-size: 13px;
  }
  body > main, main {
    width: calc(100% - 32px) !important;
    margin: 24px auto 45px !important;
  }
  .stats, .analytics {
    grid-template-columns: 1fr !important;
    gap: 14px !important;
  }
  .stat, .chart-card, .issue-card {
    padding: 20px !important;
  }
  .filter-form {
    grid-template-columns: 1fr !important;
    padding: 16px !important;
  }
  .filter-form button, .reset-button {
    width: 100%;
  }
  table {
    display: block;
    overflow-x: auto;
  }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition: none !important;
    scroll-behavior: auto !important;
  }
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

