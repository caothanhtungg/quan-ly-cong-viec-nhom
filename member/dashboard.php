<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_role(['member']);

$pageTitle = 'Dashboard Member';
$activeMenu = 'member_dashboard';
$user = current_user();
$conn = getConnection();
$featureReady = ensure_project_feature_schema($conn);

function getMemberCount($conn, $sql, $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return 0;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

function memberPercentOf($part, $total)
{
    return $total > 0 ? (int)round(($part / $total) * 100) : 0;
}

$userId = (int)$user['id'];

$totalTasks = getMemberCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE assigned_to = ?",
    [$userId]
);

$inProgressCount = getMemberCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE assigned_to = ? AND status = 'in_progress'",
    [$userId]
);

$submittedCount = getMemberCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE assigned_to = ? AND status = 'submitted'",
    [$userId]
);

$completedCount = getMemberCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks WHERE assigned_to = ? AND status = 'completed'",
    [$userId]
);

$overdueCount = getMemberCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM tasks
     WHERE assigned_to = ?
       AND due_date < CAST(GETDATE() AS DATE)
       AND status <> 'completed'",
    [$userId]
);

$dueSoonCount = getMemberCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM tasks
     WHERE assigned_to = ?
       AND status <> 'completed'
       AND due_date >= CAST(GETDATE() AS DATE)
       AND due_date <= DATEADD(DAY, 2, CAST(GETDATE() AS DATE))",
    [$userId]
);

$completionRate = memberPercentOf($completedCount, $totalTasks);
$pendingReviewCount = $featureReady
    ? getMemberCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM submissions
         WHERE submitted_by = ?
           AND review_status = 'pending'
           AND is_latest = 1",
        [$userId]
    )
    : getMemberCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM submissions
         WHERE submitted_by = ?
           AND review_status = 'pending'",
        [$userId]
    );

$latestRejectedCount = $featureReady
    ? getMemberCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM submissions
         WHERE submitted_by = ?
           AND review_status = 'rejected'
           AND is_latest = 1",
        [$userId]
    )
    : getMemberCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM submissions
         WHERE submitted_by = ?
           AND review_status = 'rejected'",
        [$userId]
    );

$priorityTasks = [];
$prioritySql = "
    SELECT TOP 5
        id,
        title,
        due_date,
        status,
        priority,
        progress_percent
    FROM tasks
    WHERE assigned_to = ?
      AND status <> 'completed'
    ORDER BY
        CASE priority
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            ELSE 3
        END,
        due_date ASC
";
$priorityStmt = sqlsrv_query($conn, $prioritySql, [$userId]);
if ($priorityStmt !== false) {
    while ($row = sqlsrv_fetch_array($priorityStmt, SQLSRV_FETCH_ASSOC)) {
        $priorityTasks[] = $row;
    }
}

$recentTaskHistory = [];
if ($featureReady) {
    $historyStmt = sqlsrv_query(
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
         WHERE t.assigned_to = ?
         ORDER BY th.created_at DESC, th.id DESC",
        [$userId]
    );
    if ($historyStmt !== false) {
        while ($row = sqlsrv_fetch_array($historyStmt, SQLSRV_FETCH_ASSOC)) {
            $recentTaskHistory[] = $row;
        }
    }
}

$rejectedSubmissions = [];
$rejectedSql = "
    SELECT TOP 5
        s.id,
        s.file_name,
        s.leader_comment,
        s.reviewed_at,
        s.version_no,
        t.id AS task_id,
        t.title AS task_title
    FROM submissions s
    INNER JOIN tasks t ON s.task_id = t.id
    WHERE s.submitted_by = ?
      AND s.review_status = 'rejected'
";

if ($featureReady) {
    $rejectedSql .= " AND s.is_latest = 1";
}

$rejectedSql .= " ORDER BY s.reviewed_at DESC";

$rejectedStmt = sqlsrv_query($conn, $rejectedSql, [$userId]);
if ($rejectedStmt !== false) {
    while ($row = sqlsrv_fetch_array($rejectedStmt, SQLSRV_FETCH_ASSOC)) {
        $rejectedSubmissions[] = $row;
    }
}

$memberMetrics = [
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
                        <span class="dashboard-kicker">Member Workspace</span>
                        <h3>Giữ nhịp công việc cá nhân</h3>
                        <p>
                            Xin chào, <?= e($user['full_name']) ?>. Dashboard này ưu tiên việc đọc nhanh khối lượng đang mở,
                            deadline gần và các bài nộp cần làm lại để bạn tập trung đúng chỗ.
                        </p>
                        <div class="dashboard-hero-actions">
                            <a href="<?= e(base_url('/member/tasks/index.php')) ?>" class="btn btn-light">Xem công việc</a>
                            <a href="<?= e(base_url('/profile.php')) ?>" class="btn btn-outline-light">Hồ sơ cá nhân</a>
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
                        <div class="stat-label">Tổng task</div>
                        <div class="stat-value"><?= e($totalTasks) ?></div>
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

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Quá hạn</div>
                        <div class="stat-value text-danger"><?= e($overdueCount) ?></div>
                    </div>
                </div>

                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Cần xử lý lại</div>
                        <div class="stat-value text-warning"><?= e($latestRejectedCount) ?></div>
                    </div>
                </div>
            </section>

            <section class="dashboard-main-grid">
                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Nhịp xử lý hiện tại</h5>
                            <p class="dashboard-panel-copy mb-0">Cân bằng giữa task đang làm, đang chờ review và đã hoàn thành.</p>
                        </div>
                        <div class="card-body">
                            <?php foreach ($memberMetrics as $metric): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><?= e($metric['label']) ?></span>
                                        <span><?= e($metric['count']) ?> task</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?= e($metric['class']) ?>" role="progressbar" style="width: <?= e(memberPercentOf($metric['count'], $totalTasks)) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="dashboard-progress-note">
                                <?= e($overdueCount) ?> task quá hạn, <?= e($latestRejectedCount) ?> bài nộp cần chỉnh sửa và nộp lại.
                            </div>
                        </div>
                    </div>

                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Công việc ưu tiên</h5>
                            <p class="dashboard-panel-copy mb-0">Danh sách chưa hoàn thành, sắp theo độ ưu tiên và deadline.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($priorityTasks)): ?>
                                <p class="text-muted mb-0">Không có công việc nào.</p>
                            <?php else: ?>
                                <?php foreach ($priorityTasks as $item): ?>
                                    <?php $isOverdue = is_task_overdue($item['status'], $item['due_date']); ?>
                                    <div class="quick-list-item <?= $isOverdue ? 'deadline-overdue' : 'deadline-soon' ?>">
                                        <div class="fw-semibold"><?= e($item['title']) ?></div>
                                        <div class="small text-muted">Deadline: <?= e(format_date($item['due_date'])) ?></div>
                                        <div class="small text-muted">Tiến độ: <?= e($item['progress_percent']) ?>%</div>
                                        <div class="mt-1">
                                            <span class="badge <?= e(priority_badge($item['priority'])) ?>">
                                                <?= e(priority_text($item['priority'])) ?>
                                            </span>
                                            <span class="badge <?= e(task_status_badge($item['status'], $item['due_date'])) ?>">
                                                <?= e(task_status_text($item['status'], $item['due_date'])) ?>
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
                            <h5 class="dashboard-panel-title">Lịch sử task gần đây</h5>
                            <p class="dashboard-panel-copy mb-0">Theo dõi những thay đổi mới nhất để không bỏ sót cập nhật từ leader.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentTaskHistory)): ?>
                                <p class="text-muted mb-0">Chưa có lịch sử task nào.</p>
                            <?php else: ?>
                                <?php foreach ($recentTaskHistory as $item): ?>
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

                <div class="dashboard-stack">
                    <div class="card section-card dashboard-panel">
                        <div class="card-header bg-white border-0">
                            <h5 class="dashboard-panel-title">Bài nộp bị từ chối</h5>
                            <p class="dashboard-panel-copy mb-0">Những việc cần sửa và nộp lại để không làm ngắt mạch hoàn thành task.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($rejectedSubmissions)): ?>
                                <p class="text-muted mb-0">Không có bài nộp nào bị từ chối.</p>
                            <?php else: ?>
                                <?php foreach ($rejectedSubmissions as $item): ?>
                                    <div class="quick-list-item">
                                        <div class="fw-semibold"><?= e($item['task_title']) ?></div>
                                        <div class="small text-muted">
                                            <?= e(submission_version_text($item['version_no'] ?? 1)) ?> - <?= e($item['file_name']) ?>
                                        </div>
                                        <?php if (!empty($item['leader_comment'])): ?>
                                            <div class="small"><?= e($item['leader_comment']) ?></div>
                                        <?php endif; ?>
                                        <div class="small text-muted mb-2"><?= e(format_datetime($item['reviewed_at'])) ?></div>
                                        <a href="<?= e(base_url('/member/tasks/submit_file.php?id=' . $item['task_id'])) ?>" class="btn btn-sm btn-outline-danger">
                                            Nộp lại
                                        </a>
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
