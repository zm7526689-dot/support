<?php
// apply_utils_url_dryrun.php
// نسخة للعرض فقط: لا تغيّر أي ملف، فقط تعرض النتائج
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>Starting dry-run (web safe)\n";

$root = __DIR__ . '/..';
$targets = [$root . '/views', $root . '/public'];

$patterns = [
  // <?= $cfg['APP_URL'] ?>/path
  '/(<\?\=\s*\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\?>)\s*\/([^\s\'"\)<]+)/i',

  // $cfg['APP_URL'] . '/path'
  '/(\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\.\s*[\'"])\/([^\'"]+)/i',

  // literal absolute base
  '/(https?:\/\/[^\s\'"]+\/supportops\/public)\/([^\s\'"\)<]+)/i',
];

$replacements = [
  "<?= Utils::url('\\2') ?>",
  "<?= Utils::url('\\2') ?>",
  "<?= Utils::url('\\2') ?>",
];

$scanned = 0;
$wouldChange = [];

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
      $wouldChange[] = $path;
      echo "Would modify: $path\n";
    }
  }
}

echo "\nSummary:\n";
echo "Files scanned: $scanned\n";
echo "Files that would be modified: " . count($wouldChange) . "\n";
if (!empty($wouldChange)) {
  echo "List:\n";
  foreach ($wouldChange as $p) echo " - $p\n";
}
echo "\nDry-run complete. No files were changed.\n</pre>";