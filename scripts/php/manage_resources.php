<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['username'])) {
    redirect_with_status(APP_LOGIN, 'error', 'Unauthorized access.');
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    redirect_with_status('../../src/dashboard.php', 'error', 'Only teachers can perform this action.');
}

require_post('../../src/dashboard.php');

$action = $_POST['action'] ?? '';
$dashboard_url = '../../src/dashboard.php';

try {
    $conn = db();
} catch (Exception $e) {
    redirect_with_status($dashboard_url, 'error', 'Database connection failed.');
}

if ($action === 'add_resource') {
    $cid = filter_var($_POST['cid'] ?? '', FILTER_VALIDATE_INT);
    $title = clean_string($_POST['title'] ?? '', 255);
    $type = clean_string($_POST['type'] ?? '', 50);
    $url = clean_string($_POST['url'] ?? '', 2000);

    if (!$cid || !$title || !$type) {
        redirect_with_status($dashboard_url, 'error', 'Course ID, Title, and Type are required.');
    }

    if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../media/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = time() . '_' . basename($_FILES['resource_file']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target_file)) {
            $url = '../media/uploads/' . $filename;
        } else {
            redirect_with_status($dashboard_url, 'error', 'File upload failed.');
        }
    } else if (empty($url)) {
        redirect_with_status($dashboard_url, 'error', 'Either a URL or a file upload is required.');
    }

    $stmt = $conn->prepare("INSERT INTO course_resources (cid, title, type, url) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $cid, $title, $type, $url);
    
    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Resource added successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to add resource.');
    }
} else if ($action === 'delete_resource') {
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if (!$id) {
        redirect_with_status($dashboard_url, 'error', 'Invalid resource ID.');
    }

    $stmt_get = $conn->prepare("SELECT url FROM course_resources WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    if ($row = $result->fetch_assoc()) {
        $file_url = $row['url'];
        if (strpos($file_url, '../media/uploads/') === 0) {
            $local_path = __DIR__ . '/../../media/uploads/' . basename($file_url);
            if (file_exists($local_path)) {
                unlink($local_path);
            }
        }
    }

    $stmt = $conn->prepare("DELETE FROM course_resources WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        redirect_with_status($dashboard_url, 'success', 'Resource deleted successfully.');
    } else {
        redirect_with_status($dashboard_url, 'error', 'Failed to delete resource.');
    }
}

redirect_with_status($dashboard_url, 'error', 'Invalid action.');
