<?php
/**
 * fix_after_upgrade.php
 * سكربت إصلاح شامل بعد upgrade_2.php
 * - يعمل من المتصفح
 * - يأخذ نسخ احتياطية قبل أي تعديل
 * - يصلح الروابط التي فقدت index.php
 * - يطبع تقريرًا واضحًا
 */

ini_set('display_errors',1);
error_reporting(E_ALL);

echo "<pre>Starting auto-fix after upgrade_2.php...\n";

$root = __DIR__ . '/..'; // supportops
$targets = [$root . '/views', $root . '/public'];
$timestamp = date('Ymd_His');

$patterns = [
  // <?= $cfg['APP_URL'] ?>/path
  '/(<\?\=\s*\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\?>)\s*\/(?!index\.php)([^\s\'"\)<]+)/i',

  // $cfg['APP_URL'] . '/path'
  '/(\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\.\s*[\'"])\/(?!index\.php)([^\'"]+)/i',

  // literal absolute base
  '/(https?:\/\/[^\s\'"]+\/supportops\/public)\/(?!index\.php)([^\s\'"\)<]+)/i',
];

$replacements = [
  '$1/index.php/$2',
  '$1/index.php/$2',
  '$1/index.php/$2',
];

$scanned = 0;
$changed = 0;
$files = [];

foreach ($targets as $t) {
  if (!is_dir($t)) {
    echo "Skipping missing folder: $t\n";
    continue;
  }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($t));
  foreach ($it as $f) {
    if ($f->isDir()) continue;
    $ext = strtolower(pathinfo($f->getPathname(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','html','htm'])) continue;
    $path = $f->getPathname();
    $scanned++;
    $orig = @file_get_contents($path);
    if ($orig === false) continue;
    $new = $orig;
    foreach ($patterns as $i => $pat) {
      $new = preg_replace($pat, $replacements[$i], $new);
    }
    if ($new !== $orig) {
      $bak = $path . '.bak.' . $timestamp;
      copy($path, $bak);
      file_put_contents($path, $new);
      $changed++;
      $files[] = $path;
      echo "Fixed: $path (backup: " . basename($bak) . ")\n";
    }
  }
}

echo "\nSummary:\n";
echo "Files scanned: $scanned\n";
echo "Files fixed: $changed\n";
if (!empty($files)) {
  echo "List:\n";
  foreach ($files as $p) echo " - $p\n";
}
echo "\nDone. Please test your app now.\n</pre>";