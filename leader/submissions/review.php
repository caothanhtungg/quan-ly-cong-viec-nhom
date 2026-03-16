<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['leader']);

$pageTitle = 'Duyệt bài nộp';
$activeMenu = 'leader_submissions';
$user = current_user();
$conn = getConnection();

if (!ensure_submission_versioning_schema($conn)) {
    set_flash('danger', 'Không thể khởi tạo lịch sử phiên bản bài nộp.');
    redirect(base_url('/leader/submissions/index.php'));
}

if (empty($user['team_id'])) {
    set_flash('warning', 'Bạn chưa được gán vào nhóm nào.');
    redirect(base_url('/leader/team.php'));
}

$teamId = (int)$user['team_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Bài nộp không hợp lệ.');
    redirect(base_url('/leader/submissions/index.php'));
}

$sql = "
    SELECT
        s.*,
        t.id AS task_id,
        t.title AS task_title,
        t.status AS task_status,
        t.progress_percent,
        u.full_name AS member_name
    FROM submissions s
    INNER JOIN tasks t ON s.task_id = t.id
    INNER JOIN users u ON s.submitted_by = u.id
    WHERE s.id = ? AND t.team_id = ?
";
$stmt = sqlsrv_query($conn, $sql, [$id, $teamId]);
$submission = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

if (!$submission) {
    set_flash('danger', 'Không tìm thấy bài nộp.');
    redirect(base_url('/leader/submissions/index.php'));
}

if (($submission['review_status'] ?? '') !== 'pending') {
    set_flash('warning', 'Bài nộp này đã được xử lý trước đó.');
    redirect(base_url('/leader/submissions/index.php'));
}

if (!is_latest_submission($submission)) {
    set_flash('warning', 'Bài nộp này không còn là phiên bản mới nhất. Vui lòng xử lý phiên bản mới hơn.');
    redirect(base_url('/leader/submissions/index.php'));
}

$errors = [];
$formData = [
    'action' => '',
    'leader_comment' => $submission['leader_comment'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $formData['action'] = trim($_POST['action'] ?? '');
    $formData['leader_comment'] = trim($_POST['leader_comment'] ?? '');

    if (!in_array($formData['action'], ['approve', 'reject'], true)) {
        $errors[] = 'Hành động không hợp lệ.';
    }

    if ($formData['leader_comment'] === '') {
        $errors[] = 'Bạn cần nhập nhận xét cho bài nộp.';
    }

    if (empty($errors)) {
        $newReviewStatus = $formData['action'] === 'approve' ? 'approved' : 'rejected';
        $newTaskStatus = $formData['action'] === 'approve' ? 'completed' : 'in_progress';

        if (!sqlsrv_begin_transaction($conn)) {
            $errors[] = 'Không thể bắt đầu giao dịch.';
        } else {
            $ok = true;

            $updateSubmissionStmt = sqlsrv_query(
                $conn,
                "UPDATE submissions
                 SET review_status = ?, leader_comment = ?, reviewed_by = ?, reviewed_at = GETDATE()
                 WHERE id = ? AND review_status = 'pending' AND is_latest = 1",
                [$newReviewStatus, $formData['leader_comment'], (int)$user['id'], $id]
            );

            if ($updateSubmissionStmt === false) {
                $ok = false;
            } elseif (sqlsrv_rows_affected($updateSubmissionStmt) !== 1) {
                $ok = false;
                $currentSubmissionStmt = sqlsrv_query(
                    $conn,
                    "SELECT review_status, is_latest
                     FROM submissions
                     WHERE id = ?",
                    [$id]
                );

                $currentSubmission = $currentSubmissionStmt
                    ? sqlsrv_fetch_array($currentSubmissionStmt, SQLSRV_FETCH_ASSOC)
                    : null;

                if ($currentSubmission && !is_latest_submission($currentSubmission)) {
                    $errors[] = 'Bài nộp này đã có phiên bản mới hơn. Vui lòng quay lại danh sách để duyệt bản mới nhất.';
                } elseif ($currentSubmission && ($currentSubmission['review_status'] ?? '') !== 'pending') {
                    $errors[] = 'Bài nộp này đã được xử lý trước đó.';
                } else {
                    $errors[] = 'Không thể cập nhật bài nộp.';
                }
            }

            if ($ok) {
                $newProgress = $newTaskStatus === 'completed' ? 100 : (int)$submission['progress_percent'];

                $updateTaskStmt = sqlsrv_query(
                    $conn,
                    "UPDATE tasks
                     SET status = ?, progress_percent = ?, updated_at = GETDATE()
                     WHERE id = ?",
                    [$newTaskStatus, $newProgress, (int)$submission['task_id']]
                );

                if ($updateTaskStmt === false) {
                    $ok = false;
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);

                record_task_history(
                    $conn,
                    (int)$submission['task_id'],
                    (int)$user['id'],
                    $newReviewStatus === 'approved' ? 'review_approved' : 'review_rejected',
                    $newReviewStatus === 'approved'
                        ? 'Duyệt ' . submission_version_text($submission['version_no'] ?? 1)
                        : 'Từ chối ' . submission_version_text($submission['version_no'] ?? 1),
                    $formData['leader_comment']
                );

                if ($newReviewStatus === 'approved') {
                    create_notification(
                        $conn,
                        (int)$submission['submitted_by'],
                        'Bài nộp đã được duyệt',
                        'Leader đã duyệt bài nộp cho công việc: ' . $submission['task_title'],
                        'submission_approved',
                        'task',
                        (int)$submission['task_id']
                    );
                } else {
                    create_notification(
                        $conn,
                        (int)$submission['submitted_by'],
                        'Bài nộp bị từ chối',
                        'Leader đã từ chối bài nộp cho công việc: ' . $submission['task_title'],
                        'submission_rejected',
                        'task',
                        (int)$submission['task_id']
                    );
                }

                log_activity(
                    $conn,
                    (int)$user['id'],
                    $newReviewStatus === 'approved' ? 'approve_submission' : 'reject_submission',
                    'submission',
                    $id,
                    ($newReviewStatus === 'approved' ? 'Duyệt' : 'Từ chối') . ' bài nộp của task: ' . $submission['task_title']
                );

                $message = $newReviewStatus === 'approved'
                    ? 'Đã duyệt bài nộp thành công.'
                    : 'Đã từ chối bài nộp.';
                set_flash('success', $message);
                redirect(base_url('/leader/submissions/index.php'));
            } else {
                sqlsrv_rollback($conn);
                if (empty($errors)) {
                    $errors[] = 'Không thể xử lý bài nộp.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Duyệt bài nộp</h3>
                <p class="text-muted mb-0 app-page-copy">
                    <?= e($submission['task_title']) ?> - <?= e($submission['member_name']) ?> - <?= e(submission_version_text($submission['version_no'] ?? 1)) ?>
                </p>
            </div>
            <a href="<?= e(base_url('/leader/submissions/index.php')) ?>" class="btn btn-outline-secondary">Quay lại</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card section-card app-form-shell">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Thông tin bài nộp</h5>

                        <p><strong>Công việc:</strong> <?= e($submission['task_title']) ?></p>
                        <p><strong>Thành viên:</strong> <?= e($submission['member_name']) ?></p>
                        <p>
                            <strong>Phiên bản:</strong>
                            <span class="badge text-bg-secondary"><?= e(submission_version_text($submission['version_no'] ?? 1)) ?></span>
                            <span class="badge text-bg-primary">Mới nhất</span>
                        </p>
                        <p><strong>Tên file:</strong> <?= e($submission['file_name']) ?></p>
                        <p><strong>Thời gian nộp:</strong> <?= e(format_datetime($submission['submitted_at'])) ?></p>
                        <p>
                            <strong>Trạng thái hiện tại:</strong>
                            <span class="badge <?= e(review_status_badge($submission['review_status'])) ?>">
                                <?= e(review_status_text($submission['review_status'])) ?>
                            </span>
                        </p>

                        <?php if (!empty($submission['note'])): ?>
                            <p class="mb-3"><strong>Ghi chú thành viên:</strong><br><?= nl2br(e($submission['note'])) ?></p>
                        <?php endif; ?>

                        <a href="<?= e(submission_download_url($submission['id'])) ?>" target="_blank" class="btn btn-outline-primary">
                            Mở file đã nộp
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card section-card app-form-shell">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Xử lý bài nộp</h5>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="app-form-grid">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label">Nhận xét leader</label>
                                <textarea name="leader_comment" rows="5" class="form-control"><?= e($formData['leader_comment']) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label d-block">Lựa chọn xử lý</label>

                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="action" id="approveAction" value="approve" <?= $formData['action'] === 'approve' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="approveAction">Duyệt bài nộp</label>
                                </div>

                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="action" id="rejectAction" value="reject" <?= $formData['action'] === 'reject' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="rejectAction">Từ chối bài nộp</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                Lưu kết quả duyệt
                            </button>

                            <div class="mt-3 text-muted app-form-note">
                                <small>
                                    Nếu duyệt: task sẽ chuyển sang <strong>Hoàn thành</strong>.<br>
                                    Nếu từ chối: task sẽ quay về <strong>Đang thực hiện</strong>.
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
