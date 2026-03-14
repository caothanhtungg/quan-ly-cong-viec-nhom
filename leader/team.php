<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role(['leader']);

$pageTitle = 'Nhóm của tôi';
$activeMenu = 'leader_team';
$user = current_user();
$conn = getConnection();

if (empty($user['team_id'])) {
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>
    <div class="main-content">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
        <main class="page-content">
            <div class="card section-card">
                <div class="card-body p-4">
                    <h4 class="fw-bold">Bạn chưa được gán vào nhóm nào</h4>
                    <p class="text-muted mb-0">Hãy vào tài khoản Admin để gán leader vào nhóm.</p>
                </div>
            </div>
        </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; exit; ?>
<?php }

$teamId = (int)$user['team_id'];

$teamSql = "
    SELECT t.id, t.team_name, t.description, t.created_at
    FROM teams t
    WHERE t.id = ?
";
$teamStmt = sqlsrv_query($conn, $teamSql, [$teamId]);
$team = $teamStmt ? sqlsrv_fetch_array($teamStmt, SQLSRV_FETCH_ASSOC) : null;

$members = [];
$memberSql = "
    SELECT
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.status,
        (SELECT COUNT(*) FROM tasks tk WHERE tk.assigned_to = u.id) AS total_tasks,
        (SELECT COUNT(*) FROM tasks tk WHERE tk.assigned_to = u.id AND tk.status = 'completed') AS completed_tasks,
        (SELECT COUNT(*) FROM tasks tk WHERE tk.assigned_to = u.id AND tk.status <> 'completed' AND tk.due_date < CAST(GETDATE() AS DATE)) AS overdue_tasks
    FROM users u
    WHERE u.team_id = ? AND u.role = 'member'
    ORDER BY u.full_name ASC
";
$memberStmt = sqlsrv_query($conn, $memberSql, [$teamId]);
if ($memberStmt !== false) {
    while ($row = sqlsrv_fetch_array($memberStmt, SQLSRV_FETCH_ASSOC)) {
        $members[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <main class="page-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Nhóm của tôi</h3>
                <p class="text-muted mb-0">Thông tin nhóm và danh sách thành viên</p>
            </div>
            <a href="<?= e(base_url('/leader/tasks/create.php')) ?>" class="btn btn-primary">
                Tạo công việc
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Thông tin nhóm</h5>
                        <p><strong>Tên nhóm:</strong> <?= e($team['team_name'] ?? '') ?></p>
                        <p><strong>Leader:</strong> <?= e($user['full_name']) ?></p>
                        <p><strong>Mô tả:</strong> <?= e($team['description'] ?? 'Không có') ?></p>
                        <p class="mb-0"><strong>Ngày tạo:</strong> <?= e(format_datetime($team['created_at'] ?? null)) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Thành viên trong nhóm</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($members)): ?>
                            <p class="text-muted mb-0">Nhóm này chưa có thành viên.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Họ tên</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Tổng task</th>
                                            <th>Hoàn thành</th>
                                            <th>Quá hạn</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td><?= e($member['full_name']) ?></td>
                                                <td><?= e($member['username']) ?></td>
                                                <td><?= e($member['email']) ?></td>
                                                <td><?= e($member['total_tasks']) ?></td>
                                                <td><span class="badge text-bg-success"><?= e($member['completed_tasks']) ?></span></td>
                                                <td><span class="badge text-bg-danger"><?= e($member['overdue_tasks']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>