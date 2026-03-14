<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Yêu cầu đăng xuất không hợp lệ.');
    redirect(base_url('/auth/login.php'));
}

$user = $_SESSION['user'] ?? null;

if ($user) {
    $conn = getConnection();
    log_activity(
        $conn,
        (int)$user['id'],
        'logout',
        'auth',
        (int)$user['id'],
        'Đăng xuất khỏi hệ thống'
    );
}

session_unset();
session_destroy();

session_start();
unset($_SESSION['csrf_token']);
set_flash('success', 'Bạn đã đăng xuất.');

redirect(base_url('/auth/login.php'));
