<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$conn = getConnection();
$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Yêu cầu không hợp lệ.');
    redirect(base_url('/admin/users/index.php'));
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Người dùng không hợp lệ.');
    redirect(base_url('/admin/users/index.php'));
}

if ($currentUser['id'] == $id) {
    set_flash('danger', 'Bạn không thể tự xóa chính mình.');
    redirect(base_url('/admin/users/index.php'));
}

$existsStmt = sqlsrv_query($conn, "SELECT TOP 1 id, full_name, role FROM users WHERE id = ?", [$id]);
$user = $existsStmt ? sqlsrv_fetch_array($existsStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$user) {
    set_flash('danger', 'Không tìm thấy người dùng.');
    redirect(base_url('/admin/users/index.php'));
}

$hasRelatedData = false;

$checks = [
    "SELECT TOP 1 id FROM teams WHERE leader_id = ?",
    "SELECT TOP 1 id FROM tasks WHERE assigned_to = ? OR created_by = ?",
    "SELECT TOP 1 id FROM task_updates WHERE user_id = ?",
    "SELECT TOP 1 id FROM submissions WHERE submitted_by = ? OR reviewed_by = ?",
    "SELECT TOP 1 id FROM activity_logs WHERE user_id = ?"
];

foreach ($checks as $query) {
    $params = substr_count($query, '?') === 2 ? [$id, $id] : [$id];
    $stmt = sqlsrv_query($conn, $query, $params);
    if ($stmt !== false && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hasRelatedData = true;
        break;
    }
}

if ($hasRelatedData) {
    if (!sqlsrv_begin_transaction($conn)) {
        set_flash('danger', 'Không thể xử lý người dùng này.');
    } else {
        $ok = true;

        if (($user['role'] ?? '') === 'leader') {
            $ok = clear_user_as_team_leader($conn, $id);
        }

        if ($ok) {
            $stmt = sqlsrv_query(
                $conn,
                "UPDATE users
                 SET status = 'inactive',
                     team_id = CASE WHEN role = 'leader' THEN NULL ELSE team_id END,
                     updated_at = GETDATE()
                 WHERE id = ?",
                [$id]
            );

            if ($stmt === false) {
                $ok = false;
            }
        }

        if ($ok) {
            sqlsrv_commit($conn);
            log_activity(
                $conn,
                (int)$currentUser['id'],
                'deactivate',
                'user',
                $id,
                'Chuyển người dùng sang trạng thái ngưng hoạt động: ' . $user['full_name']
            );
            set_flash('warning', 'Người dùng đã có dữ liệu liên quan, hệ thống đã chuyển sang trạng thái ngưng hoạt động.');
        } else {
            sqlsrv_rollback($conn);
            set_flash('danger', 'Không thể xử lý người dùng này.');
        }
    }
} else {
    $stmt = sqlsrv_query($conn, "DELETE FROM users WHERE id = ?", [$id]);

    if ($stmt === false) {
        set_flash('danger', 'Không thể xóa người dùng.');
    } else {
        log_activity(
            $conn,
            (int)$currentUser['id'],
            'delete',
            'user',
            $id,
            'Xóa người dùng: ' . $user['full_name']
        );
        set_flash('success', 'Xóa người dùng thành công.');
    }
}

redirect(base_url('/admin/users/index.php'));
