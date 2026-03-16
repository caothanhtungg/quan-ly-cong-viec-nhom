<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Quản lý nhóm';
$activeMenu = 'admin_teams';
$conn = getConnection();

$keyword = trim($_GET['keyword'] ?? '');
$page = get_page_number();

$baseSql = "
    SELECT
        t.id,
        t.team_name,
        t.description,
        t.created_at,
        u.full_name AS leader_name,
        (
            SELECT COUNT(*)
            FROM users m
            WHERE m.team_id = t.id AND m.role = 'member'
        ) AS member_count,
        (
            SELECT COUNT(*)
            FROM tasks tk
            WHERE tk.team_id = t.id
        ) AS task_count
    FROM teams t
    LEFT JOIN users u ON t.leader_id = u.id
    WHERE 1 = 1
";

$params = [];

if ($keyword !== '') {
    $baseSql .= " AND t.team_name LIKE ?";
    $params[] = '%' . $keyword . '%';
}

$pagination = paginate_sqlsrv($conn, $baseSql, $params, 'created_at DESC, id DESC', $page, 10);
$teams = $pagination['items'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Quản lý nhóm</h3>
                <p class="text-muted mb-0">Tạo nhóm, gán leader và quản lý thành viên</p>
            </div>
            <a href="<?= e(base_url('/admin/teams/create.php')) ?>" class="btn btn-primary">
                Tạo nhóm
            </a>
        </div>

        <div class="card section-card app-filter-shell mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 app-filter-form">
                    <div class="col-md-10">
                        <label class="form-label">Tìm kiếm tên nhóm</label>
                        <input type="text" name="keyword" class="form-control"
                               value="<?= e($keyword) ?>"
                               placeholder="Nhập tên nhóm">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc</button>
                        <a href="<?= e(base_url('/admin/teams/index.php')) ?>" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($teams)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Chưa có nhóm nào</h5>
                        <p class="text-muted mb-3">Hãy tạo nhóm đầu tiên cho hệ thống.</p>
                        <a href="<?= e(base_url('/admin/teams/create.php')) ?>" class="btn btn-primary">
                            Tạo nhóm
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên nhóm</th>
                                    <th>Leader</th>
                                    <th>So member</th>
                                    <th>So task</th>
                                    <th>Ngày tạo</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teams as $team): ?>
                                    <tr>
                                        <td><?= e($team['id']) ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= e($team['team_name']) ?></div>
                                            <small class="text-muted"><?= e($team['description'] ?? '') ?></small>
                                        </td>
                                        <td><?= e($team['leader_name'] ?? 'Chưa gán') ?></td>
                                        <td><?= e($team['member_count']) ?></td>
                                        <td><?= e($team['task_count']) ?></td>
                                        <td><?= e(format_datetime($team['created_at'])) ?></td>
                                        <td class="text-end">
                                            <a href="<?= e(base_url('/admin/teams/detail.php?id=' . $team['id'])) ?>" class="btn btn-sm btn-info">
                                                Chi tiết
                                            </a>
                                            <a href="<?= e(base_url('/admin/teams/edit.php?id=' . $team['id'])) ?>" class="btn btn-sm btn-warning">
                                                Sửa
                                            </a>
                                            <form method="POST"
                                                  action="<?= e(base_url('/admin/teams/delete.php')) ?>"
                                                  class="d-inline"
                                                  id="delete-team-<?= e($team['id']) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e($team['id']) ?>">
                                                <button type="button"
                                                        class="btn btn-sm btn-danger js-confirm-action"
                                                        data-confirm-form="delete-team-<?= e($team['id']) ?>"
                                                        data-confirm-message="Bạn có chắc muốn xóa nhóm này không?"
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
                    <?= render_pagination($pagination, 'nhóm') ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
