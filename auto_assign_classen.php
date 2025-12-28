<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!has_permission('admin')) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

if ($_POST['action'] == 'auto_assign') {
    // جلب الطلاب بدون صف
    $sql = "SELECT s.id, s.student_code, YEAR(s.birth_date) as birth_year
            FROM students s
            WHERE s.class_id IS NULL
            ORDER BY s.id";
    $result = $conn->query($sql);
   
    $updated_count = 0;
   
    while ($student = $result->fetch_assoc()) {
        // تحديد الصف حسب العمر
        $age = date('Y') - $student['birth_year'];
        $class_id = null;
       
        if ($age >= 4 && $age <= 5) $class_id = 1;   // الروضة
        elseif ($age >= 6 && $age <= 7) $class_id = 1; // الأول ابتدائي
        elseif ($age >= 7 && $age <= 8) $class_id = 3; // الثاني ابتدائي
        elseif ($age >= 8 && $age <= 9) $class_id = 13; // الثالث ابتدائي
        elseif ($age >= 9 && $age <= 10) $class_id = 16; // الرابع ابتدائي
        elseif ($age >= 10 && $age <= 11) $class_id = 19; // الخامس ابتدائي
        elseif ($age >= 11 && $age <= 12) $class_id = 22; // السادس ابتدائي
        else $class_id = 1; // افتراضي
       
        // تحديث الطالب
        $update_sql = "UPDATE students SET class_id = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $class_id, $student['id']);
        if ($stmt->execute()) $updated_count++;
        $stmt->close();
    }
   
    // تشغيل الإصلاح التلقائي
    $conn->query("CALL AutoFixClassLevels()");
   
    log_activity($_SESSION['user_id'], 'admin', 'الإصلاح التلقائي', "تم تعيين $updated_count طالب للصفوف");
   
    echo json_encode([
        'success' => true,
        'message' => "تم تعيين $updated_count طالب للصفوف بنجاح"
    ]);
}

$conn->close();
?>