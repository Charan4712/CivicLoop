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
