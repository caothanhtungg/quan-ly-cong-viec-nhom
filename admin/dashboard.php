<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role(['admin']);

$pageTitle = 'Dashboard Admin';
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

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../includes/flash.php'; ?>

        <div class="mb-4">
            <h3 class="fw-bold mb-1">Xin chào, <?= e($user['full_name']) ?></h3>
            <p class="text-muted mb-0">Tổng quan hệ thống quản lý công việc</p>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Tổng người dùng</div>
                        <div class="stat-value"><?= e($totalUsers) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Tổng nhóm</div>
                        <div class="stat-value"><?= e($totalTeams) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Leader</div>
                        <div class="stat-value"><?= e($totalLeaders) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Member</div>
                        <div class="stat-value"><?= e($totalMembers) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Tổng task</div>
                        <div class="stat-value"><?= e($totalTasks) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Task quá hạn</div>
                        <div class="stat-value text-danger"><?= e($totalOverdueTasks) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card quick-card h-100">
                    <div class="card-body">
                        <div class="card-title">Tỉ lệ hoàn thành</div>
                        <div class="stat-value text-success"><?= e($taskCompletionRate) ?>%</div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= e($taskCompletionRate) ?>%"></div>
                        </div>
                        <div class="text-muted mt-2"><?= e($completedTasks) ?> / <?= e($totalTasks) ?> task đã hoàn thành</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card quick-card h-100">
                    <div class="card-body">
                        <div class="card-title">Bài nộp chờ duyệt</div>
                        <div class="stat-value text-warning"><?= e($pendingSubmissionCount) ?></div>
                        <div class="text-muted mt-2">Tính theo phiên bản mới nhất của mỗi bài nộp</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card quick-card h-100">
                    <div class="card-body">
                        <div class="card-title">Tài khoản đang hoạt động</div>
                        <div class="stat-value"><?= e($totalActiveUsers) ?></div>
                        <div class="text-muted mt-2">Tổng leader/member/admin có trạng thái active</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 my-1">
            <div class="col-lg-4">
                <div class="card section-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Phân bổ task theo trạng thái</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php $statusMetrics = [
                            ['label' => 'Chưa bắt đầu', 'count' => $notStartedTasks, 'class' => 'bg-secondary'],
                            ['label' => 'Đang thực hiện', 'count' => $inProgressTasks, 'class' => 'bg-primary'],
                            ['label' => 'Đã nộp', 'count' => $submittedTasks, 'class' => 'bg-warning'],
                            ['label' => 'Hoàn thành', 'count' => $completedTasks, 'class' => 'bg-success'],
                        ]; ?>
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
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card section-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Top nhóm theo kết quả</h5>
                    </div>
                    <div class="card-body px-4">
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

            <div class="col-lg-4">
                <div class="card section-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Lịch sử task gần đây</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($recentTaskHistory)): ?>
                            <p class="text-muted mb-0">Chưa có lịch sử task nào.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentTaskHistory as $item): ?>
                                    <div class="list-group-item px-0">
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
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Nhóm mới tạo</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($recentTeams)): ?>
                            <p class="text-muted mb-0">Chưa có nhóm nào.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentTeams as $team): ?>
                                    <div class="list-group-item px-0">
                                        <div class="fw-semibold"><?= e($team['team_name']) ?></div>
                                        <small class="text-muted">
                                            Leader: <?= e($team['leader_name'] ?? 'Chưa gán') ?>
                                        </small><br>
                                        <small class="text-muted">
                                            Tạo lúc: <?= e(format_datetime($team['created_at'])) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Công việc quá hạn</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($overdueTasks)): ?>
                            <p class="text-muted mb-0">Không có công việc quá hạn.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($overdueTasks as $task): ?>
                                    <div class="list-group-item px-0">
                                        <div class="fw-semibold"><?= e($task['title']) ?></div>
                                        <small class="text-muted">
                                            Người thực hiện: <?= e($task['member_name']) ?>
                                        </small><br>
                                        <span class="badge text-bg-danger deadline-badge mt-1">
                                            Hạn: <?= e(format_datetime($task['due_date'])) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Người dùng mới</h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if (empty($recentUsers)): ?>
                            <p class="text-muted mb-0">Chưa có người dùng nào.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentUsers as $item): ?>
                                    <div class="list-group-item px-0">
                                        <div class="fw-semibold"><?= e($item['full_name']) ?></div>
                                        <small class="text-muted">
                                            <?= e($item['username']) ?> - <?= e(strtoupper($item['role'])) ?>
                                        </small><br>
                                        <small class="text-muted">
                                            Tạo lúc: <?= e(format_datetime($item['created_at'])) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
