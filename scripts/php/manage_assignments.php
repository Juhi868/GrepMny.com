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

if ($action === 'create_assignment') {
    $cid = filter_var($_POST['cid'] ?? '', FILTER_VALIDATE_INT);
    $title = clean_string($_POST['title'] ?? '', 255);
    $description = clean_string($_POST['description'] ?? '', 5000);
    $due_date_input = clean_string($_POST['due_date'] ?? '', 32);
    $type = clean_string($_POST['type'] ?? 'Assignment', 50);

    if (!$cid || !$title || !$due_date_input) {
        redirect_with_status($dashboard_url, 'error', 'Course ID, title, and due date are required.');
    }

    $due_date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $due_date_input);
    $due_date_errors = DateTime::getLastErrors();
    if (!$due_date_obj || ($due_date_errors !== false && ($due_date_errors['warning_count'] > 0 || $due_date_errors['error_count'] > 0))) {
        redirect_with_status($dashboard_url, 'error', 'Please choose a valid due date and time.');
    }

    $due_date = $due_date_obj->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO assignments (cid, title, description, due_date, type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $cid, $title, $description, $due_date, $type);
    
    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Assignment created successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to create assignment.');
    }
} else if ($action === 'submit_assignment') {
    $assignment_id = filter_var($_POST['assignment_id'] ?? '', FILTER_VALIDATE_INT);
    $semail = $_SESSION['username'];
    
    if (!$assignment_id) {
        redirect_with_status($dashboard_url, 'error', 'Invalid assignment ID.');
    }

    $existing_stmt = $conn->prepare("SELECT id FROM student_assignments WHERE assignment_id = ? AND semail = ? LIMIT 1");
    $existing_stmt->bind_param("is", $assignment_id, $semail);
    $existing_stmt->execute();
    $existing_submission = $existing_stmt->get_result()->fetch_assoc();

    if ($existing_submission) {
        $stmt = $conn->prepare("UPDATE student_assignments SET status = 'Submitted', submitted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $existing_submission['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO student_assignments (assignment_id, semail, status, submitted_at) VALUES (?, ?, 'Submitted', NOW())");
        $stmt->bind_param("is", $assignment_id, $semail);
    }

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Assignment submitted.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to submit assignment.');
    }
} else if ($action === 'grade_assignment') {
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT); // student_assignments id
    $score = filter_var($_POST['score'] ?? '', FILTER_VALIDATE_INT);
    $feedback = clean_string($_POST['feedback'] ?? '', 5000);

    if (!$id || $score === false) {
        redirect_with_status($dashboard_url, 'error', 'Invalid ID or score.');
    }

    $stmt = $conn->prepare("UPDATE student_assignments SET score = ?, feedback = ?, status = 'Graded' WHERE id = ?");
    $stmt->bind_param("isi", $score, $feedback, $id);

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Assignment graded.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to grade assignment.');
    }
}

redirect_with_status($dashboard_url, 'error', 'Invalid action.');
