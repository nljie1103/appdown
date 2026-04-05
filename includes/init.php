<?php
/**
 * 总引导文件 - 所有PHP页面和API的入口
 */

date_default_timezone_set('Asia/Shanghai');

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload.php';
