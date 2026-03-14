<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Quản lý người dùng';
$activeMenu = 'admin_users';
$conn = getConnection();

$keyword = trim($_GET['keyword'] ?? '');
$role = trim($_GET['role'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = get_page_number();

$baseSql = "
    SELECT u.id, u.full_name, u.username, u.email, u.role, u.status, u.created_at,
           t.team_name
    FROM users u
    LEFT JOIN teams t ON u.team_id = t.id
    WHERE 1 = 1
";

$params = [];

if ($keyword !== '') {
    $baseSql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $likeKeyword = '%' . $keyword . '%';
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if ($role !== '') {
    $baseSql .= " AND u.role = ?";
    $params[] = $role;
}

if ($status !== '') {
    $baseSql .= " AND u.status = ?";
    $params[] = $status;
}

$pagination = paginate_sqlsrv($conn, $baseSql, $params, 'created_at DESC, id DESC', $page, 10);
$users = $pagination['items'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Quản lý người dùng</h3>
                <p class="text-muted mb-0">Thêm, sửa, tìm kiếm và quản lý tài khoản</p>
            </div>
            <a href="<?= e(base_url('/admin/users/create.php')) ?>" class="btn btn-primary">
                Thêm người dùng
            </a>
        </div>

        <div class="card section-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="keyword" class="form-control"
                               value="<?= e($keyword) ?>"
                               placeholder="Nhập họ tên, username hoặc email">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Vai trò</label>
                        <select name="role" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="leader" <?= $role === 'leader' ? 'selected' : '' ?>>Leader</option>
                            <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Member</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc</button>
                        <a href="<?= e(base_url('/admin/users/index.php')) ?>" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Chưa có người dùng nào</h5>
                        <p class="text-muted mb-3">Hãy thêm người dùng đầu tiên cho hệ thống.</p>
                        <a href="<?= e(base_url('/admin/users/create.php')) ?>" class="btn btn-primary">
                            Thêm người dùng
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Họ tên</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Trạng thái</th>
                                    <th>Nhóm</th>
                                    <th>Ngày tạo</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $item): ?>
                                    <tr>
                                        <td><?= e($item['id']) ?></td>
                                        <td><?= e($item['full_name']) ?></td>
                                        <td><?= e($item['username']) ?></td>
                                        <td><?= e($item['email']) ?></td>
                                        <td>
                                            <span class="badge text-bg-info text-uppercase">
                                                <?= e($item['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($item['status'] === 'active'): ?>
                                                <span class="badge text-bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($item['team_name'] ?? 'Chưa có') ?></td>
                                        <td><?= e(format_datetime($item['created_at'])) ?></td>
                                        <td class="text-end">
                                            <a href="<?= e(base_url('/admin/users/edit.php?id=' . $item['id'])) ?>" class="btn btn-sm btn-warning">
                                                Sửa
                                            </a>
                                            <form method="POST"
                                                  action="<?= e(base_url('/admin/users/delete.php')) ?>"
                                                  class="d-inline"
                                                  id="delete-user-<?= e($item['id']) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                                <button type="button"
                                                        class="btn btn-sm btn-danger js-confirm-action"
                                                        data-confirm-form="delete-user-<?= e($item['id']) ?>"
                                                        data-confirm-message="Bạn có chắc muốn xử lý xóa người dùng này không?"
                                                        data-confirm-class="btn-danger"
                                                        data-confirm-text="Xác nhận xóa">
                                                    Xóa
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= render_pagination($pagination, 'người dùng') ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
