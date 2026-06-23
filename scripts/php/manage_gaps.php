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

if ($action === 'add_gap') {
    $semail = clean_string($_POST['semail'] ?? '', 80);

    if (($_SESSION['role'] ?? '') === 'student') {
        $semail = $_SESSION['username'];
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

    $stmt = $conn->prepare("INSERT INTO student_gaps (semail, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $semail, $start_date, $end_date, $reason);
    
    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Academic gap recorded successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to record gap.');
    }
} else if ($action === 'delete_gap') {
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if (!$id) {
        redirect_with_status($dashboard_url, 'error', 'Invalid gap ID.');
    }

    $stmt = $conn->prepare("DELETE FROM student_gaps WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Gap deleted successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to delete gap.');
    }
}

redirect_with_status($dashboard_url, 'error', 'Invalid action.');
