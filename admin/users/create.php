<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Thêm người dùng';
$activeMenu = 'admin_users';
$conn = getConnection();

$teams = [];
$teamStmt = sqlsrv_query($conn, "SELECT id, team_name FROM teams ORDER BY team_name ASC");
if ($teamStmt !== false) {
    while ($row = sqlsrv_fetch_array($teamStmt, SQLSRV_FETCH_ASSOC)) {
        $teams[] = $row;
    }
}

$errors = [];
$formData = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'role' => 'member',
    'status' => 'active',
    'team_id' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['username'] = trim($_POST['username'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
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
    if ($password === '') {
        $errors[] = 'Mật khẩu không được để trống.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Nhập lại mật khẩu không khớp.';
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

    $checkUserSql = "SELECT TOP 1 id FROM users WHERE username = ? OR email = ?";
    $checkUserStmt = sqlsrv_query($conn, $checkUserSql, [$formData['username'], $formData['email']]);
    if ($checkUserStmt !== false && sqlsrv_fetch_array($checkUserStmt, SQLSRV_FETCH_ASSOC)) {
        $errors[] = 'Username hoặc email đã tồn tại.';
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $assignedTeamId = ($formData['role'] === 'admin' || ($formData['role'] === 'leader' && $formData['status'] !== 'active'))
            ? null
            : $teamId;

        $sql = "
            INSERT INTO users (full_name, username, email, password_hash, role, status, team_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        $params = [
            $formData['full_name'],
            $formData['username'],
            $formData['email'],
            $passwordHash,
            $formData['role'],
            $formData['status'],
            $assignedTeamId
        ];

        if (!sqlsrv_begin_transaction($conn)) {
            $errors[] = 'Không thể bắt đầu giao dịch.';
        } else {
            $ok = true;
            $newUserId = 0;

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                $ok = false;
            }

            if ($ok) {
                $newUserIdStmt = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS id");
                $newUserIdRow = $newUserIdStmt ? sqlsrv_fetch_array($newUserIdStmt, SQLSRV_FETCH_ASSOC) : null;
                $newUserId = (int)($newUserIdRow['id'] ?? 0);

                if ($newUserId <= 0) {
                    $ok = false;
                }
            }

            if ($ok && $formData['role'] === 'leader' && $formData['status'] === 'active' && $teamId !== null) {
                $ok = assign_leader_to_team($conn, $newUserId, $teamId);
            }

            if ($ok) {
                sqlsrv_commit($conn);

                log_activity(
                    $conn,
                    (int)current_user()['id'],
                    'create',
                    'user',
                    $newUserId,
                    'Thêm người dùng mới: ' . $formData['full_name']
                );

                set_flash('success', 'Thêm người dùng thành công.');
                redirect(base_url('/admin/users/index.php'));
            }

            sqlsrv_rollback($conn);
            $errors[] = 'Không thể thêm người dùng.';
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
                <h3 class="fw-bold mb-1">Thêm người dùng</h3>
                <p class="text-muted mb-0">Tạo tài khoản mới cho hệ thống</p>
            </div>
            <a href="<?= e(base_url('/admin/users/index.php')) ?>" class="btn btn-outline-secondary">Quay lại</a>
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

                <form method="POST" class="row g-3 app-form-grid">
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
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" name="password" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nhập lại mật khẩu</label>
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
                        <small class="text-muted">Admin có thể để trống nhóm.</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Lưu người dùng</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
