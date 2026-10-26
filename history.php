<?php
require_once "db.php";

// Fetch status history along with issue details
$sql = "SELECT
            h.history_id,
            h.issue_id,
            i.title,
            h.old_status,
            h.new_status,
            h.note,
            h.changed_at
        FROM issue_history h
        JOIN issues i ON h.issue_id = i.issue_id
        ORDER BY h.changed_at DESC";

$result = $conn->query($sql);

// Safely display text from the database
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Issue History | CivicLoop</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont,
                         "Segoe UI", sans-serif;
            background: #f3f6fa;
            color: #172033;
        }

        header {
            background: #10243a;
            color: white;
            padding: 22px 7%;
        }

        header h1 {
            margin: 0 0 10px;
        }

        header a {
            color: #c6d5e5;
            text-decoration: none;
            margin-right: 18px;
        }

        main {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 14px #1720330b;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .empty {
            padding: 28px;
            text-align: center;
            color: #64748b;
        }

        footer {
            text-align: center;
            color: #64748b;
            padding: 28px 15px;
        }
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
    <nav>
        <a href="index.php">← Dashboard</a>
        <a href="manage.php">Manage Issues</a>
    </nav>
</header>

<main>
    <h2>Issue Status History</h2>
    <p>Track how community issues progress over time.</p>

    <section class="card">
        <?php if (!$result): ?>

            <p class="empty">
                Unable to load history. Please check the database connection.
            </p>

        <?php elseif ($result->num_rows === 0): ?>

            <p class="empty">
                No status changes recorded yet.
            </p>

        <?php else: ?>

            <table>
                <thead>
                    <tr>
                        <th>Issue</th>
                        <th>Previous Status</th>
                        <th>New Status</th>
                        <th>Note</th>
                        <th>Date and Time</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                #<?= (int)$row["issue_id"] ?>
                                — <?= e($row["title"]) ?>
                            </td>

                            <td>
                                <?= e($row["old_status"] ?? "—") ?>
                            </td>

                            <td>
                                <span class="status">
                                    <?= e($row["new_status"]) ?>
                                </span>
                            </td>

                            <td><?= e($row["note"] ?? "—") ?></td>

                            <td>
                                <?= e($row["changed_at"]) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </section>
</main>

<footer>
    CivicLoop — Building better communities together.
</footer>
</body>
</html>

<?php
$conn->close();
?>
