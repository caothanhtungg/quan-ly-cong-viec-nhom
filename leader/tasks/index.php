<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['leader']);

$pageTitle = 'Quản lý công việc';
$activeMenu = 'leader_tasks';
$user = current_user();
$conn = getConnection();

if (empty($user['team_id'])) {
    set_flash('warning', 'Bạn chưa được gán vào nhóm nào.');
    redirect(base_url('/leader/team.php'));
}

$teamId = (int)$user['team_id'];

$keyword = trim($_GET['keyword'] ?? '');
$status = trim($_GET['status'] ?? '');
$memberId = trim($_GET['member_id'] ?? '');
$page = get_page_number();

$members = [];
$memberStmt = sqlsrv_query(
    $conn,
    "SELECT id, full_name FROM users WHERE team_id = ? AND role = 'member' AND status = 'active' ORDER BY full_name ASC",
    [$teamId]
);
if ($memberStmt !== false) {
    while ($row = sqlsrv_fetch_array($memberStmt, SQLSRV_FETCH_ASSOC)) {
        $members[] = $row;
    }
}

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
        u.full_name AS member_name,
        c.full_name AS creator_name,
        (
            SELECT TOP 1 tu.note
            FROM task_updates tu
            WHERE tu.task_id = t.id
            ORDER BY tu.created_at DESC
        ) AS latest_update_note,
        (
            SELECT TOP 1 s.file_name
            FROM submissions s
            WHERE s.task_id = t.id
            ORDER BY s.submitted_at DESC
        ) AS latest_submission_file
    FROM tasks t
    INNER JOIN users u ON t.assigned_to = u.id
    INNER JOIN users c ON t.created_by = c.id
    WHERE t.team_id = ?
";
$params = [$teamId];

if ($keyword !== '') {
    $baseSql .= " AND t.title LIKE ?";
    $params[] = '%' . $keyword . '%';
}

if ($status !== '') {
    $baseSql .= " AND t.status = ?";
    $params[] = $status;
}

if ($memberId !== '') {
    $baseSql .= " AND t.assigned_to = ?";
    $params[] = (int)$memberId;
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
                <h3 class="fw-bold mb-1">Quản lý công việc</h3>
                <p class="text-muted mb-0">Danh sách công việc của nhóm bạn</p>
            </div>
            <a href="<?= e(base_url('/leader/tasks/create.php')) ?>" class="btn btn-primary">
                Tạo công việc
            </a>
        </div>

        <div class="card section-card app-filter-shell mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 app-filter-form">
                    <div class="col-md-5">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="keyword" class="form-control" value="<?= e($keyword) ?>" placeholder="Nhập tên công việc">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="not_started" <?= $status === 'not_started' ? 'selected' : '' ?>>Chưa bắt đầu</option>
                            <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>Đang thực hiện</option>
                            <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Đã nộp</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Thành viên</label>
                        <select name="member_id" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= e($member['id']) ?>" <?= $memberId == $member['id'] ? 'selected' : '' ?>>
                                    <?= e($member['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc</button>
                        <a href="<?= e(base_url('/leader/tasks/index.php')) ?>" class="btn btn-outline-secondary w-100">Đặt lại</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Chưa có công việc nào</h5>
                        <p class="text-muted mb-3">Hãy tạo công việc đầu tiên cho nhóm.</p>
                        <a href="<?= e(base_url('/leader/tasks/create.php')) ?>" class="btn btn-primary">
                            Tạo công việc
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Tên công việc</th>
                                    <th>Người thực hiện</th>
                                    <th>Ưu tiên</th>
                                    <th>Ngày bắt đầu</th>
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
                                        <td><?= e($task['member_name']) ?></td>
                                        <td>
                                            <span class="badge <?= e(priority_badge($task['priority'])) ?>">
                                                <?= e(priority_text($task['priority'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e(format_date($task['start_date'])) ?></td>
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
                                                data-assigned-name="<?= e($task['member_name']) ?>"
                                                data-creator-name="<?= e($task['creator_name']) ?>"
                                                data-start-date="<?= e(format_date($task['start_date'])) ?>"
                                                data-due-date="<?= e(format_date($task['due_date'])) ?>"
                                                data-priority-text="<?= e(priority_text($task['priority'])) ?>"
                                                data-priority-badge="<?= e(priority_badge($task['priority'])) ?>"
                                                data-status-text="<?= e(task_status_text($task['status'], $task['due_date'])) ?>"
                                                data-status-badge="<?= e(task_status_badge($task['status'], $task['due_date'])) ?>"
                                                data-progress="<?= e($task['progress_percent']) ?>"
                                                data-latest-update="<?= e($task['latest_update_note'] ?? 'Chưa có cập nhật tiến độ') ?>"
                                                data-latest-submission="<?= e($task['latest_submission_file'] ?? 'Chưa có bài nộp') ?>"
                                                data-detail-url="<?= e(base_url('/leader/tasks/detail.php?id=' . $task['id'])) ?>"
                                                data-edit-url="<?= e(base_url('/leader/tasks/edit.php?id=' . $task['id'])) ?>">
                                                Xem nhanh
                                            </button>

                                            <a href="<?= e(base_url('/leader/tasks/detail.php?id=' . $task['id'])) ?>" class="btn btn-sm btn-info">
                                                Chi tiết
                                            </a>
                                            <a href="<?= e(base_url('/leader/tasks/edit.php?id=' . $task['id'])) ?>" class="btn btn-sm btn-warning">
                                                Sửa
                                            </a>
                                            <form method="POST"
                                                  action="<?= e(base_url('/leader/tasks/delete.php')) ?>"
                                                  class="d-inline"
                                                  id="delete-task-<?= e($task['id']) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                                                <button type="button"
                                                        class="btn btn-sm btn-danger js-confirm-action"
                                                        data-confirm-form="delete-task-<?= e($task['id']) ?>"
                                                        data-confirm-message="Bạn có chắc muốn xóa công việc này không?"
                                                        data-confirm-class="btn-danger"
                                                        data-confirm-text="Xác nhận xóa">
                                                    Xóa
                                                </button>
                                            </form>
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
                        <div class="meta-label">Người giao</div>
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

            <div class="d-flex gap-2">
                <a id="previewDetailLink" href="#" class="btn btn-info">Chi tiết</a>
                <a id="previewEditLink" href="#" class="btn btn-warning">Sửa</a>
                <a id="previewUpdateLink" href="#" class="btn btn-primary d-none">Cập nhật</a>
                <a id="previewSubmitLink" href="#" class="btn btn-success d-none">Nộp file</a>
            </div>
        </div>
    </div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
