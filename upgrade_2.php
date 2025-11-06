<?php
/**
 * SupportOps upgrade_2: Customers CRUD, Users management (edit/delete), Ticket assignment form
 * Run once in existing supportops project
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

function mk($p){ if(!is_dir($p)){ mkdir($p, 0775, true); echo "Created: $p\n"; } }
function put($p,$c){ file_put_contents($p,$c); echo "Updated: $p\n"; }

mk("views/customers");
mk("views/users");
mk("views/tickets");

/* ====== Update Utils (ensure audit exists) ====== */
if (!file_exists(__DIR__."/src/Utils.php")) {
  echo "WARNING: src/Utils.php missing. This upgrade expects previous setup.\n";
}

/* ====== CustomersController additions: edit/delete/import/export exist ====== */
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
    $dao = new CustomersDAO($pdo); $id = $dao->create($name, $phone, $area ? $area : null);
    Utils::audit($pdo, 'create_customer', 'customer', $id, $phone);
    Utils::redirect('customers');
  }
  public function edit(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM customers WHERE id=?'); $st->execute(array($id));
    $customer = $st->fetch(PDO::FETCH_ASSOC);
    if(!$customer){ http_response_code(404); exit('Not found'); }
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/customers/edit.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $name = trim($_POST['name']); $phone = trim($_POST['phone']); $area = trim($_POST['area']);
    $pdo->prepare('UPDATE customers SET name=?, phone=?, area=? WHERE id=?')->execute(array($name,$phone,$area,$id));
    Utils::audit($pdo, 'update_customer', 'customer', $id, $phone);
    Utils::redirect('customers');
  }
  public function delete(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM customers WHERE id=?')->execute(array($id));
    Utils::audit($pdo, 'delete_customer', 'customer', $id, null);
    Utils::redirect('customers');
  }
  public function import(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    if(empty($_FILES['file']['tmp_name'])) Utils::redirect('customers');
    $fh = fopen($_FILES['file']['tmp_name'], 'r'); $dao = new CustomersDAO($pdo);
    $count=0; while(($row = fgetcsv($fh))){ if(count($row)<2) continue; $dao->create(trim($row[0]), trim($row[1]), isset($row[2]) ? trim($row[2]) : null); $count++; }
    fclose($fh); Utils::audit($pdo, 'import_customers', 'customers', null, 'count='.$count);
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

/* ====== UsersController: index/create/edit/delete/password ====== */
put("src/Controllers/UsersController.php", <<<'PHP'
<?php
class UsersController {
  public function index(){ Auth::requireRole(array('support_engineer')); global $pdo;
    $users = (new UsersDAO($pdo))->list();
    include __DIR__ . '/../../views/users/index.php';
  }
  public function create(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/users/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $username = trim($_POST['username']); $password = trim($_POST['password']); $role = trim($_POST['role']);
    (new UsersDAO($pdo))->create($username, $password, $role);
    Utils::audit($pdo, 'create_user', 'user', null, $username);
    Utils::redirect('users');
  }
  public function edit(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare('SELECT id, username, role FROM users WHERE id=?'); $st->execute(array($id));
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if(!$user){ http_response_code(404); exit('Not found'); }
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/users/edit.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $username = trim($_POST['username']); $role = trim($_POST['role']);
    $pdo->prepare('UPDATE users SET username=?, role=? WHERE id=?')->execute(array($username,$role,$id));
    if(!empty($_POST['password'])){
      $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
      $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute(array($hash,$id));
    }
    Utils::audit($pdo, 'update_user', 'user', $id, $username);
    Utils::redirect('users');
  }
  public function delete(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute(array($id));
    Utils::audit($pdo, 'delete_user', 'user', $id, null);
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

/* ====== TicketsController: add assignForm ====== */
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
    Utils::audit($pdo, 'create_ticket', 'ticket', $id, $title);
    Utils::redirect('tickets/show?id=' . $id);
  }
  public function assign(){
    Auth::requireRole(array('support_engineer')); global $pdo; $dao = new TicketsDAO($pdo);
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $ticketId = (int)$_POST['ticket_id']; $userId = (int)$_POST['user_id'];
    $dao->assign($ticketId, $userId);
    Utils::audit($pdo, 'assign_ticket', 'ticket', $ticketId, 'to_user='.$userId);
    Utils::redirect('tickets/show?id=' . $ticketId);
  }
  public function assignForm(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    $id = (int)($_GET['id'] ?? 0);
    $ticket = (new TicketsDAO($pdo))->find($id);
    if(!$ticket){ http_response_code(404); exit('Not found'); }
    $engineers = $pdo->query("SELECT id, username FROM users WHERE role='field_engineer'")->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/tickets/assign.php';
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

/* ====== Views: Customers ====== */
put("views/customers/index.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>العملاء</h1>
<div class="card">
  <a class="btn" href="<?= $cfg['APP_URL'] ?>/customers/create">إضافة عميل</a>
  <form method="post" action="<?= $cfg['APP_URL'] ?>/customers/import" enctype="multipart/form-data" style="margin-top:10px">
    <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
    <input class="input" type="file" name="file" accept=".csv" required>
    <button class="btn outline" type="submit">استيراد CSV</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/customers/export">تصدير CSV</a>
  </form>
</div>

<table class="table" style="margin-top:10px">
  <thead><tr><th>#</th><th>الاسم</th><th>الهاتف</th><th>المنطقة</th><th>إدارة</th></tr></thead>
  <tbody>
  <?php foreach($customers as $c): ?>
    <tr>
      <td><?= $c['id'] ?></td>
      <td><?= Utils::h($c['name']) ?></td>
      <td><?= Utils::h($c['phone']) ?></td>
      <td><?= Utils::h($c['area'] ?? '-') ?></td>
      <td>
        <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/customers/edit?id=<?= $c['id'] ?>">تعديل</a>
        <form style="display:inline" method="post" action="<?= $cfg['APP_URL'] ?>/customers/delete" onsubmit="return confirm('تأكيد الحذف؟')">
          <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn danger" type="submit">حذف</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

put("views/customers/edit.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تعديل عميل</h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/customers/edit?id=<?= $customer['id'] ?>">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>الاسم</label><input class="input" name="name" value="<?= Utils::h($customer['name']) ?>" required>
  <label>الهاتف</label><input class="input" name="phone" value="<?= Utils::h($customer['phone']) ?>" required>
  <label>المنطقة</label><input class="input" name="area" value="<?= Utils::h($customer['area']) ?>">
  <div style="display:flex; gap:8px">
    <button class="btn" type="submit">تحديث</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/customers">رجوع</a>
  </div>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

/* ====== Views: Users ====== */
put("views/users/index.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>المستخدمون</h1>
<a class="btn" href="<?= $cfg['APP_URL'] ?>/users/create">إضافة مستخدم</a>
<table class="table" style="margin-top:10px">
  <thead><tr><th>#</th><th>اسم المستخدم</th><th>الدور</th><th>إدارة</th></tr></thead>
  <tbody>
    <?php foreach($users as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= Utils::h($u['username']) ?></td>
        <td><?= Utils::h($u['role']) ?></td>
        <td>
          <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/users/edit?id=<?= $u['id'] ?>">تعديل</a>
          <form style="display:inline" method="post" action="<?= $cfg['APP_URL'] ?>/users/delete" onsubmit="return confirm('تأكيد حذف المستخدم؟')">
            <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="btn danger" type="submit">حذف</button>
          </form>
        </td>
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

put("views/users/edit.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تعديل مستخدم</h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/users/edit?id=<?= $user['id'] ?>">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>اسم المستخدم</label><input class="input" name="username" value="<?= Utils::h($user['username']) ?>" required>
  <label>الدور</label>
  <select class="input" name="role" required>
    <option value="support_engineer" <?= $user['role']==='support_engineer'?'selected':'' ?>>مهندس دعم</option>
    <option value="field_engineer" <?= $user['role']==='field_engineer'?'selected':'' ?>>مهندس ميداني</option>
    <option value="manager" <?= $user['role']==='manager'?'selected':'' ?>>مدير</option>
  </select>
  <label>كلمة المرور (اتركها فارغة إن لم ترد تغييرها)</label><input class="input" type="password" name="password">
  <div style="display:flex; gap:8px">
    <button class="btn" type="submit">تحديث</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/users">رجوع</a>
  </div>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

/* ====== Views: Tickets - assign form ====== */
put("views/tickets/assign.php", <<<'PHP'
<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إسناد التذكرة #<?= $ticket['id'] ?></h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/tickets/assign">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
  <label>اختر مهندس ميداني</label>
  <select class="input" name="user_id" required>
    <?php foreach($engineers as $e): ?>
      <option value="<?= $e['id'] ?>"><?= Utils::h($e['username']) ?></option>
    <?php endforeach; ?>
  </select>
  <div style="display:flex; gap:8px; margin-top:10px">
    <button class="btn" type="submit">إسناد</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/tickets/show?id=<?= $ticket['id'] ?>">رجوع</a>
  </div>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
PHP);

/* ====== Tickets index: add quick assign link ====== */
if (file_exists(__DIR__."/views/tickets/index.php")) {
  $idx = file_get_contents(__DIR__."/views/tickets/index.php");
  if (strpos($idx, 'assignForm') === false) {
    $idx = str_replace('</td></tr>', '</td><td><a class="btn outline" href="<?= $cfg[\'APP_URL\'] ?>/tickets/assignForm?id=<?= $t[\'id\'] ?>">إسناد</a></td></tr>', $idx);
    put("views/tickets/index.php", $idx);
  }
}

/* ====== Routes: add customers edit/delete, users edit/delete, tickets assignForm ====== */
$routesPath = __DIR__ . "/public/index.php";
if (file_exists($routesPath)) {
  $routes = file_get_contents($routesPath);
  if (strpos($routes, "customers/edit") === false) {
    $routes = str_replace("// Customers", "// Customers\n$router->get('/customers/edit', array(CustomersController::class, 'edit'));\n$router->post('/customers/edit', array(CustomersController::class, 'edit'));\n$router->post('/customers/delete', array(CustomersController::class, 'delete'));", $routes);
  }
  if (strpos($routes, "users/edit") === false) {
    $routes = str_replace("// Users", "// Users\n$router->get('/users/edit', array(UsersController::class, 'edit'));\n$router->post('/users/edit', array(UsersController::class, 'edit'));\n$router->post('/users/delete', array(UsersController::class, 'delete'));", $routes);
  }
  if (strpos($routes, "tickets/assignForm") === false) {
    $routes = str_replace("// Tickets", "// Tickets\n$router->get('/tickets/assignForm', array(TicketsController::class, 'assignForm'));", $routes);
  }
  file_put_contents($routesPath, $routes);
  echo "Updated routes in public/index.php\n";
} else {
  echo "WARNING: public/index.php not found. Please add routes manually.\n";
}

echo "\n✅ Upgrade_2 applied. Test: /customers (edit/delete), /users (edit/delete), /tickets/assignForm?id=XX\n";