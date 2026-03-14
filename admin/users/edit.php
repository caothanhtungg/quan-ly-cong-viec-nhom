<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Sửa người dùng';
$activeMenu = 'admin_users';
$conn = getConnection();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Người dùng không hợp lệ.');
    redirect(base_url('/admin/users/index.php'));
}

$userSql = "SELECT TOP 1 * FROM users WHERE id = ?";
$userStmt = sqlsrv_query($conn, $userSql, [$id]);
$userData = $userStmt ? sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$userData) {
    set_flash('danger', 'Không tìm thấy người dùng.');
    redirect(base_url('/admin/users/index.php'));
}

$teams = [];
$teamStmt = sqlsrv_query($conn, "SELECT id, team_name FROM teams ORDER BY team_name ASC");
if ($teamStmt !== false) {
    while ($row = sqlsrv_fetch_array($teamStmt, SQLSRV_FETCH_ASSOC)) {
        $teams[] = $row;
    }
}

$errors = [];
$formData = [
    'full_name' => $userData['full_name'],
    'username' => $userData['username'],
    'email' => $userData['email'],
    'role' => $userData['role'],
    'status' => $userData['status'],
    'team_id' => $userData['team_id'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['username'] = trim($_POST['username'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $newPassword = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $formData['role'] = trim($_POST['role'] ?? 'member');
    $formData['status'] = trim($_POST['status'] ?? 'active');
    $formData['team_id'] = trim($_POST['team_id'] ?? '');

    if ($formData['full_name'] === '') {
        $errors[] = 'Họ tên không được để trống.';
    }
    if ($formData['username'] === '') {
        $errors[] = 'Username không được để trống.';
    }
    if ($formData['email'] === '') {
        $errors[] = 'Email không được để trống.';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    if (!in_array($formData['role'], ['admin', 'leader', 'member'], true)) {
        $errors[] = 'Vai trò không hợp lệ.';
    }
    if (!in_array($formData['status'], ['active', 'inactive'], true)) {
        $errors[] = 'Trạng thái không hợp lệ.';
    }

    $teamId = null;
    if ($formData['role'] !== 'admin' && $formData['team_id'] !== '') {
        $teamId = (int)$formData['team_id'];
        $teamCheckStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM teams WHERE id = ?", [$teamId]);
        if (!$teamCheckStmt || !sqlsrv_fetch_array($teamCheckStmt, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Nhóm không hợp lệ.';
        }
    }

    if ($formData['role'] === 'leader' && $formData['status'] !== 'active') {
        $teamId = null;
    }

    if ($newPassword !== '' && strlen($newPassword) < 6) {
        $errors[] = 'Nếu đổi mật khẩu, mật khẩu mới phải có ít nhất 6 ký tự.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Nhập lại mật khẩu không khớp.';
    }

    $checkSql = "SELECT TOP 1 id FROM users WHERE (username = ? OR email = ?) AND id <> ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$formData['username'], $formData['email'], $id]);
    if ($checkStmt !== false && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
        $errors[] = 'Username hoặc email đã tồn tại.';
    }

    if (empty($errors)) {
        $assignedTeamId = ($formData['role'] === 'admin' || ($formData['role'] === 'leader' && $formData['status'] !== 'active'))
            ? null
            : $teamId;
        if ($newPassword !== '') {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $sql = "
                UPDATE users
                SET full_name = ?, username = ?, email = ?, password_hash = ?, role = ?, status = ?, team_id = ?, updated_at = GETDATE()
                WHERE id = ?
            ";
            $params = [
                $formData['full_name'],
                $formData['username'],
                $formData['email'],
                $passwordHash,
                $formData['role'],
                $formData['status'],
                $assignedTeamId,
                $id
            ];
        } else {
            $sql = "
                UPDATE users
                SET full_name = ?, username = ?, email = ?, role = ?, status = ?, team_id = ?, updated_at = GETDATE()
                WHERE id = ?
            ";
            $params = [
                $formData['full_name'],
                $formData['username'],
                $formData['email'],
                $formData['role'],
                $formData['status'],
                $assignedTeamId,
                $id
            ];
        }

        if (!sqlsrv_begin_transaction($conn)) {
            $errors[] = 'Không thể bắt đầu giao dịch.';
        } else {
            $ok = true;

            if ($formData['role'] !== 'leader' || $teamId === null || $formData['status'] !== 'active') {
                $ok = clear_user_as_team_leader($conn, $id);
            }

            if ($ok) {
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    $ok = false;
                }
            }

            if ($ok && $formData['role'] === 'leader' && $formData['status'] === 'active' && $teamId !== null) {
                $ok = assign_leader_to_team($conn, $id, $teamId);
            }

            if ($ok) {
                sqlsrv_commit($conn);

                log_activity(
                    $conn,
                    (int)current_user()['id'],
                    'update',
                    'user',
                    $id,
                    'Cập nhật người dùng: ' . $formData['full_name']
                );

                set_flash('success', 'Cập nhật người dùng thành công.');
                redirect(base_url('/admin/users/index.php'));
            }

            sqlsrv_rollback($conn);
            $errors[] = 'Không thể cập nhật người dùng.';
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
                <h3 class="fw-bold mb-1">Sửa người dùng</h3>
                <p class="text-muted mb-0">Cập nhật thông tin tài khoản</p>
            </div>
            <a href="<?= e(base_url('/admin/users/index.php')) ?>" class="btn btn-outline-secondary">Quay lại</a>
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
                    <div class="col-md-6">
                        <label class="form-label">Họ tên</label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($formData['full_name']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= e($formData['username']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($formData['email']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Vai trò</label>
                        <select name="role" class="form-select">
                            <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="leader" <?= $formData['role'] === 'leader' ? 'selected' : '' ?>>Leader</option>
                            <option value="member" <?= $formData['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="password" class="form-control">
                        <small class="text-muted">Để trống nếu không đổi mật khẩu.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nhập lại mật khẩu mới</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nhóm</label>
                        <select name="team_id" class="form-select">
                            <option value="">Không chọn</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= e($team['id']) ?>" <?= $formData['team_id'] == $team['id'] ? 'selected' : '' ?>>
                                    <?= e($team['team_name']) ?>
                                </option>
                            <?php endforeach; ?>
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
