<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['member']);

$pageTitle = 'Bài nộp của tôi';
$activeMenu = 'member_submissions';
$user = current_user();
$conn = getConnection();

if (!ensure_submission_versioning_schema($conn)) {
    set_flash('danger', 'Không thể khởi tạo lịch sử phiên bản bài nộp.');
    redirect(base_url('/member/dashboard.php'));
}

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
        s.version_no,
        s.is_latest,
        t.title AS task_title
    FROM submissions s
    INNER JOIN tasks t ON s.task_id = t.id
    WHERE s.submitted_by = ?
";
$pagination = paginate_sqlsrv($conn, $baseSql, [(int)$user['id']], 'submitted_at DESC, id DESC', $page, 10);
$submissions = $pagination['items'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="mb-4">
            <h3 class="fw-bold mb-1">Bài nộp của tôi</h3>
            <p class="text-muted mb-0">Danh sách các file bạn đã nộp</p>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($submissions)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Bạn chưa nộp file nào</h5>
                        <p class="text-muted mb-0">Sau khi nộp file cho task, thông tin sẽ hiện ở đây.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Công việc</th>
                                    <th>Phiên bản</th>
                                    <th>Tên file</th>
                                    <th>Thời gian nộp</th>
                                    <th>Trạng thái</th>
                                    <th>Mở file</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td><?= e($submission['task_title']) ?></td>
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
                                                    <div><strong>Ghi chú của bạn:</strong> <?= e($submission['note']) ?></div>
                                                <?php endif; ?>

                                                <?php if (!empty($submission['leader_comment'])): ?>
                                                    <div><strong>Nhận xét leader:</strong> <?= e($submission['leader_comment']) ?></div>
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
