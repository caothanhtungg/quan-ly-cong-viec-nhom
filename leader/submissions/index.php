<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['leader']);

$pageTitle = 'Bài nộp';
$activeMenu = 'leader_submissions';
$user = current_user();
$conn = getConnection();

if (!ensure_submission_versioning_schema($conn)) {
    set_flash('danger', 'Không thể khởi tạo lịch sử phiên bản bài nộp.');
    redirect(base_url('/leader/dashboard.php'));
}

if (empty($user['team_id'])) {
    set_flash('warning', 'Bạn chưa được gán vào nhóm nào.');
    redirect(base_url('/leader/team.php'));
}

$teamId = (int)$user['team_id'];
$keyword = trim($_GET['keyword'] ?? '');
$reviewStatus = trim($_GET['review_status'] ?? '');
$page = get_page_number();

$baseSql = "
    SELECT
        s.id,
        s.file_name,
        s.file_path,
        s.note,
        s.submitted_at,
        s.review_status,
        s.leader_comment,
        s.reviewed_at,
        s.version_no,
        s.is_latest,
        t.id AS task_id,
        t.title AS task_title,
        t.status AS task_status,
        u.full_name AS member_name
    FROM submissions s
    INNER JOIN tasks t ON s.task_id = t.id
    INNER JOIN users u ON s.submitted_by = u.id
    WHERE t.team_id = ?
";
$params = [$teamId];

if ($keyword !== '') {
    $baseSql .= " AND (t.title LIKE ? OR u.full_name LIKE ? OR s.file_name LIKE ?)";
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($reviewStatus !== '') {
    $baseSql .= " AND s.review_status = ?";
    $params[] = $reviewStatus;
}

$pagination = paginate_sqlsrv($conn, $baseSql, $params, 'submitted_at DESC, id DESC', $page, 10);
$submissions = $pagination['items'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Bài nộp của nhóm</h3>
                <p class="text-muted mb-0">Danh sách file thành viên đã nộp cho công việc</p>
            </div>
        </div>

        <div class="card section-card app-filter-shell mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 app-filter-form">
                    <div class="col-md-8">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="keyword" class="form-control"
                               value="<?= e($keyword) ?>"
                               placeholder="Nhập tên công việc, tên thành viên hoặc tên file">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Trạng thái duyệt</label>
                        <select name="review_status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="pending" <?= $reviewStatus === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                            <option value="approved" <?= $reviewStatus === 'approved' ? 'selected' : '' ?>>Đã duyệt</option>
                            <option value="rejected" <?= $reviewStatus === 'rejected' ? 'selected' : '' ?>>Từ chối</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc</button>
                        <a href="<?= e(base_url('/leader/submissions/index.php')) ?>" class="btn btn-outline-secondary w-100">Đặt lại</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($submissions)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Chưa có bài nộp nào</h5>
                        <p class="text-muted mb-0">Khi thành viên nộp file, dữ liệu sẽ hiện ở đây.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Công việc</th>
                                    <th>Thành viên</th>
                                    <th>Phiên bản</th>
                                    <th>Tên file</th>
                                    <th>Thời gian nộp</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($submission['task_title']) ?></div>
                                            <small class="text-muted">Task ID: <?= e($submission['task_id']) ?></small>
                                        </td>
                                        <td><?= e($submission['member_name']) ?></td>
                                        <td>
                                            <span class="badge text-bg-secondary"><?= e(submission_version_text($submission['version_no'] ?? 1)) ?></span>
                                            <?php if (is_latest_submission($submission)): ?>
                                                <div class="mt-1">
                                                    <span class="badge text-bg-primary">Mới nhất</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($submission['file_name']) ?></td>
                                        <td><?= e(format_datetime($submission['submitted_at'])) ?></td>
                                        <td>
                                            <span class="badge <?= e(review_status_badge($submission['review_status'])) ?>">
                                                <?= e(review_status_text($submission['review_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= e(submission_download_url($submission['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                Mở file
                                            </a>
                                            <a href="<?= e(base_url('/leader/tasks/detail.php?id=' . $submission['task_id'])) ?>" class="btn btn-sm btn-info">
                                                Task
                                            </a>
                                            <?php if ($submission['review_status'] === 'pending' && is_latest_submission($submission)): ?>
                                                <a href="<?= e(base_url('/leader/submissions/review.php?id=' . $submission['id'])) ?>" class="btn btn-sm btn-warning">
                                                    Duyệt
                                                </a>
                                            <?php elseif ($submission['review_status'] === 'pending'): ?>
                                                <span class="btn btn-sm btn-outline-secondary disabled">Đã có bản mới hơn</span>
                                            <?php else: ?>
                                                <span class="btn btn-sm btn-outline-secondary disabled">Da xu ly</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php if (!empty($submission['note']) || !empty($submission['leader_comment'])): ?>
                                        <tr>
                                            <td colspan="7">
                                                <?php if (!empty($submission['note'])): ?>
                                                    <div><strong>Ghi chú của thành viên:</strong> <?= e($submission['note']) ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($submission['leader_comment'])): ?>
                                                    <div><strong>Nhận xét leader:</strong> <?= e($submission['leader_comment']) ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($submission['reviewed_at'])): ?>
                                                    <div><small class="text-muted">Da xu ly luc: <?= e(format_datetime($submission['reviewed_at'])) ?></small></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= render_pagination($pagination, 'bài nộp') ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
