<?php
/**
 * SupportOps full upgrade script
 * - Adds advanced features and modern mobile-first UI
 * - Safe to run once on existing project created earlier
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

function mk($path){ if(!is_dir($path)){ mkdir($path, 0775, true); echo "Created dir: $path\n"; } }
function put($path, $content){ file_put_contents($path, $content); echo "Updated: $path\n"; }
function ok($msg){ echo "\n✅ $msg\n"; }

/* === Ensure directories exist === */
$dirs = [
  "public/assets/css", "public/assets/js", "public/uploads", "public",
  "config", "src/Controllers", "src/DAO", "src/Services",
  "views/customers", "views/tickets", "views/reports", "views/users",
  "views/partials", "database/migrations", "backup/daily", "cron"
];
foreach($dirs as $d) mk($d);

/* === Config: fixed APP_URL and secure settings === */
put("config/config.php", <<<'PHP'
<?php
return array(
  'APP_NAME'      => 'SupportOps',
  'APP_URL'       => 'https://alqatta-sizing.com/supportops/public',
  'DB_PATH'       => __DIR__ . '/../database/app.db',
  'UPLOAD_DIR'    => __DIR__ . '/../public/uploads',
  'ALLOWED_UPLOADS' => array('image/jpeg','image/png','application/pdf','text/plain','text/csv'),
  'MAX_UPLOAD_MB' => 20,
  'SESSION_NAME'  => 'supportops_sid',
  'SLA_HOURS'     => 48,
  'BACKUP_DIR'    => __DIR__ . '/../backup/daily',
);
PHP);

/* === Bootstrap: autoload + migrations === */
put("config/bootstrap.php", <<<'PHP'
<?php
$config = require __DIR__ . '/config.php';
session_name($config['SESSION_NAME']);
session_start();

spl_autoload_register(function($class){
  $class = str_replace('\\', '/', $class);
  $paths = array(
    __DIR__ . '/../src/' . $class . '.php',
    __DIR__ . '/../src/Controllers/' . $class . '.php',
    __DIR__ . '/../src/DAO/' . $class . '.php',
    __DIR__ . '/../src/Services/' . $class . '.php',
  );
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

// Apply 002 features migration if not applied
$mig002 = __DIR__ . '/../database/migrations_002_applied';
if (!file_exists($mig002) && file_exists(__DIR__ . '/../database/migrations/002_features.sql')) {
  $sql2 = file_get_contents(__DIR__ . '/../database/migrations/002_features.sql');
  $pdo->exec($sql2);
  file_put_contents($mig002, date('c'));
}
PHP);

/* === Initial schema if missing === */
if(!file_exists(__DIR__ . "/database/migrations/001_init.sql")){
  put("database/migrations/001_init.sql", <<<'SQL'
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
}

/* === Features migration === */
put("database/migrations/002_features.sql", <<<'SQL'
-- users_settings (password change & lock)
CREATE TABLE IF NOT EXISTS user_settings (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  failed_logins INTEGER DEFAULT 0,
  last_password_change DATETIME,
  locked_until DATETIME
);

-- audit log
CREATE TABLE IF NOT EXISTS audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  entity TEXT,
  entity_id INTEGER,
  meta TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- knowledge articles (promoted reports)
CREATE TABLE IF NOT EXISTS knowledge_articles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  summary TEXT NOT NULL,
  ticket_id INTEGER REFERENCES tickets(id),
  report_id INTEGER REFERENCES ticket_reports(id),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- import job logs
CREATE TABLE IF NOT EXISTS import_jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL,
  file_name TEXT NOT NULL,
  status TEXT NOT NULL,
  message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL);

/* === Router === */
put("src/Router.php", <<<'PHP'
<?php
class Router {
  private array $routes = array();
  public function get($path, $handler){ $this->routes['GET'][$path] = $handler; }
  public function post($path, $handler){ $this->routes['POST'][$path] = $handler; }
  public function dispatch(){
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $path = '/' . ltrim(substr($uri, strlen($base)), '/');

    $handler = isset($this->routes[$method][$path]) ? $this->routes[$method][$path] : null;
    if(!$handler){ http_response_code(404); echo '404'; return; }
    $class = $handler[0]; $action = $handler[1];
    (new $class)->$action();
  }
}
PHP);

/* === Auth & Utils === */
put("src/Auth.php", <<<'PHP'
<?php
class Auth {
  public static function check(){ return isset($_SESSION['user']); }
  public static function user(){ return isset($_SESSION['user']) ? $_SESSION['user'] : null; }
  public static function login($user){ $_SESSION['user'] = $user; }
  public static function logout(){ unset($_SESSION['user']); }
  public static function requireRole($roles){
    $u = self::user();
    if(!$u || !in_array($u['role'], $roles)){ http_response_code(403); exit('Forbidden'); }
  }
}
PHP);

put("src/Utils.php", <<<'PHP'
<?php
class Utils {
  public static function h($str){ return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
  public static function csrfToken(){
    if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
  }
  public static function checkCsrf($token){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ? $token : ''); }
  public static function redirect($path){
    $cfg = require __DIR__ . '/../config/config.php';
    header('Location: ' . rtrim($cfg['APP_URL'], '/') . '/' . ltrim($path, '/'));
    exit;
  }
  public static function audit($pdo, $action, $entity=null, $entity_id=null, $meta=null){
    $uid = Auth::user()['id'] ?? null;
    $st = $pdo->prepare('INSERT INTO audit_log(user_id, action, entity, entity_id, meta) VALUES (?,?,?,?,?)');
    $st->execute(array($uid, $action, $entity, $entity_id, $meta));
  }
}
PHP);

/* === DAO layer === */
put("src/DAO/UsersDAO.php", <<<'PHP'
<?php
class UsersDAO {
  public function __construct(private PDO $db){}
  public function findByUsername($username){
    $st = $this->db->prepare('SELECT * FROM users WHERE username=?');
    $st->execute(array($username));
    return $st->fetch(PDO::FETCH_ASSOC);
  }
  public function create($username,$password,$role){
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $this->db->prepare('INSERT INTO users(username,password,role) VALUES(?,?,?)');
    $st->execute(array($username,$hash,$role));
    return $this->db->lastInsertId();
  }
  public function list(){
    $st = $this->db->query('SELECT id, username, role, created_at FROM users ORDER BY created_at DESC');
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

put("src/DAO/CustomersDAO.php", <<<'PHP'
<?php
class CustomersDAO {
  public function __construct(private PDO $db){}
  public function findByPhone($phone){
    $st = $this->db->prepare('SELECT * FROM customers WHERE phone=?');
    $st->execute(array($phone)); return $st->fetch(PDO::FETCH_ASSOC);
  }
  public function create($name,$phone,$area=null){
    $st = $this->db->prepare('INSERT INTO customers(name,phone,area) VALUES(?,?,?)');
    $st->execute(array($name,$phone,$area)); return $this->db->lastInsertId();
  }
  public function list(){
    $st = $this->db->query('SELECT * FROM customers ORDER BY created_at DESC');
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

put("src/DAO/TicketsDAO.php", <<<'PHP'
<?php
class TicketsDAO {
  public function __construct(private PDO $db){}
  public function create($data){
    $st = $this->db->prepare('INSERT INTO tickets(customer_id,created_by_user_id,assigned_to_user_id,title,problem_description,status) VALUES (?,?,?,?,?,?)');
    $st->execute(array(
      $data['customer_id'], $data['created_by_user_id'], isset($data['assigned_to_user_id']) ? $data['assigned_to_user_id'] : null,
      $data['title'], $data['problem_description'], isset($data['status']) ? $data['status'] : 'new'
    ));
    return $this->db->lastInsertId();
  }
  public function listForUser($user){
    if($user['role']==='field_engineer'){
      $st = $this->db->prepare('SELECT * FROM tickets WHERE assigned_to_user_id = ? ORDER BY updated_at DESC');
      $st->execute(array($user['id'])); return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $this->db->query('SELECT * FROM tickets ORDER BY updated_at DESC');
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function assign($ticketId,$userId){
    $st = $this->db->prepare('UPDATE tickets SET assigned_to_user_id=?, status="assigned", updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $st->execute(array($userId,$ticketId));
  }
  public function find($id){
    $st = $this->db->prepare('SELECT * FROM tickets WHERE id=?'); $st->execute(array($id));
    return $st->fetch(PDO::FETCH_ASSOC);
  }
}
PHP);

put("src/DAO/ReportsDAO.php", <<<'PHP'
<?php
class ReportsDAO {
  public function __construct(private PDO $db){}
  public function create($ticketId,$userId,$diagnosis,$action){
    $st = $this->db->prepare('INSERT INTO ticket_reports(ticket_id,user_id,diagnosis,action_taken) VALUES (?,?,?,?)');
    $st->execute(array($ticketId,$userId,$diagnosis,$action));
    return $this->db->lastInsertId();
  }
  public function listByTicket($ticketId){
    $st = $this->db->prepare('SELECT * FROM ticket_reports WHERE ticket_id=? ORDER BY created_at DESC');
    $st->execute(array($ticketId)); return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

/* === Services === */
put("src/Services/KnowledgeService.php", <<<'PHP'
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
    $st->execute(array($qLike,$qLike,$qLike,$qLike));
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function suggestions($text, $limit=5){
    $qLike = '%' . $text . '%';
    $st = $this->db->prepare("SELECT id, title FROM tickets WHERE title LIKE ? OR problem_description LIKE ? ORDER BY updated_at DESC LIMIT ?");
    $st->execute(array($qLike,$qLike,$limit));
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

put("src/Services/AnalyticsService.php", <<<'PHP'
<?php
class AnalyticsService {
  public function __construct(private PDO $db){}
  public function frequentFaults($limit=5){
    $sql = "SELECT substr(lower(problem_description),1,50) AS keypart, COUNT(*) AS cnt
            FROM tickets GROUP BY keypart ORDER BY cnt DESC LIMIT ?";
    $st = $this->db->prepare($sql); $st->execute(array($limit));
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function slaBreaches($hours){
    $sql = "SELECT * FROM tickets WHERE status IN ('new','assigned')
            AND (julianday('now') - julianday(updated_at)) * 24 > ?";
    $st = $this->db->prepare($sql); $st->execute(array($hours));
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
PHP);

put("src/Services/AlertService.php", <<<'PHP'
<?php
class AlertService {
  public function __construct(private PDO $db){}
  public function overdueTickets($hours){
    $st = $this->db->prepare("SELECT id, title, updated_at FROM tickets WHERE status IN ('new','assigned') AND (julianday('now') - julianday(updated_at))*24 > ?");
    $st->execute(array($hours)); return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function repeatedFaults($threshold=2){
    $st = $this->db->query("SELECT problem_description AS key, COUNT(*) AS cnt FROM tickets GROUP BY key HAVING cnt>= ".(int)$threshold." ORDER BY cnt DESC");
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function email($to, $subject, $body){
    @mail($to, $subject, $body, "Content-Type: text/plain; charset=UTF-8");
  }
}
PHP);

/* === Controllers === */
put("src/Controllers/AuthController.php", <<<'PHP'
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
      Auth::login(array('id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role']));
      Utils::redirect('dashboard');
    }
    $error = 'بيانات الدخول غير صحيحة'; include __DIR__ . '/../../views/login.php';
  }
  public function logout(){ Auth::logout(); Utils::redirect('login'); }
}
PHP);

put("src/Controllers/CustomersController.php", <<<'PHP'
<?php
class CustomersController {
  public function index(){
    Auth::requireRole(array('support_engineer','manager'));
    global $pdo; $dao = new CustomersDAO($pdo); $customers = $dao->list();
    include __DIR__ . '/../../views/customers/index.php';
  }
  public function create(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/customers/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $area = trim($_POST['area'] ?? '');
    if(!$name || !$phone){ $error = 'الاسم والهاتف مطلوبان'; include __DIR__ . '/../../views/customers/create.php'; return; }
    $dao = new CustomersDAO($pdo); $dao->create($name, $phone, $area ? $area : null);
    Utils::redirect('customers');
  }
  public function import(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    if(empty($_FILES['file']['tmp_name'])) Utils::redirect('customers');
    $fh = fopen($_FILES['file']['tmp_name'], 'r'); $dao = new CustomersDAO($pdo); $count=0;
    while(($row = fgetcsv($fh))){ if(count($row)<2) continue; $dao->create(trim($row[0]), trim($row[1]), isset($row[2]) ? $row[2] : null); $count++; }
    fclose($fh);
    Utils::redirect('customers');
  }
  public function export(){
    Auth::requireRole(array('support_engineer','manager')); global $pdo;
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="customers.csv"');
    $list = (new CustomersDAO($pdo))->list();
    $out = fopen('php://output', 'w'); foreach($list as $c){ fputcsv($out, array($c['name'],$c['phone'],$c['area'])); } fclose($out);
  }
}
PHP);

put("src/Controllers/TicketsController.php", <<<'PHP'
<?php
class TicketsController {
  public function index(){
    Auth::requireRole(array('support_engineer','field_engineer','manager'));
    global $pdo; $dao = new TicketsDAO($pdo);
    $tickets = $dao->listForUser(Auth::user());
    include __DIR__ . '/../../views/tickets/index.php';
  }
  public function create(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/tickets/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $phone = trim($_POST['phone'] ?? ''); $name = trim($_POST['name'] ?? ''); $area = trim($_POST['area'] ?? '');
    $title = trim($_POST['title'] ?? ''); $desc = trim($_POST['problem_description'] ?? '');
    if(!$phone || !$title || !$desc){ $error = 'الهاتف والعنوان والوصف مطلوبة'; include __DIR__ . '/../../views/tickets/create.php'; return; }
    $cDao = new CustomersDAO($pdo); $cust = $cDao->findByPhone($phone);
    if(!$cust){ $cid = $cDao->create($name ? $name : 'عميل بدون اسم', $phone, $area ? $area : null); $cust = array('id'=>$cid); }
    $tDao = new TicketsDAO($pdo);
    $id = $tDao->create(array(
      'customer_id' => $cust['id'],
      'created_by_user_id' => Auth::user()['id'],
      'title' => $title, 'problem_description' => $desc,
    ));
    Utils::redirect('tickets/show?id=' . $id);
  }
  public function assign(){
    Auth::requireRole(array('support_engineer')); global $pdo; $dao = new TicketsDAO($pdo);
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $dao->assign((int)$_POST['ticket_id'], (int)$_POST['user_id']);
    Utils::redirect('tickets');
  }
  public function show(){
    Auth::requireRole(array('support_engineer','field_engineer','manager')); global $pdo;
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    $ticket = $pdo->prepare('SELECT * FROM tickets WHERE id=?'); $ticket->execute(array($id)); $ticket = $ticket->fetch(PDO::FETCH_ASSOC);
    if(!$ticket){ http_response_code(404); exit('Not found'); }
    $customer = $pdo->prepare('SELECT * FROM customers WHERE id=?'); $customer->execute(array((int)$ticket['customer_id'])); $customer = $customer->fetch(PDO::FETCH_ASSOC);
    $reports = $pdo->prepare('SELECT * FROM ticket_reports WHERE ticket_id=? ORDER BY created_at DESC'); $reports->execute(array($id)); $reports = $reports->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/tickets/show.php';
  }
}
PHP);

put("src/Controllers/ReportsController.php", <<<'PHP'
<?php
class ReportsController {
  public function create(){
    Auth::requireRole(array('field_engineer','support_engineer')); global $pdo;
    $cfg = require __DIR__ . '/../../config/config.php';
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $ticketId = (int)(isset($_POST['ticket_id']) ? $_POST['ticket_id'] : 0);
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
        $fileType = (strpos($type,'image/')===0) ? 'image' : (in_array($type,array('text/plain','text/csv')) ? 'log' : (strpos($type,'pdf')!==false ? 'pdf' : 'csv'));
        $pdo->prepare('INSERT INTO report_attachments(report_id,file_path,file_type) VALUES (?,?,?)')->execute(array($reportId, $safeName, $fileType));
      }
    }
    if(Auth::user()['role']==='field_engineer'){
      $pdo->prepare('UPDATE tickets SET status="solved_field", updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute(array($ticketId));
    }
    Utils::redirect('tickets/show?id=' . $ticketId);
  }
}
PHP);

put("src/Controllers/DashboardController.php", <<<'PHP'
<?php
class DashboardController {
  public function index(){
    Auth::requireRole(array('support_engineer','manager','field_engineer')); global $pdo;
    $analytics = new AnalyticsService($pdo);
    $cfg = require __DIR__ . '/../../config/config.php';
    $sla = $analytics->slaBreaches($cfg['SLA_HOURS']);
    $freq = $analytics->frequentFaults(5);

    $stats = $pdo->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

    $engineers = $pdo->query("SELECT u.username, COUNT(t.id) as closed
                              FROM users u
                              LEFT JOIN tickets t ON u.id = t.assigned_to_user_id AND t.status IN ('solved_phone','solved_field')
                              WHERE u.role='field_engineer'
                              GROUP BY u.id")->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/dashboard.php';
  }
  public function search(){
    Auth::requireRole(array('support_engineer','manager','field_engineer')); global $pdo;
    $q = trim($_GET['q'] ?? ''); $res = array();
    if($q){ $svc = new KnowledgeService($pdo); $res = $svc->search($q); }
    include __DIR__ . '/../../views/search.php';
  }
}
PHP);

put("src/Controllers/KnowledgeController.php", <<<'PHP'
<?php
class KnowledgeController {
  public function promote(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);
    $r = $pdo->prepare('SELECT diagnosis, action_taken FROM ticket_reports WHERE id=?'); $r->execute(array($reportId)); $r = $r->fetch(PDO::FETCH_ASSOC);
    if(!$r) Utils::redirect('tickets/show?id=' . $ticketId);
    $summary = mb_strimwidth(($r['diagnosis'] ?? '') . ' / ' . ($r['action_taken'] ?? ''), 0, 240, '...');
    $t = $pdo->prepare('SELECT title FROM tickets WHERE id=?'); $t->execute(array($ticketId)); $title = $t->fetchColumn();
    $pdo->prepare('INSERT INTO knowledge_articles(title,summary,ticket_id,report_id) VALUES (?,?,?,?)')->execute(array($title, $summary, $ticketId, $reportId));
    Utils::audit($pdo, 'promote_knowledge', 'ticket', $ticketId, 'report_id=' . $reportId);
    Utils::redirect('search?q=' . urlencode($title));
  }
}
PHP);

put("src/Controllers/UsersController.php", <<<'PHP'
<?php
class UsersController {
  public function index(){ Auth::requireRole(array('support_engineer')); global $pdo;
    $users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/users/index.php';
  }
  public function create(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/users/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    (new UsersDAO($pdo))->create(trim($_POST['username']), trim($_POST['password']), trim($_POST['role']));
    Utils::audit($pdo, 'create_user', 'user', null, $_POST['username']);
    Utils::redirect('users');
  }
  public function password(){
    Auth::requireRole(array('support_engineer','field_engineer','manager')); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/users/password.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $u = Auth::user(); $st = $pdo->prepare('SELECT password FROM users WHERE id=?'); $st->execute(array($u['id']));
    $oldHash = $st->fetchColumn();
    if(!password_verify($_POST['old'] ?? '', $oldHash)){ $error='كلمة المرور الحالية غير صحيحة'; include __DIR__ . '/../../views/users/password.php'; return; }
    $newHash = password_hash($_POST['new'] ?? '', PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute(array($newHash, $u['id']));
    Utils::audit($pdo, 'change_password', 'user', $u['id'], null);
    Utils::redirect('dashboard');
  }
}
PHP);

/* === Public front controller === */
put("public/index.php", <<<'PHP'
<?php
require __DIR__ . '/../config/bootstrap.php';

$router = new Router();

// Auth
$router->get('/login', array(AuthController::class, 'login'));
$router->post('/login', array(AuthController::class, 'login'));
$router->get('/logout', array(AuthController::class, 'logout'));

// Dashboard
$router->get('/dashboard', array(DashboardController::class, 'index'));

// Customers
$router->get('/customers', array(CustomersController::class, 'index'));
$router->get('/customers/create', array(CustomersController::class, 'create'));
$router->post('/customers/create', array(CustomersController::class, 'create'));
$router->post('/customers/import', array(CustomersController::class, 'import'));
$router->get('/customers/export', array(CustomersController::class, 'export'));

// Tickets
$router->get('/tickets', array(TicketsController::class, 'index'));
$router->get('/tickets/create', array(TicketsController::class, 'create'));
$router->post('/tickets/create', array(TicketsController::class, 'create'));
$router->post('/tickets/assign', array(TicketsController::class, 'assign'));
$router->get('/tickets/show', array(TicketsController::class, 'show'));

// Reports
$router->post('/reports/create', array(ReportsController::class, 'create'));

// Knowledge
$router->post('/knowledge/promote', array(KnowledgeController::class, 'promote'));

// Users
$router->get('/users', array(UsersController::class, 'index'));
$router->get('/users/create', array(UsersController::class, 'create'));
$router->post('/users/create', array(UsersController::class, 'create'));
$router->get('/users/password', array(UsersController::class, 'password'));
$router->post('/users/password', array(UsersController::class, 'password'));

$router->dispatch();
PHP);

/* === Modern mobile-first CSS === */
put("public/assets/css/app.css", <<<'CSS'
:root{
  --bg:#0f172a; --panel:#111827; --text:#e5e7eb; --muted:#9ca3af;
  --primary:#22c55e; --primary-dark:#16a34a; --danger:#ef4444; --warning:#f59e0b; --info:#3b82f6;
  --card:#0b1220; --border:#1f2937;
}
*{box-sizing:border-box}
html[dir="rtl"] body{
  margin:0; font-family:"Noto Sans Arabic", system-ui, -apple-system, Segoe UI, Tahoma, sans-serif;
  background:var(--bg); color:var(--text);
}
a{color:var(--info); text-decoration:none}
a:hover{opacity:.9}
.topbar{ position:sticky; top:0; z-index:1000; background:var(--panel); border-bottom:1px solid var(--border); }
.topbar nav{ display:flex; gap:10px; align-items:center; padding:12px; flex-wrap:wrap; }
.topbar .brand{font-weight:800; color:var(--primary)}
.topbar .actions{margin-inline-start:auto; display:flex; gap:8px; align-items:center}
.container{max-width:1100px; margin:16px auto; padding:0 12px}
.card{ background:var(--card); border:1px solid var(--border); border-radius:10px; padding:12px; box-shadow:0 2px 8px rgba(0,0,0,.3); }
.grid{display:grid; gap:12px}
.grid.kpi{grid-template-columns:repeat(2,1fr)}
@media(min-width:700px){ .grid.kpi{grid-template-columns:repeat(4,1fr)} }
.kpi .value{font-size:22px; font-weight:800}
.btn{appearance:none; border:none; border-radius:10px; padding:10px 14px; background:var(--primary); color:#021; font-weight:700; cursor:pointer}
.btn:hover{background:var(--primary-dark)}
.btn.outline{background:transparent; color:var(--primary); border:1px solid var(--primary)}
.btn.danger{background:var(--danger); color:#fff}
.input, textarea, select{ width:100%; background:#0a0f1a; border:1px solid var(--border); border-radius:8px; padding:10px; color:var(--text); margin:8px 0; }
.table{width:100%; border-collapse:collapse; background:var(--card); border-radius:10px; overflow:hidden}
.table th, .table td{border-bottom:1px solid var(--border); padding:10px; font-size:14px}
.table thead{background:var(--panel)}
.badge{display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px}
.badge.new{background:#1f2937; color:#e5e7eb}
.badge.assigned{background:var(--info); color:#04111f}
.badge.solved_phone{background:#a3e635; color:#07210a}
.badge.solved_field{background:var(--primary); color:#03150a}
.toast{position:fixed; bottom:16px; left:16px; right:16px; display:flex; gap:10px; flex-direction:column}
.toast .msg{background:#111827; border:1px solid var(--border); padding:10px; border-radius:8px}
.error { color:#ef4444; }
.suggestions { font-size: 12px; color:#9ca3af; }
CSS);

/* === JS: Toast + Offline === */
put("public/assets/js/app.js", <<<'JS'
// Simple toast notifications
export function toast(text, type='info'){
  const wrap = document.querySelector('.toast') || (()=>{ const d=document.createElement('div'); d.className='toast'; document.body.appendChild(d); return d; })();
  const m = document.createElement('div'); m.className='msg'; m.textContent = text;
  wrap.appendChild(m); setTimeout(()=> m.remove(), 4000);
}

// Offline report draft
export function saveDraft(form){
  const idEl = form.querySelector('input[name="ticket_id"]');
  const key = 'reportDraft:' + (idEl ? idEl.value : 'unknown');
  const data = new FormData(form);
  const obj = {}; for(const [k,v] of data.entries()) obj[k]=v;
  localStorage.setItem(key, JSON.stringify(obj));
  toast('تم حفظ التقرير محليًا');
}
export function loadDraft(ticketId, form){
  const key = 'reportDraft:' + ticketId; const raw = localStorage.getItem(key);
  if(!raw) return; const obj = JSON.parse(raw);
  ['diagnosis','action_taken'].forEach(k=>{ const el=form.querySelector(`[name="${k}"]`); if(el) el.value = obj[k] || ''; });
  toast('تم تحميل مسودة محلية للتقرير');
}
export async function trySync(url, form){
  try {
    const r = await fetch(url, { method:'POST', body: new FormData(form) });
    if(r.ok){ toast('تمت مزامنة التقرير بنجاح','success'); const id=form.querySelector('input[name="ticket_id"]').value; localStorage.removeItem('reportDraft:' + id); return true; }
  } catch(e){ toast('فشل الاتصال، تم الحفاظ على المسودة محليًا','warning'); }
  return false;
}
JS);

/* === Security for uploads === */
put("public/uploads/.htaccess", "php_flag engine off\nRemoveHandler .php\n");

/* === Views: layout with modern header === */
put("views/layout.php", <<<'PHP'
<?php $cfg = require __DIR__ . '/../config/config.php'; ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= Utils::h($cfg['APP_NAME']) ?></title>
  <link rel="stylesheet" href="<?= $cfg['APP_URL'] ?>/assets/css/app.css">
  <script type="module" src="<?= $cfg['APP_URL'] ?>/assets/js/app.js"></script>
</head>
<body>
<header class="topbar">
  <nav>
    <span class="brand">SupportOps</span>
    <a href="<?= $cfg['APP_URL'] ?>/dashboard">لوحة التحكم</a>
    <a href="<?= $cfg['APP_URL'] ?>/customers">العملاء</a>
    <a href="<?= $cfg['APP_URL'] ?>/tickets">التذاكر</a>
    <form class="actions" action="<?= $cfg['APP_URL'] ?>/search" method="get">
      <input class="input" name="q" placeholder="ابحث: كود 33، Pylontech 5001...">
      <button class="btn outline" type="submit">بحث</button>
      <?php if(Auth::check()): ?><a class="btn danger" href="<?= $cfg['APP_URL'] ?>/logout">خروج</a><?php endif; ?>
    </form>
  </nav>
</header>
<div class="toast"></div>
<main class="container">
  <?php if(isset($content)) echo $content; ?>
</main>
</body>
</html>
PHP);

/* === View: login === */
put("views/login.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../config/config.php'; ?>
<h1>تسجيل الدخول</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/login">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>اسم المستخدم</label>
  <input class="input" name="username" required>
  <label>كلمة المرور</label>
  <input class="input" type="password" name="password" required>
  <button class="btn" type="submit">دخول</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
PHP);

/* === View: dashboard === */
put("views/dashboard.php", <<<'PHP'
<?php ob_start(); ?>
<h1>لوحة التحكم</h1>

<div class="grid kpi">
  <div class="card kpi"><div class="label">جديدة</div><div class="value"><?= $stats['new'] ?? 0 ?></div></div>
  <div class="card kpi"><div class="label">مسندة</div><div class="value"><?= $stats['assigned'] ?? 0 ?></div></div>
  <div class="card kpi"><div class="label">هاتفياً</div><div class="value"><?= $stats['solved_phone'] ?? 0 ?></div></div>
  <div class="card kpi"><div class="label">ميدانياً</div><div class="value"><?= $stats['solved_field'] ?? 0 ?></div></div>
</div>

<div class="grid">
  <div class="card">
    <h3>توزيع الحالات</h3>
    <canvas id="statusChart" height="120"></canvas>
  </div>
  <div class="card">
    <h3>الأعطال الأكثر تكرارًا</h3>
    <canvas id="faultsChart" height="120"></canvas>
  </div>
  <div class="card">
    <h3>أداء المهندسين</h3>
    <canvas id="engineersChart" height="120"></canvas>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <h3>تذاكر متأخرة (SLA)</h3>
  <?php if(empty($sla)): ?>
    <p class="muted">لا توجد تذاكر متأخرة</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>#</th><th>العنوان</th><th>الحالة</th><th>أُحدثت</th></tr></thead>
    <tbody>
      <?php foreach($sla as $t): ?>
      <tr>
        <td><?= $t['id'] ?></td>
        <td><?= Utils::h($t['title']) ?></td>
        <td><span class="badge <?= $t['status'] ?>"><?= $t['status'] ?></span></td>
        <td><?= $t['updated_at'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('statusChart'), {
  type:'pie',
  data:{ labels:['جديدة','مسندة','هاتفياً','ميدانياً'],
    datasets:[{ data:[<?= $stats['new'] ?? 0 ?>,<?= $stats['assigned'] ?? 0 ?>,<?= $stats['solved_phone'] ?? 0 ?>,<?= $stats['solved_field'] ?? 0 ?>],
      backgroundColor:['#f44336','#3b82f6','#a3e635','#22c55e'] }] }
});
new Chart(document.getElementById('faultsChart'), {
  type:'bar',
  data:{ labels:<?= json_encode(array_column($freq,'keypart')) ?>,
    datasets:[{ label:'التكرار', data:<?= json_encode(array_column($freq,'cnt')) ?>, backgroundColor:'#3b82f6' }] },
  options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false }
});
new Chart(document.getElementById('engineersChart'), {
  type:'bar',
  data:{ labels:<?= json_encode(array_column($engineers,'username')) ?>,
    datasets:[{ label:'مغلقة', data:<?= json_encode(array_column($engineers,'closed')) ?>, backgroundColor:'#22c55e' }] },
  options:{ responsive:true, maintainAspectRatio:false }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
PHP);

/* === View: search === */
put("views/search.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../config/config.php'; ?>
<h1>بحث في قاعدة المعرفة</h1>
<?php if(!empty($res)): ?>
<table class="table">
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
<p class="muted">اكتب كلمات البحث في الشريط أعلى الصفحة.</p>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
PHP);

/* === Views: customers === */
put("views/customers/index.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>العملاء</h1>
<div class="grid">
  <div class="card">
    <a class="btn" href="<?= $cfg['APP_URL'] ?>/customers/create">إضافة عميل</a>
    <form method="post" action="<?= $cfg['APP_URL'] ?>/customers/import" enctype="multipart/form-data" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn outline" type="submit">استيراد CSV</button>
      <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/customers/export">تصدير CSV</a>
    </form>
  </div>
</div>
<table class="table" style="margin-top:10px">
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

put("views/customers/create.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إضافة عميل</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/customers/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>الاسم</label><input class="input" name="name" required>
  <label>الهاتف</label><input class="input" name="phone" required>
  <label>المنطقة</label><input class="input" name="area">
  <button class="btn" type="submit">حفظ</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

/* === Views: tickets === */
put("views/tickets/index.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>التذاكر</h1>
<?php if(Auth::user()['role']==='support_engineer'): ?>
  <a class="btn" href="<?= $cfg['APP_URL'] ?>/tickets/create">فتح تذكرة جديدة</a>
<?php endif; ?>
<table class="table" style="margin-top:10px">
  <thead><tr><th>#</th><th>العنوان</th><th>الحالة</th><th>مكلف إلى</th><th>فتح</th></tr></thead>
  <tbody>
  <?php foreach($tickets as $t): ?>
    <tr>
      <td><?= $t['id'] ?></td>
      <td><?= Utils::h($t['title']) ?></td>
      <td><span class="badge <?= $t['status'] ?>"><?= $t['status'] ?></span></td>
      <td><?= $t['assigned_to_user_id'] ?: '-' ?></td>
      <td><a href="<?= $cfg['APP_URL'] ?>/tickets/show?id=<?= $t['id'] ?>">عرض</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

put("views/tickets/create.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>فتح تذكرة جديدة</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/tickets/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <fieldset class="card">
    <legend>العميل</legend>
    <label>الهاتف</label><input class="input" name="phone" required>
    <label>الاسم (إن لم يوجد)</label><input class="input" name="name">
    <label>المنطقة</label><input class="input" name="area">
  </fieldset>
  <fieldset class="card" style="margin-top:10px">
    <legend>التذكرة</legend>
    <label>العنوان</label><input class="input" name="title" required oninput="suggest(this.value)">
    <div id="sugg" class="suggestions"></div>
    <label>وصف المشكلة</label><textarea class="input" name="problem_description" required></textarea>
  </fieldset>
  <button class="btn" type="submit">حفظ</button>
</form>
<script>
async function suggest(q){
  const box = document.getElementById('sugg'); if(!q || q.length<2){ box.innerHTML=''; return; }
  try{
    const r = await fetch('<?= $cfg['APP_URL'] ?>/search?q=' + encodeURIComponent(q));
    const text = await r.text();
    box.innerHTML = text.includes('<table') ? '<p>اقتراحات: راجع صفحة البحث؛ تم العثور على تطابقات.</p>' : '';
  }catch(e){}
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

put("views/tickets/show.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تفاصيل التذكرة #<?= $ticket['id'] ?></h1>
<div class="card">
<p><strong>العنوان:</strong> <?= Utils::h($ticket['title']) ?></p>
<p><strong>الوصف:</strong> <?= Utils::h($ticket['problem_description']) ?></p>
<p><strong>الحالة:</strong> <span class="badge <?= $ticket['status'] ?>"><?= $ticket['status'] ?></span></p>
</div>

<div class="card" style="margin-top:10px">
<h3>العميل</h3>
<p><strong>الاسم:</strong> <?= Utils::h($customer['name']) ?> — <strong>الهاتف:</strong> <?= Utils::h($customer['phone']) ?> — <strong>المنطقة:</strong> <?= Utils::h($customer['area'] ?? '-') ?></p>
</div>

<div class="card" style="margin-top:10px">
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
</div>

<?php if(in_array(Auth::user()['role'], array('field_engineer','support_engineer'))): ?>
<div class="card" style="margin-top:10px">
<h3>إضافة تقرير</h3>
<form id="reportForm" method="post" action="<?= $cfg['APP_URL'] ?>/reports/create" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
  <label>التشخيص</label><textarea class="input" name="diagnosis" required></textarea>
  <label>الإجراء المتخذ</label><textarea class="input" name="action_taken" required></textarea>
  <label>مرفقات</label><input class="input" type="file" name="attachments[]" multiple>
  <div class="grid" style="grid-template-columns:repeat(3,1fr)">
    <button class="btn" type="submit">حفظ التقرير</button>
    <button class="btn outline" type="button" onclick="saveDraft(document.getElementById('reportForm'))">حفظ محليًا</button>
    <button class="btn outline" type="button" onclick="trySync('<?= $cfg['APP_URL'] ?>/reports/create', document.getElementById('reportForm'))">مزامنة الآن</button>
  </div>
</form>
</div>
<?php endif; ?>

<?php if(Auth::user()['role']==='support_engineer' && !empty($reports)): ?>
<div class="card" style="margin-top:10px">
<form method="post" action="<?= $cfg['APP_URL'] ?>/knowledge/promote">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
  <input type="hidden" name="report_id" value="<?= (int)$reports[0]['id'] ?>">
  <button class="btn outline" type="submit">تحويل لأرشيف المعرفة</button>
</form>
</div>
<?php endif; ?>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

/* === Views: users === */
put("views/users/index.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>المستخدمون</h1>
<a class="btn" href="<?= $cfg['APP_URL'] ?>/users/create">إضافة مستخدم</a>
<table class="table" style="margin-top:10px">
  <thead><tr><th>#</th><th>اسم المستخدم</th><th>الدور</th><th>أُنشئ</th></tr></thead>
  <tbody>
    <?php foreach($users as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= Utils::h($u['username']) ?></td>
        <td><?= Utils::h($u['role']) ?></td>
        <td><?= $u['created_at'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

put("views/users/create.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إضافة مستخدم</h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/users/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>اسم المستخدم</label><input class="input" name="username" required>
  <label>كلمة المرور</label><input class="input" type="password" name="password" required>
  <label>الدور</label>
  <select class="input" name="role" required>
    <option value="support_engineer">مهندس دعم</option>
    <option value="field_engineer">مهندس ميداني</option>
    <option value="manager">مدير</option>
  </select>
  <button class="btn" type="submit">حفظ</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

put("views/users/password.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تغيير كلمة المرور</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/users/password">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>كلمة المرور الحالية</label><input class="input" type="password" name="old" required>
  <label>كلمة المرور الجديدة</label><input class="input" type="password" name="new" required>
  <button class="btn" type="submit">تحديث</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

/* === .htaccess for subfolder isolation === */
put("public/.htaccess", "RewriteEngine On\nRewriteBase /supportops/public/\nRewriteCond %{REQUEST_FILENAME} -f [OR]\nRewriteCond %{REQUEST_FILENAME} -d\nRewriteRule ^ - [L]\nRewriteRule ^ index.php [L]\n");

/* === Cron backup (unchanged but ensured) === */
put("cron/backup.php", <<<'PHP'
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

ok("Upgrade completed. Apply migrations by reloading any page or running this script again.");
