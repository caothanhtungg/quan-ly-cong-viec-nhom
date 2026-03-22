<?php
$user = current_user();
$role = $user['role'] ?? '';
$activeMenu = $activeMenu ?? '';
$navTitle = 'Khu làm việc';

if ($role === 'admin') {
    $navTitle = 'Bảng quản trị';
} elseif ($role === 'leader') {
    $navTitle = 'Công cụ trưởng nhóm';
} elseif ($role === 'member') {
    $navTitle = 'Khu vực thành viên';
}

if (!function_exists('render_sidebar_item')) {
    function render_sidebar_item($href, $key, $label, $icon, $activeMenu)
    {
        ?>
        <li class="nav-item">
            <a class="nav-link <?= is_active_menu($key, $activeMenu) ?>" href="<?= e($href) ?>">
                <span class="sidebar-link-icon"><i class="bi <?= e($icon) ?>"></i></span>
                <span class="sidebar-link-label"><?= e($label) ?></span>
            </a>
        </li>
        <?php
    }
}
?>

<aside class="sidebar">
    <div class="sidebar-brand-row">
        <a class="sidebar-brand text-decoration-none" href="<?= e(base_url('/')) ?>">
            <img
                src="<?= e(asset_url('/assets/img/hhcc-mark.svg')) ?>"
                alt="Logo HHCC"
                class="sidebar-brand-logo"
            >
            <span class="sidebar-brand-copy">
                <span class="sidebar-brand-title d-block">HHCC</span>
                <small class="sidebar-brand-subtitle">Task Management</small>
            </span>
        </a>
        <button type="button" class="sidebar-close d-lg-none js-sidebar-close" aria-label="Đóng điều hướng">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-nav-title"><?= e($navTitle) ?></div>

    <?php if ($role === 'admin'): ?>
        <ul class="nav flex-column sidebar-menu">
            <?php render_sidebar_item(base_url('/admin/dashboard.php'), 'admin_dashboard', 'Bảng điều khiển', 'bi-grid-1x2-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/users/index.php'), 'admin_users', 'Quản lý người dùng', 'bi-people-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/teams/index.php'), 'admin_teams', 'Quản lý nhóm', 'bi-diagram-3-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/tasks/index.php'), 'admin_tasks', 'Quản lý công việc', 'bi-kanban-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/logs/index.php'), 'admin_logs', 'Nhật ký hoạt động', 'bi-clock-history', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/profile.php'), 'profile', 'Hồ sơ cá nhân', 'bi-person-badge-fill', $activeMenu); ?>
        </ul>
    <?php elseif ($role === 'leader'): ?>
        <ul class="nav flex-column sidebar-menu">
            <?php render_sidebar_item(base_url('/leader/dashboard.php'), 'leader_dashboard', 'Bảng điều khiển', 'bi-speedometer2', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/leader/team.php'), 'leader_team', 'Nhóm của tôi', 'bi-people-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/leader/tasks/index.php'), 'leader_tasks', 'Quản lý công việc', 'bi-list-check', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/leader/submissions/index.php'), 'leader_submissions', 'Bài nộp', 'bi-inbox-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/profile.php'), 'profile', 'Hồ sơ cá nhân', 'bi-person-badge-fill', $activeMenu); ?>
        </ul>
    <?php elseif ($role === 'member'): ?>
        <ul class="nav flex-column sidebar-menu">
            <?php render_sidebar_item(base_url('/member/dashboard.php'), 'member_dashboard', 'Bảng điều khiển', 'bi-grid-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/member/tasks/index.php'), 'member_tasks', 'Công việc của tôi', 'bi-check2-square', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/member/submissions/index.php'), 'member_submissions', 'Bài nộp của tôi', 'bi-folder2-open', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/profile.php'), 'profile', 'Hồ sơ cá nhân', 'bi-person-badge-fill', $activeMenu); ?>
        </ul>
    <?php endif; ?>
</aside>
