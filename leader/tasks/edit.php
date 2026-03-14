<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['leader']);

$pageTitle = 'Sửa công việc';
$activeMenu = 'leader_tasks';
$user = current_user();
$conn = getConnection();

if (empty($user['team_id'])) {
    set_flash('warning', 'Bạn chưa được gán vào nhóm nào.');
    redirect(base_url('/leader/team.php'));
}

$teamId = (int)$user['team_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Công việc không hợp lệ.');
    redirect(base_url('/leader/tasks/index.php'));
}

$taskStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1 * FROM tasks WHERE id = ? AND team_id = ?",
    [$id, $teamId]
);
$task = $taskStmt ? sqlsrv_fetch_array($taskStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$task) {
    set_flash('danger', 'Không tìm thấy công việc.');
    redirect(base_url('/leader/tasks/index.php'));
}

$oldAssignedTo = (int)$task['assigned_to'];

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

$memberNames = [];
foreach ($members as $member) {
    $memberNames[(int)$member['id']] = $member['full_name'];
}

$errors = [];
$formData = [
    'title' => $task['title'],
    'description' => $task['description'] ?? '',
    'assigned_to' => $task['assigned_to'],
    'priority' => $task['priority'],
    'start_date' => $task['start_date'] instanceof DateTimeInterface ? $task['start_date']->format('Y-m-d') : '',
    'due_date' => $task['due_date'] instanceof DateTimeInterface ? $task['due_date']->format('Y-m-d') : '',
    'status' => $task['status']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['assigned_to'] = trim($_POST['assigned_to'] ?? '');
    $formData['priority'] = trim($_POST['priority'] ?? 'medium');
    $formData['start_date'] = trim($_POST['start_date'] ?? '');
    $formData['due_date'] = trim($_POST['due_date'] ?? '');
    $formData['status'] = trim($_POST['status'] ?? 'not_started');

    if ($formData['title'] === '') {
        $errors[] = 'Tên công việc không được để trống.';
    }

    if ($formData['assigned_to'] === '') {
        $errors[] = 'Bạn phải chọn thành viên thực hiện.';
    } else {
        $checkMemberStmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 id FROM users WHERE id = ? AND team_id = ? AND role = 'member' AND status = 'active'",
            [(int)$formData['assigned_to'], $teamId]
        );

        if (!$checkMemberStmt || !sqlsrv_fetch_array($checkMemberStmt, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Thành viên được giao không hợp lệ.';
        }
    }

    if (!in_array($formData['priority'], ['low', 'medium', 'high'], true)) {
        $errors[] = 'Độ ưu tiên không hợp lệ.';
    }

    if (!in_array($formData['status'], ['not_started', 'in_progress', 'submitted', 'completed'], true)) {
        $errors[] = 'Trạng thái không hợp lệ.';
    }

    if ($formData['start_date'] === '' || $formData['due_date'] === '') {
        $errors[] = 'Ngày bắt đầu và deadline không được để trống.';
    } elseif (strtotime($formData['due_date']) < strtotime($formData['start_date'])) {
        $errors[] = 'Deadline không được nhỏ hơn ngày bắt đầu.';
    }

    if (empty($errors)) {
        $progressPercent = (int)$task['progress_percent'];

        if ($formData['status'] === 'not_started') {
            $progressPercent = 0;
        } elseif ($formData['status'] === 'completed') {
            $progressPercent = 100;
        }

        $sql = "
            UPDATE tasks
            SET title = ?, description = ?, assigned_to = ?, priority = ?, start_date = ?, due_date = ?, status = ?, progress_percent = ?, updated_at = GETDATE()
            WHERE id = ? AND team_id = ?
        ";

        $params = [
            $formData['title'],
            $formData['description'] !== '' ? $formData['description'] : null,
            (int)$formData['assigned_to'],
            $formData['priority'],
            $formData['start_date'],
            $formData['due_date'],
            $formData['status'],
            $progressPercent,
            $id,
            $teamId
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors[] = 'Không thể cập nhật công việc.';
        } else {
            $changes = [];
            $newAssignedTo = (int)$formData['assigned_to'];
            $oldAssignedName = $memberNames[$oldAssignedTo] ?? ('ID ' . $oldAssignedTo);
            $newAssignedName = $memberNames[$newAssignedTo] ?? ('ID ' . $newAssignedTo);

            if ($formData['title'] !== (string)$task['title']) {
                $changes[] = 'Tên: "' . $task['title'] . '" -> "' . $formData['title'] . '"';
            }

            if (trim((string)($task['description'] ?? '')) !== $formData['description']) {
                $changes[] = 'Mô tả đã được cập nhật';
            }

            if ($newAssignedTo !== $oldAssignedTo) {
                $changes[] = 'Người thực hiện: ' . $oldAssignedName . ' -> ' . $newAssignedName;
            }

            if ($formData['priority'] !== (string)$task['priority']) {
                $changes[] = 'Ưu tiên: ' . priority_text($task['priority']) . ' -> ' . priority_text($formData['priority']);
            }

            if ($formData['start_date'] !== ($task['start_date'] instanceof DateTimeInterface ? $task['start_date']->format('Y-m-d') : '')) {
                $changes[] = 'Ngày bắt đầu: ' . format_date($task['start_date']) . ' -> ' . format_date($formData['start_date']);
            }

            if ($formData['due_date'] !== ($task['due_date'] instanceof DateTimeInterface ? $task['due_date']->format('Y-m-d') : '')) {
                $changes[] = 'Deadline: ' . format_date($task['due_date']) . ' -> ' . format_date($formData['due_date']);
            }

            if ($formData['status'] !== (string)$task['status']) {
                $changes[] = 'Trạng thái: ' . task_status_text($task['status']) . ' -> ' . task_status_text($formData['status']);
            }

            if ($progressPercent !== (int)$task['progress_percent']) {
                $changes[] = 'Tiến độ: ' . (int)$task['progress_percent'] . '% -> ' . $progressPercent . '%';
            }

            record_task_history(
                $conn,
                $id,
                (int)$user['id'],
                'updated',
                'Cập nhật công việc',
                empty($changes) ? 'Cập nhật thông tin chung của công việc.' : implode(' | ', $changes)
            );

            if ((int)$formData['assigned_to'] !== $oldAssignedTo) {
                create_notification(
                    $conn,
                    (int)$formData['assigned_to'],
                    'Bạn vừa được giao lại công việc',
                    'Công việc "' . $formData['title'] . '" vừa được giao cho bạn.',
                    'reassign_task',
                    'task',
                    $id
                );
            }

            log_activity(
                $conn,
                (int)$user['id'],
                'update',
                'task',
                $id,
                'Cập nhật công việc: ' . $formData['title']
            );

            set_flash('success', 'Cập nhật công việc thành công.');
            redirect(base_url('/leader/tasks/index.php'));
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
                <h3 class="fw-bold mb-1">Sửa công việc</h3>
                <p class="text-muted mb-0">Cập nhật thông tin công việc</p>
            </div>
            <a href="<?= e(base_url('/leader/tasks/index.php')) ?>" class="btn btn-outline-secondary">Quay lại</a>
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
                    <div class="col-md-8">
                        <label class="form-label">Tên công việc</label>
                        <input type="text" name="title" class="form-control" value="<?= e($formData['title']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Người thực hiện</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Chọn thành viên</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= e($member['id']) ?>" <?= $formData['assigned_to'] == $member['id'] ? 'selected' : '' ?>>
                                    <?= e($member['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả công việc</label>
                        <textarea name="description" rows="4" class="form-control"><?= e($formData['description']) ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Độ ưu tiên</label>
                        <select name="priority" class="form-select">
                            <option value="low" <?= $formData['priority'] === 'low' ? 'selected' : '' ?>>Thấp</option>
                            <option value="medium" <?= $formData['priority'] === 'medium' ? 'selected' : '' ?>>Trung bình</option>
                            <option value="high" <?= $formData['priority'] === 'high' ? 'selected' : '' ?>>Cao</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Ngày bắt đầu</label>
                        <input type="date" name="start_date" class="form-control" value="<?= e($formData['start_date']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="due_date" class="form-control" value="<?= e($formData['due_date']) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="not_started" <?= $formData['status'] === 'not_started' ? 'selected' : '' ?>>Chưa bắt đầu</option>
                            <option value="in_progress" <?= $formData['status'] === 'in_progress' ? 'selected' : '' ?>>Đang thực hiện</option>
                            <option value="submitted" <?= $formData['status'] === 'submitted' ? 'selected' : '' ?>>Đã nộp</option>
                            <option value="completed" <?= $formData['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
