<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Chi tiết công việc';
$activeMenu = 'admin_tasks';
$conn = getConnection();

if (!ensure_project_feature_schema($conn)) {
    set_flash('danger', 'Không thể khởi tạo dữ liệu bổ sung cho công việc.');
    redirect(base_url('/admin/tasks/index.php'));
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Công việc không hợp lệ.');
    redirect(base_url('/admin/tasks/index.php'));
}

$sql = "
    SELECT
        t.*,
        tm.team_name,
        u1.full_name AS member_name,
        u2.full_name AS leader_name
    FROM tasks t
    INNER JOIN teams tm ON t.team_id = tm.id
    INNER JOIN users u1 ON t.assigned_to = u1.id
    INNER JOIN users u2 ON t.created_by = u2.id
    WHERE t.id = ?
";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$task = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

if (!$task) {
    set_flash('danger', 'Không tìm thấy công việc.');
    redirect(base_url('/admin/tasks/index.php'));
}

$updates = [];
$updateStmt = sqlsrv_query(
    $conn,
    "SELECT tu.progress_percent, tu.note, tu.created_at, u.full_name
     FROM task_updates tu
     INNER JOIN users u ON tu.user_id = u.id
     WHERE tu.task_id = ?
     ORDER BY tu.created_at DESC",
    [$id]
);
if ($updateStmt !== false) {
    while ($row = sqlsrv_fetch_array($updateStmt, SQLSRV_FETCH_ASSOC)) {
        $updates[] = $row;
    }
}

$submissions = [];
$submissionStmt = sqlsrv_query(
    $conn,
    "SELECT s.*, u.full_name AS submitted_name
     FROM submissions s
     INNER JOIN users u ON s.submitted_by = u.id
     WHERE s.task_id = ?
     ORDER BY s.version_no DESC, s.submitted_at DESC, s.id DESC",
    [$id]
);
if ($submissionStmt !== false) {
    while ($row = sqlsrv_fetch_array($submissionStmt, SQLSRV_FETCH_ASSOC)) {
        $submissions[] = $row;
    }
}

$comments = get_task_comments($conn, $id);
$taskHistory = get_task_history($conn, $id, 12);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><?= e($task['title']) ?></h3>
                <p class="text-muted mb-0">Chi tiết công việc toàn hệ thống</p>
            </div>
            <a href="<?= e(base_url('/admin/tasks/index.php')) ?>" class="btn btn-outline-secondary">Quay lại</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card section-card">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Thông tin công việc</h5>

                        <p><strong>Tên công việc:</strong> <?= e($task['title']) ?></p>
                        <p><strong>Nhóm:</strong> <?= e($task['team_name']) ?></p>
                        <p><strong>Leader:</strong> <?= e($task['leader_name']) ?></p>
                        <p><strong>Member:</strong> <?= e($task['member_name']) ?></p>
                        <p>
                            <strong>Độ ưu tiên:</strong>
                            <span class="badge <?= e(priority_badge($task['priority'])) ?>">
                                <?= e(priority_text($task['priority'])) ?>
                            </span>
                        </p>
                        <p>
                            <strong>Trạng thái:</strong>
                            <span class="badge <?= e(task_status_badge($task['status'], $task['due_date'])) ?>">
                                <?= e(task_status_text($task['status'], $task['due_date'])) ?>
                            </span>
                        </p>
                        <p><strong>Ngày bắt đầu:</strong> <?= e(format_date($task['start_date'])) ?></p>
                        <p><strong>Deadline:</strong> <?= e(format_date($task['due_date'])) ?></p>
                        <p><strong>Tiến độ:</strong> <?= e($task['progress_percent']) ?>%</p>

                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= e($task['progress_percent']) ?>%"></div>
                        </div>

                        <p class="mb-0"><strong>Mô tả:</strong><br><?= nl2br(e($task['description'] ?? 'Không có mô tả')) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card section-card mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Lịch sử cập nhật tiến độ</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($updates)): ?>
                            <p class="text-muted mb-0">Chưa có cập nhật nào.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($updates as $item): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <div class="fw-semibold"><?= e($item['full_name']) ?> - <?= e($item['progress_percent']) ?>%</div>
                                            <small class="text-muted"><?= e(format_datetime($item['created_at'])) ?></small>
                                        </div>
                                        <div class="text-muted"><?= e($item['note'] ?? '') ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Danh sách bài nộp</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($submissions)): ?>
                            <p class="text-muted mb-0">Chưa có bài nộp nào.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Phiên bản</th>
                                            <th>Tên file</th>
                                            <th>Người nộp</th>
                                            <th>Thời gian nộp</th>
                                            <th>Trạng thái</th>
                                            <th>File</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submissions as $submission): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge text-bg-secondary"><?= e(submission_version_text($submission['version_no'] ?? 1)) ?></span>
                                                    <?php if (is_latest_submission($submission)): ?>
                                                        <div class="mt-1">
                                                            <span class="badge text-bg-primary">Mới nhất</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= e($submission['file_name']) ?></td>
                                                <td><?= e($submission['submitted_name']) ?></td>
                                                <td><?= e(format_datetime($submission['submitted_at'])) ?></td>
                                                <td>
                                                    <span class="badge <?= e(review_status_badge($submission['review_status'])) ?>">
                                                        <?= e(review_status_text($submission['review_status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?= e(submission_download_url($submission['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        Mở file
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php if (!empty($submission['note']) || !empty($submission['leader_comment'])): ?>
                                                <tr>
                                                    <td colspan="6">
                                                        <?php if (!empty($submission['note'])): ?>
                                                            <div><small><strong>Ghi chú thành viên:</strong> <?= e($submission['note']) ?></small></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($submission['leader_comment'])): ?>
                                                            <div><small><strong>Nhận xét leader:</strong> <?= e($submission['leader_comment']) ?></small></div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card section-card mt-4" id="task-comments">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Trao đổi trong công việc</h5>
                        <span class="badge text-bg-light"><?= e(count($comments)) ?> bình luận</span>
                    </div>
                    <div class="card-body px-4">
                        <form method="POST" action="<?= e(base_url('/task_comments/store.php')) ?>" class="mb-4 app-inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="task_id" value="<?= e($id) ?>">
                            <input type="hidden" name="redirect_to" value="<?= e(task_detail_url_for_role('admin', $id) . '#task-comments') ?>">
                            <label class="form-label">Thêm bình luận</label>
                            <textarea name="comment_text" rows="3" class="form-control" placeholder="Thêm ghi chú quản trị hoặc trao đổi với nhóm" required></textarea>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-outline-primary">Gửi bình luận</button>
                            </div>
                        </form>

                        <?php if (empty($comments)): ?>
                            <p class="text-muted mb-0">Chưa có trao đổi nào trong công việc này.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="fw-semibold">
                                                    <?= e($comment['full_name']) ?>
                                                    <span class="badge <?= e(task_comment_role_badge($comment['role'])) ?> ms-2">
                                                        <?= e(strtoupper($comment['role'])) ?>
                                                    </span>
                                                </div>
                                                <div class="text-muted small"><?= e(format_datetime($comment['created_at'])) ?></div>
                                            </div>
                                        </div>
                                        <div class="mt-2"><?= nl2br(e($comment['comment_text'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card section-card mt-4" id="task-history">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Lịch sử thay đổi task</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($taskHistory)): ?>
                            <p class="text-muted mb-0">Chưa có mốc thay đổi nào được ghi nhận.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($taskHistory as $history): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="fw-semibold">
                                                    <?= e($history['event_title']) ?>
                                                    <span class="badge <?= e(task_history_badge($history['event_type'])) ?> ms-2">
                                                        <?= e(task_history_text($history['event_type'])) ?>
                                                    </span>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= e($history['actor_name'] ?? 'Hệ thống') ?>
                                                    <?php if (!empty($history['actor_role'])): ?>
                                                        - <?= e(strtoupper($history['actor_role'])) ?>
                                                    <?php endif; ?>
                                                    - <?= e(format_datetime($history['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($history['event_description'])): ?>
                                            <div class="mt-2"><?= nl2br(e($history['event_description'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
