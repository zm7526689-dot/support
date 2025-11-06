<?php
require_once __DIR__ . '/../config/bootstrap.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace(['/supportops/public','index.php'],'',$path);
if($path=='/' || $path==''){ $path='dashboard'; }

switch($path){
  case 'login.php': (new AuthController)->login(); break;
  case 'logout': (new AuthController)->logout(); break;
  case 'customers': (new CustomersController)->index(); break;
  case 'engineers': (new EngineersController)->index(); break;
  case 'users': (new UsersController)->index(); break;
  case 'dashboard': include APP_ROOT.'/views/dashboard.php'; break;
  default: http_response_code(404); echo "404 Not Found"; break;
}
?>
