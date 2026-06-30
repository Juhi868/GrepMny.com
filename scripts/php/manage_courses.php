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
    redirect_with_status($dashboardPage, 'error', 'Only admins can manage courses.');
}

require_post($dashboardPage);
$action = (string) ($_POST['action'] ?? '');

try {
    $conn = db();
} catch (Exception $e) {
    redirect_with_status($dashboardPage, 'error', 'Database connection failed.');
}

if ($action === 'create_course') {
    $cid = filter_input(INPUT_POST, 'cid', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cname = clean_string((string) ($_POST['cname'] ?? ''), 60);

    if ($cid === false || $cid === null || $cname === '') {
        redirect_with_status($dashboardPage, 'error', 'Course ID and name are required.');
    }

    $stmt = $conn->prepare('INSERT INTO courses (cid, cname) VALUES (?, ?)');
    $stmt->bind_param('is', $cid, $cname);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        redirect_with_status($dashboardPage, 'error', 'Course ID or name already exists.');
    }
    redirect_with_status($dashboardPage, 'success', 'Course created successfully.');
}

if ($action === 'update_course') {
    $cid = filter_input(INPUT_POST, 'cid', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cname = clean_string((string) ($_POST['cname'] ?? ''), 60);

    if ($cid === false || $cid === null || $cname === '') {
        redirect_with_status($dashboardPage, 'error', 'Course ID and name are required.');
    }

    $stmt = $conn->prepare('UPDATE courses SET cname = ? WHERE cid = ?');
    $stmt->bind_param('si', $cname, $cid);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        redirect_with_status($dashboardPage, 'error', 'Course not found.');
    }

    $sync = $conn->prepare('UPDATE `student details` SET cname = ? WHERE cid = ?');
    $sync->bind_param('si', $cname, $cid);
    $sync->execute();

    $syncTeachers = $conn->prepare('UPDATE course_teachers SET cname = ? WHERE cid = ?');
    $syncTeachers->bind_param('si', $cname, $cid);
    $syncTeachers->execute();

    redirect_with_status($dashboardPage, 'success', 'Course updated successfully.');
}

redirect_with_status($dashboardPage, 'error', 'Invalid action.');
