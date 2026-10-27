
<?php
require_once "db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Read filter values
$search = trim($_GET["search"] ?? "");
$filterCategory = (int)($_GET["category"] ?? 0);
$filterArea = (int)($_GET["area"] ?? 0);
$filterPriority = $_GET["priority"] ?? "";
$filterStatus = $_GET["status"] ?? "";

// Allowed filter values
$allowedPriorities = ["Low", "Medium", "High"];
$allowedStatuses = [
    "Reported",
    "Acknowledged",
    "In Progress",
    "Resolved"
];

// Validate filters
if (!in_array($filterPriority, $allowedPriorities, true)) {
    $filterPriority = "";
}

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = "";
}

// Build the filtered issues query

$sql = "SELECT
            i.issue_id,
            i.title,
            i.description,
            c.category_name,
            a.area_name,
            i.priority,
            i.status,
            i.created_at,
            (
                SELECT COUNT(*)
                FROM issues duplicate
                WHERE duplicate.parent_issue_id = i.issue_id
            ) AS duplicate_count
        FROM issues i
        JOIN categories c ON i.category_id = c.category_id
        JOIN areas a ON i.area_id = a.area_id
        WHERE i.parent_issue_id IS NULL";


if ($search !== "") {
    $safeSearch = $conn->real_escape_string($search);
    $sql .= " AND (i.title LIKE '%$safeSearch%'
              OR i.description LIKE '%$safeSearch%')";
}

if ($filterCategory > 0) {
    $sql .= " AND i.category_id = $filterCategory";
}

if ($filterArea > 0) {
    $sql .= " AND i.area_id = $filterArea";
}

if ($filterPriority !== "") {
    $safePriority = $conn->real_escape_string($filterPriority);
    $sql .= " AND i.priority = '$safePriority'";
}

if ($filterStatus !== "") {
    $safeStatus = $conn->real_escape_string($filterStatus);
    $sql .= " AND i.status = '$safeStatus'";
}

$sql .= " ORDER BY i.created_at DESC";

$result = $conn->query($sql);

// Load dropdown options
$categoryOptions = $conn->query(
    "SELECT category_id, category_name
     FROM categories ORDER BY category_name"
);

$areaOptions = $conn->query(
    "SELECT area_id, area_name
     FROM areas ORDER BY area_name"
);


// Total issues, including duplicate reports
$totalResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM issues"
);
$totalCount = (int)$totalResult->fetch_assoc()["total"];

// Open issues
$openResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM issues
     WHERE status <> 'Resolved'
       AND parent_issue_id IS NULL"
);
$openCount = (int)$openResult->fetch_assoc()["total"];

// Resolved issues
$resolvedResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM issues
     WHERE status = 'Resolved'
       AND parent_issue_id IS NULL"
);
$resolvedCount = (int)$resolvedResult->fetch_assoc()["total"];

// Status analytics
$statuses = [
    "Reported" => 0,
    "Acknowledged" => 0,
    "In Progress" => 0,
    "Resolved" => 0
];

$statusResult = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM issues
     WHERE parent_issue_id IS NULL
     GROUP BY status"
);

while ($row = $statusResult->fetch_assoc()) {
    if (isset($statuses[$row["status"]])) {
        $statuses[$row["status"]] = (int)$row["total"];
    }
}

// Count original issues only for analytics; duplicate reports are excluded.
$analyticsTotal = array_sum($statuses);

// Category analytics
$categoryResult = $conn->query(
    "SELECT c.category_name, COUNT(i.issue_id) AS total
     FROM categories c
     LEFT JOIN issues i
       ON c.category_id = i.category_id
       AND i.parent_issue_id IS NULL
     GROUP BY c.category_id, c.category_name
     ORDER BY total DESC, c.category_name ASC"
);

$categories = [];

while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = [
        "name" => $row["category_name"],
        "total" => (int)$row["total"]
    ];
}

// Calculate the percentage for a chart bar
function chartPercent($value, $total) {
    if ($total === 0) {
        return 0;
    }

    return round(($value / $total) * 100, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CivicLoop | Community Issue Tracker</title>

    <style>
       
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont,
                 "Segoe UI", sans-serif;
    background: #f5f7fc;
    color: #172033;
    line-height: 1.6;
}

header {
    background: linear-gradient(135deg, #101d35, #183b63);
    color: white;
    padding: 30px 7%;
    box-shadow: 0 4px 18px rgba(16, 29, 53, 0.12);
}

header h1 {
    margin: 0;
    font-size: 32px;
    font-weight: 750;
    letter-spacing: -0.8px;
}

header p {
    margin: 7px 0 0;
    color: #c6d7ee;
}

nav {
    margin-top: 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

nav a {
    color: #d3def0;
    text-decoration: none;
    padding: 9px 15px;
    border-radius: 8px;
    transition: background 0.2s ease, color 0.2s ease;
}

nav a:hover {
    color: white;
    background: rgba(255, 255, 255, 0.12);
}

main { width: 100%; max-width: none; margin: 0; padding: 0; }

.stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin: 25px 0 32px;
}

.stat, .issue, .chart-card {
    background: white;
    border: 1px solid #e8edf5;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 5px 20px rgba(28, 48, 80, 0.045);
    transition: box-shadow 0.2s ease,
                transform 0.2s ease;
}

.stat:hover, .issue:hover, .chart-card:hover {
    box-shadow: 0 9px 26px rgba(28, 48, 80, 0.09);
}

.stat {
    flex: 1;
    min-width: 170px;
}

.stat span, .meta {
    color: #66758b;
    font-size: 14px;
}

.stat strong {
    display: block;
    font-size: 30px;
    line-height: 1.3;
    margin-top: 10px;
    color: #183b63;
}


        .analytics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin: 20px 0 35px;
        }

        .chart-card h3 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .chart-description {
            color: #64748b;
            font-size: 14px;
            margin-top: 0;
            margin-bottom: 22px;
        }

        .bar-row {
            margin-bottom: 20px;
        }

        .bar-label {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .bar-label span:first-child {
            overflow-wrap: anywhere;
        }

        .bar-label strong {
            white-space: nowrap;
        }

        .bar-track {
            height: 10px;
            background: #e8edf4;
            border-radius: 20px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            min-width: 0;
            border-radius: 20px;
            background: #0f766e;
            transition: width 0.3s ease;
        }

        .status-reported {
            background: #64748b;
        }

        .status-acknowledged {
            background: #2563eb;
        }

        .status-progress {
            background: #d97706;
        }

        .status-resolved {
            background: #0f766e;
        }

        .empty-chart {
            color: #64748b;
            font-size: 14px;
            padding: 12px 0;
        }

        .issue {
            margin-bottom: 16px;
        }

        .issue h3 {
            margin: 0 0 12px;
        }

        .issue-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 600;
        }

        .high {
            background: #fee2e2;
            color: #991b1b;
        }

        footer {
            text-align: center;
            color: #64748b;
            padding: 30px;
        }


    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        background: white;
        padding: 18px;
        margin: 18px 0 25px;
        border-radius: 12px;
        box-shadow: 0 3px 14px #1720330b;
    }

    .filter-form input,
    .filter-form select,
    .filter-form button,
    .reset-button {
        padding: 11px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font: inherit;
    }

    .filter-form input {
        flex: 2;
        min-width: 220px;
    }

    .filter-form select {
        flex: 1;
        min-width: 140px;
        background: white;
    }

    .filter-form button {
        background: #0f766e;
        color: white;
        border: none;
        cursor: pointer;
    }

    .reset-button {
        text-decoration: none;
        color: #172033;
        background: #f8fafc;
    }

    .filter-form button:hover {
        background: #115e59;
    }

    @media (max-width: 700px) {
        .analytics {
            grid-template-columns: 1fr;
        }

        main {
            margin-top: 24px;
        }
    }
    @media (max-width: 700px) {
    .analytics {
        grid-template-columns: 1fr;
    }

    main {
        margin-top: 24px;
    }
}
</style>


    <link rel="stylesheet" href="static/css/civicloop-premium.css?v=20261027-mobilefix">
</head>

<body>
<header>
    <h1>CivicLoop</h1>
    <p>Report it. Track it. Resolve it.</p>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="report.php">Report an Issue</a>
        <a href="manage.php">Manage Issues</a>
        <a href="history.php">Issue History</a>
    </nav>
</header>

<main>
    <h2>Community Issues</h2>
    <p>Explore local problems and follow their resolution.</p>

    <!-- Summary cards -->
    <section class="stats">
        <div class="stat">
            <span>Total issues</span>
            <strong><?= $totalCount ?></strong>
        </div>

        <div class="stat">
            <span>Open issues</span>
            <strong><?= $openCount ?></strong>
        </div>

        <div class="stat">
            <span>Resolved issues</span>
            <strong><?= $resolvedCount ?></strong>
        </div>
    </section>

    <!-- Analytics charts -->
    <h2>Dashboard Analytics</h2>
    <p>Understand issue progress and the most common community problems.</p>

    <section class="analytics">

        <!-- Status chart -->
        <div class="chart-card">
            <h3>Issues by Status</h3>
            <p class="chart-description">
                Current progress of all reported issues.
            </p>

            <?php if ($analyticsTotal === 0): ?>
                <p class="empty-chart">No issues available to analyse yet.</p>
            <?php else: ?>

                <?php foreach ($statuses as $status => $count): ?>
                    <?php
                    $percent = chartPercent($count, $analyticsTotal);

                    $barClass = match ($status) {
                        "Reported" => "status-reported",
                        "Acknowledged" => "status-acknowledged",
                        "In Progress" => "status-progress",
                        "Resolved" => "status-resolved",
                        default => ""
                    };
                    ?>

                    <div class="bar-row">
                        <div class="bar-label">
                            <span><?= e($status) ?></span>
                            <strong>
                                <?= $count ?> (<?= $percent ?>%)
                            </strong>
                        </div>

                        <div class="bar-track">
                            <div
                                class="bar-fill <?= e($barClass) ?>"
                                style="width: <?= $percent ?>%;">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>

        <!-- Category chart -->
        <div class="chart-card">
            <h3>Issues by Category</h3>
            <p class="chart-description">
                See which types of problems are reported most often.
            </p>

            <?php if ($analyticsTotal === 0): ?>
                <p class="empty-chart">No issues available to analyse yet.</p>
            <?php else: ?>

                <?php foreach ($categories as $category): ?>
                    <?php
                    $count = $category["total"];
                    $percent = chartPercent($count, $analyticsTotal);
                    ?>

                    <div class="bar-row">
                        <div class="bar-label">
                            <span><?= e($category["name"]) ?></span>
                            <strong>
                                <?= $count ?> (<?= $percent ?>%)
                            </strong>
                        </div>

                        <div class="bar-track">
                            <div
                                class="bar-fill"
                                style="width: <?= $percent ?>%;">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>

    </section>

    <!-- Latest reports -->
    
<h2>Search and Filter Issues</h2>

<form method="GET" action="index.php" class="filter-form">
    <input
        type="search"
        name="search"
        placeholder="Search issue title or description..."
        value="<?= e($search) ?>"
    >

    <select name="category">
        <option value="0">All Categories</option>
        <?php while ($cat = $categoryOptions->fetch_assoc()): ?>
            <option
                value="<?= (int)$cat["category_id"] ?>"
                <?= $filterCategory === (int)$cat["category_id"] ? "selected" : "" ?>
            >
                <?= e($cat["category_name"]) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <select name="area">
        <option value="0">All Areas</option>
        <?php while ($area = $areaOptions->fetch_assoc()): ?>
            <option
                value="<?= (int)$area["area_id"] ?>"
                <?= $filterArea === (int)$area["area_id"] ? "selected" : "" ?>
            >
                <?= e($area["area_name"]) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <select name="priority">
        <option value="">All Priorities</option>
        <?php foreach (["Low", "Medium", "High"] as $priority): ?>
            <option
                value="<?= e($priority) ?>"
                <?= $filterPriority === $priority ? "selected" : "" ?>
            >
                <?= e($priority) ?> Priority
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status">
        <option value="">All Statuses</option>
        <?php foreach (
            ["Reported", "Acknowledged", "In Progress", "Resolved"]
            as $status
        ): ?>
            <option
                value="<?= e($status) ?>"
                <?= $filterStatus === $status ? "selected" : "" ?>
            >
                <?= e($status) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Apply Filters</button>
    <a href="index.php" class="reset-button">Reset</a>
</form>


    <?php if (!$result): ?>
        <p>Unable to load community issues. Please check the database.</p>

    <?php elseif ($result->num_rows === 0): ?>
        <p>No community issues have been reported yet.</p>

    <?php else: ?>

        <?php while ($issue = $result->fetch_assoc()): ?>
            <article class="issue">
                <div class="issue-top">
                    <h3><?= e($issue["title"]) ?></h3>

                    <span class="badge <?= $issue["priority"] === "High" ? "high" : "" ?>">
                        <?= e($issue["priority"]) ?> Priority
                    </span>
                </div>

                <p><?= nl2br(e($issue["description"])) ?></p>

                <p class="meta">
                    Category: <?= e($issue["category_name"]) ?> |
                    Area: <?= e($issue["area_name"]) ?>
                </p>

                <span class="badge"><?= e($issue["status"]) ?></span>

<p class="meta">
    Report #<?= (int)$issue["issue_id"] ?> ·
    <?= e($issue["created_at"]) ?>
</p>

<p class="meta">
    🔁 Duplicate reports:
    <strong><?= (int)$issue["duplicate_count"] ?></strong>
</p>
            </article>
        <?php endwhile; ?>

    <?php endif; ?>
</main>

<footer>CivicLoop — Building better communities together.</footer>
</body>
</html>

<?php $conn->close(); ?>
