<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', '/esg-report-test');

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function company_id(): string {
    return $_SESSION['company_id'] ?? '';
}

function user_id(): string {
    return $_SESSION['user_id'] ?? '';
}

function user_role(): string {
    return $_SESSION['role'] ?? 'user';
}

function is_admin(): bool {
    return user_role() === 'admin';
}
