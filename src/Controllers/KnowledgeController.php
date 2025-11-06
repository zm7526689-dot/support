<?php
class KnowledgeController {
  public function promote(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $reportId = (int)($_POST['report_id'] ?? 0);
    $r = $pdo->prepare('SELECT diagnosis, action_taken FROM ticket_reports WHERE id=?'); $r->execute(array($reportId)); $r = $r->fetch(PDO::FETCH_ASSOC);
    if(!$r) Utils::redirect('tickets/show?id=' . $ticketId);
    $summary = mb_strimwidth(($r['diagnosis'] ?? '') . ' / ' . ($r['action_taken'] ?? ''), 0, 240, '...');
    $t = $pdo->prepare('SELECT title FROM tickets WHERE id=?'); $t->execute(array($ticketId)); $title = $t->fetchColumn();
    $pdo->prepare('INSERT INTO knowledge_articles(title,summary,ticket_id,report_id) VALUES (?,?,?,?)')->execute(array($title, $summary, $ticketId, $reportId));
    Utils::audit($pdo, 'promote_knowledge', 'ticket', $ticketId, 'report_id=' . $reportId);
    Utils::redirect('search?q=' . urlencode($title));
  }
}