<?php
class AnalyticsService {
  public function __construct(private PDO $db){}
  public function frequentFaults($limit=5){
    $sql = "SELECT substr(lower(problem_description),1,50) AS keypart, COUNT(*) AS cnt
            FROM tickets GROUP BY keypart ORDER BY cnt DESC LIMIT ?";
    $st = $this->db->prepare($sql); $st->execute(array($limit));
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  public function slaBreaches($hours){
    $sql = "SELECT * FROM tickets WHERE status IN ('new','assigned')
            AND (julianday('now') - julianday(updated_at)) * 24 > ?";
    $st = $this->db->prepare($sql); $st->execute(array($hours));
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}