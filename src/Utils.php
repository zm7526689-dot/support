<?php
/**
 * Utils.php
 * وظائف مساعدة شائعة: هروب HTML، توجيه، CSRF بسيط، تسجيل Audit، وبناء روابط مرنة Utils::url.
 *
 * ضَع هذه الدالة داخل class Utils الموجودة لديك أو استبدل الملف بكامل الكلاس أدناه.
 * تأكد من تعديل مسار config/config.php إن لزم.
 */

class Utils
{
    /**
     * HTML escape
     */
    public static function h($s)
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Redirect to a path relative to APP_URL.
     * $path may include index.php if needed (Utils::url handles index logic).
     */
    public static function redirect($path = '/')
    {
        $url = self::url($path);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Return APP URL aware of index.php usage.
     * $path is optional relative path (e.g., 'customers', 'tickets/show?id=1').
     */
    public static function url($path = '')
    {
        // Load config safely
        $cfgPath = __DIR__ . '/../../config/config.php';
        $base = '';
        if (file_exists($cfgPath)) {
            $cfg = require $cfgPath;
            $base = rtrim($cfg['APP_URL'] ?? '', '/');
        } else {
            // fallback to global
            $base = isset($GLOBALS['APP_URL']) ? rtrim($GLOBALS['APP_URL'], '/') : '';
        }

        // Determine whether to include index.php segment
        $useIndex = false;
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if (strpos($script, 'index.php') !== false) $useIndex = true;
        if (strpos($requestUri, 'index.php') !== false) $useIndex = true;

        // If running CLI or base missing, return safe relative/path form
        if (php_sapi_name() === 'cli' || empty($base)) {
            $url = ($base ?: '') . '/' . ltrim($path, '/');
            return rtrim($url, '/');
        }

        $url = $base;
        if ($useIndex) $url .= '/index.php';
        if ($path !== '') $url .= '/' . ltrim($path, '/');
        return $url;
    }

    /**
     * CSRF token functions (session required)
     */
    public static function csrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_token'];
    }

    public static function checkCsrf($token)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (empty($_SESSION['csrf_token'])) return false;
        return hash_equals($_SESSION['csrf_token'], (string)$token);
    }

    /**
     * Audit logging helper
     * Attempts to write a simple audit entry into audit_log table if DB accessible.
     * DB_PATH should be set in config/config.php as SQLite path or adapt accordingly.
     */
    public static function audit($pdoOrConn, $action, $entity, $entityId = null, $meta = null)
    {
        // If passed a PDO instance, use directly; otherwise try to construct from config
        try {
            if ($pdoOrConn instanceof PDO) {
                $pdo = $pdoOrConn;
            } else {
                $cfgPath = __DIR__ . '/../../config/config.php';
                if (!file_exists($cfgPath)) return; // cannot audit
                $cfg = require $cfgPath;
                if (empty($cfg['DB_PATH'])) return;
                $pdo = new PDO('sqlite:' . $cfg['DB_PATH']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }

            $stmt = $pdo->prepare('CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                user_id INTEGER,
                action TEXT,
                entity TEXT,
                entity_id TEXT,
                meta TEXT
            )');
            $stmt->execute();

            $userId = null;
            // try to resolve current user id if Auth class exists
            if (class_exists('Auth') && method_exists('Auth', 'user')) {
                $u = Auth::user();
                if (is_array($u) && isset($u['id'])) $userId = $u['id'];
            }

            $ins = $pdo->prepare('INSERT INTO audit_log(user_id, action, entity, entity_id, meta) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$userId, $action, $entity, $entityId !== null ? (string)$entityId : null, $meta]);
        } catch (Throwable $e) {
            // Non-fatal: do not break app if audit fails
            error_log('Audit failed: ' . $e->getMessage());
        }
    }

    /**
     * Safe file write helper (creates directories as needed)
     */
    public static function safeWrite($path, $content, $mode = 0644)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $content);
        @chmod($path, $mode);
    }

    /**
     * Simple debug printer (only for debugging; remove in prod)
     */
    public static function dd($v)
    {
        echo '<pre>';
        var_dump($v);
        echo '</pre>';
        exit;
    }
}