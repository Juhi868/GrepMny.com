<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../scripts/php/config.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    redirect_with_status('login.php', 'error', 'Unauthorized access.');
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

// Get existing MCQs
$q_stmt = $conn->prepare("SELECT * FROM mcq_questions WHERE assignment_id = ? ORDER BY id ASC");
$q_stmt->bind_param("i", $assignment_id);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage MCQs - GrepMny</title>
  <link rel="stylesheet" href="../assets/css/index.css">
  <style>
    .mcq-container { max-width: 800px; margin: 2rem auto; padding: 2rem; background: var(--surface); border-radius: 8px; border: 1px solid var(--line); }
    .question-card { border: 1px solid var(--line); padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
    .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid var(--line); border-radius: 4px; }
  </style>
</head>
<body class="light-mode">
  <header class="app-header">
    <h2>GrepMny - Manage MCQs</h2>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </header>

  <main class="mcq-container">
    <h3><?php echo htmlspecialchars($assignment['title']); ?> (<?php echo htmlspecialchars($assignment['type']); ?>)</h3>
    <p>Manage multiple choice questions for this assignment.</p>
    
    <hr style="border: 0; border-bottom: 1px solid var(--line); margin: 2rem 0;">

    <h4>Add New Question</h4>
    <form action="../scripts/php/manage_mcq.php" method="post" class="question-card">
      <input type="hidden" name="action" value="add_question">
      <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
      
      <div class="form-group">
        <label>Question Text</label>
        <textarea name="question_text" rows="3" required></textarea>
      </div>
      
      <div class="options-grid">
        <div class="form-group">
          <label>Option A</label>
          <input type="text" name="option_a" required>
        </div>
        <div class="form-group">
          <label>Option B</label>
          <input type="text" name="option_b" required>
        </div>
        <div class="form-group">
          <label>Option C</label>
          <input type="text" name="option_c" required>
        </div>
        <div class="form-group">
          <label>Option D</label>
          <input type="text" name="option_d" required>
        </div>
      </div>
      
      <div class="form-group">
        <label>Correct Option</label>
        <select name="correct_option" required>
          <option value="A">A</option>
          <option value="B">B</option>
          <option value="C">C</option>
          <option value="D">D</option>
        </select>
      </div>
      
      <button type="submit" class="btn btn-primary">Add Question</button>
    </form>

    <h4 style="margin-top: 2rem;">Existing Questions (<?php echo count($questions); ?>)</h4>
    <?php if (empty($questions)): ?>
      <p style="color: var(--muted);">No questions added yet.</p>
    <?php else: ?>
      <?php foreach ($questions as $index => $q): ?>
        <div class="question-card">
          <div style="display: flex; justify-content: space-between;">
            <strong>Q<?php echo $index + 1; ?>: <?php echo nl2br(htmlspecialchars($q['question_text'])); ?></strong>
            <form action="../scripts/php/manage_mcq.php" method="post" onsubmit="return confirm('Delete this question?');">
              <input type="hidden" name="action" value="delete_question">
              <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
              <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
              <button type="submit" class="btn btn-secondary" style="color: var(--danger); border-color: var(--danger); padding: 0.2rem 0.5rem;">Delete</button>
            </form>
          </div>
          <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
            <li <?php echo $q['correct_option'] === 'A' ? 'style="font-weight:bold; color:var(--primary);"' : ''; ?>>A: <?php echo htmlspecialchars($q['option_a']); ?></li>
            <li <?php echo $q['correct_option'] === 'B' ? 'style="font-weight:bold; color:var(--primary);"' : ''; ?>>B: <?php echo htmlspecialchars($q['option_b']); ?></li>
            <li <?php echo $q['correct_option'] === 'C' ? 'style="font-weight:bold; color:var(--primary);"' : ''; ?>>C: <?php echo htmlspecialchars($q['option_c']); ?></li>
            <li <?php echo $q['correct_option'] === 'D' ? 'style="font-weight:bold; color:var(--primary);"' : ''; ?>>D: <?php echo htmlspecialchars($q['option_d']); ?></li>
          </ul>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </main>
</body>
</html>
