<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role(['leader']);

$pageTitle = 'Dashboard Leader';
$activeMenu = 'leader_dashboard';
$user = current_user();
$conn = getConnection();
$featureReady = ensure_project_feature_schema($conn);

if (empty($user['team_id'])) {
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>
    <div class="main-content">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
        <main class="page-content">
            <?php require_once __DIR__ . '/../includes/flash.php'; ?>
            <div class="dashboard-shell">
                <section class="dashboard-hero">
                    <div class="dashboard-hero-grid">
                        <div class="dashboard-hero-copy">
                            <span class="dashboard-kicker">Leader Workspace</span>
                            <h3>Bạn chưa có nhóm để điều phối</h3>
                            <p>
                                Tài khoản hiện chưa được gán vào team nào, nên chưa thể hiển thị tiến độ công việc,
                                bài nộp hay hiệu suất thành viên.
                            </p>
                        </div>

                        <div class="dashboard-hero-side">
                            <div class="dashboard-hero-metric">
                                <span>Nhóm được gán</span>
                                <strong>0</strong>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="card section-card dashboard-panel">
                    <div class="card-header bg-white border-0">
                        <h5 class="dashboard-panel-title">Cần xử lý</h5>
                        <p class="dashboard-panel-copy mb-0">Liên hệ admin để được gán nhóm trước khi quản lý task.</p>
                    </div>
                    <div class="card-body">
                        <p class="mb-0 text-muted">
                            Sau khi được gán team, dashboard sẽ tự hiển thị số liệu thành viên, tiến độ task và bài nộp cần duyệt.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; exit; ?>
<?php }

$teamId = (int)$user['team_id'];

function getLeaderCount($conn, $sql, $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return 0;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

function leaderPercentOf($part, $total)
{
    return $total > 0 ? (int)round(($part / $total) * 100) : 0;
}

$memberCount = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE team_id = ? AND role = 'member' AND status = 'active'",
    [$teamId]
);

$totalTasks = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE team_id = ?",
    [$teamId]
);

$notStartedCount = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE team_id = ? AND status = 'not_started'",
    [$teamId]
);

$inProgressCount = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE team_id = ? AND status = 'in_progress'",
    [$teamId]
);

$submittedCount = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE team_id = ? AND status = 'submitted'",
    [$teamId]
);

$completedCount = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE team_id = ? AND status = 'completed'",
    [$teamId]
);

$overdueCount = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM tasks
     WHERE team_id = ?
       AND due_date < CAST(GETDATE() AS DATE)
       AND status <> 'completed'",
    [$teamId]
);

$dueSoonCount = getLeaderCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM tasks
     WHERE team_id = ?
       AND status <> 'completed'
       AND due_date >= CAST(GETDATE() AS DATE)
       AND due_date <= DATEADD(DAY, 2, CAST(GETDATE() AS DATE))",
    [$teamId]
);

$pendingReviewCount = $featureReady
    ? getLeaderCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM submissions s
         INNER JOIN tasks t ON s.task_id = t.id
         WHERE t.team_id = ?
           AND s.review_status = 'pending'
           AND s.is_latest = 1",
        [$teamId]
    )
    : getLeaderCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM submissions s
         INNER JOIN tasks t ON s.task_id = t.id
         WHERE t.team_id = ?
           AND s.review_status = 'pending'",
        [$teamId]
    );

$completionRate = leaderPercentOf($completedCount, $totalTasks);

$upcomingTasks = [];
$upcomingSql = "
    SELECT TOP 5
        t.id,
        t.title,
        t.due_date,
        t.status,
        t.priority,
        u.full_name AS member_name
    FROM tasks t
    INNER JOIN users u ON t.assigned_to = u.id
    WHERE t.team_id = ?
      AND t.status <> 'completed'
    ORDER BY t.due_date ASC
";
$upcomingStmt = sqlsrv_query($conn, $upcomingSql, [$teamId]);
if ($upcomingStmt !== false) {
    while ($row = sqlsrv_fetch_array($upcomingStmt, SQLSRV_FETCH_ASSOC)) {
        $upcomingTasks[] = $row;
    }
}

$pendingSubmissions = [];
$pendingSql = "
    SELECT TOP 5
        s.id,
        s.file_name,
        s.submitted_at,
        s.version_no,
        t.id AS task_id,
        t.title AS task_title,
        u.full_name AS member_name
    FROM submissions s
    INNER JOIN tasks t ON s.task_id = t.id
    INNER JOIN users u ON s.submitted_by = u.id
    WHERE t.team_id = ?
      AND s.review_status = 'pending'
";

if ($featureReady) {
    $pendingSql .= " AND s.is_latest = 1";
}

$pendingSql .= "
    ORDER BY s.submitted_at DESC
";
$pendingStmt = sqlsrv_query($conn, $pendingSql, [$teamId]);
if ($pendingStmt !== false) {
    while ($row = sqlsrv_fetch_array($pendingStmt, SQLSRV_FETCH_ASSOC)) {
        $pendingSubmissions[] = $row;
    }
}

$memberPerformance = [];
$memberPerformanceSql = "
    SELECT
        u.id,
        u.full_name,
        COUNT(t.id) AS total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN t.status <> 'completed' AND t.due_date < CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) AS overdue_tasks,
        AVG(CASE WHEN t.id IS NOT NULL THEN CAST(t.progress_percent AS FLOAT) END) AS avg_progress
    FROM users u
    LEFT JOIN tasks t ON t.assigned_to = u.id
    WHERE u.team_id = ?
      AND u.role = 'member'
    GROUP BY u.id, u.full_name
    ORDER BY
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) DESC,
        AVG(CASE WHEN t.id IS NOT NULL THEN CAST(t.progress_percent AS FLOAT) END) DESC,
        u.full_name ASC
";
$memberPerformanceStmt = sqlsrv_query($conn, $memberPerformanceSql, [$teamId]);
if ($memberPerformanceStmt !== false) {
    while ($row = sqlsrv_fetch_array($memberPerformanceStmt, SQLSRV_FETCH_ASSOC)) {
        $memberPerformance[] = $row;
    }
}

$teamTaskHistory = [];
if ($featureReady) {
    $teamHistoryStmt = sqlsrv_query(
        $conn,
        "SELECT TOP 8
            th.event_type,
            th.event_title,
            th.event_description,
            th.created_at,
            t.title AS task_title,
            u.full_name AS actor_name
         FROM task_history th
         INNER JOIN tasks t ON th.task_id = t.id
         LEFT JOIN users u ON th.user_id = u.id
         WHERE t.team_id = ?
         ORDER BY th.created_at DESC, th.id DESC",
        [$teamId]
    );
    if ($teamHistoryStmt !== false) {
        while ($row = sqlsrv_fetch_array($teamHistoryStmt, SQLSRV_FETCH_ASSOC)) {
            $teamTaskHistory[] = $row;
        }
    }
}

$leaderMetrics = [
    ['label' => 'Chưa bắt đầu', 'count' => $notStartedCount, 'class' => 'bg-secondary'],
    ['label' => 'Đang thực hiện', 'count' => $inProgressCount, 'class' => 'bg-primary'],
    ['label' => 'Đã nộp', 'count' => $submittedCount, 'class' => 'bg-warning'],
    ['label' => 'Hoàn thành', 'count' => $completedCount, 'class' => 'bg-success'],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../includes/flash.php'; ?>

        <div class="dashboard-shell">
            <section class="dashboard-hero">
                <div class="dashboard-hero-grid">
                    <div class="dashboard-hero-copy">
                        <span class="dashboard-kicker">Leader Workspace</span>
                        <h3>Giữ nhịp vận hành của nhóm</h3>
                        <p>
                            Xin chào, <?= e($user['full_name']) ?>. Dashboard này ưu tiên ba việc:
                            nhìn tiến độ chung, xác định task cần can thiệp và xử lý nhanh các bài nộp đang chờ duyệt.
                        </p>
                        <div class="dashboard-hero-actions">
                            <a href="<?= e(base_url('/leader/tasks/create.php')) ?>" class="btn btn-light">Tạo task mới</a>
                            <a href="<?= e(base_url('/leader/tasks/index.php')) ?>" class="btn btn-outline-light">Xem task</a>
                            <a href="<?= e(base_url('/leader/submissions/index.php')) ?>" class="btn btn-outline-light">Bài nộp</a>
                        </div>
                    </div>

                    <div class="dashboard-hero-side">
                        <div class="dashboard-hero-metric">
                            <span>Tỷ lệ hoàn thành</span>
                            <strong><?= e($completionRate) ?>%</strong>
                        </div>
                        <div class="dashboard-hero-metric">
                            <span>Bài nộp chờ duyệt</span>
                            <strong><?= e($pendingReviewCount) ?></strong>
                        </div>
                        <div class="dashboard-hero-metric">
                            <span>Task sắp đến hạn</span>
                            <strong><?= e($dueSoonCount) ?></strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard-stat-grid">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Thành viên active</div>
                        <div class="stat-value"><?= e($memberCount) ?></div>
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
                        <div class="stat-label">Chưa bắt đầu</div>
                        <div class="stat-value"><?= e($notStartedCount) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Đang thực hiện</div>
                        <div class="stat-value"><?= e($inProgressCount) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Đã nộp</div>
                        <div class="stat-value"><?= e($submittedCount) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Hoàn thành</div>
                        <div class="stat-value text-success"><?= e($completedCount) ?></div>
                    </div>
                </div>
            </section>

            <section class="dashboard-main-grid">
                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Tổng quan theo trạng thái</h5>
                            <p class="dashboard-panel-copy mb-0">Đọc nhanh tiến độ và mức độ rủi ro của toàn team.</p>
                        </div>
                        <div class="card-body">
                            <?php foreach ($leaderMetrics as $metric): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><?= e($metric['label']) ?></span>
                                        <span><?= e($metric['count']) ?> task</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?= e($metric['class']) ?>" role="progressbar" style="width: <?= e(leaderPercentOf($metric['count'], $totalTasks)) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="dashboard-progress-note">
                                <?= e($overdueCount) ?> task quá hạn, <?= e($dueSoonCount) ?> task cần xử lý trong 2 ngày tới.
                            </div>
                        </div>
                    </div>

                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Theo dõi thành viên</h5>
                            <p class="dashboard-panel-copy mb-0">So sánh khối lượng, tiến độ và điểm nghẽn của từng member.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($memberPerformance)): ?>
                                <p class="text-muted mb-0">Chưa có thành viên nào trong nhóm.</p>
                            <?php else: ?>
                                <?php foreach ($memberPerformance as $item): ?>
                                    <div class="quick-list-item">
                                        <div class="fw-semibold"><?= e($item['full_name']) ?></div>
                                        <div class="small text-muted">
                                            Task: <?= e($item['total_tasks']) ?> |
                                            Hoàn thành: <?= e(leaderPercentOf((int)$item['completed_tasks'], (int)$item['total_tasks'])) ?>% |
                                            Quá hạn: <?= e($item['overdue_tasks']) ?>
                                        </div>
                                        <div class="small text-muted">Tiến độ trung bình: <?= e((int)round((float)($item['avg_progress'] ?? 0))) ?>%</div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Công việc cần theo sát</h5>
                            <p class="dashboard-panel-copy mb-0">Danh sách task chưa hoàn thành được sắp theo deadline gần nhất.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcomingTasks)): ?>
                                <p class="text-muted mb-0">Không có công việc nào.</p>
                            <?php else: ?>
                                <?php foreach ($upcomingTasks as $item): ?>
                                    <?php $isOverdue = is_task_overdue($item['status'], $item['due_date']); ?>
                                    <div class="quick-list-item <?= $isOverdue ? 'deadline-overdue' : 'deadline-soon' ?>">
                                        <div class="fw-semibold"><?= e($item['title']) ?></div>
                                        <div class="small text-muted">Thành viên: <?= e($item['member_name']) ?></div>
                                        <div class="small text-muted">Deadline: <?= e(format_date($item['due_date'])) ?></div>
                                        <div class="mt-1">
                                            <span class="badge <?= e(task_status_badge($item['status'], $item['due_date'])) ?>">
                                                <?= e(task_status_text($item['status'], $item['due_date'])) ?>
                                            </span>
                                            <span class="badge <?= e(priority_badge($item['priority'])) ?>">
                                                <?= e(priority_text($item['priority'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Bài nộp chờ duyệt</h5>
                            <p class="dashboard-panel-copy mb-0">Xử lý nhanh các phiên bản mới nhất để không chặn tiến độ nhóm.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendingSubmissions)): ?>
                                <p class="text-muted mb-0">Không có bài nộp nào đang chờ duyệt.</p>
                            <?php else: ?>
                                <?php foreach ($pendingSubmissions as $item): ?>
                                    <div class="quick-list-item">
                                        <div class="fw-semibold"><?= e($item['task_title']) ?></div>
                                        <div class="small text-muted">Thành viên: <?= e($item['member_name']) ?></div>
                                        <div class="small text-muted">
                                            <?= e(submission_version_text($item['version_no'] ?? 1)) ?> - <?= e($item['file_name']) ?>
                                        </div>
                                        <div class="small text-muted mb-2">Nộp lúc: <?= e(format_datetime($item['submitted_at'])) ?></div>
                                        <a href="<?= e(base_url('/leader/submissions/review.php?id=' . $item['id'])) ?>" class="btn btn-sm btn-warning">
                                            Duyệt ngay
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Lịch sử hoạt động của team</h5>
                            <p class="dashboard-panel-copy mb-0">Các thay đổi gần nhất để leader nắm mạch công việc của nhóm.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($teamTaskHistory)): ?>
                                <p class="text-muted mb-0">Chưa có lịch sử task nào.</p>
                            <?php else: ?>
                                <?php foreach ($teamTaskHistory as $item): ?>
                                    <div class="quick-list-item">
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
