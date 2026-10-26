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