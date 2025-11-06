<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$checks = [];

function ok($name, $cond){ echo ($cond ? "OK" : "FAIL") . " - $name\n"; }

ok('config.php', file_exists(__DIR__ . '/config/config.php'));
ok('bootstrap.php', file_exists(__DIR__ . '/config/bootstrap.php'));
ok('Router.php', file_exists(__DIR__ . '/src/Router.php'));
ok('Auth.php', file_exists(__DIR__ . '/src/Auth.php'));
ok('Utils.php', file_exists(__DIR__ . '/src/Utils.php'));
ok('Controllers/AuthController', file_exists(__DIR__ . '/src/Controllers/AuthController.php'));
ok('Controllers/DashboardController', file_exists(__DIR__ . '/src/Controllers/DashboardController.php'));
ok('Controllers/CustomersController', file_exists(__DIR__ . '/src/Controllers/CustomersController.php'));
ok('Controllers/TicketsController', file_exists(__DIR__ . '/src/Controllers/TicketsController.php'));
ok('Controllers/ReportsController', file_exists(__DIR__ . '/src/Controllers/ReportsController.php'));
ok('Controllers/KnowledgeController', file_exists(__DIR__ . '/src/Controllers/KnowledgeController.php'));
ok('Controllers/UsersController', file_exists(__DIR__ . '/src/Controllers/UsersController.php'));
ok('views/layout.php', file_exists(__DIR__ . '/views/layout.php'));
ok('public/index.php', file_exists(__DIR__ . '/public/index.php'));

$cfg = require __DIR__ . '/config/config.php';
echo "APP_URL = " . $cfg['APP_URL'] . "\n";
echo "DB_PATH writable dir = " . (is_writable(dirname($cfg['DB_PATH'])) ? 'YES' : 'NO') . "\n";
echo "Uploads writable = " . (is_writable($cfg['UPLOAD_DIR']) ? 'YES' : 'NO') . "\n";
echo "Migrations present 001 = " . (file_exists(__DIR__ . '/database/migrations/001_init.sql') ? 'YES' : 'NO') . "\n";
echo "Migrations present 002 = " . (file_exists(__DIR__ . '/database/migrations/002_features.sql') ? 'YES' : 'NO') . "\n";

echo "Done.\n";