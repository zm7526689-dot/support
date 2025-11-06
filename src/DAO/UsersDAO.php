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