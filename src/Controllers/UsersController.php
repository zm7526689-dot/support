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