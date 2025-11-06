<?php
// add_indexphp_links_dryrun.php
// تشغيل: php add_indexphp_links_dryrun.php
// أو افتح عبر المتصفح لعرض النتائج بدون تغيير أي ملف.

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = __DIR__ . '/..'; // supportops/tools/.. => supportops
$targets = [
  $root . '/views',
  $root . '/public',
];

$timestamp = date('Ymd_His');
$summary = ['files_scanned' => 0, 'files_changed' => 0, 'changes' => []];

$patterns = [
  // Pattern 1: <?= $cfg['APP_URL'] ?>/path -> add index.php if missing (PHP short echo)
  '/(<\?\=\s*\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\?>)\s*\/(?!index\.php\/)/i',

  // Pattern 2: $cfg['APP_URL'] . '/path'  -> add index.php if missing
  '/(\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\.\s*[\'"])\s*\/(?!index\.php\/)/i',
];

// Replacement strings (used only to simulate)
$replacements = [
  '$1/index.php/',
  '$1/index.php/',
];

function simulate_changes($file, $patterns, $replacements, &$summary) {
  $summary['files_scanned']++;
  $orig = @file_get_contents($file);
  if ($orig === false) return;

  $new = $orig;
  foreach ($patterns as $i => $pat) {
    // perform preg_replace but do not write
    $new = preg_replace($pat, $replacements[$i], $new);
  }

  // Handle literal APP_URL occurrences (absolute links)
  $cfgLiteral = preg_quote('https://alqatta-sizing.com/supportops/public', '/');
  $new = preg_replace_callback("/($cfgLiteral)(?!\/index\.php)(\/)/i", function($m){
    return $m[1] . '/index.php' . $m[2];
  }, $new);

  if ($new !== $orig) {
    $summary['files_changed']++;
    $summary['changes'][] = $file;
    echo "Will modify: $file\n";
  } else {
    // echo "No change: $file\n"; // optionally show
  }
}

foreach ($targets as $t) {
  if (!is_dir($t)) {
    echo "Skipping missing folder: $t\n";
    continue;
  }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($t));
  foreach ($it as $f) {
    if ($f->isDir()) continue;
    $path = $f->getPathname();
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','html','htm'])) continue;
    simulate_changes($path, $patterns, $replacements, $summary);
  }
}

echo "\n--- Summary ---\n";
echo "Files scanned: " . $summary['files_scanned'] . PHP_EOL;
echo "Files that would be modified: " . $summary['files_changed'] . PHP_EOL;
if (!empty($summary['changes'])) {
  echo "List of files:\n";
  foreach ($summary['changes'] as $c) echo " - " . $c . PHP_EOL;
}
echo "Dry-run complete. If you approve, reply: نفّذ التغييرات\n";