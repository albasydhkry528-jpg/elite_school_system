<?php
// اتصال قاعدة البيانات
$host = '127.0.0.1';
$dbname = 'elite_school_system';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
   
    // التحقق من الاتصال
    if ($conn->connect_error) {
        throw new Exception("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
    }
   
    // تعيين ترميز الأحرف
    $conn->set_charset("utf8mb4");
   
} catch (Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>