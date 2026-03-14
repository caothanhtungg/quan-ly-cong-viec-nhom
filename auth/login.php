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
        $error = 'Phien lam viec khong hop le. Vui long thu lai.';
    }

    $loginInput = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($error === '' && ($loginInput === '' || $password === '')) {
        $error = 'Vui long nhap ten dang nhap hoac email va mat khau.';
    } elseif ($error === '') {
        $conn = getConnection();

        $sql = "SELECT TOP 1 id, full_name, username, email, password_hash, role, status, team_id
                FROM users
                WHERE username = ? OR email = ?";

        $params = [$loginInput, $loginInput];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $error = 'Loi truy van du lieu.';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

            if (!$user) {
                $error = 'Tai khoan khong ton tai.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Tai khoan da bi khoa.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Mat khau khong dung.';
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
                    'Dang nhap vao he thong'
                );

                set_flash('success', 'Dang nhap thanh cong.');
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
    <title>Dang nhap - Task Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="app-body auth-page">
<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<div class="auth-shell">
    <div class="auth-stage">
        <section class="auth-showcase">
            <div class="auth-showcase-badge">
                <i class="bi bi-stars"></i>
                Team workflow platform
            </div>

            <h1>Quan ly cong viec nhom mot cach ro rang va nhanh gon.</h1>
            <p>
                Theo doi task, bai nop, tien do va thong bao trong mot giao dien
                gon, de doc va hop ly cho ca admin, leader va member.
            </p>

            <div class="auth-metrics">
                <div class="auth-metric-card">
                    <div class="auth-metric-value">3</div>
                    <div class="auth-metric-label">Vai tro van hanh</div>
                </div>
                <div class="auth-metric-card">
                    <div class="auth-metric-value">100%</div>
                    <div class="auth-metric-label">Theo doi tien do</div>
                </div>
                <div class="auth-metric-card">
                    <div class="auth-metric-value">24/7</div>
                    <div class="auth-metric-label">Truy cap noi bo</div>
                </div>
            </div>

            <div class="auth-feature-list">
                <div class="auth-feature-item">
                    <span class="auth-feature-icon"><i class="bi bi-check2-circle"></i></span>
                    <div>
                        <strong>Phan quyen ro rang</strong>
                        <div>Admin, leader va member co luong thao tac rieng.</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <span class="auth-feature-icon"><i class="bi bi-kanban"></i></span>
                    <div>
                        <strong>Theo doi task theo trang thai</strong>
                        <div>Tu chua bat dau den hoan thanh va duyet bai nop.</div>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <span class="auth-feature-icon"><i class="bi bi-bell"></i></span>
                    <div>
                        <strong>Thong bao va lich su</strong>
                        <div>Khong bo lo task sap den han hoac bai nop can xu ly.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-head">
                    <div class="auth-card-mark">TM</div>
                    <div>
                        <div class="auth-card-kicker">Welcome back</div>
                        <h2>Dang nhap he thong</h2>
                    </div>
                </div>

                <p class="auth-card-copy">
                    Su dung ten dang nhap hoac email de truy cap vao khu vuc lam viec cua ban.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger auth-inline-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <?= csrf_field() ?>

                    <div class="auth-form-group">
                        <label class="form-label auth-label">Ten dang nhap hoac email</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon"><i class="bi bi-person"></i></span>
                            <input
                                type="text"
                                name="username"
                                class="form-control auth-input"
                                value="<?= e($loginInput) ?>"
                                placeholder="Nhap tai khoan cua ban"
                                required
                            >
                        </div>
                    </div>

                    <div class="auth-form-group">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label auth-label mb-0">Mat khau</label>
                            <span class="auth-helper-text">Bao mat bang password hash</span>
                        </div>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon"><i class="bi bi-shield-lock"></i></span>
                            <input
                                type="password"
                                name="password"
                                class="form-control auth-input"
                                placeholder="Nhap mat khau"
                                data-password-input
                                required
                            >
                            <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit-btn">
                        <span>Dang nhap</span>
                        <i class="bi bi-arrow-right-circle"></i>
                    </button>
                </form>

                <div class="auth-footnote">
                    He thong phu hop cho team hoc tap va do an co phan chia task, nop bai va duyet bai.
                </div>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('/assets/js/main.js')) ?>"></script>
</body>
</html>
