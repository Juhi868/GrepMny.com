<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

try {
    $conn = db();
    $result = $conn->query('SELECT cid, cname FROM courses ORDER BY cname ASC');
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = [
            'cid' => (int) $row['cid'],
            'cname' => $row['cname'],
        ];
    }
    echo json_encode(['courses' => $courses], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load courses.', 'courses' => []]);
}
