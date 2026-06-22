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

if ($action === 'update_profile') {
    $semail = clean_string($_POST['semail'] ?? '', 80);
    $bio = clean_string($_POST['bio'] ?? '', 1000);
    $attendance_pct = filter_var($_POST['attendance_pct'] ?? '100', FILTER_VALIDATE_INT);

    if (!$semail) {
        redirect_with_status($dashboard_url, 'error', 'Email is required.');
    }

    $stmt = $conn->prepare("INSERT INTO student_profiles (semail, bio, attendance_pct) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE bio=?, attendance_pct=?");
    $stmt->bind_param("ssisi", $semail, $bio, $attendance_pct, $bio, $attendance_pct);
    
    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Profile updated successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to update profile.');
    }
} else if ($action === 'mark_attendance') {
    $semail = clean_string($_POST['semail'] ?? '', 80);
    if (($_SESSION['role'] ?? '') === 'student' && $semail !== $_SESSION['username']) {
        redirect_with_status($dashboard_url, 'error', 'You can only mark your own attendance.');
    }

    // Logic: if attendance_pct < 100, increment it. Or just update updated_at.
    $stmt = $conn->prepare("INSERT INTO student_profiles (semail, bio, attendance_pct) VALUES (?, '', 100) ON DUPLICATE KEY UPDATE attendance_pct = LEAST(attendance_pct + 1, 100), updated_at = NOW()");
    $stmt->bind_param("s", $semail);
    
    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Daily attendance marked successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to mark attendance.');
    }
}

redirect_with_status($dashboard_url, 'error', 'Invalid action.');
