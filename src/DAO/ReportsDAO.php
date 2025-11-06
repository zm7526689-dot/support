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