<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

function refresh_current_user()
{
    if (!is_logged_in()) {
        return null;
    }

    $sessionUser = current_user();
    $userId = (int)($sessionUser['id'] ?? 0);

    if ($userId <= 0) {
        unset($_SESSION['user']);
        return null;
    }

    $conn = getConnection();
    $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP 1 id, full_name, username, email, role, status, team_id
         FROM users
         WHERE id = ?",
        [$userId]
    );

    if ($stmt === false) {
        return $sessionUser;
    }

    $dbUser = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$dbUser || ($dbUser['status'] ?? '') !== 'active') {
        unset($_SESSION['user']);
        return null;
    }

    $_SESSION['user'] = [
        'id' => $dbUser['id'],
        'full_name' => $dbUser['full_name'],
        'username' => $dbUser['username'],
        'email' => $dbUser['email'],
        'role' => $dbUser['role'],
        'team_id' => $dbUser['team_id']
    ];

    return $_SESSION['user'];
}

function require_login()
{
    if (!is_logged_in()) {
        set_flash('danger', 'Bạn cần đăng nhập để tiếp tục.');
        redirect(base_url('/auth/login.php'));
    }

    $user = refresh_current_user();

    if (!$user) {
        set_flash('danger', 'Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.');
        redirect(base_url('/auth/login.php'));
    }
}

function require_role($roles = [])
{
    require_login();

    $user = current_user();

    if (!in_array($user['role'], $roles, true)) {
        set_flash('danger', 'Bạn không có quyền truy cập trang này.');
        redirect_by_role($user['role']);
    }
}
