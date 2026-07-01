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
$teacher = $_SESSION['username'];
$stmt = $conn->prepare("SELECT * FROM mock_tests WHERE id = ? AND teacher_email = ?");
$stmt->bind_param("is", $test_id, $teacher);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();
if (!$test) {
    redirect_with_status('dashboard.php', 'error', 'Test not found.');
}

$q_stmt = $conn->prepare("SELECT * FROM mock_test_questions WHERE test_id = ? ORDER BY id ASC");
$q_stmt->bind_param("i", $test_id);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$r_stmt = $conn->prepare("SELECT r.*, COALESCE(sd.sname, r.semail) AS student_name FROM mock_test_results r LEFT JOIN `student details` sd ON sd.semail COLLATE utf8mb4_unicode_ci = r.semail COLLATE utf8mb4_unicode_ci AND sd.cid = ? WHERE r.test_id = ? GROUP BY r.id ORDER BY r.score DESC, r.submitted_at ASC");
$r_stmt->bind_param("ii", $test['cid'], $test_id);
$r_stmt->execute();
$results = $r_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$answersByResult = [];
$a_stmt = $conn->prepare("SELECT a.*, q.question_text, q.question_type, q.marks FROM mock_test_answers a JOIN mock_test_questions q ON q.id = a.question_id JOIN mock_test_results r ON r.id = a.result_id WHERE r.test_id = ? ORDER BY a.id ASC");
$a_stmt->bind_param("i", $test_id);
$a_stmt->execute();
foreach ($a_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $answer) {
    $answersByResult[(int)$answer['result_id']][] = $answer;
}

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-page="dashboard">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Test | GrepMny</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="../assets/css/app.css" rel="stylesheet">
  <style>
    .test-admin { width:min(1120px, calc(100% - 2rem)); margin:2rem auto; display:grid; gap:1.5rem; }
    .panel { background:var(--surface-strong); border:1px solid var(--line); border-radius:var(--radius); padding:1.4rem; box-shadow:var(--shadow); }
    .admin-head { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; align-items:flex-start; }
    .form-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:1rem; }
    @media (max-width: 780px) { .form-grid { grid-template-columns:1fr; } }
    .wide { grid-column:1 / -1; }
    .question-card { border:1px solid var(--line); border-radius:var(--radius); padding:1rem; margin-top:.8rem; }
    .pill { display:inline-flex; padding:.25rem .55rem; border-radius:999px; background:color-mix(in srgb, var(--primary) 12%, var(--surface)); color:var(--primary); font-weight:800; font-size:.75rem; }
    .option-list { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:.5rem; margin-top:.75rem; }
    @media (max-width: 640px) { .option-list { grid-template-columns:1fr; } }
    .option-list div { border:1px solid var(--line); border-radius:6px; padding:.55rem .7rem; }
    .correct { border-color:var(--success)!important; background:color-mix(in srgb, var(--success) 10%, var(--surface-strong)); }
    .answer-box { border-top:1px solid var(--line); padding-top:1rem; margin-top:1rem; }
    .alert-bar { padding:.75rem 1rem; border-radius:var(--radius); font-weight:700; }
    .alert-bar.success { background:color-mix(in srgb, var(--success) 15%, var(--surface-strong)); color:var(--success); }
    .alert-bar.error { background:color-mix(in srgb, var(--danger) 15%, var(--surface-strong)); color:var(--danger); }
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
      <div class="site-menu always-visible"><a href="./dashboard.php">Back to Dashboard</a></div>
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle></button>
    </nav>
  </header>

  <main class="test-admin">
    <?php if ($status && $message): ?><div class="alert-bar <?php echo $status === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <section class="panel">
      <div class="admin-head">
        <div>
          <p class="eyebrow" style="margin:0;">Test Module · Course #<?php echo (int)$test['cid']; ?></p>
          <h1 style="margin:.15rem 0 0;"><?php echo htmlspecialchars($test['title']); ?></h1>
        </div>
        <form action="../scripts/php/manage_mcq.php" method="post" onsubmit="return confirm('Delete this test and all attempts?');">
          <input type="hidden" name="action" value="delete_test">
          <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
          <button class="btn btn-secondary" style="border-color:var(--danger); color:var(--danger);" type="submit">Delete Test</button>
        </form>
      </div>
    </section>

    <section class="panel">
      <h3>Schedule & Assignment</h3>
      <?php $assignedCount = count(array_filter(explode(',', (string) $test['assigned_students']))); ?>
      <form action="../scripts/php/manage_mcq.php" method="post" class="form-grid">
        <input type="hidden" name="action" value="update_test">
        <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
        <label class="field compact"><span>Course Name</span>
          <select name="cname" required data-course-map data-cid-target="#edit-test-cid">
            <?php
              $teacherCourses = $conn->prepare('SELECT cid, cname FROM course_teachers WHERE teacher_email = ? ORDER BY cname ASC');
              $teacherCourses->bind_param('s', $teacher);
              $teacherCourses->execute();
              foreach ($teacherCourses->get_result()->fetch_all(MYSQLI_ASSOC) as $tc):
            ?>
              <option value="<?php echo htmlspecialchars($tc['cname']); ?>" data-cid="<?php echo (int) $tc['cid']; ?>" <?php echo (int) $tc['cid'] === (int) $test['cid'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tc['cname']); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field compact"><span>Course ID</span><input type="number" id="edit-test-cid" name="cid" value="<?php echo (int)$test['cid']; ?>" readonly required></label>
        <label class="field compact"><span>Title</span><input type="text" name="title" value="<?php echo htmlspecialchars($test['title']); ?>" required></label>
        <label class="field compact wide"><span>Description</span><textarea name="description" rows="3"><?php echo htmlspecialchars((string)$test['description']); ?></textarea></label>
        <label class="field compact"><span>Pass Percentage</span><input type="number" min="0" max="100" name="pass_percentage" value="<?php echo (int)$test['pass_percentage']; ?>" required></label>
        <label class="field compact wide"><span>Assigned Students</span><input type="text" value="<?php echo $assignedCount; ?> enrolled student(s) — auto-assigned from course" readonly></label>

        <div class="wide" style="grid-column:1 / -1; border:1px solid var(--line); border-radius:var(--radius); padding:1rem; background:color-mix(in srgb, var(--primary) 5%, transparent);">
          <h4 style="margin:0 0 0.75rem;">Time Configuration</h4>

          <div class="form-grid" style="margin-bottom:1rem;">
            <label class="field compact">
              <span>Test Duration (minutes)</span>
              <input type="number" min="1" max="240" name="duration_minutes" value="<?php echo (int)$test['duration_minutes']; ?>" required>
            </label>
          </div>

          <p style="margin:0 0 0.65rem; font-weight:600; font-size:0.9rem; color:var(--muted);">Exam Start</p>
          <div class="time-input-group" style="margin-bottom:1rem;">
            <label class="field compact">
              <span>Start Date</span>
              <input type="date" name="starts_at_date" value="<?php echo $test['starts_at'] ? date('Y-m-d', strtotime($test['starts_at'])) : ''; ?>">
            </label>
            <label class="field compact">
              <span>Start Time</span>
              <input type="time" name="starts_at_time" value="<?php echo $test['starts_at'] ? date('H:i', strtotime($test['starts_at'])) : ''; ?>">
            </label>
          </div>

          <p style="margin:0 0 0.65rem; font-weight:600; font-size:0.9rem; color:var(--muted);">Exam End</p>
          <div class="time-input-group" style="margin-bottom:0;">
            <label class="field compact">
              <span>End Date</span>
              <input type="date" name="ends_at_date" value="<?php echo $test['ends_at'] ? date('Y-m-d', strtotime($test['ends_at'])) : ''; ?>">
            </label>
            <label class="field compact">
              <span>End Time</span>
              <input type="time" name="ends_at_time" value="<?php echo $test['ends_at'] ? date('H:i', strtotime($test['ends_at'])) : ''; ?>">
            </label>
          </div>
        </div>

        <button class="btn btn-primary" type="submit">Save Test</button>
      </form>
    </section>

    <section class="panel">
      <h3>Add Question</h3>
      <form action="../scripts/php/manage_mcq.php" method="post" class="form-grid">
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
        <label class="field compact"><span>Type</span><select name="question_type" id="questionType"><option value="mcq">MCQ</option><option value="multiple_correct">Multiple Correct</option><option value="true_false">True / False</option><option value="fill_blank">Fill in the Blank</option><option value="subjective">Subjective</option></select></label>
        <label class="field compact"><span>Marks</span><input type="number" name="marks" min="1" max="100" value="1" required></label>
        <label class="field compact wide"><span>Question</span><textarea name="question_text" rows="3" required></textarea></label>
        <label class="field compact option-field"><span>Option A</span><input type="text" name="option_a"></label>
        <label class="field compact option-field"><span>Option B</span><input type="text" name="option_b"></label>
        <label class="field compact option-field"><span>Option C</span><input type="text" name="option_c"></label>
        <label class="field compact option-field"><span>Option D</span><input type="text" name="option_d"></label>
        <label class="field compact single-correct"><span>Correct Option</span><select name="correct_option"><option>A</option><option>B</option><option>C</option><option>D</option></select></label>
        <label class="field compact multi-correct" style="display:none;"><span>Correct Options</span><select name="correct_options[]" multiple size="4"><option>A</option><option>B</option><option>C</option><option>D</option></select></label>
        <label class="field compact true-false" style="display:none;"><span>Correct Answer</span><select name="true_false_answer"><option>TRUE</option><option>FALSE</option></select></label>
        <label class="field compact blank-answer" style="display:none;"><span>Accepted Answer</span><input type="text" name="blank_answer"></label>
        <button class="btn btn-primary" type="submit">Add Question</button>
      </form>
    </section>

    <section class="panel">
      <h3>Questions (<?php echo count($questions); ?>)</h3>
      <?php if (!$questions): ?><p class="description">No questions added yet.</p><?php endif; ?>
      <?php foreach ($questions as $i => $q): ?>
        <article class="question-card">
          <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start;">
            <div><span class="pill"><?php echo htmlspecialchars(str_replace('_', ' ', $q['question_type'])); ?> · <?php echo (int)$q['marks']; ?> marks</span><h4><?php echo ($i + 1) . '. ' . htmlspecialchars($q['question_text']); ?></h4></div>
            <form action="../scripts/php/manage_mcq.php" method="post" onsubmit="return confirm('Delete this question?');">
              <input type="hidden" name="action" value="delete_question"><input type="hidden" name="question_id" value="<?php echo (int)$q['id']; ?>"><input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
              <button class="btn btn-secondary" type="submit" style="min-height:auto; padding:.35rem .75rem;">Delete</button>
            </form>
          </div>
          <?php if (in_array($q['question_type'], ['mcq','multiple_correct','true_false'], true)): ?>
            <div class="option-list">
              <?php foreach (['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $letter => $field): if ($q[$field] === '') continue; ?>
                <div class="<?php echo in_array($letter, explode(',', (string)$q['correct_option']), true) || strtoupper((string)$q['correct_option']) === strtoupper((string)$q[$field]) ? 'correct' : ''; ?>"><strong><?php echo $letter; ?>.</strong> <?php echo htmlspecialchars($q[$field]); ?></div>
              <?php endforeach; ?>
            </div>
          <?php elseif ($q['question_type'] === 'fill_blank'): ?>
            <p><strong>Answer key:</strong> <?php echo htmlspecialchars($q['correct_option']); ?></p>
          <?php else: ?>
            <p class="description">Subjective question. Teacher evaluation required after submission.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="panel">
      <h3>Attempts & Evaluation</h3>
      <div class="data-table-wrapper">
        <table class="data-table">
          <thead><tr><th>Rank</th><th>Student</th><th>Score</th><th>Status</th><th>Submitted</th></tr></thead>
          <tbody>
            <?php if (!$results): ?><tr><td colspan="5" style="text-align:center; color:var(--muted);">No attempts yet.</td></tr><?php endif; ?>
            <?php foreach ($results as $rank => $result): $pct = (int)$result['total'] > 0 ? round(((float)$result['score'] / (int)$result['total']) * 100, 1) : 0; ?>
              <tr><td>#<?php echo $rank + 1; ?></td><td><strong><?php echo htmlspecialchars($result['student_name']); ?></strong><br><small><?php echo htmlspecialchars($result['semail']); ?></small></td><td><?php echo htmlspecialchars((string)$result['score']); ?>/<?php echo (int)$result['total']; ?> (<?php echo $pct; ?>%)</td><td><?php echo htmlspecialchars($result['status']); ?></td><td><?php echo htmlspecialchars($result['submitted_at']); ?></td></tr>
              <?php foreach ($answersByResult[(int)$result['id']] ?? [] as $answer): if ($answer['question_type'] !== 'subjective') continue; ?>
                <tr><td></td><td colspan="4">
                  <div class="answer-box">
                    <strong><?php echo htmlspecialchars($answer['question_text']); ?></strong>
                    <p><?php echo nl2br(htmlspecialchars((string)$answer['answer_text'])); ?></p>
                    <form action="../scripts/php/manage_mcq.php" method="post" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:end;">
                      <input type="hidden" name="action" value="evaluate_answer"><input type="hidden" name="test_id" value="<?php echo $test_id; ?>"><input type="hidden" name="answer_id" value="<?php echo (int)$answer['id']; ?>">
                      <label class="field compact"><span>Marks / <?php echo (int)$answer['marks']; ?></span><input type="number" step="0.5" min="0" max="<?php echo (int)$answer['marks']; ?>" name="marks_awarded" value="<?php echo htmlspecialchars((string)$answer['marks_awarded']); ?>"></label>
                      <label class="field compact"><span>Feedback</span><input type="text" name="teacher_feedback" value="<?php echo htmlspecialchars((string)$answer['teacher_feedback']); ?>"></label>
                      <button class="btn btn-primary" type="submit">Save Evaluation</button>
                    </form>
                  </div>
                </td></tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <script src="../assets/js/app.js" defer></script>
  <script>
    const typeSelect = document.getElementById('questionType');
    const groups = {
      option: document.querySelectorAll('.option-field'),
      single: document.querySelector('.single-correct'),
      multi: document.querySelector('.multi-correct'),
      tf: document.querySelector('.true-false'),
      blank: document.querySelector('.blank-answer')
    };
    function syncQuestionFields() {
      const type = typeSelect.value;
      groups.option.forEach(el => el.style.display = ['mcq','multiple_correct'].includes(type) ? '' : 'none');
      groups.single.style.display = type === 'mcq' ? '' : 'none';
      groups.multi.style.display = type === 'multiple_correct' ? '' : 'none';
      groups.tf.style.display = type === 'true_false' ? '' : 'none';
      groups.blank.style.display = type === 'fill_blank' ? '' : 'none';
    }
    typeSelect.addEventListener('change', syncQuestionFields);
    syncQuestionFields();
  </script>
</body>
</html>
