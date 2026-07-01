<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['username'])) {
    redirect_with_status(APP_LOGIN, 'error', 'Unauthorized access.');
}

require_post('../../src/dashboard.php');

$conn = db();
$action = $_POST['action'] ?? '';
$role = $_SESSION['role'] ?? 'student';
$user = $_SESSION['username'];
$dashboard_url = '../../src/dashboard.php';

function test_redirect(int $testId): string
{
    return '../../src/manage_mcq_ui.php?test_id=' . $testId;
}

function teacher_owns_test(mysqli $conn, int $testId, string $teacherEmail): bool
{
    $stmt = $conn->prepare("SELECT id FROM mock_tests WHERE id = ? AND teacher_email = ?");
    $stmt->bind_param("is", $testId, $teacherEmail);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function enrolled_student_emails(mysqli $conn, int $cid): string
{
    $stmt = $conn->prepare("SELECT DISTINCT semail FROM `student details` WHERE cid = ? ORDER BY semail ASC");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $emails = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'semail');
    return implode(',', $emails);
}

function resolve_course(mysqli $conn, int $cid, string $cname = ''): ?array
{
    if ($cname !== '') {
        $stmt = $conn->prepare('SELECT cid, cname FROM courses WHERE cname = ? LIMIT 1');
        $stmt->bind_param('s', $cname);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return ['cid' => (int) $row['cid'], 'cname' => $row['cname']];
        }
    }

    $stmt = $conn->prepare('SELECT cid, cname FROM courses WHERE cid = ? LIMIT 1');
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }

    if ($cname !== '' && strcasecmp($row['cname'], $cname) !== 0) {
        return null;
    }

    return ['cid' => (int) $row['cid'], 'cname' => $row['cname']];
}

function normalize_correct_answer(string $type, array $post): string
{
    if ($type === 'multiple_correct') {
        $answers = array_values(array_intersect(['A', 'B', 'C', 'D'], array_map('strtoupper', (array)($post['correct_options'] ?? []))));
        sort($answers);
        return implode(',', $answers);
    }
    if ($type === 'true_false') {
        return strtoupper(trim((string)($post['true_false_answer'] ?? 'TRUE'))) === 'FALSE' ? 'FALSE' : 'TRUE';
    }
    if ($type === 'fill_blank') {
        return clean_string((string)($post['blank_answer'] ?? ''), 255);
    }
    if ($type === 'subjective') {
        return '';
    }
    return strtoupper(trim((string)($post['correct_option'] ?? 'A')));
}

if ($action === 'create_test' || $action === 'update_test') {
    if ($role !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Only teachers can manage tests.');
    }

    $cid = filter_var($_POST['cid'] ?? '', FILTER_VALIDATE_INT);
    $cname = clean_string((string) ($_POST['cname'] ?? ''), 60);
    $title = clean_string((string)($_POST['title'] ?? ''), 100);
    $description = clean_string((string)($_POST['description'] ?? ''), 1000);

    $startsAtDate = trim((string)($_POST['starts_at_date'] ?? ''));
    $startsAtTime = trim((string)($_POST['starts_at_time'] ?? ''));
    $startsAt = ($startsAtDate && $startsAtTime) ? $startsAtDate . ' ' . $startsAtTime . ':00' : null;

    $endsAtDate = trim((string)($_POST['ends_at_date'] ?? ''));
    $endsAtTime = trim((string)($_POST['ends_at_time'] ?? ''));
    $endsAt = ($endsAtDate && $endsAtTime) ? $endsAtDate . ' ' . $endsAtTime . ':00' : null;

    $duration = max(1, min(240, (int)($_POST['duration_minutes'] ?? 30)));
    $pass = max(0, min(100, (int)($_POST['pass_percentage'] ?? 40)));

    $course = $cid ? resolve_course($conn, (int) $cid, $cname) : null;
    if (!$course) {
        redirect_with_status($dashboard_url, 'error', 'Select a valid course from the catalog.');
    }
    $cid = $course['cid'];

    if ($title === '') {
        redirect_with_status($dashboard_url, 'error', 'Test title is required.');
    }

    $teacherCourse = $conn->prepare('SELECT id FROM course_teachers WHERE teacher_email = ? AND cid = ? LIMIT 1');
    $teacherCourse->bind_param('si', $user, $cid);
    $teacherCourse->execute();
    if ($teacherCourse->get_result()->num_rows === 0) {
        redirect_with_status($dashboard_url, 'error', 'You can create tests only for your assigned courses.');
    }

    $assignedStudents = enrolled_student_emails($conn, $cid);
    if ($assignedStudents === '') {
        redirect_with_status($dashboard_url, 'error', 'No students are enrolled in the selected course.');
    }

    if ($action === 'update_test') {
        $testId = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$testId || !teacher_owns_test($conn, $testId, $user)) {
            redirect_with_status($dashboard_url, 'error', 'You can edit only your own tests.');
        }
        $stmt = $conn->prepare("UPDATE mock_tests SET cid = ?, title = ?, description = ?, assigned_students = ?, starts_at = ?, ends_at = ?, duration_minutes = ?, pass_percentage = ? WHERE id = ?");
        $stmt->bind_param("isssssiii", $cid, $title, $description, $assignedStudents, $startsAt, $endsAt, $duration, $pass, $testId);
        $stmt->execute();
        redirect_with_status(test_redirect($testId), 'success', 'Test schedule and assignment updated.');
    }

    $stmt = $conn->prepare("INSERT INTO mock_tests (cid, teacher_email, title, description, assigned_students, starts_at, ends_at, duration_minutes, pass_percentage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssii", $cid, $user, $title, $description, $assignedStudents, $startsAt, $endsAt, $duration, $pass);
    $stmt->execute();
    $studentCount = count(array_filter(explode(',', $assignedStudents)));
    redirect_with_status(test_redirect((int)$conn->insert_id), 'success', "Test created with {$studentCount} enrolled student(s) assigned. Add questions to publish it.");
}

if ($action === 'delete_test') {
    if ($role !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Unauthorized.');
    }
    $testId = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    if (!$testId || !teacher_owns_test($conn, $testId, $user)) {
        redirect_with_status($dashboard_url, 'error', 'Invalid test.');
    }
    $conn->begin_transaction();
    $ids = [];
    $res = $conn->prepare("SELECT id FROM mock_test_results WHERE test_id = ?");
    $res->bind_param("i", $testId);
    $res->execute();
    foreach ($res->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $ids[] = (int)$row['id'];
    }
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $delAnswers = $conn->prepare("DELETE FROM mock_test_answers WHERE result_id IN ($in)");
        $delAnswers->bind_param($types, ...$ids);
        $delAnswers->execute();
    }
    foreach (['mock_test_questions', 'mock_test_results', 'mock_tests'] as $table) {
        $column = $table === 'mock_tests' ? 'id' : 'test_id';
        $stmt = $conn->prepare("DELETE FROM `$table` WHERE `$column` = ?");
        $stmt->bind_param("i", $testId);
        $stmt->execute();
    }
    $conn->commit();
    redirect_with_status($dashboard_url, 'success', 'Test deleted.');
}

if ($action === 'add_question') {
    if ($role !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Unauthorized.');
    }
    $testId = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    $redirect = test_redirect((int)$testId);
    if (!$testId || !teacher_owns_test($conn, $testId, $user)) {
        redirect_with_status($dashboard_url, 'error', 'Invalid test.');
    }

    $type = (string)($_POST['question_type'] ?? 'mcq');
    $allowed = ['mcq', 'multiple_correct', 'true_false', 'fill_blank', 'subjective'];
    if (!in_array($type, $allowed, true)) {
        redirect_with_status($redirect, 'error', 'Invalid question type.');
    }
    $question = clean_string((string)($_POST['question_text'] ?? ''), 2000);
    $marks = max(1, min(100, (int)($_POST['marks'] ?? 1)));
    $optionA = clean_string((string)($_POST['option_a'] ?? 'True'), 255);
    $optionB = clean_string((string)($_POST['option_b'] ?? 'False'), 255);
    $optionC = clean_string((string)($_POST['option_c'] ?? ''), 255);
    $optionD = clean_string((string)($_POST['option_d'] ?? ''), 255);
    $correct = normalize_correct_answer($type, $_POST);

    if ($question === '' || ($type !== 'subjective' && $correct === '')) {
        redirect_with_status($redirect, 'error', 'Question and answer key are required.');
    }
    if (in_array($type, ['mcq', 'multiple_correct'], true) && (!$optionA || !$optionB || !$optionC || !$optionD)) {
        redirect_with_status($redirect, 'error', 'All four options are required for option-based questions.');
    }

    $stmt = $conn->prepare("INSERT INTO mock_test_questions (test_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssi", $testId, $type, $question, $optionA, $optionB, $optionC, $optionD, $correct, $marks);
    $stmt->execute();
    redirect_with_status($redirect, 'success', 'Question added.');
}

if ($action === 'delete_question') {
    if ($role !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Unauthorized.');
    }
    $questionId = filter_var($_POST['question_id'] ?? '', FILTER_VALIDATE_INT);
    $testId = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    $redirect = test_redirect((int)$testId);
    if (!$questionId || !$testId || !teacher_owns_test($conn, $testId, $user)) {
        redirect_with_status($redirect, 'error', 'Invalid question.');
    }
    $stmt = $conn->prepare("DELETE FROM mock_test_questions WHERE id = ? AND test_id = ?");
    $stmt->bind_param("ii", $questionId, $testId);
    $stmt->execute();
    redirect_with_status($redirect, 'success', 'Question deleted.');
}

if ($action === 'submit_test' || $action === 'save_test') {
    if ($role !== 'student') {
        redirect_with_status($dashboard_url, 'error', 'Only students can submit tests.');
    }
    $testId = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    if (!$testId) {
        redirect_with_status($dashboard_url, 'error', 'Invalid test.');
    }

    $chk = $conn->prepare("SELECT id FROM mock_test_results WHERE test_id = ? AND semail = ?");
    $chk->bind_param("is", $testId, $user);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        redirect_with_status($dashboard_url, 'error', 'You have already submitted this test.');
    }

    $qStmt = $conn->prepare("SELECT * FROM mock_test_questions WHERE test_id = ? ORDER BY id ASC");
    $qStmt->bind_param("i", $testId);
    $qStmt->execute();
    $questions = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (!$questions) {
        redirect_with_status($dashboard_url, 'error', 'Test has no questions.');
    }

    $score = 0.0;
    $total = 0;
    $needsReview = false;
    $graded = [];
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        $type = $q['question_type'];
        $marks = (int)$q['marks'];
        $total += $marks;
        $answer = $_POST['q_' . $qid] ?? '';
        if (is_array($answer)) {
            $answer = array_values(array_intersect(['A', 'B', 'C', 'D'], array_map('strtoupper', $answer)));
            sort($answer);
            $answer = implode(',', $answer);
        } else {
            $answer = trim((string)$answer);
        }
        $isCorrect = null;
        $awarded = 0.0;
        if ($type === 'subjective') {
            $needsReview = true;
        } elseif ($type === 'fill_blank') {
            $isCorrect = mb_strtolower($answer) === mb_strtolower((string)$q['correct_option']);
            $awarded = $isCorrect ? $marks : 0;
        } else {
            $isCorrect = strtoupper($answer) === strtoupper((string)$q['correct_option']);
            $awarded = $isCorrect ? $marks : 0;
        }
        $score += $awarded;
        $graded[] = [$qid, $answer, $isCorrect, $awarded];
    }

    $status = $needsReview ? 'Pending Review' : 'Evaluated';
    $stmt = $conn->prepare("INSERT INTO mock_test_results (test_id, semail, score, total, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdis", $testId, $user, $score, $total, $status);
    $stmt->execute();
    $resultId = (int)$conn->insert_id;

    foreach ($graded as [$qid, $answer, $isCorrect, $awarded]) {
        $correctValue = $isCorrect === null ? null : ($isCorrect ? 1 : 0);
        $aStmt = $conn->prepare("INSERT INTO mock_test_answers (result_id, question_id, answer_text, is_correct, marks_awarded) VALUES (?, ?, ?, ?, ?)");
        $aStmt->bind_param("iisid", $resultId, $qid, $answer, $correctValue, $awarded);
        $aStmt->execute();
    }

    $pct = $total > 0 ? round(($score / $total) * 100, 1) : 0;
    $message = $needsReview ? "Submitted. Objective score is $score/$total ($pct%); subjective answers are pending teacher review." : "Test submitted! You scored $score/$total ($pct%).";
    redirect_with_status($dashboard_url, 'success', $message);
}

if ($action === 'evaluate_answer') {
    if ($role !== 'teacher') {
        redirect_with_status($dashboard_url, 'error', 'Unauthorized.');
    }
    $answerId = filter_var($_POST['answer_id'] ?? '', FILTER_VALIDATE_INT);
    $testId = filter_var($_POST['test_id'] ?? '', FILTER_VALIDATE_INT);
    $marks = max(0, (float)($_POST['marks_awarded'] ?? 0));
    $feedback = clean_string((string)($_POST['teacher_feedback'] ?? ''), 1000);
    if (!$answerId || !$testId || !teacher_owns_test($conn, $testId, $user)) {
        redirect_with_status($dashboard_url, 'error', 'Invalid evaluation request.');
    }
    $stmt = $conn->prepare("UPDATE mock_test_answers SET marks_awarded = ?, is_correct = ?, teacher_feedback = ? WHERE id = ?");
    $isCorrect = $marks > 0 ? 1 : 0;
    $stmt->bind_param("disi", $marks, $isCorrect, $feedback, $answerId);
    $stmt->execute();

    $totalStmt = $conn->prepare("SELECT r.id, SUM(a.marks_awarded) AS score FROM mock_test_results r JOIN mock_test_answers a ON r.id = a.result_id WHERE r.test_id = ? GROUP BY r.id");
    $totalStmt->bind_param("i", $testId);
    $totalStmt->execute();
    foreach ($totalStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $update = $conn->prepare("UPDATE mock_test_results SET score = ?, status = 'Evaluated' WHERE id = ?");
        $score = (float)$row['score'];
        $rid = (int)$row['id'];
        $update->bind_param("di", $score, $rid);
        $update->execute();
    }
    redirect_with_status(test_redirect($testId), 'success', 'Subjective answer evaluated.');
}

redirect_with_status($dashboard_url, 'error', 'Invalid action.');
