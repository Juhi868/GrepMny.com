<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';

$dashboardPage = '../../src/dashboard.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    redirect_with_status($dashboardPage, 'error', 'Unauthorized access.');
}

$currentUserRole = $_SESSION['role'];
$action = (string) ($_POST['action'] ?? '');

if ($action === 'update_role') {
    // Only superadmin can change roles
    if ($currentUserRole !== 'superadmin') {
        redirect_with_status($dashboardPage, 'error', 'Only superadmins can change user roles.');
    }

    $targetEmail = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $newRole = clean_string((string) ($_POST['role'] ?? ''), 20);

    $validRoles = ['student', 'teacher', 'admin', 'superadmin'];
    if (!$targetEmail || !in_array($newRole, $validRoles, true)) {
        redirect_with_status($dashboardPage, 'error', 'Invalid email or role selection.');
    }

    // Prevent superadmin from demoting themselves
    if ($targetEmail === $_SESSION['username'] && $newRole !== 'superadmin') {
        redirect_with_status($dashboardPage, 'error', 'You cannot change your own superadmin role.');
    }

    try {
        $conn = db();
        $stmt = $conn->prepare('UPDATE login SET role = ? WHERE email = ?');
        $stmt->bind_param('ss', $newRole, $targetEmail);
        $stmt->execute();

        redirect_with_status($dashboardPage, 'success', 'User role updated successfully.');
    } catch (mysqli_sql_exception $error) {
        error_log($error->getMessage());
        redirect_with_status($dashboardPage, 'error', 'Failed to update user role.');
    }

} elseif ($action === 'assign_teacher') {
    // Superadmin and admin can assign teachers to courses
    if ($currentUserRole !== 'superadmin' && $currentUserRole !== 'admin') {
        redirect_with_status($dashboardPage, 'error', 'Unauthorized to assign courses.');
    }

    $teacherEmail = filter_input(INPUT_POST, 'teacher_email', FILTER_VALIDATE_EMAIL);
    $cid = filter_input(INPUT_POST, 'cid', FILTER_VALIDATE_INT);
    $cname = clean_string((string) ($_POST['cname'] ?? ''), 60);

    if (!$teacherEmail || $cid === false || $cid === null || $cname === '') {
        redirect_with_status($dashboardPage, 'error', 'Please provide a valid teacher email, course ID, and course name.');
    }

    try {
        $conn = db();
        
        // Verify that the user exists
        $chk = $conn->prepare('SELECT role FROM login WHERE email = ? LIMIT 1');
        $chk->bind_param('s', $teacherEmail);
        $chk->execute();
        $chkRes = $chk->get_result();
        
        if ($chkRes->num_rows === 0) {
            redirect_with_status($dashboardPage, 'error', 'No user found with the email ' . htmlspecialchars($teacherEmail));
        }
        
        $userObj = $chkRes->fetch_assoc();
        if ($userObj['role'] !== 'teacher') {
            // Auto elevate user to teacher if they exist but aren't marked as teacher
            $elevate = $conn->prepare("UPDATE login SET role = 'teacher' WHERE email = ?");
            $elevate->bind_param('s', $teacherEmail);
            $elevate->execute();
        }

        // Check if assignment already exists
        $stmt = $conn->prepare('SELECT id FROM course_teachers WHERE teacher_email = ? AND cid = ? LIMIT 1');
        $stmt->bind_param('si', $teacherEmail, $cid);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            // Update course name for mapping
            $update = $conn->prepare('UPDATE course_teachers SET cname = ? WHERE teacher_email = ? AND cid = ?');
            $update->bind_param('ssi', $cname, $teacherEmail, $cid);
            $update->execute();
        } else {
            // Insert mapping
            $insert = $conn->prepare('INSERT INTO course_teachers (teacher_email, cid, cname) VALUES (?, ?, ?)');
            $insert->bind_param('sis', $teacherEmail, $cid, $cname);
            $insert->execute();
        }

        redirect_with_status($dashboardPage, 'success', 'Teacher assigned to course successfully.');
    } catch (mysqli_sql_exception $error) {
        error_log($error->getMessage());
        redirect_with_status($dashboardPage, 'error', 'Failed to assign teacher to course.');
    }

} elseif ($action === 'remove_teacher') {
    // Superadmin and admin can remove teacher assignments
    if ($currentUserRole !== 'superadmin' && $currentUserRole !== 'admin') {
        redirect_with_status($dashboardPage, 'error', 'Unauthorized to remove assignments.');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($id === false || $id === null) {
        redirect_with_status($dashboardPage, 'error', 'Invalid assignment ID.');
    }

    try {
        $conn = db();
        $stmt = $conn->prepare('DELETE FROM course_teachers WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        redirect_with_status($dashboardPage, 'success', 'Teacher course mapping removed.');
    } catch (mysqli_sql_exception $error) {
        error_log($error->getMessage());
        redirect_with_status($dashboardPage, 'error', 'Failed to remove assignment.');
    }

} elseif ($action === 'delete_student') {
    // Superadmin and admin can delete student records
    if ($currentUserRole !== 'superadmin' && $currentUserRole !== 'admin') {
        redirect_with_status($dashboardPage, 'error', 'Unauthorized to delete student records.');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($id === false || $id === null) {
        redirect_with_status($dashboardPage, 'error', 'Invalid student record ID.');
    }

    try {
        $conn = db();
        $stmt = $conn->prepare('DELETE FROM `student details` WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        redirect_with_status($dashboardPage, 'success', 'Student enrollment record deleted successfully.');
    } catch (mysqli_sql_exception $error) {
        error_log($error->getMessage());
        redirect_with_status($dashboardPage, 'error', 'Failed to delete student record.');
    }

} else {
    redirect_with_status($dashboardPage, 'error', 'Invalid action request.');
}
