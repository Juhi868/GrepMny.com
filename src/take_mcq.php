<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../scripts/php/config.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
    redirect_with_status('dashboard.php', 'error', 'Only students can take tests.');
}

$test_id = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);
if (!$test_id) {
    redirect_with_status('dashboard.php', 'error', 'Invalid test ID.');
}

$conn = db();
$student = $_SESSION['username'];
$stmt = $conn->prepare("SELECT mt.* FROM mock_tests mt JOIN `student details` sd ON sd.cid = mt.cid AND sd.semail = ? WHERE mt.id = ? LIMIT 1");
$stmt->bind_param("si", $student, $test_id);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();
if (!$test) {
    redirect_with_status('dashboard.php', 'error', 'This test is not assigned to you.');
}

$assigned = array_filter(array_map('trim', explode(',', (string)$test['assigned_students'])));
if ($assigned && !in_array($student, $assigned, true)) {
    redirect_with_status('dashboard.php', 'error', 'This test is assigned to specific students only.');
}

$now = time();
if (!empty($test['starts_at']) && $now < strtotime($test['starts_at'])) {
    redirect_with_status('dashboard.php', 'error', 'This test is not open yet.');
}
if (!empty($test['ends_at']) && $now > strtotime($test['ends_at'])) {
    redirect_with_status('dashboard.php', 'error', 'This test has closed.');
}

$chk = $conn->prepare("SELECT id FROM mock_test_results WHERE test_id = ? AND semail = ?");
$chk->bind_param("is", $test_id, $student);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    redirect_with_status('dashboard.php', 'error', 'You have already submitted this test.');
}

$q_stmt = $conn->prepare("SELECT * FROM mock_test_questions WHERE test_id = ? ORDER BY id ASC");
$q_stmt->bind_param("i", $test_id);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (!$questions) {
    redirect_with_status('dashboard.php', 'error', 'This test has no questions yet.');
}

$duration = max(1, (int)$test['duration_minutes']);
$totalMarks = array_sum(array_map(static fn($q) => (int)$q['marks'], $questions));
?>
<!DOCTYPE html>
<html lang="en" data-page="dashboard">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Take Test | GrepMny</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
  <style>
    .test-layout { display:grid; grid-template-columns:230px 1fr; gap:1.5rem; width:min(1120px, calc(100% - 2rem)); margin:2rem auto; }
    @media (max-width: 780px) { .test-layout { grid-template-columns:1fr; } .timer-card { position:static!important; } }
    .timer-card, .test-card, .question-block { background:var(--surface-strong); border:1px solid var(--line); border-radius:var(--radius); padding:1.25rem; box-shadow:var(--shadow); }
    .timer-card { position:sticky; top:6rem; }
    .timer-text { font-family:'JetBrains Mono', monospace; font-size:2rem; font-weight:800; text-align:center; }
    .q-nav { display:grid; grid-template-columns:repeat(5, 1fr); gap:.45rem; margin-top:1rem; }
    .q-nav button { aspect-ratio:1; border:1px solid var(--line); border-radius:6px; background:var(--surface); color:var(--text); font-weight:800; cursor:pointer; }
    .q-nav button.is-answered { background:var(--primary); color:#fff; border-color:var(--primary); }
    .question-block { margin-bottom:1rem; }
    .question-head { display:flex; gap:.75rem; align-items:flex-start; margin-bottom:1rem; }
    .q-num { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; border-radius:50%; background:var(--primary); color:#fff; font-weight:900; }
    .option-label { display:flex; gap:.65rem; align-items:flex-start; border:1px solid var(--line); border-radius:var(--radius); padding:.8rem; margin:.5rem 0; cursor:pointer; }
    .option-label:has(input:checked) { border-color:var(--primary); background:color-mix(in srgb, var(--primary) 8%, transparent); }
    textarea, input[type="text"] { width:100%; border:1px solid var(--line); border-radius:var(--radius); padding:.75rem; background:var(--surface); color:var(--text); font:inherit; }
    .submit-bar { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; }
    .lock-overlay { display:none; position:fixed; inset:0; z-index:99; background:rgba(0,0,0,.7); align-items:center; justify-content:center; color:white; text-align:center; padding:2rem; }
    .lock-overlay.is-visible { display:flex; }
  </style>
  <script>
    const root = document.documentElement;
    root.dataset.theme = localStorage.getItem("GrepMny-theme") || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
  </script>
</head>
<body>
  <header class="site-header compact-header" data-header>
    <nav class="nav-wrap" aria-label="Primary navigation">
      <a class="brand-mark" href="./dashboard.php"><span>GM</span><strong>GrepMny</strong></a>
      <div class="site-menu always-visible"><strong style="color:var(--danger);">Test in progress</strong></div>
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
    </nav>
  </header>

  <div class="lock-overlay" id="submitOverlay"><div><h2>Time is up</h2><p>Your answers are being submitted.</p></div></div>

  <main class="test-layout">
    <aside>
      <div class="timer-card">
        <p class="eyebrow" style="text-align:center; margin:0;">Time Remaining</p>
        <div class="timer-text" id="timerText"><?php echo sprintf('%02d:00', $duration); ?></div>
        <div class="q-nav">
          <?php foreach ($questions as $i => $q): ?><button type="button" data-nav="<?php echo $i + 1; ?>" onclick="document.getElementById('q<?php echo $i + 1; ?>').scrollIntoView({behavior:'smooth', block:'center'});"><?php echo $i + 1; ?></button><?php endforeach; ?>
        </div>
        <p style="text-align:center; color:var(--muted); font-weight:700;"><span id="answeredCount">0</span>/<?php echo count($questions); ?> answered</p>
      </div>
    </aside>

    <section>
      <div class="test-card" style="margin-bottom:1rem;">
        <p class="eyebrow" style="margin:0;">Course #<?php echo (int)$test['cid']; ?> · <?php echo $totalMarks; ?> marks</p>
        <h1 style="margin:.2rem 0;"><?php echo htmlspecialchars($test['title']); ?></h1>
        <p class="description"><?php echo htmlspecialchars((string)$test['description']); ?></p>
      </div>

      <form id="testForm" action="../scripts/php/manage_mcq.php" method="post">
        <input type="hidden" name="action" value="submit_test">
        <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
        <?php foreach ($questions as $index => $q): $num = $index + 1; ?>
          <article class="question-block" id="q<?php echo $num; ?>">
            <div class="question-head">
              <span class="q-num"><?php echo $num; ?></span>
              <div><strong><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></strong><br><small><?php echo htmlspecialchars(str_replace('_', ' ', $q['question_type'])); ?> · <?php echo (int)$q['marks']; ?> marks</small></div>
            </div>
            <?php if ($q['question_type'] === 'mcq'): ?>
              <?php foreach (['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $letter => $field): ?>
                <label class="option-label"><input type="radio" name="q_<?php echo (int)$q['id']; ?>" value="<?php echo $letter; ?>" onchange="markAnswered(<?php echo $num; ?>)"> <span><strong><?php echo $letter; ?>.</strong> <?php echo htmlspecialchars($q[$field]); ?></span></label>
              <?php endforeach; ?>
            <?php elseif ($q['question_type'] === 'multiple_correct'): ?>
              <?php foreach (['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $letter => $field): ?>
                <label class="option-label"><input type="checkbox" name="q_<?php echo (int)$q['id']; ?>[]" value="<?php echo $letter; ?>" onchange="markAnswered(<?php echo $num; ?>)"> <span><strong><?php echo $letter; ?>.</strong> <?php echo htmlspecialchars($q[$field]); ?></span></label>
              <?php endforeach; ?>
            <?php elseif ($q['question_type'] === 'true_false'): ?>
              <label class="option-label"><input type="radio" name="q_<?php echo (int)$q['id']; ?>" value="TRUE" onchange="markAnswered(<?php echo $num; ?>)"> True</label>
              <label class="option-label"><input type="radio" name="q_<?php echo (int)$q['id']; ?>" value="FALSE" onchange="markAnswered(<?php echo $num; ?>)"> False</label>
            <?php elseif ($q['question_type'] === 'fill_blank'): ?>
              <input type="text" name="q_<?php echo (int)$q['id']; ?>" placeholder="Type your answer" oninput="markAnswered(<?php echo $num; ?>)">
            <?php else: ?>
              <textarea name="q_<?php echo (int)$q['id']; ?>" rows="5" placeholder="Write your answer" oninput="markAnswered(<?php echo $num; ?>)"></textarea>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>

        <div class="test-card">
          <div class="submit-bar">
            <strong><span id="answeredCount2">0</span>/<?php echo count($questions); ?> answered</strong>
            <button class="btn btn-primary" type="submit" onclick="return confirm('Submit your test? You cannot change answers after submission.');">Submit Test</button>
          </div>
        </div>
      </form>
    </section>
  </main>

  <script src="../assets/js/app.js" defer></script>
  <script>
    const totalSeconds = <?php echo $duration; ?> * 60;
    const form = document.getElementById('testForm');
    const overlay = document.getElementById('submitOverlay');
    const storageKey = 'test_start_<?php echo $test_id; ?>';
    let start = Number(sessionStorage.getItem(storageKey));
    if (!start) {
      start = Date.now();
      sessionStorage.setItem(storageKey, String(start));
    }
    function renderTime() {
      const elapsed = Math.floor((Date.now() - start) / 1000);
      const left = Math.max(0, totalSeconds - elapsed);
      const m = String(Math.floor(left / 60)).padStart(2, '0');
      const s = String(left % 60).padStart(2, '0');
      document.getElementById('timerText').textContent = `${m}:${s}`;
      if (left <= 0) {
        clearInterval(timer);
        sessionStorage.removeItem(storageKey);
        overlay.classList.add('is-visible');
        form.submit();
      }
    }
    const timer = setInterval(renderTime, 1000);
    renderTime();

    const answered = new Set();
    function markAnswered(num) {
      answered.add(num);
      document.querySelector(`[data-nav="${num}"]`)?.classList.add('is-answered');
      document.getElementById('answeredCount').textContent = answered.size;
      document.getElementById('answeredCount2').textContent = answered.size;
    }
    form.addEventListener('submit', () => sessionStorage.removeItem(storageKey));
  </script>
</body>
</html>
