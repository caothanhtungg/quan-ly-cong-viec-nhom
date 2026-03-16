<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['member']);

$pageTitle = 'Nộp file';
$activeMenu = 'member_tasks';
$user = current_user();
$conn = getConnection();

if (!ensure_submission_versioning_schema($conn)) {
    set_flash('danger', 'Không thể khởi tạo lịch sử phiên bản bài nộp.');
    redirect(base_url('/member/tasks/index.php'));
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Công việc không hợp lệ.');
    redirect(base_url('/member/tasks/index.php'));
}

$taskStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1 id, title, status, progress_percent, created_by
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
    set_flash('warning', 'Công việc đã hoàn thành, không thể nộp file.');
    redirect(base_url('/member/tasks/detail.php?id=' . $id));
}

$allowedExtensions = ['zip', 'rar', '7z', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
$maxFileSize = 20 * 1024 * 1024; // 20MB

$errors = [];
$formData = [
    'note' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $formData['note'] = trim($_POST['note'] ?? '');

    if (!isset($_FILES['submission_file']) || !is_array($_FILES['submission_file'])) {
        $errors[] = 'Dữ liệu file không hợp lệ.';
    } elseif ($_FILES['submission_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Bạn cần chọn file để nộp.';
    } elseif ($_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Tải file lên thất bại. Vui lòng thử lại.';
    }

    $file = $_FILES['submission_file'] ?? null;
    $originalName = '';
    $extension = '';

    if ($file && empty($errors)) {
        $originalName = trim((string)($file['name'] ?? ''));
        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));

        if ($originalName === '') {
            $errors[] = 'Tên file không hợp lệ.';
        }

        if (($file['size'] ?? 0) <= 0) {
            $errors[] = 'File không hợp lệ hoặc rỗng.';
        }

        if (($file['size'] ?? 0) > $maxFileSize) {
            $errors[] = 'Dung lượng file vượt quá giới hạn 20MB.';
        }

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'Định dạng file không được hỗ trợ.';
        }
    }

    if (empty($errors)) {
        $uploadDir = __DIR__ . '/../../assets/uploads/submissions';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            $errors[] = 'Không thể tạo thư mục lưu file.';
        }

        $storedFileName = '';
        $savedRelativePath = '';

        if (empty($errors)) {
            try {
                $randomToken = bin2hex(random_bytes(4));
            } catch (Throwable $e) {
                $randomToken = substr(md5((string)microtime(true)), 0, 8);
            }

            $storedFileName = 'task_' . $id . '_user_' . (int)$user['id'] . '_' . date('Ymd_His') . '_' . $randomToken . '.' . $extension;
            $savedRelativePath = 'assets/uploads/submissions/' . $storedFileName;
            $targetPath = $uploadDir . '/' . $storedFileName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $errors[] = 'Không thể lưu file trên máy chủ.';
            }
        }

        if (empty($errors)) {
            if (!sqlsrv_begin_transaction($conn)) {
                $errors[] = 'Không thể bắt đầu giao dịch.';
                if (isset($targetPath) && file_exists($targetPath)) {
                    @unlink($targetPath);
                }
            } else {
                $ok = true;
                $newVersionNo = 1;

                $versionStmt = sqlsrv_query(
                    $conn,
                    "SELECT ISNULL(MAX(version_no), 0) AS last_version
                     FROM submissions
                     WHERE task_id = ? AND submitted_by = ?",
                    [$id, (int)$user['id']]
                );

                if ($versionStmt === false) {
                    $ok = false;
                } else {
                    $versionRow = sqlsrv_fetch_array($versionStmt, SQLSRV_FETCH_ASSOC);
                    $newVersionNo = ((int)($versionRow['last_version'] ?? 0)) + 1;
                }

                if ($ok) {
                    $clearLatestStmt = sqlsrv_query(
                        $conn,
                        "UPDATE submissions
                         SET is_latest = 0
                         WHERE task_id = ? AND submitted_by = ? AND is_latest = 1",
                        [$id, (int)$user['id']]
                    );

                    if ($clearLatestStmt === false) {
                        $ok = false;
                    }
                }

                $insertSubmissionStmt = sqlsrv_query(
                    $conn,
                    "INSERT INTO submissions (task_id, submitted_by, file_name, file_path, note, review_status, submitted_at, version_no, is_latest)
                     OUTPUT INSERTED.id AS id
                     VALUES (?, ?, ?, ?, ?, ?, GETDATE(), ?, ?)",
                    [
                        $id,
                        (int)$user['id'],
                        $originalName,
                        $savedRelativePath,
                        $formData['note'] !== '' ? $formData['note'] : null,
                        'pending',
                        $newVersionNo,
                        1
                    ]
                );

                if ($insertSubmissionStmt === false) {
                    $ok = false;
                }

                if ($ok) {
                    $newSubmissionIdRow = sqlsrv_fetch_array($insertSubmissionStmt, SQLSRV_FETCH_ASSOC);
                    $newSubmissionId = (int)($newSubmissionIdRow['id'] ?? 0);

                    $updateTaskStmt = sqlsrv_query(
                        $conn,
                        "UPDATE tasks
                         SET status = 'submitted', updated_at = GETDATE()
                         WHERE id = ? AND assigned_to = ?",
                        [$id, (int)$user['id']]
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
                        'submission',
                        'Nộp ' . submission_version_text($newVersionNo),
                        $originalName . ($formData['note'] !== '' ? ' | Ghi chú: ' . $formData['note'] : '')
                    );

                    create_notification(
                        $conn,
                        (int)$task['created_by'],
                        'Có bài nộp mới',
                        $user['full_name'] . ' vừa nộp ' . submission_version_text($newVersionNo) . ' cho công việc: ' . $task['title'],
                        'new_submission',
                        'submission',
                        $newSubmissionId
                    );

                    log_activity(
                        $conn,
                        (int)$user['id'],
                        'submit',
                        'submission',
                        $id,
                        'Nộp ' . submission_version_text($newVersionNo) . ' cho task ID ' . $id . ': ' . $originalName
                    );

                    set_flash('success', 'Nộp file thành công. Đã tạo ' . submission_version_text($newVersionNo) . '.');
                    redirect(base_url('/member/tasks/detail.php?id=' . $id));
                } else {
                    sqlsrv_rollback($conn);
                    if (isset($targetPath) && file_exists($targetPath)) {
                        @unlink($targetPath);
                    }
                    $errors[] = 'Không thể lưu bài nộp vào hệ thống.';
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
                <h3 class="fw-bold mb-1">Nộp file</h3>
                <p class="text-muted mb-0 app-page-copy"><?= e($task['title']) ?></p>
            </div>
            <a href="<?= e(base_url('/member/tasks/detail.php?id=' . $id)) ?>" class="btn btn-outline-secondary">Quay lại</a>
        </div>

        <div class="card section-card app-form-shell">
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

                <div class="alert alert-info">
                    Mỗi lần nộp file sẽ tạo một phiên bản mới. Leader chỉ duyệt phiên bản mới nhất.
                </div>

                <form method="POST" enctype="multipart/form-data" class="row g-3 app-form-grid">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">Chọn file nộp</label>
                        <input type="file" name="submission_file" class="form-control" required>
                        <small class="text-muted">
                            Định dạng hỗ trợ: <?= e(strtoupper(implode(', ', $allowedExtensions))) ?>. Tối đa 20MB.
                        </small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Ghi chú (tùy chọn)</label>
                        <textarea name="note" rows="4" class="form-control"><?= e($formData['note']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success">Nộp file</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
