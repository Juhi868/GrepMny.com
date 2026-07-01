<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';

require_post(APP_LOGIN);

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = (string) ($_POST['password'] ?? '');

if (!$email || $password === '') {
    redirect_with_status(APP_LOGIN, 'error', 'Enter a valid email and password.');
}

try {
    $conn = db();
    $stmt = $conn->prepare('SELECT email, passwd, userid, role, has_logged_in FROM login WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        redirect_with_status(APP_LOGIN, 'error', 'Invalid email or password.');
    }

    $storedPassword = (string) $user['passwd'];
    $isHashed = password_get_info($storedPassword)['algo'] !== 0;
    $isValid = $isHashed ? password_verify($password, $storedPassword) : hash_equals($storedPassword, $password);

    if (!$isValid) {
        redirect_with_status(APP_LOGIN, 'error', 'Invalid email or password.');
    }

    if (!$isHashed) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE login SET passwd = ? WHERE email = ?');
        $update->bind_param('ss', $newHash, $email);
        $update->execute();
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $email;
    $_SESSION['userid'] = $user['userid'];
    $_SESSION['role'] = $user['role'];

    // First-time login: show front page, then mark as logged in
    $hasLoggedIn = (int) ($user['has_logged_in'] ?? 0);
    if ($hasLoggedIn === 0) {
        $flagStmt = $conn->prepare('UPDATE login SET has_logged_in = 1 WHERE email = ?');
        $flagStmt->bind_param('s', $email);
        $flagStmt->execute();
        redirect_with_status('../../src/grepMny.php', 'success', 'Welcome to GrepMny! Explore the workspace.');
    }

    // Returning user: go straight to dashboard
    redirect_with_status('../../src/dashboard.php', 'success', 'Logged in successfully.');
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    redirect_with_status(APP_LOGIN, 'error', 'Unable to connect right now. Please try again.');
}

