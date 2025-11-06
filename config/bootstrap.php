<?php
$config = require __DIR__ . '/config.php';
session_name($config['SESSION_NAME']);
session_start();

spl_autoload_register(function($class){
  $class = str_replace('\\', '/', $class);
  $paths = array(
    __DIR__ . '/../src/' . $class . '.php',
    __DIR__ . '/../src/Controllers/' . $class . '.php',
    __DIR__ . '/../src/DAO/' . $class . '.php',
    __DIR__ . '/../src/Services/' . $class . '.php',
  );
  foreach($paths as $p){ if(file_exists($p)){ require $p; return; } }
});

$pdo = new PDO('sqlite:' . $config['DB_PATH']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$migFlag = __DIR__ . '/../database/migrations_applied';
if (!file_exists($migFlag)) {
  $sql = file_get_contents(__DIR__ . '/../database/migrations/001_init.sql');
  $pdo->exec($sql);
  file_put_contents($migFlag, date('c'));
}

// Apply 002 features migration if not applied
$mig002 = __DIR__ . '/../database/migrations_002_applied';
if (!file_exists($mig002) && file_exists(__DIR__ . '/../database/migrations/002_features.sql')) {
  $sql2 = file_get_contents(__DIR__ . '/../database/migrations/002_features.sql');
  $pdo->exec($sql2);
  file_put_contents($mig002, date('c'));
}