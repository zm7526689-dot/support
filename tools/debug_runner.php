<?php
// debug_runner.php (استعمله مؤقتا فقط لاكتشاف الخطأ عبر المتصفح)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "بدء فحص الخطأ...\n";
$file = __FILE__;
echo "مسار الملف: $file\n";

// محاولة تحميل الملف الأصلي للتحقق من صلاحية النص
$origPath = __DIR__ . '/add_indexphp_links.php';
if (!file_exists($origPath)) {
  echo "الملف add_indexphp_links.php غير موجود في نفس المجلد.\n";
  exit;
}
$content = file_get_contents($origPath);
if ($content === false) {
  echo "فشل في قراءة الملف: $origPath\n";
  exit;
}
echo "تم قراءة الملف بنجاح. حجم المحتوى: " . strlen($content) . " بايت\n";

// حاول تنفيذ (include) الملف داخل try-catch إن كان لا يحتوي على خروج مباشر
try {
  // ضع هنا تضمين آمن بدون تنفيذ الدالة الرئيسية إن وُجدت
  // include $origPath; // لا نفعل include إلى أن نعرف المشكلة
  echo "الملف قابل للقراءة لكن لا ننفذه لأمانك. إن أردت التنفيذ ارفع لي الأمر.\n";
} catch (Throwable $e) {
  echo "خطأ وقت التنفيذ: " . $e->getMessage() . " في " . $e->getFile() . " على السطر " . $e->getLine() . "\n";
}