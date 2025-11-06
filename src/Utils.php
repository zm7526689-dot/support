<?php
class Utils {
  public static function csrfToken(){
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
  }

  public static function checkCsrf($token){
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
  }

  public static function redirect($path){
    $cfg = require APP_ROOT . '/config/config.php';
    header("Location: {$cfg['APP_URL']}/$path");
    exit;
  }

  public static function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

  public static function audit(PDO $pdo, $action, $entity, $id=null, $details=null){
    $pdo->prepare('INSERT INTO audit_log(action, entity, entity_id, details) VALUES(?,?,?,?)')
        ->execute([$action,$entity,$id,$details]);
  }
}

class Auth {
  public static function login($user){ $_SESSION['user']=$user; }
  public static function logout(){ unset($_SESSION['user']); }
  public static function user(){ return $_SESSION['user'] ?? null; }
  public static function check(){ return isset($_SESSION['user']); }
  public static function requireRole($roles){
    if(!self::check() || !in_array($_SESSION['user']['role'],$roles)){
      header('Location: login.php'); exit;
    }
  }
}
?>
