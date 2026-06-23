<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../scripts/php/config.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    redirect_with_status('dashboard.php', 'error', 'Unauthorized access.');
}

$test_id = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);
if (!$test_id) {
    redirect_with_status('dashboard.php', 'error', 'Invalid test ID.');
}

$conn = db();

// Get test details
$stmt = $conn->prepare("SELECT * FROM mock_tests WHERE id = ?");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();

if (!$test) {
    redirect_with_status('dashboard.php', 'error', 'Test not found.');
}

// Get existing questions
$q_stmt = $conn->prepare("SELECT * FROM mock_test_questions WHERE test_id = ? ORDER BY id ASC");
$q_stmt->bind_param("i", $test_id);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$question_count = count($questions);

// Get submission count
$sub_stmt = $conn->prepare("SELECT COUNT(*) as c FROM mock_test_results WHERE test_id = ?");
$sub_stmt->bind_param("i", $test_id);
$sub_stmt->execute();
$submission_count = (int) $sub_stmt->get_result()->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en" data-page="dashboard">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Questions — <?php echo htmlspecialchars($test['title']); ?> | GrepMny</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
  <style>
    .mcq-page { max-width: 860px; margin: 2rem auto; padding: 0 1rem; }
    .mcq-header {
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;
    }
    .mcq-card {
      background: var(--surface-strong); border: 1px solid var(--line);
      border-radius: var(--radius); padding: 1.5rem; box-shadow: var(--shadow);
      margin-bottom: 1.5rem;
    }
    .mcq-card h3 { margin: 0 0 0.25rem; font-size: 1.15rem; font-weight: 800; }
    .mcq-card .description { margin: 0 0 1rem; color: var(--muted); font-size: 0.9rem; }

    /* Progress ring */
    .progress-ring-wrap { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
    .progress-ring { width: 64px; height: 64px; transform: rotate(-90deg); }
    .progress-ring-bg { fill: none; stroke: var(--line); stroke-width: 6; }
    .progress-ring-fill {
      fill: none; stroke: url(#ringGrad); stroke-width: 6;
      stroke-linecap: round; transition: stroke-dashoffset 0.6s cubic-bezier(0.4,0,0.2,1);
    }
    .progress-info strong { font-size: 1.5rem; display: block; letter-spacing: -0.03em; }
    .progress-info span { font-size: 0.8rem; color: var(--muted); font-weight: 600; }

    /* Form */
    .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .options-grid { grid-template-columns: 1fr; } }
    .field-compact { display: flex; flex-direction: column; gap: 0.3rem; }
    .field-compact span { font-size: 0.8rem; font-weight: 700; color: var(--muted); }
    .field-compact input, .field-compact textarea, .field-compact select {
      padding: 0.6rem 0.75rem; border: 1px solid var(--line); border-radius: var(--radius);
      background: var(--surface-strong); color: var(--text); font-family: inherit; font-size: 0.9rem;
    }
    .field-compact textarea { resize: vertical; }

    /* Question cards */
    .q-card {
      background: color-mix(in srgb, var(--surface) 40%, transparent);
      border: 1px solid var(--line); border-radius: var(--radius);
      padding: 1.25rem; margin-bottom: 1rem;
      transition: border-color 0.2s;
    }
    .q-card:hover { border-color: var(--primary); }
    .q-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .q-number {
      display: inline-flex; align-items: center; justify-content: center;
      width: 28px; height: 28px; border-radius: 50%; font-size: 0.8rem; font-weight: 800;
      background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; flex-shrink: 0;
    }
    .q-text { font-weight: 700; font-size: 0.95rem; line-height: 1.5; }
    .q-options { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.75rem; }
    @media (max-width: 600px) { .q-options { grid-template-columns: 1fr; } }
    .q-opt {
      padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem;
      background: var(--surface-strong); border: 1px solid var(--line);
    }
    .q-opt.is-correct { border-color: var(--success); background: color-mix(in srgb, var(--success) 10%, var(--surface-strong)); font-weight: 700; }
    .q-opt-letter { font-weight: 800; margin-right: 0.4rem; }

    .btn-danger {
      background: transparent; border: 1px solid var(--danger); color: var(--danger);
      padding: 0.3rem 0.65rem; border-radius: var(--radius); font-size: 0.8rem;
      font-weight: 800; cursor: pointer; font-family: inherit; transition: background 0.2s, color 0.2s;
    }
    .btn-danger:hover { background: var(--danger); color: #fff; }

    .stats-row { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .stat-chip {
      display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;
      background: var(--surface-strong); border: 1px solid var(--line); border-radius: 999px;
      font-size: 0.85rem; font-weight: 700;
    }
    .stat-chip .dot { width: 8px; height: 8px; border-radius: 50%; }

    .alert-bar {
      padding: 0.75rem 1rem; border-radius: var(--radius); font-size: 0.9rem;
      font-weight: 600; margin-bottom: 1.5rem; animation: fadeSlideIn 0.3s ease;
    }
    .alert-bar.success { background: color-mix(in srgb, var(--success) 15%, var(--surface-strong)); color: var(--success); border: 1px solid var(--success); }
    .alert-bar.error { background: color-mix(in srgb, var(--danger) 15%, var(--surface-strong)); color: var(--danger); border: 1px solid var(--danger); }

    @keyframes fadeSlideIn {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
  <script>
    const root = document.documentElement;
    const storedTheme = localStorage.getItem("GrepMny-theme");
    const preferredDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    root.dataset.theme = storedTheme || (preferredDark ? "dark" : "light");
  </script>
</head>
<body>
  <header class="site-header compact-header" data-header>
    <nav class="nav-wrap" aria-label="Primary navigation">
      <a class="brand-mark" href="./dashboard.php" aria-label="GrepMny home">
        <span>GM</span>
        <strong>GrepMny</strong>
      </a>
      <div class="site-menu always-visible">
        <a href="./dashboard.php">← Back to Dashboard</a>
      </div>
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
    </nav>
  </header>

  <main class="mcq-page">
    <?php
    $status = $_GET['status'] ?? '';
    $message = $_GET['message'] ?? '';
    if ($status && $message):
    ?>
      <div class="alert-bar <?php echo $status === 'success' ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="mcq-header">
      <div>
        <p class="eyebrow" style="margin:0;">Mock Test · Course #<?php echo $test['cid']; ?></p>
        <h1 style="margin:0; font-size:1.6rem; letter-spacing:-0.04em;"><?php echo htmlspecialchars($test['title']); ?></h1>
      </div>
      <a href="./dashboard.php" class="btn btn-secondary" style="min-height:auto; padding:0.6rem 1.25rem;">Dashboard</a>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-chip">
        <span class="dot" style="background:var(--primary);"></span>
        <?php echo $question_count; ?>/10 Questions
      </div>
      <div class="stat-chip">
        <span class="dot" style="background:var(--success);"></span>
        <?php echo $submission_count; ?> Submissions
      </div>
      <div class="stat-chip">
        <span class="dot" style="background:var(--accent);"></span>
        30 min Time Limit
      </div>
    </div>

    <!-- Progress ring + Add form -->
    <div class="mcq-card">
      <div style="display:flex; align-items:flex-start; gap:1.5rem; flex-wrap:wrap;">
        <div class="progress-ring-wrap" style="margin-bottom:0;">
          <?php
          $circumference = 2 * M_PI * 26; // radius 26
          $offset = $circumference - ($question_count / 10) * $circumference;
          ?>
          <svg class="progress-ring" viewBox="0 0 64 64">
            <defs>
              <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="var(--primary)" />
                <stop offset="100%" stop-color="var(--accent)" />
              </linearGradient>
            </defs>
            <circle class="progress-ring-bg" cx="32" cy="32" r="26" />
            <circle class="progress-ring-fill" cx="32" cy="32" r="26"
              stroke-dasharray="<?php echo $circumference; ?>"
              stroke-dashoffset="<?php echo $offset; ?>" />
          </svg>
          <div class="progress-info">
            <strong><?php echo $question_count; ?>/10</strong>
            <span><?php echo $question_count >= 10 ? 'Test is ready!' : 'Questions added'; ?></span>
          </div>
        </div>
      </div>

      <?php if ($question_count < 10): ?>
        <h3 style="margin-top:1.25rem;">Add New Question</h3>
        <p class="description">Fill in the question and all four options. Mark the correct answer.</p>
        <form action="../scripts/php/manage_mcq.php" method="post">
          <input type="hidden" name="action" value="add_question">
          <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">

          <div class="field-compact" style="margin-bottom:1rem;">
            <span>Question Text</span>
            <textarea name="question_text" rows="3" required placeholder="Enter the question..."></textarea>
          </div>

          <div class="options-grid" style="margin-bottom:1rem;">
            <div class="field-compact">
              <span>Option A</span>
              <input type="text" name="option_a" required placeholder="First option">
            </div>
            <div class="field-compact">
              <span>Option B</span>
              <input type="text" name="option_b" required placeholder="Second option">
            </div>
            <div class="field-compact">
              <span>Option C</span>
              <input type="text" name="option_c" required placeholder="Third option">
            </div>
            <div class="field-compact">
              <span>Option D</span>
              <input type="text" name="option_d" required placeholder="Fourth option">
            </div>
          </div>

          <div style="display:flex; gap:1rem; align-items:end; flex-wrap:wrap;">
            <div class="field-compact">
              <span>Correct Answer</span>
              <select name="correct_option" required style="min-width:100px;">
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
              </select>
            </div>
            <button class="btn btn-primary" type="submit" style="min-height:2.5rem;">Add Question</button>
          </div>
        </form>
      <?php else: ?>
        <div style="margin-top:1rem; padding:1rem; border-radius:var(--radius); background:color-mix(in srgb, var(--success) 10%, var(--surface)); border:1px solid var(--success); color:var(--success); font-weight:700;">
          ✓ All 10 questions added. This test is ready for students!
        </div>
      <?php endif; ?>
    </div>

    <!-- Existing Questions -->
    <div class="mcq-card">
      <h3>Questions (<?php echo $question_count; ?>)</h3>
      <p class="description">Review and manage questions for this test.</p>

      <?php if (empty($questions)): ?>
        <p style="color:var(--muted); font-style:italic;">No questions added yet. Use the form above to add your first question.</p>
      <?php else: ?>
        <?php foreach ($questions as $index => $q): ?>
          <div class="q-card">
            <div class="q-card-header">
              <div style="display:flex; gap:0.75rem; align-items:flex-start;">
                <span class="q-number"><?php echo $index + 1; ?></span>
                <span class="q-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></span>
              </div>
              <form action="../scripts/php/manage_mcq.php" method="post" onsubmit="return confirm('Delete this question?');">
                <input type="hidden" name="action" value="delete_question">
                <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
                <button type="submit" class="btn-danger">Delete</button>
              </form>
            </div>
            <div class="q-options">
              <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field): ?>
                <div class="q-opt <?php echo $q['correct_option'] === $letter ? 'is-correct' : ''; ?>">
                  <span class="q-opt-letter"><?php echo $letter; ?>.</span>
                  <?php echo htmlspecialchars($q[$field]); ?>
                  <?php if ($q['correct_option'] === $letter): ?> <span style="float:right;">✓</span><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <script src="../assets/js/app.js" defer></script>
</body>
</html>
