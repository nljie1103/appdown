<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/super_auth.php';
header('Location: ' . (is_super_logged_in() ? '/super/dashboard.php' : '/super/login.php'));
exit;
