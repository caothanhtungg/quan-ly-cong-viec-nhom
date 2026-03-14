<?php
$user = current_user();
$role = $user['role'] ?? '';
$activeMenu = $activeMenu ?? '';
?>

<aside class="sidebar bg-dark text-white p-3">
    <div class="sidebar-brand mb-4">
        <h4 class="mb-1">Task Manager</h4>
        <small class="text-light-emphasis">Localhost Project</small>
    </div>

    <?php if ($role === 'admin'): ?>
        <ul class="nav flex-column sidebar-menu">
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('admin_dashboard', $activeMenu) ?>" href="<?= e(base_url('/admin/dashboard.php')) ?>">
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('admin_users', $activeMenu) ?>" href="<?= e(base_url('/admin/users/index.php')) ?>">
                    Quản lý người dùng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('admin_teams', $activeMenu) ?>" href="<?= e(base_url('/admin/teams/index.php')) ?>">
                    Quản lý nhóm
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('admin_tasks', $activeMenu) ?>" href="<?= e(base_url('/admin/tasks/index.php')) ?>">
                    Quản lý công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('admin_logs', $activeMenu) ?>" href="<?= e(base_url('/admin/logs/index.php')) ?>">
                    Nhật ký hoạt động
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= e(base_url('/profile.php')) ?>">
                    Hồ sơ cá nhân
                </a>
            </li>
        </ul>
    <?php elseif ($role === 'leader'): ?>
        <ul class="nav flex-column sidebar-menu">
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('leader_dashboard', $activeMenu) ?>" href="<?= e(base_url('/leader/dashboard.php')) ?>">
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('leader_team', $activeMenu) ?>" href="<?= e(base_url('/leader/team.php')) ?>">
                    Nhóm của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('leader_tasks', $activeMenu) ?>" href="<?= e(base_url('/leader/tasks/index.php')) ?>">
                    Quản lý công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('leader_submissions', $activeMenu) ?>" href="<?= e(base_url('/leader/submissions/index.php')) ?>">
                    Bài nộp
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= e(base_url('/profile.php')) ?>">
                    Hồ sơ cá nhân
                </a>
            </li>
        </ul>
    <?php elseif ($role === 'member'): ?>
        <ul class="nav flex-column sidebar-menu">
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('member_dashboard', $activeMenu) ?>" href="<?= e(base_url('/member/dashboard.php')) ?>">
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('member_tasks', $activeMenu) ?>" href="<?= e(base_url('/member/tasks/index.php')) ?>">
                    Công việc của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= is_active_menu('member_submissions', $activeMenu) ?>" href="<?= e(base_url('/member/submissions/index.php')) ?>">
                    Bài nộp của tôi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= e(base_url('/profile.php')) ?>">
                    Hồ sơ cá nhân
                </a>
            </li>
        </ul>
    <?php endif; ?>
</aside>
