<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['username'])) {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userid = $_SESSION['userid'];
    $email = $_SESSION['username'];

    try {
        $conn = db();
        $conn->begin_transaction();

        // Delete from login
        $stmt = $conn->prepare("DELETE FROM login WHERE userid = ?");
        $stmt->bind_param("s", $userid);
        $stmt->execute();

        // Delete from student details
        $stmt2 = $conn->prepare("DELETE FROM `student details` WHERE semail = ?");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();

        // Delete from student_gaps
        $stmt3 = $conn->prepare("DELETE FROM student_gaps WHERE semail = ?");
        $stmt3->bind_param("s", $email);
        $stmt3->execute();

        // Delete from student_profiles
        $stmt4 = $conn->prepare("DELETE FROM student_profiles WHERE semail = ?");
        $stmt4->bind_param("s", $email);
        $stmt4->execute();

        // Delete from student_assignments
        $stmt5 = $conn->prepare("DELETE FROM student_assignments WHERE semail = ?");
        $stmt5->bind_param("s", $email);
        $stmt5->execute();

        // Delete photo if exists
        $photo_path = __DIR__ . '/../../media/profiles/' . $userid . '.jpg';
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }

        $conn->commit();
        
        session_destroy();
        header('Location: ../../index.php?msg=Account+deleted');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die("Error deleting account: " . htmlspecialchars($e->getMessage()));
    }
} else {
    header('Location: ../../src/profile.php');
    exit;
}
