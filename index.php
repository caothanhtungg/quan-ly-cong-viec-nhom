<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect_by_role(current_user()['role']);
} else {
    redirect('/task_management/auth/login.php');
}