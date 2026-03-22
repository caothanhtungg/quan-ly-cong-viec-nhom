<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['member']);

$pageTitle = 'Công việc của tôi';
$activeMenu = 'member_tasks';
$user = current_user();
$conn = getConnection();

$keyword = trim($_GET['keyword'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = get_page_number();

$baseSql = "
    SELECT
        t.id,
        t.title,
        t.description,
        t.priority,
        t.start_date,
        t.due_date,
        t.status,
        t.progress_percent,
        t.created_at,
        u.full_name AS leader_name,
        (
            SELECT TOP 1 tu.note
            FROM task_updates tu
            WHERE tu.task_id = t.id
            ORDER BY tu.created_at DESC
        ) AS latest_update_note,
        (
            SELECT TOP 1 s.file_name
            FROM submissions s
            WHERE s.task_id = t.id AND s.submitted_by = ?
            ORDER BY s.submitted_at DESC
        ) AS latest_submission_file
    FROM tasks t
    INNER JOIN users u ON t.created_by = u.id
    WHERE t.assigned_to = ?
";
$params = [(int)$user['id'], (int)$user['id']];

if ($keyword !== '') {
    $baseSql .= " AND t.title LIKE ?";
    $params[] = '%' . $keyword . '%';
}

if ($status !== '') {
    $baseSql .= " AND t.status = ?";
    $params[] = $status;
}

$pagination = paginate_sqlsrv($conn, $baseSql, $params, 'created_at DESC, id DESC', $page, 10);
$tasks = $pagination['items'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Công việc của tôi</h3>
                <p class="text-muted mb-0">Danh sách công việc được giao cho bạn</p>
            </div>
        </div>

        <div class="card section-card app-filter-shell mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 app-filter-form">
                    <div class="col-md-8">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="keyword" class="form-control" value="<?= e($keyword) ?>" placeholder="Nhập tên công việc">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="not_started" <?= $status === 'not_started' ? 'selected' : '' ?>>Chưa bắt đầu</option>
                            <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>Đang thực hiện</option>
                            <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Đã nộp</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc</button>
                        <a href="<?= e(base_url('/member/tasks/index.php')) ?>" class="btn btn-outline-secondary w-100">Đặt lại</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Bạn chưa có công việc nào</h5>
                        <p class="text-muted mb-0">Khi leader giao việc, công việc sẽ hiện ở đây.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Tên công việc</th>
                                    <th>Leader</th>
                                    <th>Ưu tiên</th>
                                    <th>Deadline</th>
                                    <th>Trạng thái</th>
                                    <th>Tiến độ</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($task['title']) ?></td>
                                        <td><?= e($task['leader_name']) ?></td>
                                        <td>
                                            <span class="badge <?= e(priority_badge($task['priority'])) ?>">
                                                <?= e(priority_text($task['priority'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= e(format_date($task['due_date'])) ?>
                                            <?php $dueHintText = task_due_hint_text($task['status'], $task['due_date']); ?>
                                            <?php $dueHintBadge = task_due_hint_badge($task['status'], $task['due_date']); ?>
                                            <?php if ($dueHintText !== ''): ?>
                                                <div class="mt-1">
                                                    <span class="badge <?= e($dueHintBadge) ?>"><?= e($dueHintText) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(task_status_badge($task['status'], $task['due_date'])) ?>">
                                                <?= e(task_status_text($task['status'], $task['due_date'])) ?>
                                            </span>
                                        </td>
                                        <td style="min-width: 140px;">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= e($task['progress_percent']) ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= e($task['progress_percent']) ?>%</small>
                                        </td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary js-task-preview-btn"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#taskPreviewOffcanvas"
                                                data-title="<?= e($task['title']) ?>"
                                                data-description="<?= e($task['description'] ?? 'Không có mô tả') ?>"
                                                data-assigned-name="<?= e($user['full_name']) ?>"
                                                data-creator-name="<?= e($task['leader_name']) ?>"
                                                data-start-date="<?= e(format_date($task['start_date'])) ?>"
                                                data-due-date="<?= e(format_date($task['due_date'])) ?>"
                                                data-priority-text="<?= e(priority_text($task['priority'])) ?>"
                                                data-priority-badge="<?= e(priority_badge($task['priority'])) ?>"
                                                data-status-text="<?= e(task_status_text($task['status'], $task['due_date'])) ?>"
                                                data-status-badge="<?= e(task_status_badge($task['status'], $task['due_date'])) ?>"
                                                data-progress="<?= e($task['progress_percent']) ?>"
                                                data-latest-update="<?= e($task['latest_update_note'] ?? 'Chưa có cập nhật tiến độ') ?>"
                                                data-latest-submission="<?= e($task['latest_submission_file'] ?? 'Chưa có bài nộp') ?>"
                                                data-detail-url="<?= e(base_url('/member/tasks/detail.php?id=' . $task['id'])) ?>"
                                                data-update-url="<?= ($task['status'] !== 'completed' && $task['status'] !== 'submitted') ? e(base_url('/member/tasks/update_progress.php?id=' . $task['id'])) : '' ?>"
                                                data-submit-url="<?= ($task['status'] !== 'completed') ? e(base_url('/member/tasks/submit_file.php?id=' . $task['id'])) : '' ?>">
                                                Xem nhanh
                                            </button>

                                            <a href="<?= e(base_url('/member/tasks/detail.php?id=' . $task['id'])) ?>" class="btn btn-sm btn-info">
                                                Chi tiết
                                            </a>

                                            <?php if ($task['status'] !== 'completed' && $task['status'] !== 'submitted'): ?>
                                                <a href="<?= e(base_url('/member/tasks/update_progress.php?id=' . $task['id'])) ?>" class="btn btn-sm btn-primary">
                                                    Cập nhật
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($task['status'] !== 'completed'): ?>
                                                <a href="<?= e(base_url('/member/tasks/submit_file.php?id=' . $task['id'])) ?>" class="btn btn-sm btn-success">
                                                    Nộp file
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= render_pagination($pagination, 'công việc') ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="offcanvas offcanvas-end offcanvas-task" tabindex="-1" id="taskPreviewOffcanvas">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">Xem nhanh công việc</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="offcanvas-body">
            <div class="task-section">
                <div class="task-title" id="previewTaskTitle"></div>
                <div class="task-desc" id="previewTaskDescription"></div>
            </div>

            <div class="task-section">
                <div class="row">
                    <div class="col-6">
                        <div class="meta-label">Người thực hiện</div>
                        <div class="meta-value" id="previewAssignedName"></div>
                    </div>
                    <div class="col-6">
                        <div class="meta-label">Leader giao việc</div>
                        <div class="meta-value" id="previewCreatorName"></div>
                    </div>
                    <div class="col-6">
                        <div class="meta-label">Ngày bắt đầu</div>
                        <div class="meta-value" id="previewStartDate"></div>
                    </div>
                    <div class="col-6">
                        <div class="meta-label">Deadline</div>
                        <div class="meta-value" id="previewDueDate"></div>
                    </div>
                    <div class="col-6">
                        <div class="meta-label">Độ ưu tiên</div>
                        <div class="meta-value" id="previewPriorityBadge"></div>
                    </div>
                    <div class="col-6">
                        <div class="meta-label">Trạng thái</div>
                        <div class="meta-value" id="previewStatusBadge"></div>
                    </div>
                </div>
            </div>

            <div class="task-section">
                <div class="meta-label">Tiến độ hiện tại</div>
                <div class="progress mb-2" style="height: 10px;">
                    <div id="previewProgressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
                <div class="meta-value" id="previewProgressText">0%</div>
            </div>

            <div class="task-section">
                <div class="meta-label">Cập nhật gần nhất</div>
                <div class="meta-value mini-muted" id="previewLatestUpdate"></div>
            </div>

            <div class="task-section">
                <div class="meta-label">Bài nộp gần nhất</div>
                <div class="meta-value mini-muted" id="previewLatestSubmission"></div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a id="previewDetailLink" href="#" class="btn btn-info">Chi tiết</a>
                <a id="previewUpdateLink" href="#" class="btn btn-primary d-none">Cập nhật</a>
                <a id="previewSubmitLink" href="#" class="btn btn-success d-none">Nộp file</a>
                <a id="previewEditLink" href="#" class="btn btn-warning d-none">Sửa</a>
            </div>
        </div>
    </div>
    
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
