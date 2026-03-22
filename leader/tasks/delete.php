<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['leader']);

$user = current_user();
$conn = getConnection();

if (empty($user['team_id'])) {
    set_flash('warning', 'Bạn chưa được gán vào nhóm nào.');
    redirect(base_url('/leader/team.php'));
}

$teamId = (int)$user['team_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Yêu cầu không hợp lệ.');
    redirect(base_url('/leader/tasks/index.php'));
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Công việc không hợp lệ.');
    redirect(base_url('/leader/tasks/index.php'));
}

$taskStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1 id FROM tasks WHERE id = ? AND team_id = ?",
    [$id, $teamId]
);
$task = $taskStmt ? sqlsrv_fetch_array($taskStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$task) {
    set_flash('danger', 'Không tìm thấy công việc.');
    redirect(base_url('/leader/tasks/index.php'));
}

$hasUpdateStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM task_updates WHERE task_id = ?", [$id]);
$hasSubmissionStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM submissions WHERE task_id = ?", [$id]);

$hasUpdates = $hasUpdateStmt && sqlsrv_fetch_array($hasUpdateStmt, SQLSRV_FETCH_ASSOC);
$hasSubmissions = $hasSubmissionStmt && sqlsrv_fetch_array($hasSubmissionStmt, SQLSRV_FETCH_ASSOC);

if ($hasUpdates || $hasSubmissions) {
    set_flash('danger', 'Không thể xóa công việc đã có cập nhật tiến độ hoặc bài nộp.');
    redirect(base_url('/leader/tasks/index.php'));
}

$deleteStmt = sqlsrv_query($conn, "DELETE FROM tasks WHERE id = ? AND team_id = ?", [$id, $teamId]);

if ($deleteStmt === false) {
    set_flash('danger', 'Không thể xóa công việc.');
} else {
    log_activity(
        $conn,
        (int)$user['id'],
        'delete',
        'task',
        $id,
        'Xóa công việc ID: ' . $id
    );
    set_flash('success', 'Xóa công việc thành công.');
}

redirect(base_url('/leader/tasks/index.php'));
