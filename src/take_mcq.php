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

// Get test details
$stmt = $conn->prepare("SELECT * FROM mock_tests WHERE id = ?");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();

if (!$test) {
    redirect_with_status('dashboard.php', 'error', 'Test not found.');
}

// Check if already submitted
$chk = $conn->prepare("SELECT id FROM mock_test_results WHERE test_id = ? AND semail = ?");
$chk->bind_param("is", $test_id, $_SESSION['username']);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    redirect_with_status('dashboard.php', 'error', 'You have already taken this test.');
}

// Get questions (limit 10)
$q_stmt = $conn->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM mock_test_questions WHERE test_id = ? ORDER BY id ASC LIMIT 10");
$q_stmt->bind_param("i", $test_id);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($questions)) {
    redirect_with_status('dashboard.php', 'error', 'This test has no questions yet.');
}

$total_questions = count($questions);
?>
<!DOCTYPE html>
<html lang="en" data-page="dashboard">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Take Test — <?php echo htmlspecialchars($test['title']); ?> | GrepMny</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
  <style>
    .test-layout {
      display: grid; grid-template-columns: 220px 1fr;
      gap: 2rem; max-width: 1060px; margin: 2rem auto; padding: 0 1rem;
    }
    @media (max-width: 768px) {
      .test-layout { grid-template-columns: 1fr; }
      .test-sidebar { position: static !important; }
    }

    /* Timer */
    .timer-card {
      background: var(--surface-strong); border: 1px solid var(--line);
      border-radius: var(--radius); padding: 1.25rem; box-shadow: var(--shadow);
      position: sticky; top: 6rem; text-align: center;
    }
    .timer-ring-wrap { margin: 0 auto 1rem; width: 120px; height: 120px; position: relative; }
    .timer-ring { width: 120px; height: 120px; transform: rotate(-90deg); }
    .timer-ring-bg { fill: none; stroke: var(--line); stroke-width: 6; }
    .timer-ring-fill {
      fill: none; stroke: url(#timerGrad); stroke-width: 6;
      stroke-linecap: round;
      transition: stroke-dashoffset 1s linear;
    }
    .timer-ring-fill.is-warning { stroke: var(--danger); }
    .timer-text {
      position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
      font-family: 'JetBrains Mono', monospace; font-size: 1.4rem; font-weight: 700;
      color: var(--text); letter-spacing: -0.02em;
    }
    .timer-text.is-warning { color: var(--danger); animation: timerPulse 1s ease infinite; }
    .timer-label { font-size: 0.8rem; font-weight: 700; color: var(--muted); margin-bottom: 1.25rem; }

    /* Question nav */
    .q-nav { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.4rem; margin-bottom: 1.25rem; }
    .q-nav-btn {
      width: 100%; aspect-ratio: 1; border: 1px solid var(--line); border-radius: 6px;
      background: var(--surface-strong); color: var(--muted); font-weight: 800; font-size: 0.8rem;
      cursor: pointer; transition: all 0.15s; font-family: inherit;
    }
    .q-nav-btn:hover { border-color: var(--primary); color: var(--text); }
    .q-nav-btn.is-answered { background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; border-color: transparent; }
    .q-nav-btn.is-current { border-color: var(--primary); box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 30%, transparent); }

    /* Main content */
    .test-card {
      background: var(--surface-strong); border: 1px solid var(--line);
      border-radius: var(--radius); padding: 1.5rem; box-shadow: var(--shadow);
    }
    .test-card h3 { margin: 0 0 0.25rem; font-size: 1.1rem; }
    .test-card .description { margin: 0 0 1.5rem; color: var(--muted); font-size: 0.9rem; }

    /* Question blocks */
    .question-block {
      background: color-mix(in srgb, var(--surface) 30%, transparent);
      border: 1px solid var(--line); border-radius: var(--radius);
      padding: 1.5rem; margin-bottom: 1.25rem;
      transition: border-color 0.2s;
    }
    .question-block:hover { border-color: color-mix(in srgb, var(--primary) 40%, var(--line)); }
    .question-header { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
    .q-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 32px; height: 32px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: #fff; font-size: 0.85rem; font-weight: 800; flex-shrink: 0;
    }
    .q-title { font-weight: 700; font-size: 1rem; line-height: 1.5; }

    /* Options */
    .options-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .option-label {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 0.85rem 1rem; border: 2px solid var(--line); border-radius: var(--radius);
      cursor: pointer; transition: all 0.2s; font-weight: 500;
    }
    .option-label:hover { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 5%, transparent); }
    .option-label input[type="radio"] { display: none; }
    .option-label input[type="radio"]:checked + .opt-circle {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      border-color: transparent;
    }
    .option-label input[type="radio"]:checked + .opt-circle::after { opacity: 1; }
    .option-label:has(input:checked) {
      border-color: var(--primary);
      background: color-mix(in srgb, var(--primary) 8%, transparent);
    }
    .opt-circle {
      width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--line);
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      transition: all 0.2s; position: relative;
    }
    .opt-circle::after {
      content: '✓'; color: #fff; font-size: 0.7rem; font-weight: 900;
      opacity: 0; transition: opacity 0.15s;
    }
    .opt-letter { font-weight: 800; color: var(--muted); min-width: 1.2rem; }
    .opt-text { font-size: 0.95rem; }

    /* Submit bar */
    .submit-bar {
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem;
    }
    .answered-count { font-size: 0.9rem; font-weight: 700; color: var(--muted); }

    /* Warning overlay for auto-submit */
    .auto-submit-overlay {
      display: none; position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,0.7); align-items: center; justify-content: center;
    }
    .auto-submit-overlay.is-visible { display: flex; }
    .auto-submit-msg {
      background: var(--surface-strong); border-radius: var(--radius);
      padding: 2rem; text-align: center; max-width: 400px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: scaleIn 0.3s ease;
    }
    .auto-submit-msg h2 { margin: 0 0 0.5rem; color: var(--danger); }
    .auto-submit-msg p { margin: 0; color: var(--muted); }

    @keyframes timerPulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
    @keyframes scaleIn {
      from { opacity: 0; transform: scale(0.9); }
      to { opacity: 1; transform: scale(1); }
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
        <span style="color:var(--danger); font-weight:800; font-size:0.9rem;">⏱ TEST IN PROGRESS</span>
      </div>
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
    </nav>
  </header>

  <!-- Auto-submit overlay -->
  <div class="auto-submit-overlay" id="autoSubmitOverlay">
    <div class="auto-submit-msg">
      <h2>⏱ Time's Up!</h2>
      <p>Your test is being submitted automatically...</p>
    </div>
  </div>

  <main class="test-layout">
    <!-- Sidebar: Timer + Question Nav -->
    <aside class="test-sidebar">
      <div class="timer-card">
        <div class="timer-ring-wrap">
          <svg class="timer-ring" viewBox="0 0 120 120">
            <defs>
              <linearGradient id="timerGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="var(--primary)" />
                <stop offset="100%" stop-color="var(--accent)" />
              </linearGradient>
            </defs>
            <circle class="timer-ring-bg" cx="60" cy="60" r="52" />
            <circle class="timer-ring-fill" cx="60" cy="60" r="52" id="timerRing"
              stroke-dasharray="326.73" stroke-dashoffset="0" />
          </svg>
          <div class="timer-text" id="timerText">30:00</div>
        </div>
        <div class="timer-label">TIME REMAINING</div>

        <div class="q-nav" id="questionNav">
          <?php for ($i = 1; $i <= $total_questions; $i++): ?>
            <button type="button" class="q-nav-btn" data-nav-q="<?php echo $i; ?>"
              onclick="scrollToQuestion(<?php echo $i; ?>)"><?php echo $i; ?></button>
          <?php endfor; ?>
        </div>

        <div style="font-size:0.8rem; color:var(--muted); font-weight:600;">
          <span id="answeredDisplay">0</span>/<?php echo $total_questions; ?> answered
        </div>
      </div>
    </aside>

    <!-- Test Content -->
    <section>
      <div class="test-card" style="margin-bottom:1.5rem;">
        <p class="eyebrow" style="margin:0;">Mock Test · Course #<?php echo $test['cid']; ?></p>
        <h1 style="margin:0.25rem 0 0; font-size:1.4rem; letter-spacing:-0.03em;"><?php echo htmlspecialchars($test['title']); ?></h1>
        <p class="description" style="margin-top:0.5rem;">
          Answer all <?php echo $total_questions; ?> questions within 30 minutes. Your test will be auto-submitted when time expires.
        </p>
      </div>

      <form id="testForm" action="../scripts/php/manage_mcq.php" method="post">
        <input type="hidden" name="action" value="submit_test">
        <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">

        <?php foreach ($questions as $index => $q): $num = $index + 1; ?>
          <div class="question-block" id="question-<?php echo $num; ?>" data-question="<?php echo $num; ?>">
            <div class="question-header">
              <span class="q-badge"><?php echo $num; ?></span>
              <span class="q-title"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></span>
            </div>
            <div class="options-list">
              <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field): ?>
                <label class="option-label">
                  <input type="radio" name="q_<?php echo $q['id']; ?>" value="<?php echo $letter; ?>"
                    onchange="markAnswered(<?php echo $num; ?>)">
                  <span class="opt-circle"></span>
                  <span class="opt-letter"><?php echo $letter; ?>.</span>
                  <span class="opt-text"><?php echo htmlspecialchars($q[$field]); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="test-card">
          <div class="submit-bar">
            <span class="answered-count"><span id="answeredDisplay2">0</span>/<?php echo $total_questions; ?> questions answered</span>
            <button type="submit" class="btn btn-primary"
              onclick="return confirm('Submit your test? You cannot change answers later.');"
              style="min-height:2.75rem; padding: 0 2rem; font-size:1rem;">
              Submit Test
            </button>
          </div>
        </div>
      </form>
    </section>
  </main>

  <script src="../assets/js/app.js" defer></script>
  <script>
    // ── 30-MINUTE COUNTDOWN TIMER ────────────────────────────
    const TOTAL_SECONDS = 30 * 60; // 30 minutes
    const WARNING_THRESHOLD = 5 * 60; // 5 minutes warning
    const circumference = 2 * Math.PI * 52;

    let secondsLeft = TOTAL_SECONDS;
    const timerText = document.getElementById('timerText');
    const timerRing = document.getElementById('timerRing');
    const overlay = document.getElementById('autoSubmitOverlay');
    const form = document.getElementById('testForm');

    // Persist start time in sessionStorage to survive page refresh
    const storageKey = 'mocktest_start_<?php echo $test_id; ?>';
    let startTime = sessionStorage.getItem(storageKey);
    if (!startTime) {
      startTime = Date.now();
      sessionStorage.setItem(storageKey, startTime);
    } else {
      startTime = parseInt(startTime);
      const elapsed = Math.floor((Date.now() - startTime) / 1000);
      secondsLeft = Math.max(0, TOTAL_SECONDS - elapsed);
    }

    function formatTime(s) {
      const m = Math.floor(s / 60);
      const sec = s % 60;
      return `${m.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
    }

    function updateTimer() {
      const elapsed = Math.floor((Date.now() - startTime) / 1000);
      secondsLeft = Math.max(0, TOTAL_SECONDS - elapsed);

      timerText.textContent = formatTime(secondsLeft);

      // Update ring
      const progress = secondsLeft / TOTAL_SECONDS;
      const offset = circumference * (1 - progress);
      timerRing.style.strokeDashoffset = offset;

      // Warning state
      if (secondsLeft <= WARNING_THRESHOLD) {
        timerText.classList.add('is-warning');
        timerRing.classList.add('is-warning');
      }

      // Time's up — auto-submit
      if (secondsLeft <= 0) {
        clearInterval(timerInterval);
        sessionStorage.removeItem(storageKey);
        overlay.classList.add('is-visible');
        setTimeout(() => form.submit(), 1500);
        return;
      }
    }

    updateTimer();
    const timerInterval = setInterval(updateTimer, 1000);

    // ── QUESTION NAVIGATION ──────────────────────────────────
    const answeredSet = new Set();

    function markAnswered(questionNum) {
      answeredSet.add(questionNum);
      // Update nav buttons
      const btn = document.querySelector(`[data-nav-q="${questionNum}"]`);
      if (btn) btn.classList.add('is-answered');
      // Update counters
      document.getElementById('answeredDisplay').textContent = answeredSet.size;
      document.getElementById('answeredDisplay2').textContent = answeredSet.size;
    }

    function scrollToQuestion(num) {
      const el = document.getElementById('question-' + num);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Highlight briefly
        el.style.borderColor = 'var(--primary)';
        setTimeout(() => el.style.borderColor = '', 1500);
      }
      // Update current
      document.querySelectorAll('.q-nav-btn').forEach(b => b.classList.remove('is-current'));
      const btn = document.querySelector(`[data-nav-q="${num}"]`);
      if (btn) btn.classList.add('is-current');
    }

    // Track scroll position to highlight current question
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const num = entry.target.dataset.question;
          document.querySelectorAll('.q-nav-btn').forEach(b => b.classList.remove('is-current'));
          const btn = document.querySelector(`[data-nav-q="${num}"]`);
          if (btn) btn.classList.add('is-current');
        }
      });
    }, { threshold: 0.5 });

    document.querySelectorAll('.question-block').forEach(block => observer.observe(block));

    // Warn before leaving
    window.addEventListener('beforeunload', (e) => {
      e.preventDefault();
      e.returnValue = '';
    });
  </script>
</body>
</html>
