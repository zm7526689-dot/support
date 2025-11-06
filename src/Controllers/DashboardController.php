<?php
class DashboardController {
  public function index(){
    Auth::requireRole(array('support_engineer','manager','field_engineer')); global $pdo;
    $analytics = new AnalyticsService($pdo);
    $cfg = require __DIR__ . '/../../config/config.php';
    $sla = $analytics->slaBreaches($cfg['SLA_HOURS']);
    $freq = $analytics->frequentFaults(5);

    $stats = $pdo->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

    $engineers = $pdo->query("SELECT u.username, COUNT(t.id) as closed
                              FROM users u
                              LEFT JOIN tickets t ON u.id = t.assigned_to_user_id AND t.status IN ('solved_phone','solved_field')
                              WHERE u.role='field_engineer'
                              GROUP BY u.id")->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/dashboard.php';
  }
  public function search(){
    Auth::requireRole(array('support_engineer','manager','field_engineer')); global $pdo;
    $q = trim($_GET['q'] ?? ''); $res = array();
    if($q){ $svc = new KnowledgeService($pdo); $res = $svc->search($q); }
    include __DIR__ . '/../../views/search.php';
  }
}