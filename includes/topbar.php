<?php
require_once __DIR__ . '/../config/database.php';

$user = current_user();
$conn = getConnection();

ensure_due_soon_notifications($conn, $user);

$unreadCount = count_unread_notifications($conn, (int)$user['id']);
$notifications = get_recent_notifications($conn, (int)$user['id'], 8);

$currentUrl = $_SERVER['REQUEST_URI'] ?? base_url('/');
?>
<header class="topbar d-flex justify-content-between align-items-center gap-3 px-3 px-lg-4 py-3">
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="app-chrome-btn d-lg-none js-sidebar-toggle" aria-label="Mở điều hướng">
            <i class="bi bi-list"></i>
        </button>

        <div class="topbar-titles">
            <div class="topbar-title"><?= e(APP_NAME) ?></div>
            <small class="topbar-subtitle"><?= e($pageTitle ?? '') ?></small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 gap-lg-3">
        <div class="dropdown">
            <button class="app-chrome-btn position-relative" data-bs-toggle="dropdown">
                <i class="bi bi-bell-fill"></i>
                <span class="d-none d-md-inline">Thông báo</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">
                        <?= e($unreadCount) ?>
                    </span>
                <?php endif; ?>
            </button>

            <div class="dropdown-menu dropdown-menu-end p-0 notification-menu">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong>Thông báo</strong>
                    <form method="POST" action="<?= e(base_url('/notification/mark_all_read.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="<?= e($currentUrl) ?>">
                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Đánh dấu đã đọc</button>
                    </form>
                </div>

                <div class="notification-menu-body">
                    <?php if (empty($notifications)): ?>
                        <div class="px-3 py-3 text-muted">Chưa có thông báo nào.</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php $targetUrl = notification_target_url($notification, $user['role']); ?>
                            <form method="POST" action="<?= e(base_url('/notification/mark_read.php')) ?>" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($notification['id']) ?>">
                                <input type="hidden" name="target" value="<?= e($targetUrl) ?>">
                                <button type="submit"
                                        class="dropdown-item px-3 py-3 border-bottom text-start w-100 border-0 <?= (int)$notification['is_read'] === 0 ? 'bg-light' : 'bg-transparent' ?>">
                                    <div class="fw-semibold"><?= e($notification['title']) ?></div>
                                    <div class="small"><?= e($notification['content']) ?></div>
                                    <div class="small text-muted mt-1"><?= e(format_datetime($notification['created_at'])) ?></div>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="user-chip text-start">
            <span class="user-chip-icon"><i class="bi bi-person-circle"></i></span>
            <div class="user-chip-meta">
                <div class="fw-semibold"><?= e($user['full_name'] ?? '') ?></div>
                <small><?= e(strtoupper($user['role'] ?? '')) ?></small>
            </div>
        </div>

        <form method="POST" action="<?= e(base_url('/auth/logout.php')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm app-logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-md-inline">Đăng xuất</span>
            </button>
        </form>
    </div>
</header>
