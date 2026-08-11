<?php
/**
 * Legacy system page shim.
 * Environment management now lives in Admin 2.0 so there is only one source of truth
 * for Docker/KVM/macOS SSH/Xcode status and SSH host-key policy.
 */
require_once __DIR__ . '/../includes/init.php';
require_auth();

header('Location: /admin/app.php#/system', true, 302);
exit;
