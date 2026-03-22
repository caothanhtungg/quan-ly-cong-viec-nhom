<?php

require_once __DIR__ . '/../config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $_SESSION['csrf_token'] = hash('sha256', uniqid((string)mt_rand(), true));
        }
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function is_valid_csrf_token($token)
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    header("Location: $path");
    exit;
}

function base_url($path = '')
{
    return BASE_URL . $path;
}

function asset_url($path)
{
    $normalizedPath = '/' . ltrim((string)$path, '/');
    $assetUrl = base_url($normalizedPath);
    $assetFile = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    if (is_file($assetFile)) {
        return $assetUrl . '?v=' . filemtime($assetFile);
    }

    return $assetUrl;
}

function normalize_internal_path($path, $fallback = null)
{
    $fallback = $fallback ?? base_url('/');
    $path = is_string($path) ? trim($path) : '';

    if ($path === '' || strpos($path, BASE_URL) !== 0) {
        return $fallback;
    }

    return $path;
}

function is_logged_in()
{
    return isset($_SESSION['user']);
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function clear_team_leader($conn, $teamId)
{
    $teamId = (int)$teamId;

    if ($teamId <= 0) {
        return false;
    }

    $clearLeaderUsersStmt = sqlsrv_query(
        $conn,
        "UPDATE users
         SET team_id = NULL, updated_at = GETDATE()
         WHERE team_id = ? AND role = 'leader'",
        [$teamId]
    );

    if ($clearLeaderUsersStmt === false) {
        return false;
    }

    $clearTeamStmt = sqlsrv_query(
        $conn,
        "UPDATE teams
         SET leader_id = NULL, updated_at = GETDATE()
         WHERE id = ?",
        [$teamId]
    );

    return $clearTeamStmt !== false;
}

function clear_user_as_team_leader($conn, $userId, $exceptTeamId = null)
{
    $userId = (int)$userId;

    if ($userId <= 0) {
        return false;
    }

    $sql = "
        UPDATE teams
        SET leader_id = NULL, updated_at = GETDATE()
        WHERE leader_id = ?
    ";
    $params = [$userId];

    if ($exceptTeamId !== null) {
        $sql .= " AND id <> ?";
        $params[] = (int)$exceptTeamId;
    }

    return sqlsrv_query($conn, $sql, $params) !== false;
}

function assign_leader_to_team($conn, $leaderId, $teamId)
{
    $leaderId = (int)$leaderId;
    $teamId = (int)$teamId;

    if ($leaderId <= 0 || $teamId <= 0) {
        return false;
    }

    if (!clear_user_as_team_leader($conn, $leaderId, $teamId)) {
        return false;
    }

    $clearOtherLeadersStmt = sqlsrv_query(
        $conn,
        "UPDATE users
         SET team_id = NULL, updated_at = GETDATE()
         WHERE team_id = ? AND role = 'leader' AND id <> ?",
        [$teamId, $leaderId]
    );

    if ($clearOtherLeadersStmt === false) {
        return false;
    }

    $assignTeamStmt = sqlsrv_query(
        $conn,
        "UPDATE teams
         SET leader_id = ?, updated_at = GETDATE()
         WHERE id = ?",
        [$leaderId, $teamId]
    );

    if ($assignTeamStmt === false) {
        return false;
    }

    $assignUserStmt = sqlsrv_query(
        $conn,
        "UPDATE users
         SET team_id = ?, updated_at = GETDATE()
         WHERE id = ? AND role = 'leader'",
        [$teamId, $leaderId]
    );

    return $assignUserStmt !== false;
}

function redirect_by_role($role)
{
    switch ($role) {
        case 'admin':
            redirect(base_url('/admin/dashboard.php'));
            break;
        case 'leader':
            redirect(base_url('/leader/dashboard.php'));
            break;
        case 'member':
            redirect(base_url('/member/dashboard.php'));
            break;
        default:
            redirect(base_url('/auth/login.php'));
    }
}

function set_flash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function is_active_menu($key, $activeMenu = '')
{
    return $key === $activeMenu ? 'active' : '';
}

function format_datetime($value)
{
    if ($value instanceof DateTime) {
        return $value->format('d/m/Y H:i');
    }

    if (is_string($value) && strtotime($value)) {
        return date('d/m/Y H:i', strtotime($value));
    }

    return '';
}
function format_date($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y');
    }

    if (is_string($value) && strtotime($value)) {
        return date('d/m/Y', strtotime($value));
    }

    return '';
}

function is_task_overdue($status, $dueDate)
{
    if ($status === 'completed') {
        return false;
    }

    if ($dueDate instanceof DateTimeInterface) {
        $today = new DateTime('today');
        $due = new DateTime($dueDate->format('Y-m-d'));
        return $due < $today;
    }

    if (is_string($dueDate) && strtotime($dueDate)) {
        return strtotime(date('Y-m-d', strtotime($dueDate))) < strtotime(date('Y-m-d'));
    }

    return false;
}

function task_status_text($status, $dueDate = null)
{
    if ($dueDate !== null && is_task_overdue($status, $dueDate)) {
        return 'Quá hạn';
    }

    $map = [
        'not_started' => 'Chưa bắt đầu',
        'in_progress' => 'Đang thực hiện',
        'submitted'   => 'Đã nộp',
        'completed'   => 'Hoàn thành'
    ];

    return $map[$status] ?? $status;
}

function task_status_badge($status, $dueDate = null)
{
    if ($dueDate !== null && is_task_overdue($status, $dueDate)) {
        return 'text-bg-danger';
    }

    $map = [
        'not_started' => 'text-bg-secondary',
        'in_progress' => 'text-bg-primary',
        'submitted'   => 'text-bg-warning',
        'completed'   => 'text-bg-success'
    ];

    return $map[$status] ?? 'text-bg-secondary';
}

function user_status_text($status)
{
    $map = [
        'active' => 'Hoạt động',
        'inactive' => 'Ngưng hoạt động',
    ];

    return $map[$status] ?? $status;
}

function user_status_badge($status)
{
    $map = [
        'active' => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
    ];

    return $map[$status] ?? 'text-bg-secondary';
}

function priority_text($priority)
{
    $map = [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao'
    ];

    return $map[$priority] ?? $priority;
}

function priority_badge($priority)
{
    $map = [
        'low' => 'text-bg-secondary',
        'medium' => 'text-bg-info',
        'high' => 'text-bg-danger'
    ];

    return $map[$priority] ?? 'text-bg-secondary';
}
function review_status_text($status)
{
    $map = [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối'
    ];

    return $map[$status] ?? $status;
}

function review_status_badge($status)
{
    $map = [
        'pending' => 'text-bg-warning',
        'approved' => 'text-bg-success',
        'rejected' => 'text-bg-danger'
    ];

    return $map[$status] ?? 'text-bg-secondary';
}

function activity_action_text($actionType)
{
    $map = [
        'login' => 'Đăng nhập',
        'logout' => 'Đăng xuất',
        'create' => 'Tạo mới',
        'update' => 'Cập nhật',
        'delete' => 'Xóa',
        'deactivate' => 'Ngưng hoạt động',
        'update_progress' => 'Cập nhật tiến độ',
        'submit' => 'Nộp bài',
        'comment' => 'Bình luận',
        'approve_submission' => 'Duyệt bài nộp',
        'reject_submission' => 'Từ chối bài nộp',
    ];

    return $map[$actionType] ?? $actionType;
}

function entity_type_text($entityType)
{
    $map = [
        'auth' => 'Xác thực',
        'user' => 'Người dùng',
        'team' => 'Nhóm',
        'task' => 'Công việc',
        'submission' => 'Bài nộp',
    ];

    return $map[$entityType] ?? $entityType;
}

function is_task_due_soon($status, $dueDate, $days = 2)
{
    if ($status === 'completed') {
        return false;
    }

    if ($dueDate instanceof DateTimeInterface) {
        $today = new DateTime('today');
        $due = new DateTime($dueDate->format('Y-m-d'));
        $diff = (int)$today->diff($due)->format('%r%a');
        return $diff >= 0 && $diff <= $days;
    }

    if (is_string($dueDate) && strtotime($dueDate)) {
        $todayTs = strtotime(date('Y-m-d'));
        $dueTs = strtotime(date('Y-m-d', strtotime($dueDate)));
        $diffDays = (int)(($dueTs - $todayTs) / 86400);
        return $diffDays >= 0 && $diffDays <= $days;
    }

    return false;
}

function task_due_hint_text($status, $dueDate)
{
    if (is_task_overdue($status, $dueDate)) {
        return 'Quá hạn';
    }

    if (is_task_due_soon($status, $dueDate)) {
        return 'Sắp đến hạn';
    }

    return '';
}

function task_due_hint_badge($status, $dueDate)
{
    if (is_task_overdue($status, $dueDate)) {
        return 'text-bg-danger';
    }

    if (is_task_due_soon($status, $dueDate)) {
        return 'text-bg-warning';
    }

    return '';
}
function log_activity($conn, $userId, $actionType, $entityType, $entityId, $description)
{
    $sql = "
        INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, description)
        VALUES (?, ?, ?, ?, ?)
    ";

    $params = [
        (int)$userId,
        $actionType,
        $entityType,
        (int)$entityId,
        $description
    ];

    return sqlsrv_query($conn, $sql, $params);
}
function create_notification($conn, $userId, $title, $content, $type = 'info', $referenceType = null, $referenceId = null)
{
    $sql = "
        INSERT INTO notifications (user_id, title, content, type, reference_type, reference_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    return sqlsrv_query($conn, $sql, [
        (int)$userId,
        $title,
        $content,
        $type,
        $referenceType,
        $referenceId
    ]);
}

function count_unread_notifications($conn, $userId)
{
    $stmt = sqlsrv_query(
        $conn,
        "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0",
        [(int)$userId]
    );

    if ($stmt === false) {
        return 0;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

function get_recent_notifications($conn, $userId, $limit = 8)
{
    $data = [];

    $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP {$limit} *
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC",
        [(int)$userId]
    );

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }

    return $data;
}

function notification_target_url($notification, $role)
{
    $referenceType = $notification['reference_type'] ?? null;
    $referenceId = (int)($notification['reference_id'] ?? 0);
    $type = $notification['type'] ?? null;

    if ($referenceType === 'task' && $referenceId > 0) {
        if ($role === 'leader') {
            return base_url('/leader/tasks/detail.php?id=' . $referenceId);
        }

        if ($role === 'member') {
            return base_url('/member/tasks/detail.php?id=' . $referenceId);
        }

        return base_url('/admin/tasks/index.php');
    }

    if ($referenceType === 'submission' && $referenceId > 0) {
        if ($role === 'leader') {
            return base_url('/leader/submissions/review.php?id=' . $referenceId);
        }

        if ($role === 'member') {
            return base_url('/member/submissions/index.php');
        }
    }

    if (in_array($type, ['new_task', 'reassign_task', 'deadline_soon', 'submission_approved', 'submission_rejected'], true)) {
        if ($role === 'leader') {
            return base_url('/leader/tasks/index.php');
        }

        if ($role === 'member') {
            return base_url('/member/tasks/index.php');
        }

        return base_url('/admin/tasks/index.php');
    }

    if ($type === 'new_submission') {
        if ($role === 'leader') {
            return base_url('/leader/submissions/index.php');
        }

        if ($role === 'member') {
            return base_url('/member/submissions/index.php');
        }
    }

    return base_url('/profile.php');
}

function submission_download_url($submissionId)
{
    return base_url('/submissions/download.php?id=' . (int)$submissionId);
}

function get_page_number($key = 'page', $default = 1)
{
    $page = (int)($_GET[$key] ?? $default);
    return $page > 0 ? $page : (int)$default;
}

function current_path()
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : ($_SERVER['PHP_SELF'] ?? '');
}

function current_url_with_query(array $overrides = [], array $remove = [])
{
    $query = $_GET;

    foreach ($remove as $key) {
        unset($query[$key]);
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }

        $query[$key] = $value;
    }

    $path = current_path();
    $queryString = http_build_query($query);

    return $queryString !== '' ? $path . '?' . $queryString : $path;
}

function paginate_sqlsrv($conn, $baseSql, array $params, $orderBy, $page = 1, $perPage = 10)
{
    $page = max(1, (int)$page);
    $perPage = max(1, (int)$perPage);
    $total = 0;

    $countStmt = sqlsrv_query(
        $conn,
        "SELECT COUNT(*) AS total FROM (" . $baseSql . ") AS pagination_source",
        $params
    );

    if ($countStmt !== false) {
        $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
        $total = (int)($countRow['total'] ?? 0);
    }

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $items = [];
    $dataSql = "
        SELECT *
        FROM (" . $baseSql . ") AS pagination_source
        ORDER BY " . $orderBy . "
        OFFSET " . $offset . " ROWS
        FETCH NEXT " . $perPage . " ROWS ONLY
    ";

    $stmt = sqlsrv_query($conn, $dataSql, $params);

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $items[] = $row;
        }
    }

    return [
        'items' => $items,
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'from' => $total > 0 ? ($offset + 1) : 0,
        'to' => $total > 0 ? ($offset + count($items)) : 0,
    ];
}

function render_pagination($pagination, $itemLabel = 'bản ghi')
{
    $total = (int)($pagination['total'] ?? 0);
    $currentPage = (int)($pagination['current_page'] ?? 1);
    $totalPages = (int)($pagination['total_pages'] ?? 1);

    if ($total <= 0) {
        return '';
    }

    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    $html = '<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">';
    $html .= '<div class="text-muted small">Hiển thị ' . (int)$pagination['from'] . '-' . (int)$pagination['to'] . ' / ' . $total . ' ' . e($itemLabel) . '</div>';

    if ($totalPages <= 1) {
        $html .= '</div>';
        return $html;
    }

    $html .= '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';

    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e(current_url_with_query(['page' => $currentPage - 1], ['page'])) . '">Trước</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Trước</span></li>';
    }

    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e(current_url_with_query(['page' => 1], ['page'])) . '">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++) {
        if ($pageNumber === $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $pageNumber . '</span></li>';
            continue;
        }

        $html .= '<li class="page-item"><a class="page-link" href="' . e(current_url_with_query(['page' => $pageNumber], ['page'])) . '">' . $pageNumber . '</a></li>';
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . e(current_url_with_query(['page' => $totalPages], ['page'])) . '">' . $totalPages . '</a></li>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e(current_url_with_query(['page' => $currentPage + 1], ['page'])) . '">Sau</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Sau</span></li>';
    }

    $html .= '</ul></nav></div>';

    return $html;
}

function task_detail_url_for_role($role, $taskId)
{
    $taskId = (int)$taskId;

    if ($taskId <= 0) {
        return base_url('/');
    }

    switch ($role) {
        case 'admin':
            return base_url('/admin/tasks/detail.php?id=' . $taskId);
        case 'leader':
            return base_url('/leader/tasks/detail.php?id=' . $taskId);
        case 'member':
            return base_url('/member/tasks/detail.php?id=' . $taskId);
        default:
            return base_url('/');
    }
}

function get_accessible_task($conn, $taskId, $user)
{
    $taskId = (int)$taskId;

    if ($taskId <= 0 || !$user || empty($user['role'])) {
        return null;
    }

    $sql = "
        SELECT t.id, t.title, t.team_id, t.assigned_to
        FROM tasks t
        WHERE t.id = ?
    ";
    $params = [$taskId];

    if ($user['role'] === 'leader') {
        $teamId = (int)($user['team_id'] ?? 0);

        if ($teamId <= 0) {
            return null;
        }

        $sql .= " AND t.team_id = ?";
        $params[] = $teamId;
    } elseif ($user['role'] === 'member') {
        $sql .= " AND t.assigned_to = ?";
        $params[] = (int)$user['id'];
    } elseif ($user['role'] !== 'admin') {
        return null;
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    return $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
}

function sqlsrv_table_exists($conn, $tableName)
{
    $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP 1 1 AS exists_flag
         FROM sys.tables
         WHERE name = ? AND schema_id = SCHEMA_ID('dbo')",
        [$tableName]
    );

    if ($stmt === false) {
        return false;
    }

    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) !== null;
}

function sqlsrv_column_exists($conn, $tableName, $columnName)
{
    $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP 1 1 AS exists_flag
         FROM sys.columns c
         INNER JOIN sys.tables t ON c.object_id = t.object_id
         INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
         WHERE t.name = ? AND s.name = 'dbo' AND c.name = ?",
        [$tableName, $columnName]
    );

    if ($stmt === false) {
        return false;
    }

    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) !== null;
}

function ensure_task_comments_schema($conn)
{
    if (sqlsrv_table_exists($conn, 'task_comments')) {
        return true;
    }

    $createTableStmt = sqlsrv_query(
        $conn,
        "CREATE TABLE task_comments (
            id INT IDENTITY(1,1) PRIMARY KEY,
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            comment_text NVARCHAR(MAX) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            updated_at DATETIME NULL
        )"
    );

    if ($createTableStmt === false) {
        return false;
    }

    $createIndexStmt = sqlsrv_query(
        $conn,
        "CREATE INDEX IX_task_comments_task_created_at
         ON task_comments (task_id, created_at DESC, id DESC)"
    );

    return $createIndexStmt !== false;
}

function ensure_submission_versioning_schema($conn)
{
    if (!sqlsrv_column_exists($conn, 'submissions', 'version_no')) {
        $addVersionStmt = sqlsrv_query($conn, "ALTER TABLE submissions ADD version_no INT NULL");

        if ($addVersionStmt === false) {
            return false;
        }
    }

    if (!sqlsrv_column_exists($conn, 'submissions', 'is_latest')) {
        $addLatestStmt = sqlsrv_query($conn, "ALTER TABLE submissions ADD is_latest BIT NULL");

        if ($addLatestStmt === false) {
            return false;
        }
    }

    $backfillStmt = sqlsrv_query(
        $conn,
        "WITH numbered AS (
            SELECT
                id,
                ROW_NUMBER() OVER (PARTITION BY task_id, submitted_by ORDER BY submitted_at ASC, id ASC) AS version_no,
                CASE
                    WHEN ROW_NUMBER() OVER (PARTITION BY task_id, submitted_by ORDER BY submitted_at DESC, id DESC) = 1 THEN 1
                    ELSE 0
                END AS is_latest
            FROM submissions
        )
        UPDATE s
        SET
            s.version_no = COALESCE(s.version_no, n.version_no),
            s.is_latest = COALESCE(s.is_latest, n.is_latest)
        FROM submissions s
        INNER JOIN numbered n ON n.id = s.id
        WHERE s.version_no IS NULL OR s.is_latest IS NULL"
    );

    if ($backfillStmt === false) {
        return false;
    }

    return true;
}

function ensure_task_history_schema($conn)
{
    if (sqlsrv_table_exists($conn, 'task_history')) {
        return true;
    }

    $createTableStmt = sqlsrv_query(
        $conn,
        "CREATE TABLE task_history (
            id INT IDENTITY(1,1) PRIMARY KEY,
            task_id INT NOT NULL,
            user_id INT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_title NVARCHAR(255) NOT NULL,
            event_description NVARCHAR(MAX) NULL,
            created_at DATETIME NOT NULL DEFAULT GETDATE()
        )"
    );

    if ($createTableStmt === false) {
        return false;
    }

    $createIndexStmt = sqlsrv_query(
        $conn,
        "CREATE INDEX IX_task_history_task_created_at
         ON task_history (task_id, created_at DESC, id DESC)"
    );

    return $createIndexStmt !== false;
}

function ensure_project_feature_schema($conn)
{
    static $ready = false;

    if ($ready) {
        return true;
    }

    if (!ensure_task_comments_schema($conn)) {
        return false;
    }

    if (!ensure_submission_versioning_schema($conn)) {
        return false;
    }

    if (!ensure_task_history_schema($conn)) {
        return false;
    }

    $ready = true;
    return true;
}

function submission_version_text($versionNo)
{
    $version = max(1, (int)$versionNo);
    return 'V' . $version;
}

function is_latest_submission($submission)
{
    return (int)($submission['is_latest'] ?? 0) === 1;
}

function task_comment_role_badge($role)
{
    $map = [
        'admin' => 'text-bg-dark',
        'leader' => 'text-bg-primary',
        'member' => 'text-bg-success',
    ];

    return $map[$role] ?? 'text-bg-secondary';
}

function get_task_comments($conn, $taskId)
{
    $taskId = (int)$taskId;

    if ($taskId <= 0) {
        return [];
    }

    if (!ensure_task_comments_schema($conn)) {
        return [];
    }

    $comments = [];
    $stmt = sqlsrv_query(
        $conn,
        "SELECT tc.id, tc.comment_text, tc.created_at, u.full_name, u.role
         FROM task_comments tc
         INNER JOIN users u ON tc.user_id = u.id
         WHERE tc.task_id = ?
         ORDER BY tc.created_at DESC, tc.id DESC",
        [$taskId]
    );

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $comments[] = $row;
        }
    }

    return $comments;
}

function task_history_badge($eventType)
{
    $map = [
        'created' => 'text-bg-primary',
        'updated' => 'text-bg-info',
        'progress' => 'text-bg-secondary',
        'submission' => 'text-bg-warning',
        'review_approved' => 'text-bg-success',
        'review_rejected' => 'text-bg-danger',
    ];

    return $map[$eventType] ?? 'text-bg-dark';
}

function task_history_text($eventType)
{
    $map = [
        'created' => 'Tạo mới',
        'updated' => 'Cập nhật',
        'progress' => 'Tiến độ',
        'submission' => 'Bài nộp',
        'review_approved' => 'Đã duyệt',
        'review_rejected' => 'Từ chối',
    ];

    return $map[$eventType] ?? $eventType;
}

function record_task_history($conn, $taskId, $userId, $eventType, $eventTitle, $eventDescription = null)
{
    $taskId = (int)$taskId;

    if ($taskId <= 0 || !ensure_task_history_schema($conn)) {
        return false;
    }

    return sqlsrv_query(
        $conn,
        "INSERT INTO task_history (task_id, user_id, event_type, event_title, event_description, created_at)
         VALUES (?, ?, ?, ?, ?, GETDATE())",
        [
            $taskId,
            $userId !== null ? (int)$userId : null,
            $eventType,
            $eventTitle,
            $eventDescription,
        ]
    ) !== false;
}

function get_task_history($conn, $taskId, $limit = 12)
{
    $taskId = (int)$taskId;
    $limit = max(1, (int)$limit);

    if ($taskId <= 0 || !ensure_task_history_schema($conn)) {
        return [];
    }

    $items = [];
    $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP {$limit}
            th.id,
            th.event_type,
            th.event_title,
            th.event_description,
            th.created_at,
            u.full_name AS actor_name,
            u.role AS actor_role
         FROM task_history th
         LEFT JOIN users u ON th.user_id = u.id
         WHERE th.task_id = ?
         ORDER BY th.created_at DESC, th.id DESC",
        [$taskId]
    );

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $items[] = $row;
        }
    }

    return $items;
}

function ensure_due_soon_notifications($conn, $user)
{
    if (!$user || !isset($user['id'], $user['role'])) {
        return;
    }

    $userId = (int)$user['id'];

    if ($user['role'] !== 'member') {
        return;
    }

    $sql = "
        SELECT id, title, due_date
        FROM tasks
        WHERE assigned_to = ?
          AND status <> 'completed'
          AND due_date >= CAST(GETDATE() AS DATE)
          AND due_date <= DATEADD(DAY, 2, CAST(GETDATE() AS DATE))
    ";

    $stmt = sqlsrv_query($conn, $sql, [$userId]);

    if ($stmt === false) {
        return;
    }

    while ($task = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $taskId = (int)$task['id'];

        $checkStmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 id
             FROM notifications
             WHERE user_id = ?
               AND type = 'deadline_soon'
               AND reference_type = 'task'
               AND reference_id = ?
               AND CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)",
            [$userId, $taskId]
        );

        $exists = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;

        if (!$exists) {
            create_notification(
                $conn,
                $userId,
                'Công việc sắp đến hạn',
                'Công việc "' . $task['title'] . '" sắp đến hạn.',
                'deadline_soon',
                'task',
                $taskId
            );
        }
    }
}
