<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['userid'])) {
    header('Location: ../../index.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $userid = $_SESSION['userid'];
    $file = $_FILES['profile_photo'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowedTypes)) {
            $dest = __DIR__ . '/../../media/profiles/' . $userid . '.jpg';
            // We move and enforce the .jpg extension for simplicity on the frontend
            move_uploaded_file($file['tmp_name'], $dest);
        }
    }
}

header('Location: ../../src/profile.php');
exit;
