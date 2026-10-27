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

        main { width: 100%; max-width: none; margin: 0; padding: 0; }

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


    <link rel="stylesheet" href="static/css/civicloop-premium.css?v=20261027-mobilefix">
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
