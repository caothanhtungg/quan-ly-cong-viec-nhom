<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect_by_role(current_user()['role']);
}

$error = '';
$loginInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $loginInput = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($error === '' && ($loginInput === '' || $password === '')) {
        $error = 'Vui lòng nhập tên đăng nhập hoặc email và mật khẩu.';
    } elseif ($error === '') {
        $conn = getConnection();

        $sql = "SELECT TOP 1 id, full_name, username, email, password_hash, role, status, team_id
                FROM users
                WHERE username = ? OR email = ?";

        $params = [$loginInput, $loginInput];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $error = 'Lỗi truy vấn dữ liệu.';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

            if (!$user) {
                $error = 'Tài khoản không tồn tại.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Tài khoản đã bị khóa.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Mật khẩu không đúng.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'team_id' => $user['team_id'],
                ];

                log_activity(
                    $conn,
                    (int)$user['id'],
                    'login',
                    'auth',
                    (int)$user['id'],
                    'Đăng nhập vào hệ thống'
                );

                set_flash('success', 'Đăng nhập thành công.');
                redirect_by_role($user['role']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Task Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('/assets/img/hhcc-mark.svg')) ?>">
    <link href="<?= e(asset_url('/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="app-body auth-page">
<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<div class="auth-shell">
    <div class="auth-grid">
        <section class="auth-intro">
            <div class="auth-brand">
                <img
                    src="<?= e(asset_url('/assets/img/hhcc-logo.svg')) ?>"
                    alt="Logo HHCC"
                    class="auth-brand-logo"
                >
                <div class="auth-brand-copy">
                    <div class="auth-brand-name">Task Management</div>
                    <div class="auth-brand-subtitle">Đồ án Điện toán đám mây</div>
                </div>
            </div>

            <div class="auth-intro-copy">
                <div class="auth-overline">Truy cập hệ thống</div>
                <h1>Đăng nhập<br><span>để tiếp tục.</span></h1>
                <p>
                    Hệ thống quản lý công việc theo nhóm được xây dựng phục vụ đồ án
                    Điện toán đám mây của sinh viên Cao Thanh Tùng, Trường Đại học Kinh doanh và Công nghệ Hà Nội.
                </p>
            </div>

            <div class="auth-role-row">
                <span class="auth-role-pill">Đồ án Điện toán đám mây</span>
                <span class="auth-role-pill">Cao Thanh Tùng</span>
                <span class="auth-role-pill">MSSV 2924111392</span>
            </div>

            <div class="auth-intro-note">
                <div class="auth-intro-note-title">Sinh viên Cao Thanh Tùng</div>
                <p class="mb-0">
                    Trường Đại học Kinh doanh và Công nghệ Hà Nội<br>
                    Mã sinh viên: 2924111392
                </p>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-heading">
                    <div class="auth-card-eyebrow">Lối vào hệ thống</div>
                    <h2>Đăng nhập</h2>
                    <p class="auth-card-copy">
                        Nhập thông tin tài khoản để vào hệ thống.
                    </p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger auth-inline-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <?= csrf_field() ?>

                    <div class="auth-form-group">
                        <label for="login_username" class="form-label auth-label">Tên đăng nhập hoặc email</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon"><i class="bi bi-person"></i></span>
                            <input
                                id="login_username"
                                type="text"
                                name="username"
                                class="form-control auth-input"
                                value="<?= e($loginInput) ?>"
                                placeholder="Nhập tài khoản của bạn"
                                autocomplete="username"
                                autofocus
                                required
                            >
                        </div>
                    </div>

                    <div class="auth-form-group">
                        <div class="auth-label-row">
                            <label for="login_password" class="form-label auth-label mb-0">Mật khẩu</label>
                            <span class="auth-helper-text">Bắt buộc</span>
                        </div>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon"><i class="bi bi-shield-lock"></i></span>
                            <input
                                id="login_password"
                                type="password"
                                name="password"
                                class="form-control auth-input"
                                placeholder="Nhập mật khẩu"
                                autocomplete="current-password"
                                data-password-input
                                required
                            >
                            <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Hiển thị mật khẩu">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit-btn">Đăng nhập</button>
                </form>

                <div class="auth-footnote">
                    Nếu bạn chưa có tài khoản, liên hệ admin để được cấp quyền truy cập.
                </div>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('/assets/js/main.js')) ?>"></script>
</body>
</html>
