<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['admin']);

$pageTitle = 'Sửa nhóm';
$activeMenu = 'admin_teams';
$conn = getConnection();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Nhóm không hợp lệ.');
    redirect(base_url('/admin/teams/index.php'));
}

$teamStmt = sqlsrv_query($conn, "SELECT TOP 1 * FROM teams WHERE id = ?", [$id]);
$team = $teamStmt ? sqlsrv_fetch_array($teamStmt, SQLSRV_FETCH_ASSOC) : null;

if (!$team) {
    set_flash('danger', 'Không tìm thấy nhóm.');
    redirect(base_url('/admin/teams/index.php'));
}

function getLeadersForEdit($conn)
{
    $data = [];
    $sql = "SELECT id, full_name FROM users WHERE role = 'leader' AND status = 'active' ORDER BY full_name ASC";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
    return $data;
}

function getMembersForEdit($conn)
{
    $data = [];
    $sql = "SELECT id, full_name, team_id FROM users WHERE role = 'member' AND status = 'active' ORDER BY full_name ASC";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
    return $data;
}

$leaders = getLeadersForEdit($conn);
$members = getMembersForEdit($conn);

$currentMemberIds = [];
$currentMemberStmt = sqlsrv_query($conn, "SELECT id FROM users WHERE team_id = ? AND role = 'member'", [$id]);
if ($currentMemberStmt !== false) {
    while ($row = sqlsrv_fetch_array($currentMemberStmt, SQLSRV_FETCH_ASSOC)) {
        $currentMemberIds[] = (int)$row['id'];
    }
}

$errors = [];
$formData = [
    'team_name' => $team['team_name'],
    'description' => $team['description'] ?? '',
    'leader_id' => $team['leader_id'] ?? '',
    'member_ids' => $currentMemberIds
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

    $checkNameStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM teams WHERE team_name = ? AND id <> ?", [$formData['team_name'], $id]);
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

            $updateTeamStmt = sqlsrv_query(
                $conn,
                "UPDATE teams SET team_name = ?, description = ?, updated_at = GETDATE() WHERE id = ?",
                [
                    $formData['team_name'],
                    $formData['description'] !== '' ? $formData['description'] : null,
                    $id
                ]
            );

            if ($updateTeamStmt === false) {
                $ok = false;
            }

            if ($ok && $leaderId !== null) {
                $ok = assign_leader_to_team($conn, $leaderId, $id);
            }

            if ($ok && $leaderId === null) {
                $ok = clear_team_leader($conn, $id);
            }

            if ($ok) {
                $clearOldMembersStmt = sqlsrv_query(
                    $conn,
                    "UPDATE users SET team_id = NULL, updated_at = GETDATE() WHERE team_id = ? AND role = 'member'",
                    [$id]
                );

                if ($clearOldMembersStmt === false) {
                    $ok = false;
                }
            }

            if ($ok && !empty($memberIds)) {
                foreach ($memberIds as $memberId) {
                    $stmt = sqlsrv_query(
                        $conn,
                        "UPDATE users SET team_id = ?, updated_at = GETDATE() WHERE id = ? AND role = 'member'",
                        [$id, $memberId]
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
                    'update',
                    'team',
                    $id,
                    'Cập nhật nhóm: ' . $formData['team_name']
                );

                set_flash('success', 'Cập nhật nhóm thành công.');
                redirect(base_url('/admin/teams/index.php'));
            } else {
                sqlsrv_rollback($conn);
                $errors[] = 'Không thể cập nhật nhóm.';
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
                <h3 class="fw-bold mb-1">Sửa nhóm</h3>
                <p class="text-muted mb-0">Cập nhật thông tin nhóm, leader và thành viên</p>
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
                                <option value="<?= e($member['id']) ?>" <?= in_array((int)$member['id'], $formData['member_ids'], true) ? 'selected' : '' ?>>
                                    <?= e($member['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Giữ phím Ctrl để chọn nhiều thành viên.</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
