<?php
require_once "db.php";

$error = "";
$possibleDuplicates = [];
$showDuplicateWarning = false;

$submittedTitle = "";
$submittedDescription = "";
$submittedCategoryId = 0;
$submittedAreaId = 0;
$submittedReporter = "";
$submittedPriority = "Medium";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category_id = (int)($_POST["category_id"] ?? 0);
    $area_id = (int)($_POST["area_id"] ?? 0);
    $reporter = trim($_POST["reporter"] ?? "");
    $priority = $_POST["priority"] ?? "Medium";

    $submittedTitle = $title;
    $submittedDescription = $description;
    $submittedCategoryId = $category_id;
    $submittedAreaId = $area_id;
    $submittedReporter = $reporter;
    $submittedPriority = $priority;

    $allowedPriorities = ["Low", "Medium", "High"];

    if (
        $title === "" || $description === "" || $reporter === "" ||
        $category_id < 1 || $area_id < 1 ||
        !in_array($priority, $allowedPriorities, true)
    ) {
        $error = "Please complete all fields with valid values.";
    } else {
        $action = $_POST["duplicate_action"] ?? "";
        $originalIssueId = (int)($_POST["original_issue_id"] ?? 0);

        if ($action === "confirm_duplicate" || $action === "submit_separately") {
            $parentIssueId = null;

            if ($action === "confirm_duplicate") {
                $check = $conn->prepare(
                    "SELECT issue_id
                     FROM issues
                     WHERE issue_id = ?
                       AND category_id = ?
                       AND area_id = ?
                       AND parent_issue_id IS NULL"
                );

                $check->bind_param(
                    "iii",
                    $originalIssueId,
                    $category_id,
                    $area_id
                );

                $check->execute();
                $checkResult = $check->get_result();
                $originalExists = $checkResult->num_rows === 1;
                $check->close();

                if (!$originalExists) {
                    $error = "The selected original issue is invalid. Please check possible duplicates again.";
                } else {
                    $parentIssueId = $originalIssueId;
                }
            }

            if ($error === "") {
                if ($parentIssueId === null) {
                    $stmt = $conn->prepare(
                        "INSERT INTO issues
                        (title, description, category_id, area_id,
                         reported_by_name, priority)
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "ssiiss",
                        $title,
                        $description,
                        $category_id,
                        $area_id,
                        $reporter,
                        $priority
                    );
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO issues
                        (title, description, category_id, area_id,
                         reported_by_name, priority, parent_issue_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "ssiissi",
                        $title,
                        $description,
                        $category_id,
                        $area_id,
                        $reporter,
                        $priority,
                        $parentIssueId
                    );
                }

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: index.php?submitted=1");
                    exit;
                }

                $error = "Could not save the report. Please try again.";
                $stmt->close();
            }
        } else {
            // Find possible duplicates before saving a new report.
            
            // Load main issues from the same category and area.
            $duplicateStmt = $conn->prepare(
                "SELECT issue_id, title, description, status, created_at
                 FROM issues
                 WHERE category_id = ?
                   AND area_id = ?
                   AND parent_issue_id IS NULL
                 ORDER BY created_at DESC
                 LIMIT 200"
            );

            $duplicateStmt->bind_param(
                "ii",
                $category_id,
                $area_id
            );

            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result();

            // Normalize titles so capitalization and punctuation matter less.
            $normalizeTitle = function ($text) {
                $text = mb_strtolower($text, "UTF-8");
                $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text);
            };

            $newTitleNormalized = $normalizeTitle($title);
            $newWords = array_unique(
                array_filter(explode(' ', $newTitleNormalized))
            );

            while ($duplicate = $duplicateResult->fetch_assoc()) {
                $existingTitleNormalized = $normalizeTitle($duplicate["title"]);
                $existingWords = array_unique(
                    array_filter(explode(' ', $existingTitleNormalized))
                );

                // Calculate word overlap.
                $commonWords = array_intersect($newWords, $existingWords);
                $allWords = array_unique(
                    array_merge($newWords, $existingWords)
                );

                $wordScore = count($allWords) > 0
                    ? count($commonWords) / count($allWords)
                    : 0;

                // Calculate character-level similarity too.
                similar_text(
                    $newTitleNormalized,
                    $existingTitleNormalized,
                    $characterPercent
                );

                $characterScore = $characterPercent / 100;

                // Flag candidates that have enough word or character similarity.
                if (
    count($commonWords) >= 2 &&
    ($wordScore >= 0.50 || $characterScore >= 0.75)
) {
                    $duplicate["similarity_percent"] = (int)round(
                        max($wordScore, $characterScore) * 100
                    );

                    $possibleDuplicates[] = $duplicate;
                }
            }

            $duplicateStmt->close();


            if (!empty($possibleDuplicates)) {
                $showDuplicateWarning = true;
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO issues
                    (title, description, category_id, area_id,
                     reported_by_name, priority)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssiiss",
                    $title,
                    $description,
                    $category_id,
                    $area_id,
                    $reporter,
                    $priority
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: index.php?submitted=1");
                    exit;
                }

                $error = "Could not save the report. Please try again.";
                $stmt->close();
            }
        }
    }
}



$categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories ORDER BY category_name"
);



$areas = $conn->query(
    "SELECT area_id, area_name
     FROM areas ORDER BY area_name"
);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report an Issue | CivicLoop</title>
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
        header h1 { margin: 0; }
        header a { color: #c6d5e5; text-decoration: none; }
        main {
            max-width: 720px;
            margin: 35px auto;
            padding: 0 20px;
        }
        form {
            background: white;
            padding: 28px;
            border-radius: 14px;
            box-shadow: 0 3px 14px #1720330b;
        }
        label {
            display: block;
            margin: 18px 0 7px;
            font-weight: 600;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: inherit;
        }
        textarea { min-height: 120px; resize: vertical; }
        button {
            margin-top: 24px;
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 8px;
            background: #0f766e;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #115e59; }
        .message {
            padding: 12px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
        }
        .hint { color: #64748b; font-size: 14px; }
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
    <p>Report it. Track it. Resolve it.</p>
    <a href="index.php">← Back to dashboard</a>
</header>

<main>
    <h2>Report a Community Issue</h2>
    <p class="hint">Describe the problem so it can be tracked.</p>

    
<?php if ($error !== ""): ?>
    <p class="message"><?= e($error) ?></p>
<?php endif; ?>


<?php if ($showDuplicateWarning && !empty($possibleDuplicates)): ?>
    <div class="message" style="background:#fff7ed; color:#9a3412;">
        <h3>Possible Duplicate Issue Found</h3>
        <p>Similar issues already exist in this category and area.</p>

        <?php foreach ($possibleDuplicates as $duplicate): ?>
            <div style="background:white; padding:12px; margin:12px 0; border-radius:8px;">
                <strong><?= e($duplicate["title"]) ?></strong>
                
<p>
    Issue #<?= (int)$duplicate["issue_id"] ?>
    · Status: <?= e($duplicate["status"]) ?>
    · Title similarity: <?= (int)($duplicate["similarity_percent"] ?? 0) ?>%
</p>

                <p><?= e($duplicate["description"]) ?></p>

                <form method="POST" action="report.php">
                    <input type="hidden" name="title" value="<?= e($submittedTitle) ?>">
                    <input type="hidden" name="description" value="<?= e($submittedDescription) ?>">
                    <input type="hidden" name="category_id" value="<?= (int)$submittedCategoryId ?>">
                    <input type="hidden" name="area_id" value="<?= (int)$submittedAreaId ?>">
                    <input type="hidden" name="reporter" value="<?= e($submittedReporter) ?>">
                    <input type="hidden" name="priority" value="<?= e($submittedPriority) ?>">
                    <input type="hidden" name="original_issue_id" value="<?= (int)$duplicate["issue_id"] ?>">
                    <button type="submit" name="duplicate_action" value="confirm_duplicate">
                        Link to This Existing Issue
                    </button>
                </form>
            </div>
        <?php endforeach; ?>

        <form method="POST" action="report.php">
            <input type="hidden" name="title" value="<?= e($submittedTitle) ?>">
            <input type="hidden" name="description" value="<?= e($submittedDescription) ?>">
            <input type="hidden" name="category_id" value="<?= (int)$submittedCategoryId ?>">
            <input type="hidden" name="area_id" value="<?= (int)$submittedAreaId ?>">
            <input type="hidden" name="reporter" value="<?= e($submittedReporter) ?>">
            <input type="hidden" name="priority" value="<?= e($submittedPriority) ?>">
            <button type="submit" name="duplicate_action" value="submit_separately">
                Submit as a Separate Issue
            </button>
        </form>
    </div>
<?php endif; ?>



    <form method="POST" action="report.php">
    <input type="hidden" name="confirm_duplicate" value="1">
        <label for="title">Issue title</label>
        <input id="title" name="title" maxlength="150"
       placeholder="e.g. Streetlight not working"
       value="<?= e($submittedTitle) ?>" required>

        <label for="description">Description</label>
        <textarea id="description" name="description"
          placeholder="Explain the problem..."
          required><?= e($submittedDescription) ?></textarea>

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <option value="">Choose a category</option>
            <?php while ($category = $categories->fetch_assoc()): ?>
                <option value="<?= (int)$category["category_id"] ?>"
    <?= $submittedCategoryId === (int)$category["category_id"] ? "selected" : "" ?>>
                    <?= e($category["category_name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="area_id">Area</label>
        <select id="area_id" name="area_id" required>
            <option value="">Choose an area</option>
            <?php while ($area = $areas->fetch_assoc()): ?>
                <option value="<?= (int)$area["area_id"] ?>"
    <?= $submittedAreaId === (int)$area["area_id"] ? "selected" : "" ?>>
                    <?= e($area["area_name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="reporter">Your name</label>
        <input id="reporter" name="reporter" maxlength="100"
       placeholder="Enter your name"
       value="<?= e($submittedReporter) ?>" required>

        
<label for="priority">Priority</label>
<select id="priority" name="priority" required>
    <option value="Low" <?= $submittedPriority === "Low" ? "selected" : "" ?>>Low</option>
    <option value="Medium" <?= $submittedPriority === "Medium" ? "selected" : "" ?>>Medium</option>
    <option value="High" <?= $submittedPriority === "High" ? "selected" : "" ?>>High</option>
</select>

        <button type="submit">Submit Issue</button>
    </form>
</main>
</body>
</html>
<?php $conn->close(); ?>