<?php
class CustomersController {
  public function index(){
    Auth::requireRole(array('support_engineer','manager'));
    global $pdo; $dao = new CustomersDAO($pdo); $customers = $dao->list();
    include __DIR__ . '/../../views/customers/index.php';
  }

  public function create(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if($_SERVER['REQUEST_METHOD']==='GET'){ include __DIR__ . '/../../views/customers/create.php'; return; }
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $area = trim($_POST['area'] ?? '');
    if(!$name || !$phone){ $error = 'الاسم والهاتف مطلوبان'; include __DIR__ . '/../../views/customers/create.php'; return; }
    $dao = new CustomersDAO($pdo); $id = $dao->create($name, $phone, $area ? $area : null);
    Utils::audit($pdo, 'create_customer', 'customer', $id, $phone);
    Utils::redirect('customers');
  }

  public function edit(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    $dao = new CustomersDAO($pdo);
    if($_SERVER['REQUEST_METHOD']==='GET'){
      $id = intval($_GET['id'] ?? 0);
      if(!$id) { Utils::redirect('customers'); }
      $customer = $dao->findById($id);
      if(!$customer){ Utils::redirect('customers'); }
      include __DIR__ . '/../../views/customers/edit.php'; return;
    }
    // POST - update
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $area = trim($_POST['area'] ?? '');
    if(!$id || !$name || !$phone){ $error = 'الاسم والهاتف مطلوبان'; $customer = $dao->findById($id); include __DIR__ . '/../../views/customers/edit.php'; return; }
    $dao->update($id, $name, $phone, $area ? $area : null);
    Utils::audit($pdo, 'update_customer', 'customer', $id, $phone);
    Utils::redirect('customers');
  }

  public function delete(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $id = intval($_POST['id'] ?? 0); if(!$id) Utils::redirect('customers');
    $dao = new CustomersDAO($pdo);
    $dao->delete($id);
    Utils::audit($pdo, 'delete_customer', 'customer', $id, null);
    Utils::redirect('customers');
  }

  public function import(){
    Auth::requireRole(array('support_engineer')); global $pdo;
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    if(empty($_FILES['file']['tmp_name'])) Utils::redirect('customers');
    $fh = fopen($_FILES['file']['tmp_name'], 'r'); $dao = new CustomersDAO($pdo);
    $count=0; while(($row = fgetcsv($fh))!==false){ if(count($row)<2) continue; $dao->create(trim($row[0]), trim($row[1]), isset($row[2]) ? trim($row[2]) : null); $count++; }
    fclose($fh); Utils::audit($pdo, 'import_customers', 'customers', null, 'count='.$count);
    Utils::redirect('customers');
  }

  public function export(){
    Auth::requireRole(array('support_engineer','manager')); global $pdo;
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="customers.csv"');
    $list = (new CustomersDAO($pdo))->list();
    $out = fopen('php://output', 'w'); foreach($list as $c){ fputcsv($out, array($c['name'],$c['phone'],$c['area'])); } fclose($out);
  }
}
