<?php
/**
 * Create SupportOps project (best-in-class MVP)
 * Run once inside an empty folder named supportops/
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

function mk($path){ if(!is_dir($path)){ mkdir($path, 0775, true); echo "Created dir: $path\n"; } }
function writeFile($path, $content){ file_put_contents($path, $content); echo "Created file: $path\n"; }
function done($msg){ echo "\n✅ $msg\n"; }

/* ========= Directories ========= */
$dirs = [
  "public/assets/css",
  "public/assets/js",
  "public/uploads",
  "public/.well-known",
  "config",
  "src/Controllers",
  "src/DAO",
  "src/Services",
  "views/customers",
  "views/tickets",
  "views/reports",
  "views/partials",
  "database/migrations",
  "backup/daily",
  "cron"
];
foreach($dirs as $d) mk($d);

/* ========= Config ========= */
writeFile("config/config.php", <<<'PHP'
<?php
return [
  'APP_NAME' => 'SupportOps',
  'APP_URL'  => rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')), '/'),
  'DB_PATH'  => __DIR__ . '/../database/app.db',
  'UPLOAD_DIR' => __DIR__ . '/../public/uploads',
  'ALLOWED_UPLOADS' => ['image/jpeg','image/png','application/pdf','text/plain','text/csv'],
  'MAX_UPLOAD_MB' => 20,
  'SESSION_NAME' => 'supportops_sid',
  'SLA_HOURS' => 48,
  'BACKUP_DIR' => __DIR__ . '/../backup/daily',
];
PHP);

writeFile("config/bootstrap.php", <<<'PHP'
<?php
$config = require __DIR__ . '/config.php';
session_name($config['SESSION_NAME']);
session_start();

spl_autoload_register(function($class){
  $class = str_replace('\\', '/', $class);
  $paths = [
    __DIR__ . '/../src/' . $class . '.php',
    __DIR__ . '/../src/Controllers/' . $class . '.php',
    __DIR__ . '/../src/DAO/' . $class . '.php',
    __DIR__ . '/../src/Services/' . $class . '.php',
  ];
  foreach($paths as $p){ if(file_exists($p)){ require $p; return; } }
});

$pdo = new PDO('sqlite:' . $config['DB_PATH']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$migFlag = __DIR__ . '/../database/migrations_applied';
if (!file_exists($migFlag)) {
  $sql = file_get_contents(__DIR__ . '/../database/migrations/001_init.sql');
  $pdo->exec($sql);
  file_put_contents($migFlag, date('c'));
}
PHP);

/* ========= Migration ========= */
writeFile("database/migrations/001_init.sql", <<<'SQL'
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('support_engineer','field_engineer','manager')),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  phone TEXT UNIQUE NOT NULL,
  area TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products_catalog (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL CHECK (type IN ('inverter','battery','panel')),
  brand TEXT NOT NULL,
  model TEXT NOT NULL,
  UNIQUE(type, brand, model)
);

CREATE TABLE IF NOT EXISTS customer_assets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  product_id INTEGER NOT NULL REFERENCES products_catalog(id) ON DELETE RESTRICT,
  quantity INTEGER NOT NULL CHECK (quantity >= 0)
);

CREATE TABLE IF NOT EXISTS tickets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  created_by_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  assigned_to_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  title TEXT NOT NULL,
  problem_description TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('new','assigned','solved_phone','solved_field')),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  diagnosis TEXT,
  action_taken TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS report_attachments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  report_id INTEGER NOT NULL REFERENCES ticket_reports(id) ON DELETE CASCADE,
  file_path TEXT NOT NULL,
  file_type TEXT NOT NULL CHECK (file_type IN ('log','image','pdf','csv')),
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_updated ON tickets(updated_at);
CREATE INDEX IF NOT EXISTS idx_reports_ticket ON ticket_reports(ticket_id);
CREATE INDEX IF NOT EXISTS idx_search_tickets ON tickets(title, problem_description);
CREATE INDEX IF NOT EXISTS idx_search_reports ON ticket_reports(diagnosis, action_taken);
SQL);

/* ========= Router ========= */
writeFile("src/Router.php", <<<'PHP'
<?php
class Router {
  private array $routes = [];
  public function get($path, $handler){ $this->routes['GET'][$path] = $handler; }
  public function post($path, $handler){ $this->routes['POST'][$path] = $handler; }
  public function dispatch(){
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $path = '/' . ltrim(substr($uri, strlen($base)), '/');

    $handler = $this->routes[$method][$path] ?? null;
    if(!$handler){ http_response_code(404); echo '404'; return; }
    [$class, $action] = $handler;
    (new $class)->$action();
  }
}
PHP);

/* ========= Auth & Utils ========= */
writeFile("src/Auth.php", <<<'PHP'
<?php
class Auth {
  public static function check(){ return isset($_SESSION['user']); }
  public static function user(){ return $_SESSION['user'] ?? null; }
  public static function login($user){ $_SESSION['user'] = $user; }
  public static function logout(){ unset($_SESSION['user']); }
  public static function requireRole(array $roles){
    $u = self::user();
    if(!$u || !in_array($u['role'], $roles)){ http_response_code(403); exit('Forbidden'); }
  }
}
PHP);

writeFile("src/Utils.php", <<<'PHP'
<?php
class Utils {
  public static function h($str){ return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
  public static function csrfToken(){
    if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
  }
  public static function checkCsrf($token){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ?? ''); }
  public static function redirect($path){
    $cfg = require __DIR__ . '/../config/config.php';
    header('Location: ' . rtrim($cfg['APP_URL'], '/') . '/' . ltrim($path, '/'));
    exit;
  }
}
PHP);

/* ========= DAO layer ========= */
writeFile("src/DAO/UsersDAO.php", <<<'PHP'
<?php
class UsersDAO {
  public function __construct(private PDO $db){}
  public function findByUsername($username){
    $st = $this->db->prepare('SELECT * FROM users WHERE username=?');
    $st->execute([$username]);
    return $st->fetch(PDO::FETCH_ASSOC);
  }
  public function create($username,$password,$role){
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $this->db->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)');
    $st->execute([$username,$hash,$role]);
    return $this->db->lastInsertId();
  }
}
PHP);

writeFile("src/DAO/CustomersDAO.php", <<<'PHP'
<?php
class CustomersDAO {
  public function __construct(private PDO $db){}
  public function findByPhone($phone){
    $st = $this->db->prepare('SELECT * FROM customers WHERE phone=?');
    $st->execute([$phone]); return $st->fetch(PDO::FETCH_ASSOC);
  }
  public function create($name,$phone,$area=null){
    $st = $this->db->prepare('INSERT INTO customers(name,phone,area) VALUES(?,?,?)');
    $st->execute([$name,$phone,$area]); return $this->db->lastInsertId();
  }
  public function list(){
    $st = $this->db->query('SELECT * FROM customers ORDER BY created_at DESC');
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

writeFile("src/DAO/TicketsDAO.php", <<<'PHP'
<?php
class TicketsDAO {
  public function __construct(private PDO $db){}
  public function create($data){
    $st = $this->db->prepare('INSERT INTO tickets(customer_id,created_by_user_id,assigned_to_user_id,title,problem_description,status) VALUES (?,?,?,?,?,?)');
    $st->execute([
      $data['customer_id'], $data['created_by_user_id'], $data['assigned_to_user_id'] ?? null,
      $data['title'], $data['problem_description'], $data['status'] ?? 'new'
    ]);
    return $this->db->lastInsertId();
  }
  public function listForUser($user){
    if($user['role']==='field_engineer'){
      $st = $this->db->prepare('SELECT * FROM tickets WHERE assigned_to_user_id = ? ORDER BY updated_at DESC');
      $st->execute([$user['id']]); return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $this->db->query('SELECT * FROM tickets ORDER BY updated_at DESC');
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function assign($ticketId,$userId){
    $st = $this->db->prepare('UPDATE tickets SET assigned_to_user_id=?, status="assigned", updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $st->execute([$userId,$ticketId]);
  }
  public function find($id){
    $st = $this->db->prepare('SELECT * FROM tickets WHERE id=?'); $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC);
  }
}
PHP);

writeFile("src/DAO/ReportsDAO.php", <<<'PHP'
<?php
class ReportsDAO {
  public function __construct(private PDO $db){}
  public function create($ticketId,$userId,$diagnosis,$action){
    $st = $this->db->prepare('INSERT INTO ticket_reports(ticket_id,user_id,diagnosis,action_taken) VALUES (?,?,?,?)');
    $st->execute([$ticketId,$userId,$diagnosis,$action]);
    return $this->db->lastInsertId();
  }
  public function listByTicket($ticketId){
    $st = $this->db->prepare('SELECT * FROM ticket_reports WHERE ticket_id=? ORDER BY created_at DESC');
    $st->execute([$ticketId]); return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

/* ========= Services ========= */
writeFile("src/Services/KnowledgeService.php", <<<'PHP'
<?php
class KnowledgeService {
  public function __construct(private PDO $db){}
  public function search($q){
    $qLike = '%' . $q . '%';
    $sql = "
      SELECT t.id AS ticket_id, t.title, t.problem_description,
             r.id AS report_id, r.diagnosis, r.action_taken
      FROM tickets t
      LEFT JOIN ticket_reports r ON r.ticket_id = t.id
      WHERE t.title LIKE ? OR t.problem_description LIKE ?
         OR r.diagnosis LIKE ? OR r.action_taken LIKE ?
      ORDER BY t.updated_at DESC";
    $st = $this->db->prepare($sql);
    $st->execute([$qLike,$qLike,$qLike,$qLike]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function suggestions($text, $limit=5){
    $qLike = '%' . $text . '%';
    $st = $this->db->prepare("SELECT id, title FROM tickets WHERE title LIKE ? OR problem_description LIKE ? ORDER BY updated_at DESC LIMIT ?");
    $st->execute([$qLike,$qLike,$limit]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

writeFile("src/Services/AnalyticsService.php", <<<'PHP'
<?php
class AnalyticsService {
  public function __construct(private PDO $db){}
  public function frequentFaults($limit=5){
    $sql = "SELECT substr(lower(problem_description),1,50) AS keypart, COUNT(*) AS cnt
            FROM tickets GROUP BY keypart ORDER BY cnt DESC LIMIT ?";
    $st = $this->db->prepare($sql); $st->execute([$limit]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function slaBreaches($hours){
    $sql = "SELECT * FROM tickets WHERE status IN ('new','assigned')
            AND (julianday('now') - julianday(updated_at)) * 24 > ?";
    $st = $this->db->prepare($sql); $st->execute([$hours]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

/* ========= Controllers ========= */
writeFile("src/Controllers/AuthController.php", <<<'PHP'
<?php
class AuthController {
  public function login(){
    global $pdo; $cfg = require __DIR__ . '/../../config/config.php';
    if($_SERVER['REQUEST_METHOD']==='GET'){
      $error = null; include __DIR__ . '/../../views/login.php'; return;
    }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $dao = new UsersDAO($pdo);
    $u = $dao->findByUsername(trim($_POST['username'] ?? ''));
    if($u && password_verify($_POST['password'] ?? '', $u['password'])){
      Auth::login(['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role']]);
      Utils::redirect('dashboard');
    }
    $error = 'بيانات الدخول غير صحيحة'; include __DIR__ . '/../../views/login.php';
  }
  public function logout(){ Auth::logout(); Utils::redirect('login'); }
}
PHP);

writeFile("src/Controllers/CustomersController.php", <<<'PHP'
<?php
class CustomersController {
  public function index(){
    Auth::requireRole(['support_engineer','manager']);
    global $pdo; $dao = new CustomersDAO($pdo); $customers = $dao->list();
    include __DIR__ . '/../../views/customers/index.php';
  }
  public function create(){
    Auth::requireRole(['support_engineer']); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/customers/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $area = trim($_POST['area'] ?? '');
    if(!$name || !$phone){ $error = 'الاسم والهاتف مطلوبان'; include __DIR__ . '/../../views/customers/create.php'; return; }
    $dao = new CustomersDAO($pdo); $dao->create($name, $phone, $area ?: null);
    Utils::redirect('customers');
  }
}
PHP);

writeFile("src/Controllers/TicketsController.php", <<<'PHP'
<?php
class TicketsController {
  public function index(){
    Auth::requireRole(['support_engineer','field_engineer','manager']);
    global $pdo; $dao = new TicketsDAO($pdo);
    $tickets = $dao->listForUser(Auth::user());
    include __DIR__ . '/../../views/tickets/index.php';
  }
  public function create(){
    Auth::requireRole(['support_engineer']); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/tickets/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $phone = trim($_POST['phone'] ?? ''); $name = trim($_POST['name'] ?? ''); $area = trim($_POST['area'] ?? '');
    $title = trim($_POST['title'] ?? ''); $desc = trim($_POST['problem_description'] ?? '');
    if(!$phone || !$title || !$desc){ $error = 'الهاتف والعنوان والوصف مطلوبة'; include __DIR__ . '/../../views/tickets/create.php'; return; }
    $cDao = new CustomersDAO($pdo); $cust = $cDao->findByPhone($phone);
    if(!$cust){ $cid = $cDao->create($name ?: 'عميل بدون اسم', $phone, $area ?: null); $cust = ['id'=>$cid]; }
    $tDao = new TicketsDAO($pdo);
    $id = $tDao->create([
      'customer_id' => $cust['id'],
      'created_by_user_id' => Auth::user()['id'],
      'title' => $title, 'problem_description' => $desc,
    ]);
    Utils::redirect('tickets/show?id=' . $id);
  }
  public function assign(){
    Auth::requireRole(['support_engineer']); global $pdo; $dao = new TicketsDAO($pdo);
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $dao->assign((int)$_POST['ticket_id'], (int)$_POST['user_id']);
    Utils::redirect('tickets');
  }
  public function show(){
    Auth::requireRole(['support_engineer','field_engineer','manager']); global $pdo;
    $id = (int)($_GET['id'] ?? 0);
    $ticket = $pdo->prepare('SELECT * FROM tickets WHERE id=?'); $ticket->execute([$id]); $ticket = $ticket->fetch(PDO::FETCH_ASSOC);
    if(!$ticket){ http_response_code(404); exit('Not found'); }
    $customer = $pdo->prepare('SELECT * FROM customers WHERE id=?'); $customer->execute([(int)$ticket['customer_id']]); $customer = $customer->fetch(PDO::FETCH_ASSOC);
    $reports = $pdo->prepare('SELECT * FROM ticket_reports WHERE ticket_id=? ORDER BY created_at DESC'); $reports->execute([$id]); $reports = $reports->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/tickets/show.php';
  }
}
PHP);

writeFile("src/Controllers/ReportsController.php", <<<'PHP'
<?php
class ReportsController {
  public function create(){
    Auth::requireRole(['field_engineer','support_engineer']); global $pdo;
    $cfg = require __DIR__ . '/../../config/config.php';
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $diagnosis = trim($_POST['diagnosis'] ?? ''); $action = trim($_POST['action_taken'] ?? '');
    if(!$ticketId || !$diagnosis || !$action){ http_response_code(400); exit('Missing fields'); }
    $dao = new ReportsDAO($pdo); $reportId = $dao->create($ticketId, Auth::user()['id'], $diagnosis, $action);

    // uploads
    if(!empty($_FILES['attachments']['tmp_name'])){
      foreach($_FILES['attachments']['tmp_name'] as $i => $tmp){
        if(!$tmp) continue;
        $type = mime_content_type($tmp);
        if(!in_array($type, $cfg['ALLOWED_UPLOADS'])) continue;
        $name = $_FILES['attachments']['name'][$i];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $safeName = 'r' . $reportId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        move_uploaded_file($tmp, $cfg['UPLOAD_DIR'] . '/' . $safeName);
        $fileType = (str_starts_with($type,'image/')) ? 'image' : (in_array($type,['text/plain','text/csv']) ? 'log' : (str_contains($type,'pdf') ? 'pdf' : 'csv'));
        $pdo->prepare('INSERT INTO report_attachments(report_id,file_path,file_type) VALUES (?,?,?)')->execute([$reportId, $safeName, $fileType]);
      }
    }
    if(Auth::user()['role']==='field_engineer'){
      $pdo->prepare('UPDATE tickets SET status="solved_field", updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$ticketId]);
    }
    Utils::redirect('tickets/show?id=' . $ticketId);
  }
}
PHP);

writeFile("src/Controllers/DashboardController.php", <<<'PHP'
<?php
class DashboardController {
  public function index(){
    Auth::requireRole(['support_engineer','manager','field_engineer']); global $pdo;
    $analytics = new AnalyticsService($pdo);
    $cfg = require __DIR__ . '/../../config/config.php';
    $sla = $analytics->slaBreaches($cfg['SLA_HOURS']);
    $freq = $analytics->frequentFaults(5);
    include __DIR__ . '/../../views/dashboard.php';
  }
  public function search(){
    Auth::requireRole(['support_engineer','manager','field_engineer']); global $pdo;
    $q = trim($_GET['q'] ?? ''); $res = [];
    if($q){ $svc = new KnowledgeService($pdo); $res = $svc->search($q); }
    include __DIR__ . '/../../views/search.php';
  }
}
PHP);

/* ========= Public front controller ========= */
writeFile("public/index.php", <<<'PHP'
<?php
require __DIR__ . '/../config/bootstrap.php';

$router = new Router();

// Auth
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);

// Customers
$router->get('/customers', [CustomersController::class, 'index']);
$router->get('/customers/create', [CustomersController::class, 'create']);
$router->post('/customers/create', [CustomersController::class, 'create']);

// Tickets
$router->get('/tickets', [TicketsController::class, 'index']);
$router->get('/tickets/create', [TicketsController::class, 'create']);
$router->post('/tickets/create', [TicketsController::class, 'create']);
$router->post('/tickets/assign', [TicketsController::class, 'assign']);
$router->get('/tickets/show', [TicketsController::class, 'show']);

// Reports
$router->post('/reports/create', [ReportsController::class, 'create']);

// Knowledge
$router->get('/search', [DashboardController::class, 'search']);

$router->dispatch();
PHP);

/* ========= Views ========= */
writeFile("views/layout.php", <<<'PHP'
<?php $cfg = require __DIR__ . '/../config/config.php'; ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= Utils::h($cfg['APP_NAME']) ?></title>
  <link rel="stylesheet" href="<?= $cfg['APP_URL'] ?>/assets/css/app.css">
</head>
<body>
<header class="topbar">
  <nav>
    <a href="<?= $cfg['APP_URL'] ?>/dashboard">لوحة التحكم</a>
    <a href="<?= $cfg['APP_URL'] ?>/customers">العملاء</a>
    <a href="<?= $cfg['APP_URL'] ?>/tickets">التذاكر</a>
    <form class="search" action="<?= $cfg['APP_URL'] ?>/search" method="get">
      <input name="q" placeholder="ابحث: كود 33، Pylontech 5001..." />
    </form>
    <?php if(Auth::check()): ?><a class="logout" href="<?= $cfg['APP_URL'] ?>/logout">تسجيل الخروج</a><?php endif; ?>
  </nav>
</header>
<main class="container">
  <?php if(isset($content)) echo $content; ?>
</main>
</body>
</html>
PHP);

writeFile("views/login.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../config/config.php'; ?>
<h1>تسجيل الدخول</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/login">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>اسم المستخدم</label>
  <input name="username" required>
  <label>كلمة المرور</label>
  <input type="password" name="password" required>
  <button type="submit">دخول</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
PHP);

writeFile("views/dashboard.php", <<<'PHP'
<?php ob_start(); ?>
<h1>لوحة التحكم</h1>
<section>
  <h3>تجاوزات SLA (<?= Utils::h((string)count($sla)) ?>)</h3>
  <ul>
    <?php foreach($sla as $t): ?>
      <li>#<?= $t['id'] ?> - <?= Utils::h($t['title']) ?> (<?= Utils::h($t['status']) ?>)</li>
    <?php endforeach; ?>
  </ul>
</section>
<section>
  <h3>الأعطال المتكررة</h3>
  <ul>
    <?php foreach($freq as $f): ?>
      <li><?= Utils::h($f['keypart']) ?> — <?= Utils::h($f['cnt']) ?> مرة</li>
    <?php endforeach; ?>
  </ul>
</section>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
PHP);

writeFile("views/search.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../config/config.php'; ?>
<h1>بحث في قاعدة المعرفة</h1>
<?php if(!empty($res)): ?>
<table>
  <thead><tr><th>التذكرة</th><th>العنوان</th><th>ملخص</th></tr></thead>
  <tbody>
  <?php foreach($res as $row): ?>
    <tr>
      <td>#<?= $row['ticket_id'] ?></td>
      <td><?= Utils::h($row['title']) ?></td>
      <td><?= Utils::h(mb_strimwidth(($row['diagnosis'] ?? '').' / '.($row['action_taken'] ?? ''), 0, 80, '...')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<p>اكتب كلمات البحث في الشريط أعلى الصفحة.</p>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
PHP);

writeFile("views/customers/index.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>العملاء</h1>
<a class="btn" href="<?= $cfg['APP_URL'] ?>/customers/create">إضافة عميل</a>
<table>
  <thead><tr><th>#</th><th>الاسم</th><th>الهاتف</th><th>المنطقة</th></tr></thead>
  <tbody>
  <?php foreach($customers as $c): ?>
    <tr>
      <td><?= $c['id'] ?></td>
      <td><?= Utils::h($c['name']) ?></td>
      <td><?= Utils::h($c['phone']) ?></td>
      <td><?= Utils::h($c['area'] ?? '-') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

writeFile("views/customers/create.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إضافة عميل</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/customers/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>الاسم</label><input name="name" required>
  <label>الهاتف</label><input name="phone" required>
  <label>المنطقة</label><input name="area">
  <button type="submit">حفظ</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

writeFile("views/tickets/index.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>التذاكر</h1>
<?php if(Auth::user()['role']==='support_engineer'): ?>
  <a class="btn" href="<?= $cfg['APP_URL'] ?>/tickets/create">فتح تذكرة جديدة</a>
<?php endif; ?>
<table>
  <thead><tr><th>#</th><th>العنوان</th><th>الحالة</th><th>مكلف إلى</th><th>فتح</th></tr></thead>
  <tbody>
  <?php foreach($tickets as $t): ?>
    <tr>
      <td><?= $t['id'] ?></td>
      <td><?= Utils::h($t['title']) ?></td>
      <td><?= $t['status'] ?></td>
      <td><?= $t['assigned_to_user_id'] ?: '-' ?></td>
      <td><a href="<?= $cfg['APP_URL'] ?>/tickets/show?id=<?= $t['id'] ?>">عرض</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

writeFile("views/tickets/create.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>فتح تذكرة جديدة</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/tickets/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <fieldset>
    <legend>العميل</legend>
    <label>الهاتف</label><input name="phone" required>
    <label>الاسم (إن لم يوجد)</label><input name="name">
    <label>المنطقة</label><input name="area">
  </fieldset>
  <fieldset>
    <legend>التذكرة</legend>
    <label>العنوان</label><input name="title" required oninput="suggest(this.value)">
    <div id="sugg" class="suggestions"></div>
    <label>وصف المشكلة</label><textarea name="problem_description" required></textarea>
  </fieldset>
  <button type="submit">حفظ</button>
</form>
<script>
async function suggest(q){
  const box = document.getElementById('sugg'); if(!q || q.length<2){ box.innerHTML=''; return; }
  try{
    const r = await fetch('<?= $cfg['APP_URL'] ?>/search?q=' + encodeURIComponent(q));
    const text = await r.text(); // simple reuse
    box.innerHTML = text.includes('<table') ? '<p>اقتراحات متاحة في صفحة البحث</p>' : '';
  }catch(e){}
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

writeFile("views/tickets/show.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تفاصيل التذكرة #<?= $ticket['id'] ?></h1>
<p><strong>العنوان:</strong> <?= Utils::h($ticket['title']) ?></p>
<p><strong>الوصف:</strong> <?= Utils::h($ticket['problem_description']) ?></p>
<p><strong>الحالة:</strong> <?= $ticket['status'] ?></p>

<h3>العميل</h3>
<p><strong>الاسم:</strong> <?= Utils::h($customer['name']) ?> — <strong>الهاتف:</strong> <?= Utils::h($customer['phone']) ?> — <strong>المنطقة:</strong> <?= Utils::h($customer['area'] ?? '-') ?></p>

<h3>التقارير</h3>
<ul>
<?php foreach($reports as $r): ?>
  <li>
    <strong>التاريخ:</strong> <?= $r['created_at'] ?> —
    <strong>التشخيص:</strong> <?= Utils::h($r['diagnosis']) ?> —
    <strong>الإجراء:</strong> <?= Utils::h($r['action_taken']) ?>
  </li>
<?php endforeach; ?>
</ul>

<?php if(in_array(Auth::user()['role'], ['field_engineer','support_engineer'])): ?>
<h3>إضافة تقرير</h3>
<form method="post" action="<?= $cfg['APP_URL'] ?>/reports/create" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
  <label>التشخيص</label><textarea name="diagnosis" required></textarea>
  <label>الإجراء المتخذ</label><textarea name="action_taken" required></textarea>
  <label>مرفقات</label><input type="file" name="attachments[]" multiple>
  <button type="submit">حفظ التقرير</button>
</form>
<?php endif; ?>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

/* ========= Assets ========= */
writeFile("public/assets/css/app.css", <<<'CSS'
html[dir="rtl"] body { font-family: "Noto Sans Arabic", Tahoma, sans-serif; direction: rtl; text-align: right; }
.topbar nav { display:flex; gap:12px; align-items:center; padding:10px; background:#f7f7f7; }
.container { max-width: 1100px; margin: 20px auto; padding: 0 10px; }
table { width:100%; border-collapse: collapse; }
th, td { border:1px solid #ddd; padding:8px; }
.btn { display:inline-block; padding:6px 10px; background:#1e88e5; color:#fff; text-decoration:none; border-radius:4px; }
input, textarea, select { width:100%; padding:8px; margin:6px 0; }
.error { color:#c62828; }
.suggestions { font-size: 12px; color:#555; }
@media (max-width: 600px){ .topbar nav { flex-wrap: wrap; } table, thead { font-size: 14px; } }
CSS);

/* ========= .htaccess for uploads security (if Apache) ========= */
writeFile("public/uploads/.htaccess", <<<'HTA'
php_flag engine off
RemoveHandler .php
HTA);

/* ========= Cron backup ========= */
writeFile("cron/backup.php", <<<'PHP'
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
PHP);

/* ========= Seed admin creator (temporary) ========= */
writeFile("create_admin.php", <<<'PHP'
<?php
require __DIR__ . '/config/bootstrap.php';
$dao = new UsersDAO($pdo);
$u = $dao->findByUsername('admin');
if(!$u){
  $dao->create('admin','StrongPass!','support_engineer');
  echo "Admin user created: admin / StrongPass!\n";
} else {
  echo "Admin already exists.\n";
}
PHP);

done("Project generated. Next: set permissions and run create_admin.php once.");