<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';

$dashboardPage = '../../src/dashboard.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    redirect_with_status($dashboardPage, 'error', 'Unauthorized access.');
}

$role = $_SESSION['role'];
if ($role !== 'superadmin' && $role !== 'admin') {
    redirect_with_status($dashboardPage, 'error', 'Only admins can manage timetables.');
}

require_post($dashboardPage);
$action = (string) ($_POST['action'] ?? '');

try {
    $conn = db();
} catch (Exception $e) {
    redirect_with_status($dashboardPage, 'error', 'Database connection failed.');
}

$validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

if ($action === 'create_timetable' || $action === 'update_timetable') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $cid = filter_input(INPUT_POST, 'cid', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $batchName = clean_string((string) ($_POST['batch_name'] ?? ''), 80);
    $dayOfWeek = clean_string((string) ($_POST['day_of_week'] ?? ''), 20);
    $startTime = clean_string((string) ($_POST['start_time'] ?? ''), 8);
    $endTime = clean_string((string) ($_POST['end_time'] ?? ''), 8);
    $venue = clean_string((string) ($_POST['venue'] ?? ''), 120);
    $notes = clean_string((string) ($_POST['notes'] ?? ''), 1000);

    if ($cid === false || $cid === null || $dayOfWeek === '' || $startTime === '' || $endTime === '') {
        redirect_with_status($dashboardPage, 'error', 'Course, day, start time, and end time are required.');
    }

    if (!in_array($dayOfWeek, $validDays, true)) {
        redirect_with_status($dashboardPage, 'error', 'Invalid day of week.');
    }

    if ($endTime <= $startTime) {
        redirect_with_status($dashboardPage, 'error', 'End time must be after start time.');
    }

    $courseCheck = $conn->prepare('SELECT cid FROM courses WHERE cid = ? LIMIT 1');
    $courseCheck->bind_param('i', $cid);
    $courseCheck->execute();
    if ($courseCheck->get_result()->num_rows === 0) {
        redirect_with_status($dashboardPage, 'error', 'Selected course does not exist.');
    }

    if ($action === 'update_timetable') {
        if ($id === false || $id === null) {
            redirect_with_status($dashboardPage, 'error', 'Invalid timetable ID.');
        }
        $stmt = $conn->prepare('UPDATE course_timetables SET cid = ?, batch_name = ?, day_of_week = ?, start_time = ?, end_time = ?, venue = ?, notes = ? WHERE id = ?');
        $stmt->bind_param('issssssi', $cid, $batchName, $dayOfWeek, $startTime, $endTime, $venue, $notes, $id);
        $stmt->execute();
        redirect_with_status($dashboardPage, 'success', 'Timetable updated successfully.');
    }

    $stmt = $conn->prepare('INSERT INTO course_timetables (cid, batch_name, day_of_week, start_time, end_time, venue, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssss', $cid, $batchName, $dayOfWeek, $startTime, $endTime, $venue, $notes);
    $stmt->execute();
    redirect_with_status($dashboardPage, 'success', 'Timetable entry created successfully.');
}

if ($action === 'delete_timetable') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id === false || $id === null) {
        redirect_with_status($dashboardPage, 'error', 'Invalid timetable ID.');
    }
    $stmt = $conn->prepare('DELETE FROM course_timetables WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    redirect_with_status($dashboardPage, 'success', 'Timetable entry deleted.');
}

redirect_with_status($dashboardPage, 'error', 'Invalid action.');
