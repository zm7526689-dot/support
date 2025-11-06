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