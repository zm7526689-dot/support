<?php
class TicketsController {
  public function index(){
    Auth::requireRole(array('support_engineer','field_engineer','manager'));
    global $pdo; $dao = new TicketsDAO($pdo);
    $tickets = $dao->listForUser(Auth::user());
    include __DIR__ . '/../../views/tickets/index.php';
  }
  public function create(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/tickets/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $phone = trim($_POST['phone'] ?? ''); $name = trim($_POST['name'] ?? ''); $area = trim($_POST['area'] ?? '');
    $title = trim($_POST['title'] ?? ''); $desc = trim($_POST['problem_description'] ?? '');
    if(!$phone || !$title || !$desc){ $error = 'الهاتف والعنوان والوصف مطلوبة'; include __DIR__ . '/../../views/tickets/create.php'; return; }
    $cDao = new CustomersDAO($pdo); $cust = $cDao->findByPhone($phone);
    if(!$cust){ $cid = $cDao->create($name ? $name : 'عميل بدون اسم', $phone, $area ? $area : null); $cust = array('id'=>$cid); }
    $tDao = new TicketsDAO($pdo);
    $id = $tDao->create(array(
      'customer_id' => $cust['id'],
      'created_by_user_id' => Auth::user()['id'],
      'title' => $title, 'problem_description' => $desc,
    ));
    Utils::audit($pdo, 'create_ticket', 'ticket', $id, $title);
    Utils::redirect('tickets/show?id=' . $id);
  }
  public function assign(){
    Auth::requireRole(array('support_engineer')); global $pdo; $dao = new TicketsDAO($pdo);
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $ticketId = (int)$_POST['ticket_id']; $userId = (int)$_POST['user_id'];
    $dao->assign($ticketId, $userId);
    Utils::audit($pdo, 'assign_ticket', 'ticket', $ticketId, 'to_user='.$userId);
    Utils::redirect('tickets/show?id=' . $ticketId);
  }
  public function assignForm(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    $id = (int)($_GET['id'] ?? 0);
    $ticket = (new TicketsDAO($pdo))->find($id);
    if(!$ticket){ http_response_code(404); exit('Not found'); }
    $engineers = $pdo->query("SELECT id, username FROM users WHERE role='field_engineer'")->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/tickets/assign.php';
  }
  public function show(){
    Auth::requireRole(array('support_engineer','field_engineer','manager')); global $pdo;
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    $ticket = $pdo->prepare('SELECT * FROM tickets WHERE id=?'); $ticket->execute(array($id)); $ticket = $ticket->fetch(PDO::FETCH_ASSOC);
    if(!$ticket){ http_response_code(404); exit('Not found'); }
    $customer = $pdo->prepare('SELECT * FROM customers WHERE id=?'); $customer->execute(array((int)$ticket['customer_id'])); $customer = $customer->fetch(PDO::FETCH_ASSOC);
    $reports = $pdo->prepare('SELECT * FROM ticket_reports WHERE ticket_id=? ORDER BY created_at DESC'); $reports->execute(array($id)); $reports = $reports->fetchAll(PDO::FETCH_ASSOC);
    include __DIR__ . '/../../views/tickets/show.php';
  }
}