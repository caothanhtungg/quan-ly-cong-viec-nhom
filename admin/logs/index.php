<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Nhật ký hoạt động';
$activeMenu = 'admin_logs';
$conn = getConnection();

$keyword = trim($_GET['keyword'] ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');
$page = get_page_number();

$baseSql = "
    SELECT
        al.id,
        al.action_type,
        al.entity_type,
        al.entity_id,
        al.description,
        al.created_at,
        u.full_name AS actor_name,
        u.role AS actor_role
    FROM activity_logs al
    INNER JOIN users u ON al.user_id = u.id
    WHERE 1 = 1
";
$params = [];

if ($keyword !== '') {
    $baseSql .= " AND (al.description LIKE ? OR u.full_name LIKE ?)";
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($actionType !== '') {
    $baseSql .= " AND al.action_type = ?";
    $params[] = $actionType;
}

if ($entityType !== '') {
    $baseSql .= " AND al.entity_type = ?";
    $params[] = $entityType;
}

$pagination = paginate_sqlsrv($conn, $baseSql, $params, 'created_at DESC, id DESC', $page, 10);
$logs = $pagination['items'];

$actionTypes = [
    'login', 'logout',
    'create', 'update', 'delete', 'deactivate',
    'update_progress', 'submit', 'comment',
    'approve_submission', 'reject_submission'
];

$entityTypes = [
    'auth', 'user', 'team', 'task', 'submission'
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head mb-4">
            <h3 class="fw-bold mb-1">Nhật ký hoạt động</h3>
            <p class="text-muted mb-0">Admin theo dõi biến động trong hệ thống</p>
        </div>

        <div class="card section-card app-filter-shell mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 app-filter-form">
                    <div class="col-md-6">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="keyword" class="form-control"
                               value="<?= e($keyword) ?>"
                               placeholder="Mô tả hoặc tên người thực hiện">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Action type</label>
                        <select name="action_type" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($actionTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= $actionType === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Entity type</label>
                        <select name="entity_type" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($entityTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= $entityType === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Lọc dữ liệu</button>
                        <a href="<?= e(base_url('/admin/logs/index.php')) ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Chưa có nhật ký nào</h5>
                        <p class="text-muted mb-0">Hệ thống sẽ ghi nhận các thao tác quan trọng vào đây.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Thời gian</th>
                                    <th>Người thực hiện</th>
                                    <th>Vai trò</th>
                                    <th>Action</th>
                                    <th>Entity</th>
                                    <th>Entity ID</th>
                                    <th>Mô tả</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= e(format_datetime($log['created_at'])) ?></td>
                                        <td><?= e($log['actor_name']) ?></td>
                                        <td>
                                            <span class="badge text-bg-dark"><?= e(strtoupper($log['actor_role'])) ?></span>
                                        </td>
                                        <td><?= e($log['action_type']) ?></td>
                                        <td><?= e($log['entity_type']) ?></td>
                                        <td><?= e($log['entity_id']) ?></td>
                                        <td><?= e($log['description']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= render_pagination($pagination, 'nhật ký') ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
