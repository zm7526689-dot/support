<?php
class AuthController {
  public function login(){
    global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include APP_ROOT.'/views/auth/login.php'; return; }

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $dao = new UsersDAO($pdo);
    $user = $dao->findByUsername($username);

    if(!$user || !password_verify($password, $user['password'])){
      $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
      include APP_ROOT.'/views/auth/login.php'; return;
    }

    Auth::login($user);
    Utils::redirect('dashboard');
  }

  public function logout(){
    Auth::logout();
    Utils::redirect('login.php');
  }
}
?>
