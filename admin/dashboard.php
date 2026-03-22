<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin']);

$pageTitle = 'Bảng điều khiển Admin';
$activeMenu = 'admin_dashboard';
$user = current_user();
$conn = getConnection();
$featureReady = ensure_project_feature_schema($conn);

function getCount($conn, $sql, $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        return 0;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

function percentOf($part, $total)
{
    return $total > 0 ? (int)round(($part / $total) * 100) : 0;
}

$totalUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users");
$totalActiveUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE status = 'active'");
$totalTeams = getCount($conn, "SELECT COUNT(*) AS total FROM teams");
$totalLeaders = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'leader'");
$totalMembers = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'member'");
$totalTasks = getCount($conn, "SELECT COUNT(*) AS total FROM tasks");
$notStartedTasks = getCount($conn, "SELECT COUNT(*) AS total FROM tasks WHERE status = 'not_started'");
$inProgressTasks = getCount($conn, "SELECT COUNT(*) AS total FROM tasks WHERE status = 'in_progress'");
$submittedTasks = getCount($conn, "SELECT COUNT(*) AS total FROM tasks WHERE status = 'submitted'");
$completedTasks = getCount($conn, "SELECT COUNT(*) AS total FROM tasks WHERE status = 'completed'");
$totalOverdueTasks = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM tasks
     WHERE due_date < CAST(GETDATE() AS DATE)
       AND status <> 'completed'"
);
$pendingSubmissionCount = $featureReady
    ? getCount($conn, "SELECT COUNT(*) AS total FROM submissions WHERE is_latest = 1 AND review_status = 'pending'")
    : getCount($conn, "SELECT COUNT(*) AS total FROM submissions WHERE review_status = 'pending'");
$taskCompletionRate = percentOf($completedTasks, $totalTasks);

$teamPerformance = [];
$teamPerformanceStmt = sqlsrv_query(
    $conn,
    "SELECT TOP 5
        tm.team_name,
        COUNT(t.id) AS total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN t.due_date < CAST(GETDATE() AS DATE) AND t.status <> 'completed' THEN 1 ELSE 0 END) AS overdue_tasks
     FROM teams tm
     LEFT JOIN tasks t ON tm.id = t.team_id
     GROUP BY tm.id, tm.team_name
     ORDER BY
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) DESC,
        COUNT(t.id) DESC,
        tm.team_name ASC"
);
if ($teamPerformanceStmt !== false) {
    while ($row = sqlsrv_fetch_array($teamPerformanceStmt, SQLSRV_FETCH_ASSOC)) {
        $teamPerformance[] = $row;
    }
}

$recentTaskHistory = [];
if ($featureReady) {
    $recentTaskHistoryStmt = sqlsrv_query(
        $conn,
        "SELECT TOP 8
            th.event_type,
            th.event_title,
            th.event_description,
            th.created_at,
            t.id AS task_id,
            t.title AS task_title,
            u.full_name AS actor_name
         FROM task_history th
         INNER JOIN tasks t ON th.task_id = t.id
         LEFT JOIN users u ON th.user_id = u.id
         ORDER BY th.created_at DESC, th.id DESC"
    );
    if ($recentTaskHistoryStmt !== false) {
        while ($row = sqlsrv_fetch_array($recentTaskHistoryStmt, SQLSRV_FETCH_ASSOC)) {
            $recentTaskHistory[] = $row;
        }
    }
}

$recentTeams = [];
$sqlRecentTeams = "
    SELECT TOP 5 t.team_name, t.created_at, u.full_name AS leader_name
    FROM teams t
    LEFT JOIN users u ON t.leader_id = u.id
    ORDER BY t.created_at DESC
";
$stmtRecentTeams = sqlsrv_query($conn, $sqlRecentTeams);
if ($stmtRecentTeams !== false) {
    while ($row = sqlsrv_fetch_array($stmtRecentTeams, SQLSRV_FETCH_ASSOC)) {
        $recentTeams[] = $row;
    }
}

$overdueTasks = [];
$sqlOverdueTasks = "
    SELECT TOP 5 t.title, t.due_date, u.full_name AS member_name
    FROM tasks t
    INNER JOIN users u ON t.assigned_to = u.id
    WHERE t.due_date < CAST(GETDATE() AS DATE)
      AND t.status <> 'completed'
    ORDER BY t.due_date ASC
";
$stmtOverdueTasks = sqlsrv_query($conn, $sqlOverdueTasks);
if ($stmtOverdueTasks !== false) {
    while ($row = sqlsrv_fetch_array($stmtOverdueTasks, SQLSRV_FETCH_ASSOC)) {
        $overdueTasks[] = $row;
    }
}

$recentUsers = [];
$sqlRecentUsers = "
    SELECT TOP 5 full_name, username, role, created_at
    FROM users
    ORDER BY created_at DESC
";
$stmtRecentUsers = sqlsrv_query($conn, $sqlRecentUsers);
if ($stmtRecentUsers !== false) {
    while ($row = sqlsrv_fetch_array($stmtRecentUsers, SQLSRV_FETCH_ASSOC)) {
        $recentUsers[] = $row;
    }
}

$statusMetrics = [
    ['label' => 'Chưa bắt đầu', 'count' => $notStartedTasks, 'class' => 'bg-secondary'],
    ['label' => 'Đang thực hiện', 'count' => $inProgressTasks, 'class' => 'bg-primary'],
    ['label' => 'Đã nộp', 'count' => $submittedTasks, 'class' => 'bg-warning'],
    ['label' => 'Hoàn thành', 'count' => $completedTasks, 'class' => 'bg-success'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../includes/flash.php'; ?>

        <div class="dashboard-shell">
            <section class="dashboard-hero">
                <div class="dashboard-hero-grid">
                    <div class="dashboard-hero-copy">
                        <span class="dashboard-kicker">Không gian quản trị</span>
                        <h3>Điều phối toàn bộ hệ thống</h3>
                        <p>
                            Xin chào, <?= e($user['full_name']) ?>. Màn hình này gom chỉ số vận hành,
                            tình trạng task và các tín hiệu phát sinh theo một nhịp đọc nhất quán để bạn
                            kiểm soát hệ thống nhanh hơn.
                        </p>
                        <div class="dashboard-hero-actions">
                            <a href="<?= e(base_url('/admin/users/index.php')) ?>" class="btn btn-light">Quản lý người dùng</a>
                            <a href="<?= e(base_url('/admin/teams/index.php')) ?>" class="btn btn-outline-light">Xem nhóm</a>
                            <a href="<?= e(base_url('/admin/tasks/index.php')) ?>" class="btn btn-outline-light">Theo dõi task</a>
                        </div>
                    </div>

                    <div class="dashboard-hero-side">
                        <div class="dashboard-hero-metric">
                            <span>Tài khoản hoạt động</span>
                            <strong><?= e($totalActiveUsers) ?></strong>
                        </div>
                        <div class="dashboard-hero-metric">
                            <span>Bài nộp chờ duyệt</span>
                            <strong><?= e($pendingSubmissionCount) ?></strong>
                        </div>
                        <div class="dashboard-hero-metric">
                            <span>Tỷ lệ hoàn thành</span>
                            <strong><?= e($taskCompletionRate) ?>%</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard-stat-grid">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Tổng người dùng</div>
                        <div class="stat-value"><?= e($totalUsers) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Tổng nhóm</div>
                        <div class="stat-value"><?= e($totalTeams) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Leader</div>
                        <div class="stat-value"><?= e($totalLeaders) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Member</div>
                        <div class="stat-value"><?= e($totalMembers) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Tổng task</div>
                        <div class="stat-value"><?= e($totalTasks) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Task quá hạn</div>
                        <div class="stat-value text-danger"><?= e($totalOverdueTasks) ?></div>
                    </div>
                </div>
            </section>

            <section class="dashboard-main-grid">
                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Phân bổ task theo trạng thái</h5>
                            <p class="dashboard-panel-copy mb-0">Theo dõi nhịp thực thi của toàn hệ thống theo từng giai đoạn.</p>
                        </div>
                        <div class="card-body">
                            <?php foreach ($statusMetrics as $metric): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><?= e($metric['label']) ?></span>
                                        <span><?= e($metric['count']) ?> task</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?= e($metric['class']) ?>" role="progressbar" style="width: <?= e(percentOf($metric['count'], $totalTasks)) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="dashboard-progress-note">
                                <?= e($completedTasks) ?> / <?= e($totalTasks) ?> task đã hoàn thành, tương ứng <?= e($taskCompletionRate) ?>%.
                            </div>
                        </div>
                    </div>

                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Top nhóm theo kết quả</h5>
                            <p class="dashboard-panel-copy mb-0">Ưu tiên nhóm có tỷ lệ hoàn thành tốt và ít công việc quá hạn.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($teamPerformance)): ?>
                                <p class="text-muted mb-0">Chưa có dữ liệu task theo nhóm.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nhóm</th>
                                                <th>Task</th>
                                                <th>Hoàn thành</th>
                                                <th>Quá hạn</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($teamPerformance as $team): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?= e($team['team_name']) ?></td>
                                                    <td><?= e($team['total_tasks']) ?></td>
                                                    <td><?= e(percentOf((int)$team['completed_tasks'], (int)$team['total_tasks'])) ?>%</td>
                                                    <td><?= e($team['overdue_tasks']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Lịch sử task gần đây</h5>
                            <p class="dashboard-panel-copy mb-0">Các thay đổi mới nhất của hệ thống theo dòng thời gian.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentTaskHistory)): ?>
                                <p class="text-muted mb-0">Chưa có lịch sử task nào.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentTaskHistory as $item): ?>
                                        <div class="list-group-item">
                                            <div class="fw-semibold">
                                                <?= e($item['event_title']) ?>
                                                <span class="badge <?= e(task_history_badge($item['event_type'])) ?> ms-2">
                                                    <?= e(task_history_text($item['event_type'])) ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted">
                                                <?= e($item['task_title']) ?><?= !empty($item['actor_name']) ? ' - ' . e($item['actor_name']) : '' ?>
                                            </div>
                                            <?php if (!empty($item['event_description'])): ?>
                                                <div class="small"><?= e($item['event_description']) ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted"><?= e(format_datetime($item['created_at'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Nhóm mới tạo</h5>
                            <p class="dashboard-panel-copy mb-0">Kiểm tra các nhóm vừa mở để phân bổ leader nhanh hơn.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentTeams)): ?>
                                <p class="text-muted mb-0">Chưa có nhóm nào.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentTeams as $team): ?>
                                        <div class="list-group-item">
                                            <div class="fw-semibold"><?= e($team['team_name']) ?></div>
                                            <div class="small text-muted">Leader: <?= e($team['leader_name'] ?? 'Chưa gán') ?></div>
                                            <div class="small text-muted">Tạo lúc: <?= e(format_datetime($team['created_at'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Công việc quá hạn</h5>
                            <p class="dashboard-panel-copy mb-0">Những đầu việc cần can thiệp ngay để tránh kéo chậm toàn cục.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($overdueTasks)): ?>
                                <p class="text-muted mb-0">Không có công việc quá hạn.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($overdueTasks as $task): ?>
                                        <div class="list-group-item">
                                            <div class="fw-semibold"><?= e($task['title']) ?></div>
                                            <div class="small text-muted">Người thực hiện: <?= e($task['member_name']) ?></div>
                                            <span class="badge text-bg-danger deadline-badge mt-2">Hạn: <?= e(format_datetime($task['due_date'])) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Người dùng mới</h5>
                            <p class="dashboard-panel-copy mb-0">Theo dõi tài khoản vừa tạo để hoàn tất phân quyền và kích hoạt.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentUsers)): ?>
                                <p class="text-muted mb-0">Chưa có người dùng nào.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentUsers as $item): ?>
                                        <div class="list-group-item">
                                            <div class="fw-semibold"><?= e($item['full_name']) ?></div>
                                            <div class="small text-muted"><?= e($item['username']) ?> - <?= e(strtoupper($item['role'])) ?></div>
                                            <div class="small text-muted">Tạo lúc: <?= e(format_datetime($item['created_at'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
