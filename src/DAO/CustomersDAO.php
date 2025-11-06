<?php
class CustomersDAO {
  public function __construct(private PDO $db){}

  // البحث عن عميل عبر رقم الهاتف
  public function findByPhone($phone){
    $st = $this->db->prepare('SELECT * FROM customers WHERE phone=?');
    $st->execute(array($phone));
    return $st->fetch(PDO::FETCH_ASSOC);
  }

  // إنشاء عميل جديد
  public function create($name, $phone, $area = null){
    $st = $this->db->prepare('INSERT INTO customers(name, phone, area) VALUES(?, ?, ?)');
    $st->execute(array($name, $phone, $area));
    return $this->db->lastInsertId();
  }

  // جلب قائمة جميع العملاء
  public function list(){
    $st = $this->db->query('SELECT * FROM customers ORDER BY created_at DESC');
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  // جلب عميل واحد عبر الـ ID
  public function findById($id){
    $st = $this->db->prepare('SELECT * FROM customers WHERE id=?');
    $st->execute(array($id));
    return $st->fetch(PDO::FETCH_ASSOC);
  }

  // تحديث بيانات عميل
  public function update($id, $name, $phone, $area = null){
    $st = $this->db->prepare('UPDATE customers SET name=?, phone=?, area=? WHERE id=?');
    $st->execute(array($name, $phone, $area, $id));
    return $st->rowCount();
  }

  // حذف عميل
  public function delete($id){
    $st = $this->db->prepare('DELETE FROM customers WHERE id=?');
    $st->execute(array($id));
    return $st->rowCount();
  }
}
