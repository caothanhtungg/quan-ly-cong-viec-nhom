<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Yeu cau không hợp lệ.');
    redirect(base_url('/admin/teams/index.php'));
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Nhóm không hợp lệ.');
    redirect(base_url('/admin/teams/index.php'));
}

$teamStmt = sqlsrv_query($conn, "SELECT TOP 1 id, team_name FROM teams WHERE id = ?", [$id]);
$team = $teamStmt ? sqlsrv_fetch_array($teamStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$team) {
    set_flash('danger', 'Không tìm thấy nhóm.');
    redirect(base_url('/admin/teams/index.php'));
}

$taskCheckStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM tasks WHERE team_id = ?", [$id]);
$hasTasks = $taskCheckStmt && sqlsrv_fetch_array($taskCheckStmt, SQLSRV_FETCH_ASSOC);

if ($hasTasks) {
    set_flash('danger', 'Không thể xóa nhóm này vì đã có công việc liên quan.');
    redirect(base_url('/admin/teams/index.php'));
}

if (!sqlsrv_begin_transaction($conn)) {
    set_flash('danger', 'Không thể bắt đầu giao dịch.');
    redirect(base_url('/admin/teams/index.php'));
}

$ok = true;

$clearUsersStmt = sqlsrv_query(
    $conn,
    "UPDATE users SET team_id = NULL, updated_at = GETDATE() WHERE team_id = ?",
    [$id]
);

if ($clearUsersStmt === false) {
    $ok = false;
}

if ($ok) {
    $clearLeaderStmt = sqlsrv_query(
        $conn,
        "UPDATE teams SET leader_id = NULL, updated_at = GETDATE() WHERE id = ?",
        [$id]
    );

    if ($clearLeaderStmt === false) {
        $ok = false;
    }
}

if ($ok) {
    $deleteTeamStmt = sqlsrv_query($conn, "DELETE FROM teams WHERE id = ?", [$id]);

    if ($deleteTeamStmt === false) {
        $ok = false;
    }
}

if ($ok) {
    sqlsrv_commit($conn);
    log_activity(
        $conn,
        (int)current_user()['id'],
        'delete',
        'team',
        $id,
        'Xóa nhóm: ' . $team['team_name']
    );
    set_flash('success', 'Xóa nhóm thành công.');
} else {
    sqlsrv_rollback($conn);
    set_flash('danger', 'Không thể xóa nhóm.');
}

redirect(base_url('/admin/teams/index.php'));
