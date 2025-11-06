<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/src/DAO/UsersDAO.php';

try {
  $cfg = require __DIR__ . '/config/config.php';

  // Check DB directory writable
  $dbDir = dirname($cfg['DB_PATH']);
  if (!is_dir($dbDir)) {
    if (!mkdir($dbDir, 0775, true)) {
      throw new RuntimeException("Failed to create database directory: $dbDir");
    }
  }
  if (!is_writable($dbDir)) {
    throw new RuntimeException("Database directory not writable: $dbDir");
  }

  // Touch DB file if missing
  if (!file_exists($cfg['DB_PATH'])) {
    if (false === @touch($cfg['DB_PATH'])) {
      throw new RuntimeException("Cannot create DB file: " . $cfg['DB_PATH']);
    }
  }

  // Run migrations if not done
  $migFlag = __DIR__ . '/database/migrations_applied';
  if (!file_exists($migFlag)) {
    $sql = file_get_contents(__DIR__ . '/database/migrations/001_init.sql');
    $pdo->exec($sql);
    file_put_contents($migFlag, date('c'));
  }

  $dao = new UsersDAO($pdo);
  $u = $dao->findByUsername('admin');
  if(!$u){
    $dao->create('admin','StrongPass!','support_engineer');
    echo "Admin user created: admin / StrongPass! (change immediately)\n";
  } else {
    echo "Admin already exists.\n";
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo "Error: " . $e->getMessage();
}