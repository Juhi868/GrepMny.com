<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

require_post();

$registryPage = '../../src/data.html';
$sname = clean_string((string) ($_POST['sname'] ?? ''), 40);
$semail = filter_input(INPUT_POST, 'semail', FILTER_VALIDATE_EMAIL);
$cid = filter_input(INPUT_POST, 'cid', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 999999],
]);
$cname = clean_string((string) ($_POST['cname'] ?? ''), 60);
$duration = clean_string((string) ($_POST['duration'] ?? ''), 20);
$startDate = clean_string((string) ($_POST['start_date'] ?? ''), 10);
$endDate = clean_string((string) ($_POST['end_date'] ?? ''), 10);
$fees = filter_input(INPUT_POST, 'fees', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 9999999],
]);

if ($sname === '' || !$semail || $cid === false || $cid === null || $cname === '' || $duration === '' || $fees === false || $fees === null) {
    redirect_with_status($registryPage, 'error', 'Please complete every field with valid values.');
}

$start = DateTime::createFromFormat('Y-m-d', $startDate);
$end = DateTime::createFromFormat('Y-m-d', $endDate);

if (!$start || !$end || $end < $start) {
    redirect_with_status($registryPage, 'error', 'End date must be the same as or later than the start date.');
}

try {
    $conn = db();
    $stmt = $conn->prepare('INSERT INTO `student details` (sname, semail, cid, cname, duration, start_date, end_date, fees) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssissssi', $sname, $semail, $cid, $cname, $duration, $startDate, $endDate, $fees);
    $stmt->execute();

    redirect_with_status($registryPage, 'success', 'Student course record saved successfully.');
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    redirect_with_status($registryPage, 'error', 'Unable to save the record right now.');
}
