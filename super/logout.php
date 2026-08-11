<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/super_auth.php';
super_logout();
header('Location: /super/login.php');
exit;
