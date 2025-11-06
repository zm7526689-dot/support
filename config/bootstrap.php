<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('APP_ROOT', dirname(__DIR__));
define('DB_PATH', APP_ROOT . '/database/supportops_final.db');

spl_autoload_register(function($class){
  $paths = [APP_ROOT.'/src/Controllers/', APP_ROOT.'/src/DAO/', APP_ROOT.'/src/'];
  foreach($paths as $p){
    $f = $p . $class . '.php';
    if(file_exists($f)) require_once $f;
  }
});

require_once APP_ROOT . '/config/config.php';

$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
