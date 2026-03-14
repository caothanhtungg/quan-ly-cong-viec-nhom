<?php
$user = current_user();
$role = $user['role'] ?? '';
$activeMenu = $activeMenu ?? '';
$navTitle = 'Workspace';

if ($role === 'admin') {
    $navTitle = 'Admin panel';
} elseif ($role === 'leader') {
    $navTitle = 'Leader tools';
} elseif ($role === 'member') {
    $navTitle = 'Member area';
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
            <span class="sidebar-brand-mark">TM</span>
            <span>
                <span class="sidebar-brand-title d-block">Task Manager</span>
                <small class="sidebar-brand-subtitle">Team workflow</small>
            </span>
        </a>
        <button type="button" class="sidebar-close d-lg-none js-sidebar-close" aria-label="Close navigation">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-nav-title"><?= e($navTitle) ?></div>

    <?php if ($role === 'admin'): ?>
        <ul class="nav flex-column sidebar-menu">
            <?php render_sidebar_item(base_url('/admin/dashboard.php'), 'admin_dashboard', 'Dashboard', 'bi-grid-1x2-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/users/index.php'), 'admin_users', 'Quan ly nguoi dung', 'bi-people-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/teams/index.php'), 'admin_teams', 'Quan ly nhom', 'bi-diagram-3-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/tasks/index.php'), 'admin_tasks', 'Quan ly cong viec', 'bi-kanban-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/admin/logs/index.php'), 'admin_logs', 'Nhat ky hoat dong', 'bi-clock-history', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/profile.php'), 'profile', 'Ho so ca nhan', 'bi-person-badge-fill', $activeMenu); ?>
        </ul>
    <?php elseif ($role === 'leader'): ?>
        <ul class="nav flex-column sidebar-menu">
            <?php render_sidebar_item(base_url('/leader/dashboard.php'), 'leader_dashboard', 'Dashboard', 'bi-speedometer2', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/leader/team.php'), 'leader_team', 'Nhom cua toi', 'bi-people-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/leader/tasks/index.php'), 'leader_tasks', 'Quan ly cong viec', 'bi-list-check', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/leader/submissions/index.php'), 'leader_submissions', 'Bai nop', 'bi-inbox-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/profile.php'), 'profile', 'Ho so ca nhan', 'bi-person-badge-fill', $activeMenu); ?>
        </ul>
    <?php elseif ($role === 'member'): ?>
        <ul class="nav flex-column sidebar-menu">
            <?php render_sidebar_item(base_url('/member/dashboard.php'), 'member_dashboard', 'Dashboard', 'bi-grid-fill', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/member/tasks/index.php'), 'member_tasks', 'Cong viec cua toi', 'bi-check2-square', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/member/submissions/index.php'), 'member_submissions', 'Bai nop cua toi', 'bi-folder2-open', $activeMenu); ?>
            <?php render_sidebar_item(base_url('/profile.php'), 'profile', 'Ho so ca nhan', 'bi-person-badge-fill', $activeMenu); ?>
        </ul>
    <?php endif; ?>
</aside>
