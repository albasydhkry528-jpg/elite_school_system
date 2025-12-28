<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!has_permission('admin')) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = (int)$_POST['student_id'];
    $class_id = (int)$_POST['class_id'];
    $section = clean_input($_POST['section'] ?? '');
   
    // تحديث الطالب
    $sql = "UPDATE students SET class_id = ?, section = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $class_id, $section, $student_id);
   
    if ($stmt->execute()) {
        // تسجيل النشاط
        log_activity($_SESSION['user_id'], 'admin', 'تحديث صف طالب', "تم تحديث الصف للطالب ID: $student_id");
       
        echo json_encode(['success' => true, 'message' => 'تم تحديث الصف بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات']);
    }
   
    $stmt->close();
}

$conn->close();
?>