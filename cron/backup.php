<?php
$cfg = require __DIR__ . '/../config/config.php';
$dt = date('Ymd_His');
$db = $cfg['DB_PATH'];
$uploads = realpath($cfg['UPLOAD_DIR']);
$zipPath = $cfg['BACKUP_DIR'] . "/backup_{$dt}.zip";

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
  if(file_exists($db)) $zip->addFile($db, 'app.db');
  if($uploads){
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads));
    foreach($it as $file){ if($file->isDir()) continue; $zip->addFile($file->getRealPath(), 'uploads/' . basename($file)); }
  }
  $zip->close();
  echo "Backup created: $zipPath\n";
} else {
  echo "Failed to create backup\n";
}