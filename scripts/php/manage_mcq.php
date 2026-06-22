<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['username'])) {
    redirect_with_status(APP_LOGIN, 'error', 'Unauthorized access.');
}

require_post('../../src/dashboard.php');

$action = $_POST['action'] ?? '';

try {
    $conn = db();
} catch (Exception $e) {
    redirect_with_status('../../src/dashboard.php', 'error', 'Database connection failed.');
}

if ($action === 'add_question') {
    if (($_SESSION['role'] ?? '') !== 'teacher') {
        redirect_with_status('../../src/dashboard.php', 'error', 'Unauthorized.');
    }
    $assignment_id = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    $question_text = clean_string($_POST['question_text'] ?? '', 1000);
    $option_a = clean_string($_POST['option_a'] ?? '', 255);
    $option_b = clean_string($_POST['option_b'] ?? '', 255);
    $option_c = clean_string($_POST['option_c'] ?? '', 255);
    $option_d = clean_string($_POST['option_d'] ?? '', 255);
    $correct_option = filter_input(INPUT_POST, 'correct_option', FILTER_SANITIZE_STRING) ?? 'A';

    $redirect_url = '../../src/manage_mcq_ui.php?assignment_id=' . $assignment_id;

    if (!$assignment_id || !$question_text || !$option_a || !$option_b || !$option_c || !$option_d || !in_array($correct_option, ['A','B','C','D'])) {
        redirect_with_status($redirect_url, 'error', 'Invalid input.');
    }

    $stmt = $conn->prepare("INSERT INTO mcq_questions (assignment_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $assignment_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option);

    if ($stmt->execute()) {
        redirect_with_status($redirect_url, 'success', 'Question added.');
    } else {
        redirect_with_status($redirect_url, 'error', 'Database error.');
    }
} else if ($action === 'delete_question') {
    if (($_SESSION['role'] ?? '') !== 'teacher') {
        redirect_with_status('../../src/dashboard.php', 'error', 'Unauthorized.');
    }
    $question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
    $assignment_id = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    
    $redirect_url = '../../src/manage_mcq_ui.php?assignment_id=' . $assignment_id;

    $stmt = $conn->prepare("DELETE FROM mcq_questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    if ($stmt->execute()) {
        redirect_with_status($redirect_url, 'success', 'Question deleted.');
    } else {
        redirect_with_status($redirect_url, 'error', 'Delete failed.');
    }
} else if ($action === 'submit_test') {
    if (($_SESSION['role'] ?? '') !== 'student') {
        redirect_with_status('../../src/dashboard.php', 'error', 'Only students can submit tests.');
    }
    
    $assignment_id = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    $semail = $_SESSION['email'];
    
    if (!$assignment_id) {
        redirect_with_status('../../src/dashboard.php', 'error', 'Invalid test.');
    }

    // Check if already submitted
    $chk = $conn->prepare("SELECT id FROM student_assignments WHERE assignment_id = ? AND semail = ?");
    $chk->bind_param("is", $assignment_id, $semail);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        redirect_with_status('../../src/dashboard.php', 'error', 'You have already submitted this test.');
    }

    // Get all questions to grade
    $q_stmt = $conn->prepare("SELECT id, correct_option FROM mcq_questions WHERE assignment_id = ?");
    $q_stmt->bind_param("i", $assignment_id);
    $q_stmt->execute();
    $questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $total_questions = count($questions);
    if ($total_questions === 0) {
        redirect_with_status('../../src/dashboard.php', 'error', 'Test has no questions.');
    }

    $correct_answers = 0;
    $answers_to_insert = [];

    foreach ($questions as $q) {
        $qid = $q['id'];
        $selected = $_POST['q_' . $qid] ?? '';
        
        if (in_array($selected, ['A','B','C','D'])) {
            $answers_to_insert[] = ['qid' => $qid, 'selected' => $selected];
            if ($selected === $q['correct_option']) {
                $correct_answers++;
            }
        }
    }

    $score_pct = round(($correct_answers / $total_questions) * 100);

    $conn->begin_transaction();
    try {
        $ins_sa = $conn->prepare("INSERT INTO student_assignments (assignment_id, semail, status, score, submitted_at) VALUES (?, ?, 'Submitted', ?, NOW())");
        $ins_sa->bind_param("isi", $assignment_id, $semail, $score_pct);
        $ins_sa->execute();
        $student_assignment_id = $conn->insert_id;

        if (!empty($answers_to_insert)) {
            $ins_ans = $conn->prepare("INSERT INTO student_mcq_answers (student_assignment_id, question_id, selected_option) VALUES (?, ?, ?)");
            foreach ($answers_to_insert as $ans) {
                $ins_ans->bind_param("iis", $student_assignment_id, $ans['qid'], $ans['selected']);
                $ins_ans->execute();
            }
        }
        $conn->commit();
        redirect_with_status('../../src/dashboard.php', 'success', "Test submitted successfully. You scored $score_pct%.");
    } catch (Exception $e) {
        $conn->rollback();
        redirect_with_status('../../src/dashboard.php', 'error', 'Failed to save test results.');
    }
} else {
    redirect_with_status('../../src/dashboard.php', 'error', 'Invalid action.');
}
