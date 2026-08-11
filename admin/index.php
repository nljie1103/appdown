<?php
require_once __DIR__ . '/../includes/init.php';
require_auth();
header('Location: /admin/app.php#/dashboard');
exit;
