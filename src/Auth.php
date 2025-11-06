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