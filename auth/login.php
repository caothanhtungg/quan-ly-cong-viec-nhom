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
    <link href="<?= e(asset_url('/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="app-body auth-page">
<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<div class="auth-shell">
    <div class="auth-grid">
        <section class="auth-intro">
            <div class="auth-brand">
                <div class="auth-brand-mark">TM</div>
                <div class="auth-brand-copy">
                    <div class="auth-brand-name">Task Management</div>
                    <div class="auth-brand-subtitle">Internal workflow system</div>
                </div>
            </div>

            <div class="auth-intro-copy">
                <div class="auth-overline">Workspace access</div>
                <h1>Dang nhap de tiep tuc lam viec.</h1>
                <p>
                    Mot cua vao gon va ro rang cho admin, leader va member.
                    Sau khi dang nhap, he thong se dua ban den dung dashboard theo vai tro.
                </p>
            </div>

            <div class="auth-intro-list">
                <div class="auth-intro-item">
                    <span class="auth-intro-icon"><i class="bi bi-grid-1x2"></i></span>
                    <div class="auth-intro-text">
                        <strong>Di den dung dashboard</strong>
                        <span>Moi vai tro duoc dua vao dung khu vuc thao tac ngay sau khi dang nhap.</span>
                    </div>
                </div>

                <div class="auth-intro-item">
                    <span class="auth-intro-icon"><i class="bi bi-kanban"></i></span>
                    <div class="auth-intro-text">
                        <strong>Theo doi task va bai nop</strong>
                        <span>Cong viec, tien do va review duoc gom trong cung mot he thong.</span>
                    </div>
                </div>

                <div class="auth-intro-item">
                    <span class="auth-intro-icon"><i class="bi bi-shield-check"></i></span>
                    <div class="auth-intro-text">
                        <strong>Dang nhap an toan</strong>
                        <span>Phien lam viec moi duoc tao lai sau khi xac thuc thanh cong.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-heading">
                    <div class="auth-card-kicker">Secure sign in</div>
                    <h2>Dang nhap</h2>
                    <p class="auth-card-copy">
                        Su dung ten dang nhap hoac email de vao khu vuc lam viec cua ban.
                    </p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger auth-inline-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <div class="auth-role-row">
                    <span class="auth-role-pill">Admin</span>
                    <span class="auth-role-pill">Leader</span>
                    <span class="auth-role-pill">Member</span>
                </div>

                <form method="POST" class="auth-form">
                    <?= csrf_field() ?>

                    <div class="auth-form-group">
                        <label for="login_username" class="form-label auth-label">Ten dang nhap hoac email</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon"><i class="bi bi-person"></i></span>
                            <input
                                id="login_username"
                                type="text"
                                name="username"
                                class="form-control auth-input"
                                value="<?= e($loginInput) ?>"
                                placeholder="Nhap tai khoan cua ban"
                                autocomplete="username"
                                autofocus
                                required
                            >
                        </div>
                    </div>

                    <div class="auth-form-group">
                        <div class="auth-label-row">
                            <label for="login_password" class="form-label auth-label mb-0">Mat khau</label>
                            <span class="auth-helper-text">Bat buoc</span>
                        </div>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon"><i class="bi bi-shield-lock"></i></span>
                            <input
                                id="login_password"
                                type="password"
                                name="password"
                                class="form-control auth-input"
                                placeholder="Nhap mat khau"
                                autocomplete="current-password"
                                data-password-input
                                required
                            >
                            <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit-btn">Dang nhap</button>
                </form>

                <div class="auth-footnote">
                    Neu ban chua co tai khoan, lien he admin de duoc cap quyen truy cap vao team.
                </div>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset_url('/assets/js/main.js')) ?>"></script>
</body>
</html>
