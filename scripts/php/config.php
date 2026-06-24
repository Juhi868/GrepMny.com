<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');

const APP_HOME = '../../src/grepMny.html';
const APP_LOGIN = '../../index.html';

function db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = getenv('GrepMny_DB_HOST') ?: 'localhost';
    $user = getenv('GrepMny_DB_USER') ?: 'root';
    $pass = getenv('GrepMny_DB_PASS') ?: '';
    $name = getenv('GrepMny_DB_NAME') ?: 'grepmny';
    $port = (int) (getenv('GrepMny_DB_PORT') ?: 3307);

    $connection = new mysqli($host, $user, $pass, $name, $port);
    $connection->set_charset('utf8mb4');

    return $connection;
}

function clean_string(string $value, int $maxLength): string
{
    $value = trim($value);
    return substr($value, 0, $maxLength);
}

function redirect_with_status(string $location, string $status, string $message): never
{
    $separator = str_contains($location, '?') ? '&' : '?';
    header('Location: ' . $location . $separator . http_build_query([
        'status' => $status,
        'message' => $message,
    ]));
    exit;
}

function require_post(string $fallbackLocation): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect_with_status($fallbackLocation, 'error', 'Please submit the form from the website page.');
    }
}
