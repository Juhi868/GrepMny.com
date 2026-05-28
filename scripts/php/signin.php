<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

require_post();

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$userid = clean_string((string) ($_POST['userid'] ?? ''), 32);
$password1 = (string) ($_POST['password1'] ?? '');
$password2 = (string) ($_POST['password2'] ?? '');
$signupPage = '../../src/signup.html';

if (!$email) {
    redirect_with_status($signupPage, 'error', 'Enter a valid email address.');
}

if (!preg_match('/^[A-Za-z0-9_-]{3,32}$/', $userid)) {
    redirect_with_status($signupPage, 'error', 'User ID can use letters, numbers, underscores, and hyphens.');
}

if (strlen($password1) < 6 || $password1 !== $password2) {
    redirect_with_status($signupPage, 'error', 'Passwords must match and contain at least 6 characters.');
}

try {
    $conn = db();
    $exists = $conn->prepare('SELECT 1 FROM login WHERE email = ? OR userid = ? LIMIT 1');
    $exists->bind_param('ss', $email, $userid);
    $exists->execute();

    if ($exists->get_result()->num_rows > 0) {
        redirect_with_status($signupPage, 'error', 'Email or user ID already exists.');
    }

    $hash = password_hash($password1, PASSWORD_DEFAULT);
    $insert = $conn->prepare('INSERT INTO login (email, passwd, userid) VALUES (?, ?, ?)');
    $insert->bind_param('sss', $email, $hash, $userid);
    $insert->execute();

    redirect_with_status(APP_LOGIN, 'success', 'Account created. You can log in now.');
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    redirect_with_status($signupPage, 'error', 'Unable to create the account right now.');
}
