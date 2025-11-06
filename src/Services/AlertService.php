<?php
class AlertService {
  public function __construct(private PDO $db){}
  public function overdueTickets($hours){
    $st = $this->db->prepare("SELECT id, title, updated_at FROM tickets WHERE status IN ('new','assigned') AND (julianday('now') - julianday(updated_at))*24 > ?");
    $st->execute(array($hours)); return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function repeatedFaults($threshold=2){
    $st = $this->db->query("SELECT problem_description AS key, COUNT(*) AS cnt FROM tickets GROUP BY key HAVING cnt>= ".(int)$threshold." ORDER BY cnt DESC");
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function email($to, $subject, $body){
    @mail($to, $subject, $body, "Content-Type: text/plain; charset=UTF-8");
  }
}