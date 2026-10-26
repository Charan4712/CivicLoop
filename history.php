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
