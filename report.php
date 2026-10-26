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