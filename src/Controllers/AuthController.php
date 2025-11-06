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