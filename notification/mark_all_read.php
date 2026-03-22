<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$user = current_user();
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Yêu cầu không hợp lệ.');
    redirect(base_url('/'));
}

$return = normalize_internal_path($_POST['return'] ?? base_url('/'));

sqlsrv_query(
    $conn,
    "UPDATE notifications
     SET is_read = 1, read_at = GETDATE()
     WHERE user_id = ? AND is_read = 0",
    [(int)$user['id']]
);

redirect($return);
