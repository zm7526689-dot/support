<?php
// add_indexphp_links.php
// تشغيل: php add_indexphp_links.php
// أو افتح عبر المتصفح (مرة واحدة) إذا السيرفر يسمح.

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
  // Pattern 1: <?= $cfg['APP_URL'] ?>/path -> add index.php if missing
  // we capture the prefix and the following slash+path
  '/(<\?\=\s*\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\?>)\s*\/(?!index\.php\/)/i',

  // Pattern 2: <?= $cfg["APP_URL"] ?>/path (double quotes variant)
  // (covered by previous with i flag; kept for clarity)

  // Pattern 3: $cfg['APP_URL'] . '/path'  -> $cfg['APP_URL'] . '/index.php/path'
  '/(\$cfg

\[\s*[\'"]APP_URL[\'"]\s*\]

\s*\.\s*[\'"])\s*\/(?!index\.php\/)/i',

  // Pattern 4: $cfg["APP_URL"] . "/path"
  // covered by pattern 3 with i flag
];

// Replacement mapping: for pattern 1 -> \1 . '/index.php/'  but keep original formatting
$replacements = [
  // for pattern 1 (<?= $cfg['APP_URL'] ?>/<path>) => <?= $cfg['APP_URL'] ?>/index.php/<path>
  '$1/index.php/',

  // pattern 3 ($cfg['APP_URL'] . '/path') => $cfg['APP_URL'] . '/index.php/path'
  '$1/index.php/',
];

function process_file($file, $patterns, $replacements, &$summary, $timestamp) {
  $summary['files_scanned']++;
  $orig = file_get_contents($file);
  $new = $orig;

  // Apply all patterns
  foreach ($patterns as $i => $pat) {
    $new = preg_replace($pat, $replacements[$i], $new);
  }

  // Also handle href="/supportops/public/..." absolute occurrences that start with APP_URL literal
  // If your code uses literal 'https://alqatta-sizing.com/supportops/public' we add index.php after that base
  $cfgLiteral = preg_quote('https://alqatta-sizing.com/supportops/public', '/');
  $new = preg_replace_callback("/($cfgLiteral)(?!\/index\.php)(\/)/i", function($m){
    return $m[1] . '/index.php' . $m[2];
  }, $new);

  if ($new !== $orig) {
    // backup original
    $bak = $file . '.bak.' . $timestamp;
    if (!copy($file, $bak)) {
      echo "فشل إنشاء نسخة احتياطية لملف: $file\n";
      return;
    }
    // write new
    file_put_contents($file, $new);
    $summary['files_changed']++;
    $summary['changes'][] = $file;
    echo "Modified: $file (backup: " . basename($bak) . ")\n";
  } else {
    // no change
  }
}

foreach ($targets as $t) {
  if (!is_dir($t)) {
    echo "مجلد غير موجود: $t — تخطي\n";
    continue;
  }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($t));
  foreach ($it as $f) {
    if ($f->isDir()) continue;
    $path = $f->getPathname();
    // process only PHP/HTML/template files
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','html','htm'])) continue;
    process_file($path, $patterns, $replacements, $summary, $timestamp);
  }
}

echo "\n--- ملخص ---\n";
echo "ملفات ممسوحة: " . $summary['files_scanned'] . PHP_EOL;
echo "ملفات مُعدّلة: " . $summary['files_changed'] . PHP_EOL;
if (!empty($summary['changes'])) {
  echo "قائمة الملفات المعدلة:\n";
  foreach ($summary['changes'] as $c) echo " - " . $c . PHP_EOL;
}
echo "انتهى.\n";