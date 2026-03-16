<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Tạo nhóm';
$activeMenu = 'admin_teams';
$conn = getConnection();

function getAvailableLeaders($conn)
{
    $data = [];
    $sql = "
        SELECT id, full_name
        FROM users
        WHERE role = 'leader' AND status = 'active'
        ORDER BY full_name ASC
    ";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
    return $data;
}

function getAvailableMembers($conn)
{
    $data = [];
    $sql = "
        SELECT id, full_name
        FROM users
        WHERE role = 'member' AND status = 'active'
        ORDER BY full_name ASC
    ";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
    return $data;
}

$leaders = getAvailableLeaders($conn);
$members = getAvailableMembers($conn);

$errors = [];
$formData = [
    'team_name' => '',
    'description' => '',
    'leader_id' => '',
    'member_ids' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    }

    $formData['team_name'] = trim($_POST['team_name'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['leader_id'] = trim($_POST['leader_id'] ?? '');
    $formData['member_ids'] = $_POST['member_ids'] ?? [];

    if ($formData['team_name'] === '') {
        $errors[] = 'Tên nhóm không được để trống.';
    }

    $checkNameStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM teams WHERE team_name = ?", [$formData['team_name']]);
    if ($checkNameStmt !== false && sqlsrv_fetch_array($checkNameStmt, SQLSRV_FETCH_ASSOC)) {
        $errors[] = 'Tên nhóm đã tồn tại.';
    }

    $leaderId = null;
    if ($formData['leader_id'] !== '') {
        $leaderId = (int)$formData['leader_id'];
        $checkLeaderStmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 id FROM users WHERE id = ? AND role = 'leader' AND status = 'active'",
            [$leaderId]
        );
        if (!$checkLeaderStmt || !sqlsrv_fetch_array($checkLeaderStmt, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Leader không hợp lệ.';
        }
    }

    $memberIds = array_map('intval', $formData['member_ids']);
    $memberIds = array_values(array_unique(array_filter($memberIds)));

    if (empty($errors)) {
        if (!sqlsrv_begin_transaction($conn)) {
            $errors[] = 'Không thể bắt đầu giao dịch.';
        } else {
            $ok = true;

            $insertTeamSql = "
                INSERT INTO teams (team_name, description, leader_id)
                OUTPUT INSERTED.id AS id
                VALUES (?, ?, NULL)
            ";
            $insertTeamStmt = sqlsrv_query($conn, $insertTeamSql, [
                $formData['team_name'],
                $formData['description'] !== '' ? $formData['description'] : null
            ]);

            if ($insertTeamStmt === false) {
                $ok = false;
            }

            $newTeamId = null;

            if ($ok) {
                $insertedRow = sqlsrv_fetch_array($insertTeamStmt, SQLSRV_FETCH_ASSOC);
                $newTeamId = (int)($insertedRow['id'] ?? 0);

                if ($newTeamId <= 0) {
                    $ok = false;
                }
            }

            if ($ok && $leaderId !== null) {
                $ok = assign_leader_to_team($conn, $leaderId, $newTeamId);
            }

            if ($ok && !empty($memberIds)) {
                foreach ($memberIds as $memberId) {
                    $stmt = sqlsrv_query(
                        $conn,
                        "UPDATE users SET team_id = ?, updated_at = GETDATE() WHERE id = ? AND role = 'member'",
                        [$newTeamId, $memberId]
                    );

                    if ($stmt === false) {
                        $ok = false;
                        break;
                    }
                }
            }

            if ($ok) {
                sqlsrv_commit($conn);

                log_activity(
                    $conn,
                    (int)current_user()['id'],
                    'create',
                    'team',
                    $newTeamId,
                    'Tạo nhóm mới: ' . $formData['team_name']
                );

                set_flash('success', 'Tạo nhóm thành công.');
                redirect(base_url('/admin/teams/index.php'));
            } else {
                sqlsrv_rollback($conn);
                $errors[] = 'Không thể tạo nhóm.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="page-content">
        <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

        <div class="app-page-head d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Tạo nhóm</h3>
                <p class="text-muted mb-0">Nhập thông tin nhóm, leader và thành viên</p>
            </div>
            <a href="<?= e(base_url('/admin/teams/index.php')) ?>" class="btn btn-outline-secondary">Quay lại</a>
        </div>

        <div class="card section-card app-form-shell">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="row g-3 app-form-grid">
                    <?= csrf_field() ?>
                    <div class="col-md-6">
                        <label class="form-label">Tên nhóm</label>
                        <input type="text" name="team_name" class="form-control" value="<?= e($formData['team_name']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Leader</label>
                        <select name="leader_id" class="form-select">
                            <option value="">Chọn leader</option>
                            <?php foreach ($leaders as $leader): ?>
                                <option value="<?= e($leader['id']) ?>" <?= $formData['leader_id'] == $leader['id'] ? 'selected' : '' ?>>
                                    <?= e($leader['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($formData['description']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Thành viên</label>
                        <select name="member_ids[]" class="form-select" multiple size="8">
                            <?php foreach ($members as $member): ?>
                                <option value="<?= e($member['id']) ?>" <?= in_array($member['id'], $formData['member_ids']) ? 'selected' : '' ?>>
                                    <?= e($member['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Giữ phím Ctrl để chọn nhiều thành viên.</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Lưu nhóm</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
