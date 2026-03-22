<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect_by_role(current_user()['role']);
} else {
    redirect(base_url('/auth/login.php'));
}
