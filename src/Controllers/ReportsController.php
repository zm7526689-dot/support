<?php
class ReportsController {
  public function create(){
    Auth::requireRole(array('field_engineer','support_engineer')); global $pdo;
    $cfg = require __DIR__ . '/../../config/config.php';
    if(!Utils::checkCsrf($_POST['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF'); }
    $ticketId = (int)(isset($_POST['ticket_id']) ? $_POST['ticket_id'] : 0);
    $diagnosis = trim($_POST['diagnosis'] ?? ''); $action = trim($_POST['action_taken'] ?? '');
    if(!$ticketId || !$diagnosis || !$action){ http_response_code(400); exit('Missing fields'); }
    $dao = new ReportsDAO($pdo); $reportId = $dao->create($ticketId, Auth::user()['id'], $diagnosis, $action);

    // uploads
    if(!empty($_FILES['attachments']['tmp_name'])){
      foreach($_FILES['attachments']['tmp_name'] as $i => $tmp){
        if(!$tmp) continue;
        $type = mime_content_type($tmp);
        if(!in_array($type, $cfg['ALLOWED_UPLOADS'])) continue;
        $name = $_FILES['attachments']['name'][$i];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $safeName = 'r' . $reportId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        move_uploaded_file($tmp, $cfg['UPLOAD_DIR'] . '/' . $safeName);
        $fileType = (strpos($type,'image/')===0) ? 'image' : (in_array($type,array('text/plain','text/csv')) ? 'log' : (strpos($type,'pdf')!==false ? 'pdf' : 'csv'));
        $pdo->prepare('INSERT INTO report_attachments(report_id,file_path,file_type) VALUES (?,?,?)')->execute(array($reportId, $safeName, $fileType));
      }
    }
    if(Auth::user()['role']==='field_engineer'){
      $pdo->prepare('UPDATE tickets SET status="solved_field", updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute(array($ticketId));
    }
    Utils::redirect('tickets/show?id=' . $ticketId);
  }
}