<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$user = current_user();
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Yeu cau không hợp lệ.');
    redirect(base_url('/'));
}

$id = (int)($_POST['id'] ?? 0);
$target = normalize_internal_path($_POST['target'] ?? base_url('/'));

if ($id > 0) {
    sqlsrv_query(
        $conn,
        "UPDATE notifications
         SET is_read = 1, read_at = GETDATE()
         WHERE id = ? AND user_id = ?",
        [$id, (int)$user['id']]
    );
}

redirect($target);
