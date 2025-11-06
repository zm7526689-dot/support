<?php
class EngineersController {

  // عرض المهندسين
  public function index(){
    Auth::requireRole(['support_engineer', 'manager']);
    global $pdo;
    $dao = new EngineersDAO($pdo);
    $engineers = $dao->list();
    include __DIR__ . '/../../views/engineers/index.php';
  }

  // إضافة مهندس
  public function create(){
    Auth::requireRole(['support_engineer']);
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      include __DIR__ . '/../../views/engineers/create.php';
      return;
    }

    if (!Utils::checkCsrf($_POST['csrf'] ?? '')) {
      http_response_code(400);
      exit('Bad CSRF');
    }

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');

    if (!$name || !$phone) {
      $error = 'الاسم ورقم الهاتف مطلوبان';
      include __DIR__ . '/../../views/engineers/create.php';
      return;
    }

    $dao = new EngineersDAO($pdo);
    $id = $dao->create($name, $phone, $specialty ?: null);

    Utils::audit($pdo, 'create_engineer', 'engineer', $id, $name);
    Utils::redirect('engineers');
  }

  // تعديل مهندس
  public function edit(){
    Auth::requireRole(['support_engineer']);
    global $pdo;
    $dao = new EngineersDAO($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $id = intval($_GET['id'] ?? 0);
      if (!$id) Utils::redirect('engineers');
      $engineer = $dao->findById($id);
      if (!$engineer) Utils::redirect('engineers');
      include __DIR__ . '/../../views/engineers/edit.php';
      return;
    }

    if (!Utils::checkCsrf($_POST['csrf'] ?? '')) {
      http_response_code(400);
      exit('Bad CSRF');
    }

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');

    if (!$id || !$name || !$phone) {
      $error = 'البيانات غير كاملة';
      $engineer = $dao->findById($id);
      include __DIR__ . '/../../views/engineers/edit.php';
      return;
    }

    $dao->update($id, $name, $phone, $specialty ?: null);
    Utils::audit($pdo, 'update_engineer', 'engineer', $id, $name);
    Utils::redirect('engineers');
  }

  // حذف مهندس
  public function delete(){
    Auth::requireRole(['support_engineer']);
    global $pdo;

    if (!Utils::checkCsrf($_POST['csrf'] ?? '')) {
      http_response_code(400);
      exit('Bad CSRF');
    }

    $id = intval($_POST['id'] ?? 0);
    if (!$id) Utils::redirect('engineers');

    $dao = new EngineersDAO($pdo);
    $dao->delete($id);
    Utils::audit($pdo, 'delete_engineer', 'engineer', $id, null);
    Utils::redirect('engineers');
  }
}
