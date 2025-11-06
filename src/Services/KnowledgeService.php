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