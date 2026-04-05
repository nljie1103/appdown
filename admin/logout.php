<?php
require_once __DIR__ . '/../includes/init.php';
do_logout();
header('Location: /admin/login.php');
exit;
