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
                    'team_id' => $user['team_id']
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
    <title>Đăng nhập - Hệ thống quản lý công việc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h3 class="text-center mb-3">Đăng nhập hệ thống</h3>
                    <p class="text-center text-muted mb-4">Quản lý công việc theo nhóm</p>

                    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập hoặc email</label>
                            <input type="text" name="username" class="form-control"
                                   value="<?= e($loginInput) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
