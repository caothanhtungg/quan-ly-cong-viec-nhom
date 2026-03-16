<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Quản lý công việc';
$activeMenu = 'admin_tasks';
$conn = getConnection();

$keyword = trim($_GET['keyword'] ?? '');
$status = trim($_GET['status'] ?? '');
$teamId = trim($_GET['team_id'] ?? '');
$leaderId = trim($_GET['leader_id'] ?? '');
$memberId = trim($_GET['member_id'] ?? '');
$page = get_page_number();

$teams = [];
$teamStmt = sqlsrv_query($conn, "SELECT id, team_name FROM teams ORDER BY team_name ASC");
if ($teamStmt !== false) {
    while ($row = sqlsrv_fetch_array($teamStmt, SQLSRV_FETCH_ASSOC)) {
        $teams[] = $row;
    }
}

$leaders = [];
$leaderStmt = sqlsrv_query($conn, "SELECT id, full_name FROM users WHERE role = 'leader' ORDER BY full_name ASC");
if ($leaderStmt !== false) {
    while ($row = sqlsrv_fetch_array($leaderStmt, SQLSRV_FETCH_ASSOC)) {
        $leaders[] = $row;
    }
}

$members = [];
$memberStmt = sqlsrv_query($conn, "SELECT id, full_name FROM users WHERE role = 'member' ORDER BY full_name ASC");
if ($memberStmt !== false) {
    while ($row = sqlsrv_fetch_array($memberStmt, SQLSRV_FETCH_ASSOC)) {
        $members[] = $row;
    }
}

$baseSql = "
    SELECT
        t.id,
        t.title,
        t.priority,
        t.start_date,
        t.due_date,
        t.status,
        t.progress_percent,
        t.created_at,
        tm.team_name,
        m.full_name AS member_name,
        l.full_name AS leader_name
    FROM tasks t
    INNER JOIN teams tm ON t.team_id = tm.id
    INNER JOIN users m ON t.assigned_to = m.id
    INNER JOIN users l ON t.created_by = l.id
    WHERE 1 = 1
";
$params = [];

if ($keyword !== '') {
    $baseSql .= " AND (t.title LIKE ? OR tm.team_name LIKE ? OR m.full_name LIKE ?)";
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== '') {
    $baseSql .= " AND t.status = ?";
    $params[] = $status;
}

if ($teamId !== '') {
    $baseSql .= " AND t.team_id = ?";
    $params[] = (int)$teamId;
}

if ($leaderId !== '') {
    $baseSql .= " AND t.created_by = ?";
    $params[] = (int)$leaderId;
}

if ($memberId !== '') {
    $baseSql .= " AND t.assigned_to = ?";
    $params[] = (int)$memberId;
}

$pagination = paginate_sqlsrv($conn, $baseSql, $params, 'created_at DESC, id DESC', $page, 10);
$tasks = $pagination['items'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head mb-4">
            <h3 class="fw-bold mb-1">Quản lý công việc toàn hệ thống</h3>
            <p class="text-muted mb-0">Admin theo dõi công việc của tất cả nhóm</p>
        </div>

        <div class="card section-card app-filter-shell mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 app-filter-form">
                    <div class="col-md-4">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="keyword" class="form-control"
                               value="<?= e($keyword) ?>"
                               placeholder="Tên công việc, nhóm, thành viên">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="not_started" <?= $status === 'not_started' ? 'selected' : '' ?>>Chưa bắt đầu</option>
                            <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>Đang thực hiện</option>
                            <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Đã nộp</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Nhóm</label>
                        <select name="team_id" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= e($team['id']) ?>" <?= $teamId == $team['id'] ? 'selected' : '' ?>>
                                    <?= e($team['team_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Leader</label>
                        <select name="leader_id" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($leaders as $leader): ?>
                                <option value="<?= e($leader['id']) ?>" <?= $leaderId == $leader['id'] ? 'selected' : '' ?>>
                                    <?= e($leader['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Member</label>
                        <select name="member_id" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= e($member['id']) ?>" <?= $memberId == $member['id'] ? 'selected' : '' ?>>
                                    <?= e($member['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Lọc dữ liệu</button>
                        <a href="<?= e(base_url('/admin/tasks/index.php')) ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="text-center py-4">
                        <h5 class="mb-2">Chưa có công việc nào</h5>
                        <p class="text-muted mb-0">Công việc sẽ hiện ở đây khi leader bắt đầu tạo task.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Công việc</th>
                                    <th>Nhóm</th>
                                    <th>Leader</th>
                                    <th>Member</th>
                                    <th>Ưu tiên</th>
                                    <th>Deadline</th>
                                    <th>Trạng thái</th>
                                    <th>Tiến độ</th>
                                    <th class="text-end">Chi tiết</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($task['title']) ?></td>
                                        <td><?= e($task['team_name']) ?></td>
                                        <td><?= e($task['leader_name']) ?></td>
                                        <td><?= e($task['member_name']) ?></td>
                                        <td>
                                            <span class="badge <?= e(priority_badge($task['priority'])) ?>">
                                                <?= e(priority_text($task['priority'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= e(format_date($task['due_date'])) ?>
                                            <?php $dueHintText = task_due_hint_text($task['status'], $task['due_date']); ?>
                                            <?php $dueHintBadge = task_due_hint_badge($task['status'], $task['due_date']); ?>
                                            <?php if ($dueHintText !== ''): ?>
                                                <div class="mt-1">
                                                    <span class="badge <?= e($dueHintBadge) ?>"><?= e($dueHintText) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(task_status_badge($task['status'], $task['due_date'])) ?>">
                                                <?= e(task_status_text($task['status'], $task['due_date'])) ?>
                                            </span>
                                        </td>
                                        <td style="min-width: 140px;">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= e($task['progress_percent']) ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= e($task['progress_percent']) ?>%</small>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= e(base_url('/admin/tasks/detail.php?id=' . $task['id'])) ?>" class="btn btn-sm btn-info">
                                                Xem
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= render_pagination($pagination, 'công việc') ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
