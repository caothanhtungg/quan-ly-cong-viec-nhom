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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../includes/flash.php'; ?>

        <div class="mb-4">
            <h3 class="fw-bold mb-1">Xin chào, <?= e($user['full_name']) ?></h3>
            <p class="text-muted mb-0">Tổng quan công việc cá nhân của bạn</p>
        </div>

        <div class="row g-3 mb-4">
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
                        <div class="stat-label">Đang thực hiện</div>
                        <div class="stat-value"><?= e($inProgressCount) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Đã nộp</div>
                        <div class="stat-value"><?= e($submittedCount) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Hoàn thành</div>
                        <div class="stat-value text-success"><?= e($completedCount) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Quá hạn</div>
                        <div class="stat-value text-danger"><?= e($overdueCount) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="stat-label">Cần xử lý lại</div>
                        <div class="stat-value text-warning"><?= e($latestRejectedCount) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card quick-card h-100">
                    <div class="card-body">
                        <div class="card-title">Tỉ lệ hoàn thành</div>
                        <div class="stat-value text-success"><?= e($completionRate) ?>%</div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= e($completionRate) ?>%"></div>
                        </div>
                        <div class="text-muted mt-2"><?= e($completedCount) ?> / <?= e($totalTasks) ?> task da xong</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card quick-card h-100">
                    <div class="card-body">
                        <div class="card-title">Sắp đến hạn</div>
                        <div class="stat-value text-danger"><?= e($dueSoonCount) ?></div>
                        <div class="text-muted mt-2">Task cần xử lý trong 2 ngày tới</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card quick-card h-100">
                    <div class="card-body">
                        <div class="card-title">Bài nộp đang chờ duyệt</div>
                        <div class="stat-value text-warning"><?= e($pendingReviewCount) ?></div>
                        <div class="text-muted mt-2">Tính theo phiên bản mới nhất</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Công việc ưu tiên</h5>
                    </div>
                    <div class="card-body px-4">
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

            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Lịch sử task gần đây</h5>
                    </div>
                    <div class="card-body px-4">
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

            <div class="col-lg-4">
                <div class="card section-card">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0 fw-bold">Bài nộp bị từ chối</h5>
                    </div>
                    <div class="card-body px-4">
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
                                        Nộp lai
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
