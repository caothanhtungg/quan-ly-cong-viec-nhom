<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Chi tiết nhóm';
$activeMenu = 'admin_teams';
$conn = getConnection();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Nhóm không hợp lệ.');
    redirect(base_url('/admin/teams/index.php'));
}

$teamSql = "
    SELECT t.*, u.full_name AS leader_name
    FROM teams t
    LEFT JOIN users u ON t.leader_id = u.id
    WHERE t.id = ?
";
$teamStmt = sqlsrv_query($conn, $teamSql, [$id]);
$team = $teamStmt ? sqlsrv_fetch_array($teamStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$team) {
    set_flash('danger', 'Không tìm thấy nhóm.');
    redirect(base_url('/admin/teams/index.php'));
}

$members = [];
$memberStmt = sqlsrv_query(
    $conn,
    "SELECT id, full_name, username, email, status
     FROM users
     WHERE team_id = ? AND role = 'member'
     ORDER BY full_name ASC",
    [$id]
);
if ($memberStmt !== false) {
    while ($row = sqlsrv_fetch_array($memberStmt, SQLSRV_FETCH_ASSOC)) {
        $members[] = $row;
    }
}

$tasks = [];
$taskStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 10 t.id, t.title, t.status, t.progress_percent, t.due_date, u.full_name AS member_name
     FROM tasks t
     INNER JOIN users u ON t.assigned_to = u.id
     WHERE t.team_id = ?
     ORDER BY t.created_at DESC",
    [$id]
);
if ($taskStmt !== false) {
    while ($row = sqlsrv_fetch_array($taskStmt, SQLSRV_FETCH_ASSOC)) {
        $tasks[] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><?= e($team['team_name']) ?></h3>
                <p class="text-muted mb-0">Chi tiết nhóm và thành viên</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(base_url('/admin/teams/edit.php?id=' . $team['id'])) ?>" class="btn btn-warning">Sửa nhóm</a>
                <a href="<?= e(base_url('/admin/teams/index.php')) ?>" class="btn btn-outline-secondary">Quay lại</a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Thông tin nhóm</h5>
                        <p><strong>Tên nhóm:</strong> <?= e($team['team_name']) ?></p>
                        <p><strong>Leader:</strong> <?= e($team['leader_name'] ?? 'Chưa gán') ?></p>
                        <p><strong>Mô tả:</strong> <?= e($team['description'] ?? 'Không có') ?></p>
                        <p class="mb-0"><strong>Ngày tạo:</strong> <?= e(format_datetime($team['created_at'])) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card section-card mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Thành viên trong nhóm</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($members)): ?>
                            <p class="text-muted mb-0">Chưa có thành viên nào.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Họ tên</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td><?= e($member['full_name']) ?></td>
                                                <td><?= e($member['username']) ?></td>
                                                <td><?= e($member['email']) ?></td>
                                                <td>
                                                    <span class="badge <?= e(user_status_badge($member['status'])) ?>">
                                                        <?= e(user_status_text($member['status'])) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Công việc gần đây của nhóm</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($tasks)): ?>
                            <p class="text-muted mb-0">Nhóm này chưa có công việc nào.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Tên công việc</th>
                                            <th>Người thực hiện</th>
                                            <th>Trạng thái</th>
                                            <th>Tiến độ</th>
                                            <th>Deadline</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr>
                                                <td><?= e($task['title']) ?></td>
                                                <td><?= e($task['member_name']) ?></td>
                                                <td>
                                                    <span class="badge <?= e(task_status_badge($task['status'], $task['due_date'])) ?>">
                                                        <?= e(task_status_text($task['status'], $task['due_date'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= e($task['progress_percent']) ?>%</td>
                                                <td><?= e(format_datetime($task['due_date'])) ?></td>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
