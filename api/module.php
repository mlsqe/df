<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) exit;
$module = $_GET['name'] ?? '';
$allowed = ['my_profile','my_orders','new_order','booster_orders','booster_progress','booster_invites','admin_orders','admin_boosters','admin_disputes','admin_types','admin_invites','admin_levels','admin_settings','admin_admins'];
if (in_array($module, $allowed)) {
    $file = __DIR__ . '/../includes/modules/' . $module . '.php';
    if (file_exists($file)) {
        include $file;
    }
}