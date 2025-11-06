<?php
// dryrun_debug.php — نسخة تشخيصية آمنة تعمل عبر الويب
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting dry-run debug\n";

$root = __DIR__ . '/..';
$targets = [
  $root . '/views',
  $root . '/public',
];

$patterns = [
  '/(<\?\=\s*\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\?>)\s*\/(?!index\.php\/)/i',
  '/(\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\.\s*[\'"])\s*\/(?!index\.php\/)/i',
];

$cfgLiteral = preg_quote('https://alqatta-sizing.com/supportops/public', '/');

$scanned = 0;
$willChange = [];

foreach ($targets as $t) {
  if (!is_dir($t)) {
    echo "Missing folder (skipped): $t\n";
    continue;
  }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($t));
  foreach ($it as $f) {
    if ($f->isDir()) continue;
    $path = $f->getPathname();
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','html','htm'])) continue;
    $scanned++;
    $orig = @file_get_contents($path);
    if ($orig === false) { echo "Cannot read: $path\n"; continue; }

    $new = $orig;
    foreach ($patterns as $i => $pat) {
      $new = preg_replace($pat, '$1/index.php/', $new);
    }
    $new = preg_replace_callback("/($cfgLiteral)(?!\/index\.php)(\/)/i", function($m){
      return $m[1] . '/index.php' . $m[2];
    }, $new);

    if ($new !== $orig) {
      $willChange[] = $path;
      echo "Would modify: $path\n";
    }
  }
}

echo "\nSummary:\n";
echo "Files scanned: $scanned\n";
echo "Files to change: " . count($willChange) . "\n";
if (!empty($willChange)) {
  foreach ($willChange as $p) echo " - $p\n";
}
echo "\nIf you want me to perform the actual replacements, reply: نفّذ التغييرات\n";