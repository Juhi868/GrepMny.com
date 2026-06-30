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
$role = $_SESSION['role'] ?? 'student';
$user = $_SESSION['username'];

try {
    $conn = db();
} catch (Exception $e) {
    redirect_with_status($dashboard_url, 'error', 'Database connection failed.');
}

function teacher_can_review_student(mysqli $conn, string $teacherEmail, string $studentEmail): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM `student details` sd
         JOIN course_teachers ct ON ct.cid = sd.cid
         WHERE sd.semail = ? AND ct.teacher_email = ?
         LIMIT 1'
    );
    $stmt->bind_param('ss', $studentEmail, $teacherEmail);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

if ($action === 'add_gap') {
    $semail = clean_string($_POST['semail'] ?? '', 80);

    if ($role === 'student') {
        $semail = $user;
    }

    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = clean_string($_POST['reason'] ?? '', 1000);

    if (!$semail || !$start_date || !$end_date) {
        redirect_with_status($dashboard_url, 'error', 'Student email, start date, and end date are required.');
    }

    if ($end_date < $start_date) {
        redirect_with_status($dashboard_url, 'error', 'End date must be the same as or later than the start date.');
    }

    $status = 'Pending';
    $stmt = $conn->prepare('INSERT INTO student_gaps (semail, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $semail, $start_date, $end_date, $reason, $status);

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Gap request submitted successfully.');
    }
    redirect_with_status($dashboard_url, 'error', 'Failed to submit gap request.');
}

if ($action === 'review_gap') {
    if (!in_array($role, ['teacher', 'admin', 'superadmin'], true)) {
        redirect_with_status($dashboard_url, 'error', 'You are not allowed to review gap requests.');
    }

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    $decision = clean_string((string) ($_POST['decision'] ?? ''), 20);
    $reviewNote = clean_string((string) ($_POST['review_note'] ?? ''), 1000);

    if (!$id || !in_array($decision, ['Approved', 'Rejected'], true)) {
        redirect_with_status($dashboard_url, 'error', 'Invalid review request.');
    }

    $gapStmt = $conn->prepare('SELECT semail, status FROM student_gaps WHERE id = ? LIMIT 1');
    $gapStmt->bind_param('i', $id);
    $gapStmt->execute();
    $gap = $gapStmt->get_result()->fetch_assoc();
    if (!$gap) {
        redirect_with_status($dashboard_url, 'error', 'Gap request not found.');
    }

    if ($role === 'teacher' && !teacher_can_review_student($conn, $user, $gap['semail'])) {
        redirect_with_status($dashboard_url, 'error', 'You can review gap requests only for students in your courses.');
    }

    $stmt = $conn->prepare('UPDATE student_gaps SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?');
    $stmt->bind_param('sssi', $decision, $user, $reviewNote, $id);

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Gap request ' . strtolower($decision) . '.');
    }
    redirect_with_status($dashboard_url, 'error', 'Failed to update gap request.');
}

if ($action === 'delete_gap') {
    if ($role === 'student') {
        redirect_with_status($dashboard_url, 'error', 'Students cannot delete gap records.');
    }

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if (!$id) {
        redirect_with_status($dashboard_url, 'error', 'Invalid gap ID.');
    }

    if ($role === 'teacher') {
        $gapStmt = $conn->prepare('SELECT semail FROM student_gaps WHERE id = ? LIMIT 1');
        $gapStmt->bind_param('i', $id);
        $gapStmt->execute();
        $gap = $gapStmt->get_result()->fetch_assoc();
        if (!$gap || !teacher_can_review_student($conn, $user, $gap['semail'])) {
            redirect_with_status($dashboard_url, 'error', 'You can delete gap records only for students in your courses.');
        }
    }

    $stmt = $conn->prepare('DELETE FROM student_gaps WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Gap request deleted.');
    }
    redirect_with_status($dashboard_url, 'error', 'Failed to delete gap request.');
}

redirect_with_status($dashboard_url, 'error', 'Invalid action.');
