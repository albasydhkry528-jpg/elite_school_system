<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// التحقق من صلاحيات المستخدم
if (!has_permission('admin')) {
    header("Location: ../login.php");
    exit();
}

// ============ معالجة حذف المعلم ============
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $teacher_id = clean_input($_GET['delete_id']);
    
    // الحصول على user_id للمعلم أولاً
    $sql = "SELECT user_id FROM teachers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];
        
        // بدء المعاملة
        $conn->begin_transaction();
        
        try {
            // حذف سجلات teacher_classes أولاً
            $sql1 = "DELETE FROM teacher_classes WHERE teacher_id = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("i", $teacher_id);
            $stmt1->execute();
            
            // حذف سجلات classes التي يرتبط بها المعلم
            $sql2 = "UPDATE classes SET teacher_id = NULL WHERE teacher_id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("i", $teacher_id);
            $stmt2->execute();
            
            // حذف المعلم من جدول teachers
            $sql3 = "DELETE FROM teachers WHERE id = ?";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param("i", $teacher_id);
            $stmt3->execute();
            
            // حذف المستخدم من جدول users
            $sql4 = "DELETE FROM users WHERE id = ?";
            $stmt4 = $conn->prepare($sql4);
            $stmt4->bind_param("i", $user_id);
            $stmt4->execute();
            
            // تأكيد المعاملة
            $conn->commit();
            
            $_SESSION['success_msg'] = "تم حذف المعلم بنجاح!";
            
        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة الخطأ
            $conn->rollback();
            $_SESSION['error_msg'] = "حدث خطأ أثناء حذف المعلم: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "المعلم غير موجود!";
    }
    
    // البقاء في نفس الصفحة بدلاً من إعادة التوجيه
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============ معالجة تحديث المعلم ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
    $teacher_id = clean_input($_POST['teacher_id']);
    $full_name = clean_input($_POST['full_name']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $national_id = clean_input($_POST['national_id']);
    $birth_date = clean_input($_POST['birth_date']);
    $qualification = clean_input($_POST['qualification']);
    $specialization = clean_input($_POST['specialization']);
    $hire_date = clean_input($_POST['hire_date']);
    $employment_type = clean_input($_POST['employment_type']);
    $salary = clean_input($_POST['salary']);
    $experience_years = clean_input($_POST['experience_years']);
    $user_status = clean_input($_POST['user_status']);
    
    // الحصول على user_id للمعلم
    $sql = "SELECT user_id FROM teachers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];
        
        // بدء المعاملة
        $conn->begin_transaction();
        
        try {
            // تحديث جدول users
            $sql1 = "UPDATE users SET 
                    full_name = ?, 
                    email = ?, 
                    phone = ?, 
                    status = ? 
                    WHERE id = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("ssssi", $full_name, $email, $phone, $user_status, $user_id);
            $stmt1->execute();
            
            // تحديث جدول teachers
            $sql2 = "UPDATE teachers SET 
                    national_id = ?, 
                    birth_date = ?, 
                    qualification = ?, 
                    specialization = ?, 
                    hire_date = ?, 
                    employment_type = ?, 
                    salary = ?, 
                    experience_years = ? 
                    WHERE id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ssssssisi", 
                $national_id, $birth_date, $qualification, $specialization, 
                $hire_date, $employment_type, $salary, $experience_years, $teacher_id);
            $stmt2->execute();
            
            // تأكيد المعاملة
            $conn->commit();
            
            $_SESSION['success_msg'] = "تم تحديث بيانات المعلم بنجاح!";
            
        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة الخطأ
            $conn->rollback();
            $_SESSION['error_msg'] = "حدث خطأ أثناء تحديث بيانات المعلم: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "المعلم غير موجود!";
    }
    
    // البقاء في نفس الصفحة بدلاً من إعادة التوجيه
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============ معالجة البحث فقط ============
$search = '';
$where_clause = '';
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = clean_input($_GET['search']);
    $where_clause = "WHERE (t.teacher_code LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR t.specialization LIKE ?)";
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term, $search_term, $search_term];
    $types = "sssss";
}

// ============ جلب بيانات المعلمين ============
$sql = "SELECT
            t.id,
            t.teacher_code,
            t.national_id,
            t.birth_date,
            t.qualification,
            t.specialization,
            t.hire_date,
            t.employment_type,
            t.salary,
            t.experience_years,
            t.user_id,
            u.full_name,
            u.email,
            u.phone,
            u.status as user_status,
            u.created_at
        
        FROM teachers t
        INNER JOIN users u ON t.user_id = u.id
        $where_clause
        ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ============ جلب الصفوف لكل معلم ============
$teachers_with_classes = [];
$teacher_count = 0;

while ($teacher = $result->fetch_assoc()) {
    $teacher_id = $teacher['id'];
   
    // محاولة الطريقة الأولى: البحث في جدول classes مباشرة
    $classes_sql = "SELECT CONCAT(c.grade, ' ', c.class_name, ' ', COALESCE(c.section, '')) as class_name
                   FROM classes c
                   WHERE c.teacher_id = ?";
   
    $classes_stmt = $conn->prepare($classes_sql);
    $classes_stmt->bind_param("i", $teacher_id);
    $classes_stmt->execute();
    $classes_result = $classes_stmt->get_result();
   
    $classes = [];
   
    if ($classes_result->num_rows > 0) {
        // الطريقة الأولى نجحت (teacher_id مخزن مباشرة في classes)
        while ($class_row = $classes_result->fetch_assoc()) {
            $classes[] = $class_row['class_name'];
        }
    } else {
        // محاولة الطريقة الثانية: البحث في جدول teacher_classes
        $classes_sql2 = "SELECT CONCAT(c.grade, ' ', c.class_name, ' ', COALESCE(c.section, '')) as class_name
                        FROM teacher_classes tc
                        INNER JOIN classes c ON tc.class_id = c.id
                        WHERE tc.teacher_id = ?";
       
        $classes_stmt2 = $conn->prepare($classes_sql2);
        $classes_stmt2->bind_param("i", $teacher_id);
        $classes_stmt2->execute();
        $classes_result2 = $classes_stmt2->get_result();
       
        while ($class_row = $classes_result2->fetch_assoc()) {
            $classes[] = $class_row['class_name'];
        }
       
        $classes_stmt2->close();
    }
   
    $teacher['teaching_classes'] = $classes;
    $teachers_with_classes[] = $teacher;
    $teacher_count++;
   
    $classes_stmt->close();
}

// ============ رسائل النجاح/الخطأ ============
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض المعلمين - نظام النخبة التعليمي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --success-color: #00b894;
            --warning-color: #fdcb6e;
            --danger-color: #e17055;
            --dark-color: #2d3436;
            --light-color: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            --gradient-warning: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            --gradient-danger: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            --shadow-soft: 0 5px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.12);
            --shadow-strong: 0 12px 35px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --border-radius: 15px;
            --border-radius-sm: 10px;
            --border-radius-lg: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e3edf7 100%);
            min-height: 100vh;
            color: var(--dark-color);
            overflow-x: hidden;
            padding: 20px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .glass-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-5px);
        }

        /* Header Styles */
        .main-header {
            background: var(--gradient-success);
            border-radius: var(--border-radius-lg);
            padding: 35px 40px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-strong);
            position: relative;
            overflow: hidden;
        }

        .main-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }

        .header-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .logo-text h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .logo-text p {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .header-btn {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            padding: 14px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            backdrop-filter: blur(10px);
        }

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            color: white;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-sm);
            padding: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.25);
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 800;
            color: white;
            margin-bottom: 5px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .stat-label {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* Search Section */
        .search-section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .search-wrapper {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 18px 25px;
            padding-right: 70px;
            border: 2px solid rgba(67, 97, 238, 0.2);
            border-radius: 15px;
            font-size: 1.1rem;
            transition: var(--transition);
            background: rgba(248, 249, 250, 0.7);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
            background: white;
            transform: translateY(-2px);
        }

        .search-icon {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.4rem;
            opacity: 0.8;
        }

        /* Teachers Table Container */
        .table-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-strong);
            margin-bottom: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .table-header {
            background: var(--gradient-primary);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid rgba(255, 255, 255, 0.2);
        }

        .table-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 0;
        }

        .table-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Table Styles */
        .teachers-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .teachers-table thead {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .teachers-table thead th {
            padding: 20px 15px;
            text-align: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            border-bottom: 3px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }

        .teachers-table tbody tr {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            background: white;
        }

        .teachers-table tbody tr:nth-child(even) {
            background: rgba(248, 249, 250, 0.5);
        }

        .teachers-table tbody tr:hover {
            background: linear-gradient(90deg, rgba(0, 176, 155, 0.05) 0%, rgba(150, 201, 61, 0.05) 100%);
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(0, 176, 155, 0.1);
        }

        .teachers-table tbody td {
            padding: 20px 15px;
            text-align: center;
            vertical-align: middle;
            border-left: 1px solid rgba(0, 0, 0, 0.05);
        }

        .teachers-table tbody td:first-child {
            border-left: none;
        }

        /* Teacher Profile Card */
        .teacher-profile-card {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(0, 176, 155, 0.1) 0%, rgba(150, 201, 61, 0.1) 100%);
            border-radius: var(--border-radius-sm);
            border: 1px solid rgba(0, 176, 155, 0.2);
        }

        .teacher-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #00b09b;
            box-shadow: 0 4px 15px rgba(0, 176, 155, 0.3);
            transition: var(--transition);
        }

        .teacher-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 176, 155, 0.4);
        }

        .teacher-basic-info {
            flex: 1;
            text-align: right;
        }

        .teacher-name {
            font-weight: 800;
            color: var(--dark-color);
            font-size: 1.3rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .teacher-contact {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.9rem;
            color: #555;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        /* Info Cards */
        .info-card {
            background: white;
            border-radius: var(--border-radius-sm);
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(67, 97, 238, 0.2);
        }

        .info-card-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .info-card-title {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1rem;
        }

        .info-item-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 0.85rem;
            color: #777;
            font-weight: 600;
            text-align: right;
        }

        .info-value {
            font-size: 0.95rem;
            color: #333;
            font-weight: 700;
            text-align: right;
            padding: 8px 12px;
            background: rgba(248, 249, 250, 0.5);
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .info-value.highlight {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .info-value.success {
            background: var(--gradient-success);
            color: white;
            box-shadow: 0 3px 10px rgba(0, 176, 155, 0.3);
        }

        .info-value.warning {
            background: var(--gradient-warning);
            color: #333;
            box-shadow: 0 3px 10px rgba(253, 203, 110, 0.3);
        }

        /* Class Badges */
        .class-badges-container {
            padding: 10px;
        }

        .class-badge {
            display: inline-block;
            background: var(--gradient-primary);
            color: white;
            padding: 10px 18px;
            margin: 5px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
            text-align: center;
            min-width: 120px;
        }

        .class-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }

        .no-classes {
            background: rgba(240, 240, 240, 0.8);
            color: #666;
            padding: 12px 20px;
            border-radius: 20px;
            font-style: italic;
            text-align: center;
            font-size: 0.9rem;
        }

        /* Status Badge */
        .status-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        .status-badge {
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
            min-width: 120px;
            justify-content: center;
        }

        .status-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .status-active {
            background: var(--gradient-success);
            color: white;
        }

        .status-inactive {
            background: var(--gradient-danger);
            color: white;
        }

        .status-suspended {
            background: var(--gradient-warning);
            color: #333;
        }

        /* Action Buttons */
        .action-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            justify-content: center;
            height: 100%;
        }

        .btn-action {
            padding: 12px 25px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            color: white;
            box-shadow: var(--shadow-soft);
            justify-content: center;
            min-width: 140px;
        }

        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .btn-edit {
            background: var(--gradient-primary);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4090 100%);
        }

        .btn-delete {
            background: var(--gradient-danger);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #e76c7c 0%, #e8a798 100%);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            position: fixed;
            top: 50%;
            right: 50%;
            transform: translate(50%, -50%);
            background: white;
            padding: 30px;
            border-radius: var(--border-radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: var(--shadow-strong);
            animation: slideInDown 0.4s ease;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            transition: var(--transition);
            padding: 5px;
        }

        .modal-close:hover {
            color: var(--danger-color);
            transform: rotate(90deg);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            text-align: right;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
            text-align: right;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
            cursor: pointer;
            text-align: right;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn-submit {
            background: var(--gradient-success);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .btn-cancel {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e0e0e0;
            padding: 12px 30px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        /* Delete Confirmation Modal */
        .delete-modal {
            text-align: center;
            padding: 30px;
        }

        .delete-icon {
            font-size: 4rem;
            color: var(--danger-color);
            margin-bottom: 20px;
            animation: pulse 1.5s infinite;
        }

        .delete-text {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .delete-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-confirm-delete {
            background: var(--gradient-danger);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-confirm-delete:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        /* Messages */
        .alert-message {
            padding: 20px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            animation: slideInDown 0.5s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow-soft);
            border: none;
            position: relative;
        }

        .alert-close {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.7;
            transition: var(--transition);
        }

        .alert-close:hover {
            opacity: 1;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 25px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .empty-state h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #888;
            font-weight: 600;
        }

        .empty-state p {
            font-size: 1.1rem;
            color: #aaa;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Table Wrapper */
        .table-responsive {
            position: relative;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4090 100%);
        }

        /* Floating Animation */
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .teachers-table {
                min-width: 100%;
            }
           
            .header-content {
                flex-direction: column;
                text-align: center;
            }
           
            .logo-container {
                justify-content: center;
            }
           
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
           
            .info-item-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
           
            .main-header {
                padding: 25px 20px;
            }
           
            .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }
           
            .logo-text h1 {
                font-size: 1.6rem;
            }
           
            .stats-container {
                grid-template-columns: 1fr;
            }
           
            .table-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 20px;
            }
           
            .teacher-profile-card {
                flex-direction: column;
                text-align: center;
            }
           
            .teacher-basic-info {
                text-align: center;
            }
           
            .teacher-name {
                justify-content: center;
            }
           
            .contact-item {
                justify-content: center;
            }
           
            .btn-action {
                min-width: 100%;
            }
           
            .modal-content {
                width: 95%;
                padding: 20px;
            }
           
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <header class="main-header">
            <div class="header-content">
                <div class="logo-container">
                    <div class="logo-icon floating">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="logo-text">
                        <h1>إدارة المعلمين</h1>
                        <p>نظام النخبة التعليمي المتكامل</p>
                    </div>
                </div>
            
                <button class="header-btn hover-lift" onclick="window.location.href='users.php'">
                    <i class="fas fa-home"></i>
                    العودة إلى لوحة التحكم
                </button>
            </div>
        
            <div class="stats-container">
                <div class="stat-card hover-lift">
                    <div class="stat-number"><?php echo $teacher_count; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-users"></i> إجمالي المعلمين
                    </div>
                </div>
                <div class="stat-card hover-lift">
                    <?php
                    $avg_salary_sql = "SELECT AVG(salary) as avg_salary FROM teachers WHERE salary IS NOT NULL";
                    $avg_result = $conn->query($avg_salary_sql);
                    $avg_salary = $avg_result->fetch_assoc()['avg_salary'] ?? 0;
                    ?>
                    <div class="stat-number"><?php echo number_format($avg_salary, 0); ?> ر.س</div>
                    <div class="stat-label">
                        <i class="fas fa-money-bill-wave"></i> متوسط الرواتب
                    </div>
                </div>
                <div class="stat-card hover-lift">
                    <?php
                    $teachers_with_classes_count = 0;
                    foreach ($teachers_with_classes as $teacher) {
                        if (!empty($teacher['teaching_classes'])) {
                            $teachers_with_classes_count++;
                        }
                    }
                    ?>
                    <div class="stat-number"><?php echo $teachers_with_classes_count; ?></div>
                    <div class="stat-label">
                        <i class="fas fa-chalkboard"></i> معلمون نشيطون
                    </div>
                </div>
                <div class="stat-card hover-lift">
                    <?php
                    $total_exp_sql = "SELECT SUM(experience_years) as total_exp FROM teachers";
                    $exp_result = $conn->query($total_exp_sql);
                    $total_exp = $exp_result->fetch_assoc()['total_exp'] ?? 0;
                    ?>
                    <div class="stat-number"><?php echo $total_exp; ?>+</div>
                    <div class="stat-label">
                        <i class="fas fa-star"></i> سنوات الخبرة
                    </div>
                </div>
            </div>
        </header>

        <!-- Messages -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert-message alert-success" id="successMessage">
                <i class="fas fa-check-circle fa-lg"></i>
                <span class="ms-2"><?php echo $success_msg; ?></span>
                <button class="alert-close" onclick="closeMessage('successMessage')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
    
        <?php if (!empty($error_msg)): ?>
            <div class="alert-message alert-error" id="errorMessage">
                <i class="fas fa-exclamation-circle fa-lg"></i>
                <span class="ms-2"><?php echo $error_msg; ?></span>
                <button class="alert-close" onclick="closeMessage('errorMessage')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-section glass-card">
            <div class="search-wrapper">
                <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="searchForm">
                    <input type="text"
                           name="search"
                           class="search-input"
                           placeholder="🔍 ابحث عن معلم بالاسم، الكود، التخصص، البريد الإلكتروني أو الهاتف..."
                           value="<?php echo htmlspecialchars($search ?? ''); ?>"
                           autocomplete="off">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </form>
            </div>
        </div>

        <!-- Teachers Table -->
        <div class="table-container glass-card">
            <div class="table-header">
                <h2>
                    <i class="fas fa-list-alt"></i>
                    قائمة المعلمين
                </h2>
                <div class="table-count">
                    <i class="fas fa-user-check me-2"></i>
                    <?php echo $teacher_count; ?> معلم
                </div>
            </div>
        
            <?php if ($teacher_count > 0): ?>
                <div class="table-responsive">
                    <table class="teachers-table">
                        <thead>
                            <tr>
                                <th width="25%">الملف الشخصي</th>
                                <th width="20%">المعلومات الأكاديمية</th>
                                <th width="20%">المعلومات الوظيفية</th>
                                <th width="15%">الصفوف المشرفة</th>
                                <th width="10%">الحالة</th>
                                <th width="10%">العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers_with_classes as $teacher): ?>
                                <?php
                                // تنظيف البيانات
                                $full_name = !empty($teacher['full_name']) ? htmlspecialchars($teacher['full_name']) : 'غير محدد';
                                $email = !empty($teacher['email']) ? htmlspecialchars($teacher['email']) : 'غير محدد';
                                $phone = !empty($teacher['phone']) ? htmlspecialchars($teacher['phone']) : 'غير محدد';
                                $teacher_code = !empty($teacher['teacher_code']) ? htmlspecialchars($teacher['teacher_code']) : 'غير محدد';
                                $national_id = !empty($teacher['national_id']) ? htmlspecialchars($teacher['national_id']) : 'غير محدد';
                                $birth_date = !empty($teacher['birth_date']) ? date('d/m/Y', strtotime($teacher['birth_date'])) : 'غير محدد';
                                $qualification = !empty($teacher['qualification']) ? htmlspecialchars($teacher['qualification']) : 'غير محدد';
                                $specialization = !empty($teacher['specialization']) ? htmlspecialchars($teacher['specialization']) : 'غير محدد';
                                $employment_type = !empty($teacher['employment_type']) ? htmlspecialchars($teacher['employment_type']) : 'غير محدد';
                                $salary = !empty($teacher['salary']) ? number_format($teacher['salary'], 0) . ' ر.س' : 'غير محدد';
                                $experience_years = !empty($teacher['experience_years']) ? $teacher['experience_years'] . ' سنوات' : '0 سنوات';
                                $hire_date = !empty($teacher['hire_date']) ? date('d/m/Y', strtotime($teacher['hire_date'])) : 'غير محدد';
                                $teaching_classes = $teacher['teaching_classes'] ?? [];
                               
                                // حساب العمر
                                $age = 'غير محدد';
                                if (!empty($teacher['birth_date'])) {
                                    $birth_date_obj = new DateTime($teacher['birth_date']);
                                    $today = new DateTime();
                                    $age_interval = $today->diff($birth_date_obj);
                                    $age = $age_interval->y . ' سنة';
                                }
                               
                                // حساب مدة العمل
                                $work_duration = 'غير محدد';
                                if (!empty($teacher['hire_date'])) {
                                    $hire_date_obj = new DateTime($teacher['hire_date']);
                                    $today = new DateTime();
                                    $interval = $today->diff($hire_date_obj);
                                    if ($interval->y > 0) {
                                        $work_duration = $interval->y . ' سنوات';
                                    } elseif ($interval->m > 0) {
                                        $work_duration = $interval->m . ' أشهر';
                                    } else {
                                        $work_duration = 'أقل من شهر';
                                    }
                                }
                                ?>
                            
                                <tr>
                                    <!-- الملف الشخصي -->
                                    <td>
                                        <div class="teacher-profile-card">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=00b09b&color=fff&size=128&font-size=0.5&bold=true"
                                                 alt="صورة المعلم"
                                                 class="teacher-avatar">
                                            <div class="teacher-basic-info">
                                                <div class="teacher-name">
                                                    <?php echo $full_name; ?>
                                                    <span class="teacher-code">
                                                        <i class="fas fa-hashtag"></i> <?php echo $teacher['id']; ?>
                                                    </span>
                                                </div>
                                                <div class="teacher-contact">
                                                    <div class="contact-item">
                                                        <i class="fas fa-envelope"></i>
                                                        <span><?php echo $email; ?></span>
                                                    </div>
                                                    <?php if (!empty($phone)): ?>
                                                    <div class="contact-item">
                                                        <i class="fas fa-phone"></i>
                                                        <span><?php echo $phone; ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="contact-item">
                                                        <i class="fas fa-id-card"></i>
                                                        <span><?php echo $national_id; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                
                                    <!-- المعلومات الأكاديمية -->
                                    <td>
                                        <div class="info-card">
                                            <div class="info-card-header">
                                                <div class="info-card-icon">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </div>
                                                <div class="info-card-title">المعلومات الأكاديمية</div>
                                            </div>
                                            <div class="info-item-grid">
                                                <div class="info-item">
                                                    <span class="info-label">الكود:</span>
                                                    <span class="info-value highlight"><?php echo $teacher_code; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">المؤهل:</span>
                                                    <span class="info-value success"><?php echo $qualification; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">التخصص:</span>
                                                    <span class="info-value highlight"><?php echo $specialization; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">الخبرة:</span>
                                                    <span class="info-value warning"><?php echo $experience_years; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">الميلاد:</span>
                                                    <span class="info-value"><?php echo $birth_date; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">العمر:</span>
                                                    <span class="info-value success"><?php echo $age; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                
                                    <!-- المعلومات الوظيفية -->
                                    <td>
                                        <div class="info-card">
                                            <div class="info-card-header">
                                                <div class="info-card-icon">
                                                    <i class="fas fa-briefcase"></i>
                                                </div>
                                                <div class="info-card-title">المعلومات الوظيفية</div>
                                            </div>
                                            <div class="info-item-grid">
                                                <div class="info-item">
                                                    <span class="info-label">تاريخ التعيين:</span>
                                                    <span class="info-value success"><?php echo $hire_date; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">مدة العمل:</span>
                                                    <span class="info-value highlight"><?php echo $work_duration; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">نوع التوظيف:</span>
                                                    <span class="info-value success"><?php echo $employment_type; ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">الراتب:</span>
                                                    <span class="info-value highlight"><?php echo $salary; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                
                                    <!-- الصفوف المشرفة -->
                                    <td>
                                        <div class="class-badges-container">
                                            <?php if (!empty($teaching_classes)): ?>
                                                <?php foreach ($teaching_classes as $class): ?>
                                                    <div class="class-badge">
                                                        <i class="fas fa-chalkboard-teacher"></i>
                                                        <?php echo htmlspecialchars($class); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="no-classes">
                                                    <i class="fas fa-times-circle"></i>
                                                    لا يوجد صفوف حالياً
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                
                                    <!-- الحالة -->
                                    <td>
                                        <div class="status-container">
                                            <?php if (!empty($teacher['user_status'])): ?>
                                                <?php if ($teacher['user_status'] == 'active'): ?>
                                                    <span class="status-badge status-active">
                                                        <i class="fas fa-check-circle"></i>
                                                        نشط
                                                    </span>
                                                <?php elseif ($teacher['user_status'] == 'inactive'): ?>
                                                    <span class="status-badge status-inactive">
                                                        <i class="fas fa-times-circle"></i>
                                                        غير نشط
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge status-suspended">
                                                        <i class="fas fa-ban"></i>
                                                        موقوف
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">
                                                    غير محدد
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                
                                    <!-- العمليات -->
                                    <td>
                                        <div class="action-container">
                                            <button class="btn-action btn-edit" 
                                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($teacher)); ?>)">
                                                <i class="fas fa-edit"></i>
                                                تعديل
                                            </button>
                                            <button class="btn-action btn-delete" 
                                                    onclick="confirmDelete(<?php echo $teacher['id']; ?>, '<?php echo addslashes($full_name); ?>')">
                                                <i class="fas fa-trash"></i>
                                                حذف
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>لا توجد بيانات للمعلمين</h3>
                    <p><?php echo !empty($search) ? 'لم يتم العثور على معلمين مطابقين للبحث' : 'لم يتم تسجيل أي معلمين بعد'; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-edit"></i>
                    تعديل بيانات المعلم
                </h2>
                <button class="modal-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editTeacherForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <input type="hidden" name="teacher_id" id="teacher_id">
                <input type="hidden" name="update_teacher" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="full_name">الاسم الكامل *</label>
                        <input type="text" class="form-input" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">البريد الإلكتروني *</label>
                        <input type="email" class="form-input" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone">رقم الهاتف</label>
                        <input type="tel" class="form-input" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="national_id">رقم الهوية الوطنية</label>
                        <input type="text" class="form-input" id="national_id" name="national_id">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="birth_date">تاريخ الميلاد</label>
                        <input type="date" class="form-input" id="birth_date" name="birth_date">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="qualification">المؤهل العلمي</label>
                        <input type="text" class="form-input" id="qualification" name="qualification">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="specialization">التخصص *</label>
                        <input type="text" class="form-input" id="specialization" name="specialization" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="hire_date">تاريخ التعيين</label>
                        <input type="date" class="form-input" id="hire_date" name="hire_date">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="employment_type">نوع التوظيف *</label>
                        <select class="form-select" id="employment_type" name="employment_type" required>
                            <option value="دائم">دائم</option>
                            <option value="متعاقد">متعاقد</option>
                            <option value="جزء من الوقت">جزء من الوقت</option>
                            <option value="استشاري">استشاري</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="salary">الراتب</label>
                        <input type="number" class="form-input" id="salary" name="salary" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="experience_years">سنوات الخبرة</label>
                        <input type="number" class="form-input" id="experience_years" name="experience_years" min="0" max="50">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="user_status">حالة المستخدم *</label>
                        <select class="form-select" id="user_status" name="user_status" required>
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                            <option value="suspended">موقوف</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">إلغاء</button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content delete-modal">
            <div class="delete-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="delete-text" id="deleteMessage">هل أنت متأكد من حذف هذا المعلم؟</h3>
            <div class="delete-actions">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">إلغاء</button>
                <button type="button" class="btn-confirm-delete" onclick="performDelete()">
                    <i class="fas fa-trash"></i>
                    نعم، احذف
                </button>
            </div>
        </div>
    </div>

    <script>
        // ============ متغيرات عامة ============
        let currentTeacherId = null;

        // ============ فتح نافذة التعديل ============
        function openEditModal(teacherData) {
            document.getElementById('teacher_id').value = teacherData.id;
            document.getElementById('full_name').value = teacherData.full_name || '';
            document.getElementById('email').value = teacherData.email || '';
            document.getElementById('phone').value = teacherData.phone || '';
            document.getElementById('national_id').value = teacherData.national_id || '';
            document.getElementById('birth_date').value = teacherData.birth_date || '';
            document.getElementById('qualification').value = teacherData.qualification || '';
            document.getElementById('specialization').value = teacherData.specialization || '';
            document.getElementById('hire_date').value = teacherData.hire_date || '';
            document.getElementById('employment_type').value = teacherData.employment_type || 'دائم';
            document.getElementById('salary').value = teacherData.salary || '';
            document.getElementById('experience_years').value = teacherData.experience_years || '';
            document.getElementById('user_status').value = teacherData.user_status || 'active';
            
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // ============ إغلاق نافذة التعديل ============
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // ============ تأكيد الحذف ============
        function confirmDelete(teacherId, teacherName) {
            currentTeacherId = teacherId;
            document.getElementById('deleteMessage').innerHTML = 
                `هل أنت متأكد من حذف المعلم <strong>${teacherName}</strong>؟<br>هذا الإجراء لا يمكن التراجع عنه.`;
            document.getElementById('deleteModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // ============ إغلاق نافذة الحذف ============
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            currentTeacherId = null;
        }

        // ============ تنفيذ الحذف ============
        function performDelete() {
            if (currentTeacherId) {
                window.location.href = `<?php echo $_SERVER['PHP_SELF']; ?>?delete_id=${currentTeacherId}`;
            }
        }

        // ============ إغلاق رسائل النجاح/الخطأ ============
        function closeMessage(messageId) {
            document.getElementById(messageId).style.display = 'none';
        }

        // ============ إغلاق رسائل النجاح/الخطأ تلقائياً بعد 5 ثوانٍ ============
        setTimeout(function() {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            if (successMsg) {
                successMsg.style.display = 'none';
            }
            if (errorMsg) {
                errorMsg.style.display = 'none';
            }
        }, 5000);

        // ============ إغلاق النوافذ بالضغط على ESC ============
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
                closeDeleteModal();
            }
        });

        // ============ إغلاق النوافذ بالضغط خارجها ============
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'editModal') closeEditModal();
                    if (this.id === 'deleteModal') closeDeleteModal();
                }
            });
        });

        // ============ البحث الفوري ============
        document.querySelector('.search-input').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.teachers-table tbody tr');
        
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // ============ إرسال البحث عند الضغط على Enter ============
        document.querySelector('.search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchValue = this.value.trim();
                const url = new URL(window.location.href);
                
                if (searchValue !== '') {
                    url.searchParams.set('search', searchValue);
                } else {
                    url.searchParams.delete('search');
                }
                
                window.location.href = url.toString();
            }
        });

        // ============ Auto-focus على حقل البحث ============
        setTimeout(() => {
            document.querySelector('.search-input').focus();
        }, 500);

        // ============ تأثيرات التمرير ============
        const tableRows = document.querySelectorAll('.teachers-table tbody tr');
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'slideInRight 0.6s ease forwards';
                    entry.target.style.opacity = '0';
                    entry.target.style.transform = 'translateX(50px)';
                   
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateX(0)';
                    }, 300);
                   
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        tableRows.forEach(row => observer.observe(row));

        // إضافة أنماط للرسوم المتحركة
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(50px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>