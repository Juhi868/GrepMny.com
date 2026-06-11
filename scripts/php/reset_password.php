<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$forgotPage = '../../src/forgot.html';
require_post($forgotPage);

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$userid = clean_string((string) ($_POST['userid'] ?? ''), 32);
$password = (string) ($_POST['password'] ?? '');

if (!$email || !$userid || strlen($password) < 6) {
    redirect_with_status($forgotPage, 'error', 'Please provide valid email, user ID, and a 6+ char password.');
}

try {
    $conn = db();
    $check = $conn->prepare('SELECT id FROM login WHERE email = ? AND userid = ? LIMIT 1');
    $check->bind_param('ss', $email, $userid);
    $check->execute();
    
    if ($check->get_result()->num_rows === 0) {
        redirect_with_status($forgotPage, 'error', 'No account matches that Email and User ID combination.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE login SET passwd = ? WHERE email = ? AND userid = ?');
    $update->bind_param('sss', $hash, $email, $userid);
    $update->execute();

    redirect_with_status(APP_LOGIN, 'success', 'Password reset successful. You can log in now.');
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    redirect_with_status($forgotPage, 'error', 'Unable to reset password right now.');
}
