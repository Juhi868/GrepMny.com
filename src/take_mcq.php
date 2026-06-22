<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../scripts/php/config.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
    redirect_with_status('login.php', 'error', 'Only students can take tests.');
}

$assignment_id = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
if (!$assignment_id) {
    redirect_with_status('dashboard.php', 'error', 'Invalid assignment ID.');
}

$conn = db();

// Get assignment details
$stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    redirect_with_status('dashboard.php', 'error', 'Assignment not found.');
}

// Check if already submitted
$chk = $conn->prepare("SELECT id FROM student_assignments WHERE assignment_id = ? AND semail = ?");
$chk->bind_param("is", $assignment_id, $_SESSION['email']);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    redirect_with_status('dashboard.php', 'error', 'You have already submitted this test.');
}

// Get existing MCQs
$q_stmt = $conn->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM mcq_questions WHERE assignment_id = ? ORDER BY id ASC");
$q_stmt->bind_param("i", $assignment_id);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($questions)) {
    redirect_with_status('dashboard.php', 'error', 'This test has no questions yet.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Take Test - GrepMny</title>
  <link rel="stylesheet" href="../assets/css/index.css">
  <style>
    .test-container { max-width: 800px; margin: 2rem auto; padding: 2rem; background: var(--surface); border-radius: 8px; border: 1px solid var(--line); }
    .question-block { border: 1px solid var(--line); padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 6px; }
    .question-text { font-weight: bold; font-size: 1.1rem; margin-bottom: 1rem; }
    .option-label { display: block; padding: 0.75rem; border: 1px solid var(--line); border-radius: 4px; margin-bottom: 0.5rem; cursor: pointer; transition: background 0.2s; }
    .option-label:hover { background: var(--hover); }
    input[type="radio"] { margin-right: 0.5rem; }
  </style>
</head>
<body class="light-mode">
  <header class="app-header">
    <h2>GrepMny - Take Test</h2>
    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
  </header>

  <main class="test-container">
    <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
    <p>Please answer all questions below and submit your test.</p>
    
    <hr style="border: 0; border-bottom: 1px solid var(--line); margin: 2rem 0;">

    <form action="../scripts/php/manage_mcq.php" method="post">
      <input type="hidden" name="action" value="submit_test">
      <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
      
      <?php foreach ($questions as $index => $q): ?>
        <div class="question-block">
          <div class="question-text">Q<?php echo $index + 1; ?>: <?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
          
          <label class="option-label">
            <input type="radio" name="q_<?php echo $q['id']; ?>" value="A" required>
            A. <?php echo htmlspecialchars($q['option_a']); ?>
          </label>
          <label class="option-label">
            <input type="radio" name="q_<?php echo $q['id']; ?>" value="B">
            B. <?php echo htmlspecialchars($q['option_b']); ?>
          </label>
          <label class="option-label">
            <input type="radio" name="q_<?php echo $q['id']; ?>" value="C">
            C. <?php echo htmlspecialchars($q['option_c']); ?>
          </label>
          <label class="option-label">
            <input type="radio" name="q_<?php echo $q['id']; ?>" value="D">
            D. <?php echo htmlspecialchars($q['option_d']); ?>
          </label>
        </div>
      <?php endforeach; ?>
      
      <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to submit? You cannot change your answers later.');">Submit Test</button>
    </form>
  </main>
</body>
</html>
