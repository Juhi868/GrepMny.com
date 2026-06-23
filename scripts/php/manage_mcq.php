<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['username'])) {
    redirect_with_status(APP_LOGIN, 'error', 'Unauthorized access.');
}

require_post('../../src/dashboard.php');

$action = $_POST['action'] ?? '';
$dashboard_url = '../../src/dashboard.php';

try {
    $conn = db();
} catch (Exception $e) {
    redirect_with_status($dashboard_url, 'error', 'Database connection failed.');
}

// ── CREATE TEST (Teacher only) ──────────────────────────
if ($action === 'create_test') {
    if (($_SESSION['role'] ?? '') !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Only teachers can create tests.');
    }

    $cid = filter_var($_POST['cid'] ?? '', FILTER_VALIDATE_INT);
    $title = clean_string($_POST['title'] ?? '', 100);
    $teacher_email = $_SESSION['username'];

    if (!$cid || !$title) {
        redirect_with_status($dashboard_url, 'error', 'Course ID and title are required.');
    }

    $stmt = $conn->prepare("INSERT INTO mock_tests (cid, teacher_email, title) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $cid, $teacher_email, $title);

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Mock test created successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to create mock test.');
    }

// ── DELETE TEST (Teacher only) ──────────────────────────
} else if ($action === 'delete_test') {
    if (($_SESSION['role'] ?? '') !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Unauthorized.');
    }

    $test_id = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    if (!$test_id) {
        redirect_with_status($dashboard_url, 'error', 'Invalid test ID.');
    }

    $conn->begin_transaction();
    try {
        $del_q = $conn->prepare("DELETE FROM mock_test_questions WHERE test_id = ?");
        $del_q->bind_param("i", $test_id);
        $del_q->execute();

        $del_r = $conn->prepare("DELETE FROM mock_test_results WHERE test_id = ?");
        $del_r->bind_param("i", $test_id);
        $del_r->execute();

        $del_t = $conn->prepare("DELETE FROM mock_tests WHERE id = ?");
        $del_t->bind_param("i", $test_id);
        $del_t->execute();

        $conn->commit();
        redirect_with_status($dashboard_url, 'success', 'Mock test deleted.');
    } catch (Exception $e) {
        $conn->rollback();
        redirect_with_status($dashboard_url, 'error', 'Failed to delete test.');
    }

// ── ADD QUESTION (Teacher only, max 10) ─────────────────
} else if ($action === 'add_question') {
    if (($_SESSION['role'] ?? '') !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Unauthorized.');
    }

    $test_id = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    $question_text = clean_string($_POST['question_text'] ?? '', 1000);
    $option_a = clean_string($_POST['option_a'] ?? '', 255);
    $option_b = clean_string($_POST['option_b'] ?? '', 255);
    $option_c = clean_string($_POST['option_c'] ?? '', 255);
    $option_d = clean_string($_POST['option_d'] ?? '', 255);
    $correct_option = strtoupper(trim($_POST['correct_option'] ?? 'A'));

    $redirect_url = '../../src/manage_mcq_ui.php?test_id=' . $test_id;

    if (!$test_id || !$question_text || !$option_a || !$option_b || !$option_c || !$option_d || !in_array($correct_option, ['A','B','C','D'])) {
        redirect_with_status($redirect_url, 'error', 'All fields are required with a valid correct option (A-D).');
    }

    // Enforce 10-question limit
    $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM mock_test_questions WHERE test_id = ?");
    $count_stmt->bind_param("i", $test_id);
    $count_stmt->execute();
    $count = (int) $count_stmt->get_result()->fetch_assoc()['c'];

    if ($count >= 10) {
        redirect_with_status($redirect_url, 'error', 'Maximum 10 questions per test. Delete a question first to add a new one.');
    }

    $stmt = $conn->prepare("INSERT INTO mock_test_questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $test_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option);

    if ($stmt->execute()) {
        redirect_with_status($redirect_url, 'success', 'Question added. (' . ($count + 1) . '/10)');
    } else {
        redirect_with_status($redirect_url, 'error', 'Failed to add question.');
    }

// ── DELETE QUESTION (Teacher only) ──────────────────────
} else if ($action === 'delete_question') {
    if (($_SESSION['role'] ?? '') !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Unauthorized.');
    }

    $question_id = filter_var($_POST['question_id'] ?? '', FILTER_VALIDATE_INT);
    $test_id = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    $redirect_url = '../../src/manage_mcq_ui.php?test_id=' . $test_id;

    if (!$question_id) {
        redirect_with_status($redirect_url, 'error', 'Invalid question ID.');
    }

    $stmt = $conn->prepare("DELETE FROM mock_test_questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);

    if ($stmt->execute()) {
        redirect_with_status($redirect_url, 'success', 'Question deleted.');
    } else {
        redirect_with_status($redirect_url, 'error', 'Delete failed.');
    }

// ── SUBMIT TEST (Student only, auto-graded) ─────────────
} else if ($action === 'submit_test') {
    if (($_SESSION['role'] ?? '') !== 'student') {
        redirect_with_status($dashboard_url, 'error', 'Only students can submit tests.');
    }

    $test_id = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    $semail = $_SESSION['username'];

    if (!$test_id) {
        redirect_with_status($dashboard_url, 'error', 'Invalid test.');
    }

    // Prevent duplicate submissions
    $chk = $conn->prepare("SELECT id FROM mock_test_results WHERE test_id = ? AND semail = ?");
    $chk->bind_param("is", $test_id, $semail);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        redirect_with_status($dashboard_url, 'error', 'You have already taken this test.');
    }

    // Fetch questions and grade
    $q_stmt = $conn->prepare("SELECT id, correct_option FROM mock_test_questions WHERE test_id = ?");
    $q_stmt->bind_param("i", $test_id);
    $q_stmt->execute();
    $questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $total_questions = count($questions);
    if ($total_questions === 0) {
        redirect_with_status($dashboard_url, 'error', 'Test has no questions.');
    }

    $correct_answers = 0;
    foreach ($questions as $q) {
        $selected = $_POST['q_' . $q['id']] ?? '';
        if ($selected === $q['correct_option']) {
            $correct_answers++;
        }
    }

    $stmt = $conn->prepare("INSERT INTO mock_test_results (test_id, semail, score, total) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $test_id, $semail, $correct_answers, $total_questions);

    if ($stmt->execute()) {
        $pct = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100) : 0;
        redirect_with_status($dashboard_url, 'success', "Test submitted! You scored $correct_answers/$total_questions ($pct%).");
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to save test results.');
    }

} else {
    redirect_with_status($dashboard_url, 'error', 'Invalid action.');
}
