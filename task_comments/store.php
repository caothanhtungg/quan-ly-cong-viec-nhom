<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin', 'leader', 'member']);

$user = current_user();
$conn = getConnection();

$taskId = (int)($_POST['task_id'] ?? 0);
$redirectTo = normalize_internal_path(
    $_POST['redirect_to'] ?? task_detail_url_for_role($user['role'] ?? '', $taskId),
    task_detail_url_for_role($user['role'] ?? '', $taskId)
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Yêu cầu không hợp lệ.');
    redirect($redirectTo);
}

if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
    redirect($redirectTo);
}

if (!ensure_project_feature_schema($conn)) {
    set_flash('danger', 'Không thể khởi tạo tính năng bình luận.');
    redirect($redirectTo);
}

$task = get_accessible_task($conn, $taskId, $user);

if (!$task) {
    set_flash('danger', 'Bạn không có quyền bình luận ở công việc này.');
    redirect(base_url('/'));
}

$commentText = trim($_POST['comment_text'] ?? '');

if ($commentText === '') {
    set_flash('danger', 'Nội dung bình luận không được để trống.');
    redirect($redirectTo);
}

$insertStmt = sqlsrv_query(
    $conn,
    "INSERT INTO task_comments (task_id, user_id, comment_text, created_at)
     VALUES (?, ?, ?, GETDATE())",
    [$taskId, (int)$user['id'], $commentText]
);

if ($insertStmt === false) {
    set_flash('danger', 'Không thể lưu bình luận vào hệ thống.');
    redirect($redirectTo);
}

log_activity(
    $conn,
    (int)$user['id'],
    'comment',
    'task',
    $taskId,
    'Thêm bình luận cho task: ' . ($task['title'] ?? ('ID ' . $taskId))
);

set_flash('success', 'Đã thêm bình luận.');
redirect($redirectTo);
