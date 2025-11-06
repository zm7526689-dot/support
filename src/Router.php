<?php
/**
 * Router.php
 * بسيط وخالي من الاعتماديات الخارجية. يدعم تسجيل مسارات GET/POST والـ dispatch المرن.
 *
 * الاستخدام النموذجي:
 *  $router = new Router();
 *  $router->get('/login', array(AuthController::class, 'login'));
 *  $router->dispatch();
 */

class Router
{
    // routes structure: $routes['GET']['/path'] = handlerArray(class, method)
    private $routes = [];

    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function addRoute($method, $path, $handler)
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);
        if (!isset($this->routes[$method])) $this->routes[$method] = [];
        $this->routes[$method][$path] = $handler;
    }

    private function normalizePath($p)
    {
        if ($p === '' || $p === null) return '/';
        $p = '/' . ltrim($p, '/');
        // remove trailing slash except for root
        if ($p !== '/' && substr($p, -1) === '/') $p = rtrim($p, '/');
        return $p;
    }

    /**
     * Dispatch the current HTTP request to the matched handler.
     * This implementation is resilient: supports
     *  - /base/index.php/path
     *  - /base/path  (pretty)
     *  - direct /index.php/path anywhere in URI
     * It prints a diagnostic computed path on 404 to help debugging; remove prints in production.
     */
    public function dispatch()
    {
        // During setup/diagnostics show all errors; remove/disable in production
        if (!ini_get('display_errors')) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        }
        error_reporting(E_ALL);

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $scriptDir = rtrim(dirname($scriptName), '/\\');

        // Compute candidate path
        $path = '/';

        // 1) If request starts with scriptDir/index.php
        $indexToken = $scriptDir . '/index.php';
        if (strpos($uri, $indexToken) === 0) {
            $path = substr($uri, strlen($indexToken));
        } elseif (strpos($uri, $scriptDir) === 0) {
            // 2) If request starts with scriptDir (pretty URL)
            $path = substr($uri, strlen($scriptDir));
        } else {
            // 3) fallback: look for /index.php anywhere
            $pos = strpos($uri, '/index.php');
            if ($pos !== false) {
                $path = substr($uri, $pos + strlen('/index.php'));
            } else {
                // 4) last-resort: use URI as-is
                $path = $uri;
            }
        }

        // normalize
        $path = '/' . ltrim($path, '/');
        if ($path === '') $path = '/';

        // try direct match then alternate without trailing slash
        $methodRoutes = $this->routes[strtoupper($method)] ?? [];
        $handler = $methodRoutes[$path] ?? null;
        if (!$handler) {
            $alt = rtrim($path, '/');
            if ($alt === '') $alt = '/';
            $handler = $methodRoutes[$alt] ?? null;
        }

        if (!$handler) {
            http_response_code(404);
            // Diagnostic output (remove in production)
            echo "404: computed path = " . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . "\n";
            // echo "Registered routes for {$method}: "; print_r(array_keys($methodRoutes));
            return;
        }

        // Handler expected as [ClassName, 'method'] or callable
        if (is_array($handler) && count($handler) === 2) {
            $class = $handler[0];
            $action = $handler[1];
            if (!class_exists($class)) {
                http_response_code(500);
                echo "Router error: controller class not found: {$class}";
                return;
            }
            $controller = new $class();
            if (!method_exists($controller, $action)) {
                http_response_code(500);
                echo "Router error: method {$action} not found on " . get_class($controller);
                return;
            }
            // call controller action
            $controller->$action();
            return;
        }

        // If handler is callable, call directly
        if (is_callable($handler)) {
            call_user_func($handler);
            return;
        }

        // Unknown handler type
        http_response_code(500);
        echo "Router error: invalid handler for path {$path}";
    }

    /**
     * For debugging: return registered routes array
     */
    public function getRegisteredRoutes()
    {
        return $this->routes;
    }
}