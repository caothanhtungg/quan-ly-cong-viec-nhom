<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['member']);

$pageTitle = 'Cập nhật tiến độ';
$activeMenu = 'member_tasks';
$user = current_user();
$conn = getConnection();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Công việc không hợp lệ.');
    redirect(base_url('/member/tasks/index.php'));
}

$taskStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1 id, title, status, progress_percent, due_date
     FROM tasks
     WHERE id = ? AND assigned_to = ?",
    [$id, (int)$user['id']]
);
$task = $taskStmt ? sqlsrv_fetch_array($taskStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$task) {
    set_flash('danger', 'Không tìm thấy công việc.');
    redirect(base_url('/member/tasks/index.php'));
}

if ($task['status'] === 'completed') {
    set_flash('warning', 'Công việc đã hoàn thành, không thể cập nhật thêm.');
    redirect(base_url('/member/tasks/detail.php?id=' . $id));
}

if ($task['status'] === 'submitted') {
    set_flash('warning', 'Công việc đã ở trạng thái đã nộp, tạm thời không cập nhật tiến độ.');
    redirect(base_url('/member/tasks/detail.php?id=' . $id));
}

$errors = [];
$formData = [
    'progress_percent' => (int)$task['progress_percent'],
    'note' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $formData['progress_percent'] = (int)($_POST['progress_percent'] ?? 0);
    $formData['note'] = trim($_POST['note'] ?? '');

    if ($formData['progress_percent'] < 0 || $formData['progress_percent'] > 100) {
        $errors[] = 'Tiến độ phải nằm trong khoảng 0 đến 100.';
    }

    if ($formData['note'] === '') {
        $errors[] = 'Bạn cần nhập ghi chú cập nhật.';
    }

    if (empty($errors)) {
        $newStatus = $formData['progress_percent'] > 0 ? 'in_progress' : 'not_started';

        if (!sqlsrv_begin_transaction($conn)) {
            $errors[] = 'Không thể bắt đầu giao dịch.';
        } else {
            $ok = true;

            $insertUpdateStmt = sqlsrv_query(
                $conn,
                "INSERT INTO task_updates (task_id, user_id, progress_percent, note)
                 VALUES (?, ?, ?, ?)",
                [$id, (int)$user['id'], $formData['progress_percent'], $formData['note']]
            );

            if ($insertUpdateStmt === false) {
                $ok = false;
            }

            if ($ok) {
                $updateTaskStmt = sqlsrv_query(
                    $conn,
                    "UPDATE tasks
                     SET progress_percent = ?, status = ?, updated_at = GETDATE()
                     WHERE id = ? AND assigned_to = ?",
                    [$formData['progress_percent'], $newStatus, $id, (int)$user['id']]
                );

                if ($updateTaskStmt === false) {
                    $ok = false;
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);

                record_task_history(
                    $conn,
                    $id,
                    (int)$user['id'],
                    'progress',
                    'Cập nhật tiến độ lên ' . $formData['progress_percent'] . '%',
                    $formData['note']
                );

                log_activity(
                    $conn,
                    (int)$user['id'],
                    'update_progress',
                    'task',
                    $id,
                    'Cập nhật tiến độ task ID ' . $id . ': ' . $formData['progress_percent'] . '%'
                );

                set_flash('success', 'Cập nhật tiến độ thành công.');
                redirect(base_url('/member/tasks/detail.php?id=' . $id));
            } else {
                sqlsrv_rollback($conn);
                $errors[] = 'Không thể cập nhật tiến độ.';
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Cập nhật tiến độ</h3>
                <p class="text-muted mb-0"><?= e($task['title']) ?></p>
            </div>
            <a href="<?= e(base_url('/member/tasks/detail.php?id=' . $id)) ?>" class="btn btn-outline-secondary">Quay lại</a>
        </div>

        <div class="card section-card">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-4">
                        <label class="form-label">Tiến độ (%)</label>
                        <input type="number" min="0" max="100" name="progress_percent" class="form-control"
                               value="<?= e($formData['progress_percent']) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Ghi chú cập nhật</label>
                        <textarea name="note" rows="4" class="form-control"><?= e($formData['note']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Lưu cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
