<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

require_login();

$user = current_user();
$conn = getConnection();

$pageTitle = 'Hồ sơ cá nhân';
$activeMenu = '';

$dbUserStmt = sqlsrv_query(
    $conn,
    "SELECT u.*, t.team_name
     FROM users u
     LEFT JOIN teams t ON u.team_id = t.id
     WHERE u.id = ?",
    [(int)$user['id']]
);
$dbUser = $dbUserStmt ? sqlsrv_fetch_array($dbUserStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$dbUser) {
    set_flash('danger', 'Không tìm thấy thông tin người dùng.');
    redirect(base_url('/'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'Vui lòng nhập đầy đủ thông tin đổi mật khẩu.';
    } elseif (!password_verify($currentPassword, $dbUser['password_hash'])) {
        $errors[] = 'Mật khẩu hiện tại không đúng.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'Nhập lại mật khẩu mới không khớp.';
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = sqlsrv_query(
            $conn,
            "UPDATE users SET password_hash = ?, updated_at = GETDATE() WHERE id = ?",
            [$newHash, (int)$user['id']]
        );

        if ($updateStmt === false) {
            $errors[] = 'Không thể đổi mật khẩu.';
        } else {
            set_flash('success', 'Đổi mật khẩu thành công.');
            redirect(base_url('/profile.php'));
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/includes/flash.php'; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card section-card app-form-shell">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3">Thông tin cá nhân</h4>

                        <p><strong>Họ tên:</strong> <?= e($dbUser['full_name']) ?></p>
                        <p><strong>Username:</strong> <?= e($dbUser['username']) ?></p>
                        <p><strong>Email:</strong> <?= e($dbUser['email']) ?></p>
                        <p><strong>Vai trò:</strong> <?= e(strtoupper($dbUser['role'])) ?></p>
                        <p><strong>Trạng thái:</strong> <?= e($dbUser['status']) ?></p>
                        <p><strong>Nhóm:</strong> <?= e($dbUser['team_name'] ?? 'Chưa có') ?></p>
                        <p class="mb-0"><strong>Ngày tạo:</strong> <?= e(format_datetime($dbUser['created_at'])) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card section-card">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3">Đổi mật khẩu</h4>

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
                            <div class="col-12">
                                <label class="form-label">Mật khẩu hiện tại</label>
                                <input type="password" name="current_password" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Mật khẩu mới</label>
                                <input type="password" name="new_password" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Nhập lại mật khẩu mới</label>
                                <input type="password" name="confirm_password" class="form-control">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
