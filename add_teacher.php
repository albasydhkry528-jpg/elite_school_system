<?php
session_start();
require_once "includes/config.php";
require_once "includes/functions.php";

// التحقق من صلاحيات المدير أو المشرف
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'moderator'])) {
    header("Location: login.php");
    exit;
}

$user_type = $_SESSION['user_type'];
$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

$success_message = '';
$error_message = '';

// توليد رقم معلم تلقائي
function generateTeacherCode($conn) {
    $year = date('Y');
    $query = "SELECT COUNT(*) as count FROM teachers WHERE teacher_code LIKE 'T{$year}%'";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $next_number = str_pad($row['count'] + 1, 3, '0', STR_PAD_LEFT);
        return "T{$year}{$next_number}";
    }
    return "T{$year}001";
}

$teacher_code = generateTeacherCode($conn);

// معالجة استمارة الإضافة
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // الحصول على البيانات
    $full_name = clean_input($_POST['full_name']);
    $username = clean_input($_POST['username']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = clean_input($_POST['phone']);
    $birth_date = clean_input($_POST['birth_date']);
    $gender = clean_input($_POST['gender']);
    $national_id = clean_input($_POST['national_id']);
    $address = clean_input($_POST['address']);
    $teacher_code_input = clean_input($_POST['teacher_code']);
    $specialization = clean_input($_POST['specialization']);
   
    // الحصول على المواد من checkbox بالاسم
    $teaching_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    $subjects_text = !empty($teaching_subjects) ? implode(', ', $teaching_subjects) : '';
   
    $qualification = clean_input($_POST['qualification']);
    $experience_years = (int)$_POST['experience_years'];
    $salary = (float)$_POST['salary'];
    $hire_date = clean_input($_POST['hire_date']);
    $assigned_classes = isset($_POST['assigned_classes']) ? $_POST['assigned_classes'] : [];
    $additional_roles = isset($_POST['additional_roles']) ? $_POST['additional_roles'] : [];
    $additional_notes = clean_input($_POST['additional_notes']);
  
    // التحقق من صحة البيانات
    $errors = [];
  
    if (empty($full_name)) $errors[] = "الاسم الكامل مطلوب";
    if (empty($username)) $errors[] = "اسم المستخدم مطلوب";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "البريد الإلكتروني غير صحيح";
    if (empty($password)) $errors[] = "كلمة المرور مطلوبة";
    if ($password != $confirm_password) $errors[] = "كلمات المرور غير متطابقة";
    if (empty($phone)) $errors[] = "رقم الهاتف مطلوب";
    if (empty($birth_date)) $errors[] = "تاريخ الميلاد مطلوب";
    if (empty($national_id)) $errors[] = "رقم الهوية الوطنية مطلوب";
    if (empty($specialization)) $errors[] = "التخصص مطلوب";
    if (empty($teaching_subjects)) $errors[] = "الرجاء اختيار مادة واحدة على الأقل";
  
    // التحقق من عدم تكرار البريد أو اسم المستخدم
    $check_query = "SELECT id FROM users WHERE email = ? OR username = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ss", $email, $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
  
    if ($check_result->num_rows > 0) {
        $errors[] = "البريد الإلكتروني أو اسم المستخدم موجود مسبقاً";
    }
  
    // إذا لم توجد أخطاء
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
          
            // تجهيز كلمة المرور
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
          
            // إضافة المستخدم
            $user_query = "INSERT INTO users (full_name, username, email, password, phone, user_type, status, created_at)
                          VALUES (?, ?, ?, ?, ?, 'teacher', 'active', NOW())";
            $user_stmt = $conn->prepare($user_query);
          
            if (!$user_stmt) {
                throw new Exception("فشل في تحضير استعلام المستخدم: " . $conn->error);
            }
          
            $user_stmt->bind_param("sssss", $full_name, $username, $email, $hashed_password, $phone);
          
            if ($user_stmt->execute()) {
                $teacher_user_id = $conn->insert_id;
              
                // استخدام الرقم المقدم أو التلقائي
                $final_teacher_code = !empty($teacher_code_input) ? $teacher_code_input : generateTeacherCode($conn);
              
                // التحقق من أعمدة جدول teachers الموجودة
                $columns_result = $conn->query("SHOW COLUMNS FROM teachers");
                $existing_columns = [];
                while ($column = $columns_result->fetch_assoc()) {
                    $existing_columns[] = $column['Field'];
                }
              
                // بناء الاستعلام ديناميكياً بناءً على الأعمدة الموجودة
                $teacher_columns = ['user_id', 'teacher_code', 'birth_date', 'gender', 'national_id'];
                $teacher_placeholders = ['?', '?', '?', '?', '?'];
                $teacher_params = [$teacher_user_id, $final_teacher_code, $birth_date, $gender, $national_id];
                $teacher_types = "issss";
              
                // إضافة الأعمدة الاختيارية إذا كانت موجودة في الجدول
                if (in_array('address', $existing_columns)) {
                    $teacher_columns[] = 'address';
                    $teacher_placeholders[] = '?';
                    $teacher_params[] = $address;
                    $teacher_types .= 's';
                }
              
                if (in_array('specialization', $existing_columns)) {
                    $teacher_columns[] = 'specialization';
                    $teacher_placeholders[] = '?';
                    $teacher_params[] = $specialization;
                    $teacher_types .= 's';
                }
              
                if (in_array('subjects', $existing_columns)) {
                    $teacher_columns[] = 'subjects';
                    $teacher_placeholders[] = '?';
                    $teacher_params[] = $subjects_text;
                    $teacher_types .= 's';
                }
              
                if (in_array('qualification', $existing_columns)) {
                    $teacher_columns[] = 'qualification';
                    $teacher_placeholders[] = '?';
                    $teacher_params[] = $qualification;
                    $teacher_types .= 's';
                }
              
                if (in_array('experience_years', $existing_columns)) {
                    $teacher_columns[] = 'experience_years';
                    $teacher_placeholders[] = '?';
                    $teacher_params[] = $experience_years;
                    $teacher_types .= 'i';
                }
              
                if (in_array('salary', $existing_columns)) {
                    $teacher_columns[] = 'salary';
                    $teacher_placeholders[] = '?';
                    $teacher_params[] = $salary;
                    $teacher_types .= 'd';
                }
              
                if (in_array('hire_date', $existing_columns)) {
                    $teacher_columns[] = 'hire_date';
                    $teacher_placeholders[] = '?';
                    $teacher_params[] = $hire_date;
                    $teacher_types .= 's';
                }
              
                // إضافة created_at إذا كان موجوداً
                if (in_array('created_at', $existing_columns)) {
                    $teacher_columns[] = 'created_at';
                    $teacher_placeholders[] = 'NOW()';
                }
              
                // بناء استعلام إضافة المعلم
                $teacher_query = "INSERT INTO teachers (" . implode(', ', $teacher_columns) . ")
                                VALUES (" . implode(', ', $teacher_placeholders) . ")";
              
                $teacher_stmt = $conn->prepare($teacher_query);
              
                if (!$teacher_stmt) {
                    throw new Exception("فشل في تحضير استعلام المعلم: " . $conn->error);
                }
              
                // ربط المعاملات
                if ($teacher_types) {
                    $teacher_stmt->bind_param($teacher_types, ...$teacher_params);
                }
              
                if ($teacher_stmt->execute()) {
                    $teacher_id = $conn->insert_id;
                  
                    // إضافة المواد التي يدرسها المعلم
                    if (!empty($teaching_subjects)) {
                        foreach ($teaching_subjects as $subject_name) {
                            // البحث عن ID المادة باستخدام الاسم
                            $subject_query = "SELECT id FROM subjects WHERE subject_name = ?";
                            $subject_stmt = $conn->prepare($subject_query);
                            if ($subject_stmt) {
                                $subject_stmt->bind_param("s", $subject_name);
                                $subject_stmt->execute();
                                $subject_result = $subject_stmt->get_result();
                              
                                if ($subject_row = $subject_result->fetch_assoc()) {
                                    $subject_id = $subject_row['id'];
                                  
                                    // التحقق من وجود جدول teacher_subjects
                                    $table_exists = $conn->query("SHOW TABLES LIKE 'teacher_subjects'");
                                    if ($table_exists->num_rows > 0) {
                                        $teacher_subject_query = "INSERT INTO teacher_subjects (teacher_id, subject_id, is_primary, created_at)
                                                                VALUES (?, ?, 1, NOW())";
                                        $teacher_subject_stmt = $conn->prepare($teacher_subject_query);
                                        if ($teacher_subject_stmt) {
                                            $teacher_subject_stmt->bind_param("ii", $teacher_id, $subject_id);
                                            $teacher_subject_stmt->execute();
                                        }
                                    }
                                }
                            }
                        }
                    }
                   
                    // إضافة الصفوف التي يشرف عليها المعلم
                    if (!empty($assigned_classes)) {
                        // التحقق من وجود جدول teacher_classes
                        $table_exists = $conn->query("SHOW TABLES LIKE 'teacher_classes'");
                        if ($table_exists->num_rows > 0) {
                            foreach ($assigned_classes as $class_id) {
                                $class_query = "INSERT INTO teacher_classes (teacher_id, class_id, role, academic_year, created_at)
                                              VALUES (?, ?, 'مشرف', ?, NOW())";
                                $class_stmt = $conn->prepare($class_query);
                                if ($class_stmt) {
                                    $academic_year = date('Y') . '-' . (date('Y') + 1);
                                    $class_stmt->bind_param("iis", $teacher_id, $class_id, $academic_year);
                                    $class_stmt->execute();
                                }
                            }
                        }
                    }
                  
                    $conn->commit();
                  
                    // تعيين رسالة النجاح وعرضها في نفس الصفحة
                    $success_message = "تم إضافة المعلم <strong>{$full_name}</strong> بنجاح!";
                    
                    // إعادة توليد رقم معلم جديد للاستخدام التالي
                    $teacher_code = generateTeacherCode($conn);
                    
                    // إعادة تعيين متغيرات النموذج (اختياري)
                    $full_name = $username = $email = $phone = $birth_date = $national_id = $address = $specialization = $qualification = $additional_notes = '';
                    $experience_years = 0;
                    $salary = 0;
                    $hire_date = date('Y-m-d');
                    
                } else {
                    throw new Exception("فشل في إضافة بيانات المعلم: " . $teacher_stmt->error);
                }
            } else {
                throw new Exception("فشل في إنشاء حساب المستخدم: " . $user_stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "حدث خطأ أثناء إضافة المعلم: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// جلب قائمة الصفوف للمعلم
$classes_query = "SELECT id, class_name, grade, section FROM classes WHERE is_active = 1 ORDER BY level_id, grade, section";
$classes_result = $conn->query($classes_query);

// جلب قائمة المواد الدراسية
$subjects_query = "SELECT id, subject_name FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة معلم جديد | نظام الإدارة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6a11cb;
            --primary-light: #8a2be2;
            --secondary: #2575fc;
            --secondary-light: #3b9eff;
            --success: #00b894;
            --success-light: #00cec9;
            --warning: #fdcb6e;
            --warning-light: #ffeaa7;
            --danger: #d63031;
            --danger-light: #ff7675;
            --info: #0984e3;
            --info-light: #74b9ff;
            --dark: #2d3436;
            --dark-light: #636e72;
            --light: #f8f9fa;
            --light-gray: #dfe6e9;
            --sidebar-bg: #1a1a2e;
            --card-shadow: 0 15px 40px rgba(0,0,0,0.12);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            --gradient-primary: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            --gradient-success: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
            padding: 0;
            margin: 0;
        }

        .container {
            max-width: 100%;
            margin: 0;
            padding: 20px;
        }

        /* شريط العودة */
        .top-bar {
            background: white;
            padding: 25px 40px;
            border-radius: 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--card-shadow);
            border-right: 8px solid var(--primary);
            position: relative;
            overflow: hidden;
        }

        .top-bar:before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(106, 17, 203, 0.05));
            z-index: 0;
        }

        .top-bar > * {
            position: relative;
            z-index: 1;
        }

        .back-btn {
            background: var(--gradient-primary);
            color: white;
            padding: 15px 30px;
            border-radius: 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            transition: var(--transition);
            box-shadow: 0 8px 25px rgba(106, 17, 203, 0.4);
        }

        .back-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 35px rgba(106, 17, 203, 0.5);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.4);
        }

        /* رأس الصفحة */
        .page-header {
            background: white;
            padding: 40px;
            border-radius: 25px;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .page-header:before {
            content: '👨‍🏫';
            position: absolute;
            top: -30px;
            left: -30px;
            font-size: 12rem;
            opacity: 0.05;
            z-index: 0;
        }

        .page-title {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .page-title:after {
            content: '';
            display: block;
            width: 100px;
            height: 5px;
            background: var(--gradient-primary);
            margin: 15px auto;
            border-radius: 5px;
        }

        .page-subtitle {
            color: var(--dark-light);
            font-size: 1.2rem;
            max-width: 100%;
            margin: 0 auto;
            line-height: 1.8;
            padding: 0 20px;
        }

        /* رسائل التنبيه */
        .alert {
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            animation: slideInDown 0.5s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: none;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .alert:before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3));
            z-index: -1;
        }

        @keyframes slideInDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        /* نموذج الإضافة */
        .form-container {
            background: white;
            border-radius: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 40px;
            overflow: hidden;
            width: 100%;
        }

        .form-header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px 40px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .form-icon {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            backdrop-filter: blur(10px);
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
        }

        .form-steps {
            display: flex;
            justify-content: center;
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 30px;
            background: white;
            border-radius: 15px;
            margin: 0 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: var(--transition);
        }

        .step.active {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(106, 17, 203, 0.3);
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .step.active .step-number {
            background: white;
            color: var(--primary);
        }

        .form-content {
            padding: 40px;
        }

        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 40px;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .tab-btn {
            padding: 20px 30px;
            background: #f8f9fa;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-light);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
        }

        .tab-btn:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        .tab-btn.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.3);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* شبكة النماذج - عرض كامل الشاشة */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
            width: 100%;
        }

        .form-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 20px;
            border-right: 5px solid var(--primary);
            transition: var(--transition);
            width: 100%;
        }

        .form-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }

        .section-title i {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 25px;
            width: 100%;
        }

        .form-group label {
            display: block;
            margin-bottom: 12px;
            color: var(--dark);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group label i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .required {
            color: #d63031;
            margin-right: 5px;
        }

        .form-control {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
            color: var(--dark);
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(106, 17, 203, 0.15);
            transform: translateY(-2px);
        }

        .form-control:hover {
            border-color: var(--primary-light);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236a11cb' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 20px center;
            background-size: 16px;
            padding-right: 20px;
            padding-left: 50px;
        }

        /* المجموعات المختارة */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            background: white;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .checkbox-item:last-child {
            border-bottom: none;
        }

        .checkbox-item input[type="checkbox"] {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: var(--transition);
        }

        .checkbox-item input[type="checkbox"]:checked {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
            flex: 1;
        }

        /* زر توليد كلمة المرور */
        .password-group {
            position: relative;
        }

        .generate-password {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .generate-password:hover {
            background: var(--secondary);
            transform: translateY(-50%) scale(1.05);
        }

        .password-strength {
            margin-top: 10px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0%;
            background: #ff4757;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* بطاقة المعلومات */
        .info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            border-right: 5px solid var(--info);
            display: flex;
            align-items: center;
            gap: 20px;
            width: 100%;
        }

        .info-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        /* الأزرار */
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 0 0 25px 25px;
            border-top: 1px solid #eee;
            width: 100%;
        }

        .btn {
            padding: 20px 40px;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 200px;
            justify-content: center;
        }

        .btn-submit {
            background: var(--gradient-success);
            color: white;
            box-shadow: 0 8px 25px rgba(0, 184, 148, 0.4);
        }

        .btn-submit:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 184, 148, 0.5);
        }

        .btn-reset {
            background: linear-gradient(135deg, #fdcb6e 0%, #ffeaa7 100%);
            color: var(--dark);
            box-shadow: 0 8px 25px rgba(253, 203, 110, 0.4);
        }

        .btn-reset:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(253, 203, 110, 0.5);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #dfe6e9 0%, #b2bec3 100%);
            color: var(--dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-cancel:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(178, 190, 195, 0.4);
        }

        /* معاينة الرقم */
        .code-preview {
            background: var(--gradient-primary);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            gap: 15px;
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        /* التحقق من الصحة */
        .validation-message {
            display: none;
            padding: 10px 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
            animation: slideIn 0.3s ease;
        }

        .validation-success {
            background: #d4edda;
            color: #155724;
            display: block;
        }

        .validation-error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* قائمة متعددة الاختيارات */
        .multi-select-container {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 15px;
            background: white;
        }

        .multi-select-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .multi-select-item:last-child {
            border-bottom: none;
        }

        /* التجاوب */
        @media (max-width: 1200px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
          
            .form-actions {
                flex-direction: column;
            }
          
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
          
            .top-bar {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
          
            .page-title {
                font-size: 2.2rem;
            }
          
            .form-tabs {
                flex-direction: column;
            }
          
            .tab-btn {
                width: 100%;
                justify-content: center;
            }
          
            .step {
                margin: 5px;
                padding: 12px 20px;
            }
          
            .page-header,
            .form-content,
            .form-actions {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.8rem;
            }
          
            .form-header {
                flex-direction: column;
                text-align: center;
            }
          
            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }

        /* تأثيرات خاصة */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 184, 148, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(0, 184, 148, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 184, 148, 0); }
        }

        .floating {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .glow {
            text-shadow: 0 0 15px rgba(106, 17, 203, 0.5);
        }

        /* تصميم كامل الشاشة */
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }

        .full-width {
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
       
        /* تنبيه عند عدم وجود مواد */
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #ddd;
        }
    </style>
</head>
<body>
    <div class="container full-width">
        <!-- شريط العودة -->
        <div class="top-bar full-width">
            <a href="teacher management.php?section=teachers" class="back-btn">
                <i class="fas fa-arrow-right"></i>
                العودة لإدارة المعلمين
            </a>
         
            <div class="user-info">
                <div class="user-avatar floating">
                    <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                </div>
                <div>
                    <strong style="font-size: 1.2rem;"><?php echo htmlspecialchars($full_name); ?></strong>
                    <div style="font-size: 0.9rem; color: #666;">
                        <?php
                        $user_types = [
                            'admin' => '👑 مدير النظام',
                            'moderator' => '⚡ مشرف النظام'
                        ];
                        echo $user_types[$user_type] ?? $user_type;
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- رأس الصفحة -->
        <div class="page-header full-width">
            <h1 class="page-title">
                <i class="fas fa-chalkboard-teacher"></i>
                إضافة معلم جديد
            </h1>
            <p class="page-subtitle">أضف معلماً جديداً إلى النظام مع جميع البيانات المطلوبة | الرقم التلقائي:
                <span class="code-preview">
                    <i class="fas fa-id-card"></i>
                    <?php echo $teacher_code; ?>
                </span>
            </p>
        </div>

        <!-- رسائل التنبيه -->
        <?php if($success_message): ?>
            <div class="alert alert-success full-width">
                <i class="fas fa-check-circle fa-2x"></i>
                <div>
                    <strong>نجاح!</strong>
                    <p><?php echo $success_message; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="alert alert-danger full-width">
                <i class="fas fa-exclamation-circle fa-2x"></i>
                <div>
                    <strong>خطأ!</strong>
                    <p><?php echo $error_message; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- نموذج الإضافة -->
        <div class="form-container full-width">
            <!-- رأس النموذج -->
            <div class="form-header">
                <div class="form-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <h2 class="form-title">نموذج تسجيل معلم جديد</h2>
                    <p style="opacity: 0.9; margin-top: 10px;">املأ جميع الحقول المطلوبة بدقة</p>
                </div>
            </div>

            <!-- الخطوات -->
            <div class="form-steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div>البيانات الأساسية</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div>المعلومات الشخصية</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div>المعلومات الوظيفية</div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div>الواجبات والمهام</div>
                </div>
            </div>

            <!-- محتوى النموذج -->
            <form method="POST" class="form-content full-width" id="addTeacherForm">
                <!-- أزرار التبويب -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="basic">
                        <i class="fas fa-user-circle"></i>
                        البيانات الأساسية
                    </button>
                    <button type="button" class="tab-btn" data-tab="personal">
                        <i class="fas fa-id-card"></i>
                        المعلومات الشخصية
                    </button>
                    <button type="button" class="tab-btn" data-tab="professional">
                        <i class="fas fa-briefcase"></i>
                        المعلومات الوظيفية
                    </button>
                    <button type="button" class="tab-btn" data-tab="duties">
                        <i class="fas fa-tasks"></i>
                        الواجبات والمهام
                    </button>
                </div>

                <!-- بطاقة معلومات -->
                <div class="info-card full-width">
                    <div class="info-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <strong>ملاحظة هامة:</strong>
                        <p>جميع الحقول التي تحمل علامة (<span class="required">*</span>) إلزامية. تأكد من صحة البيانات قبل الإرسال.</p>
                    </div>
                </div>

                <!-- تبويب البيانات الأساسية -->
                <div id="basic" class="tab-content active">
                    <div class="form-grid full-width">
                        <!-- العمود الأول -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                معلومات الحساب
                            </h3>
                          
                            <div class="form-group">
                                <label for="full_name">
                                    <i class="fas fa-signature"></i>
                                    <span class="required">*</span> الاسم الكامل
                                </label>
                                <input type="text" id="full_name" name="full_name" class="form-control"
                                       placeholder="أدخل الاسم الكامل ثلاثي" required
                                       oninput="validateField(this, 'text')" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                <div class="validation-message" id="full_name_validation"></div>
                            </div>
                          
                            <div class="form-group">
                                <label for="username">
                                    <i class="fas fa-at"></i>
                                    <span class="required">*</span> اسم المستخدم
                                </label>
                                <input type="text" id="username" name="username" class="form-control"
                                       placeholder="يستخدم لتسجيل الدخول" required
                                       oninput="validateUsername(this)" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                <div class="validation-message" id="username_validation"></div>
                            </div>
                          
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    <span class="required">*</span> البريد الإلكتروني
                                </label>
                                <input type="email" id="email" name="email" class="form-control"
                                       placeholder="example@school.com" required
                                       oninput="validateEmail(this)" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                <div class="validation-message" id="email_validation"></div>
                            </div>
                        </div>
                      
                        <!-- العمود الثاني -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-lock"></i>
                                كلمة المرور
                            </h3>
                          
                            <div class="form-group password-group">
                                <label for="password">
                                    <i class="fas fa-key"></i>
                                    <span class="required">*</span> كلمة المرور
                                </label>
                                <button type="button" class="generate-password" onclick="generatePassword()">
                                    <i class="fas fa-sync-alt"></i>
                                    توليد
                                </button>
                                <input type="password" id="password" name="password" class="form-control"
                                       placeholder="كلمة مرور قوية" required
                                       oninput="checkPasswordStrength(this)">
                                <div class="password-strength">
                                    <div class="strength-meter" id="passwordStrength"></div>
                                </div>
                            </div>
                          
                            <div class="form-group">
                                <label for="confirm_password">
                                    <i class="fas fa-key"></i>
                                    <span class="required">*</span> تأكيد كلمة المرور
                                </label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       class="form-control" placeholder="أعد إدخال كلمة المرور" required
                                       oninput="checkPasswordMatch()">
                                <div class="validation-message" id="password_validation"></div>
                            </div>
                          
                            <div class="form-group">
                                <label for="phone">
                                    <i class="fas fa-phone"></i>
                                    <span class="required">*</span> رقم الهاتف
                                </label>
                                <input type="tel" id="phone" name="phone" class="form-control"
                                       placeholder="05XXXXXXXX" required
                                       oninput="validatePhone(this)" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                <div class="validation-message" id="phone_validation"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تبويب المعلومات الشخصية -->
                <div id="personal" class="tab-content">
                    <div class="form-grid full-width">
                        <!-- العمود الأول -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user-tag"></i>
                                الهوية الشخصية
                            </h3>
                          
                            <div class="form-group">
                                <label for="birth_date">
                                    <i class="fas fa-birthday-cake"></i>
                                    <span class="required">*</span> تاريخ الميلاد
                                </label>
                                <input type="date" id="birth_date" name="birth_date" class="form-control" required value="<?php echo isset($_POST['birth_date']) ? htmlspecialchars($_POST['birth_date']) : ''; ?>">
                            </div>
                          
                            <div class="form-group">
                                <label for="gender">
                                    <i class="fas fa-venus-mars"></i>
                                    الجنس
                                </label>
                                <select id="gender" name="gender" class="form-control">
                                    <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>ذكر</option>
                                    <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>أنثى</option>
                                </select>
                            </div>
                          
                            <div class="form-group">
                                <label for="national_id">
                                    <i class="fas fa-id-card-alt"></i>
                                    <span class="required">*</span> رقم الهوية الوطنية
                                </label>
                                <input type="text" id="national_id" name="national_id" class="form-control"
                                       placeholder="10 أرقام" required
                                       oninput="validateNationalId(this)" value="<?php echo isset($_POST['national_id']) ? htmlspecialchars($_POST['national_id']) : ''; ?>">
                                <div class="validation-message" id="national_id_validation"></div>
                            </div>
                        </div>
                      
                        <!-- العمود الثاني -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-map-marker-alt"></i>
                                معلومات الاتصال
                            </h3>
                          
                            <div class="form-group">
                                <label for="address">
                                    <i class="fas fa-home"></i>
                                    العنوان
                                </label>
                                <textarea id="address" name="address" class="form-control"
                                          rows="4" placeholder="العنوان التفصيلي"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>
                          
                            <div class="info-card" style="margin-top: 20px;">
                                <i class="fas fa-shield-alt" style="color: #0984e3; font-size: 1.2rem;"></i>
                                <div>
                                    <strong>حماية البيانات:</strong>
                                    <p>جميع المعلومات الشخصية محمية وفق سياسة الخصوصية ولا يتم مشاركتها مع أي جهة خارجية.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تبويب المعلومات الوظيفية -->
                <div id="professional" class="tab-content">
                    <div class="form-grid full-width">
                        <!-- العمود الأول -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-chalkboard-teacher"></i>
                                المعلومات الوظيفية
                            </h3>
                          
                            <div class="form-group">
                                <label for="teacher_code">
                                    <i class="fas fa-id-badge"></i>
                                    رقم المعلم
                                </label>
                                <input type="text" id="teacher_code" name="teacher_code"
                                       class="form-control" placeholder="اتركه فارغاً للتوليد التلقائي"
                                       value="<?php echo isset($_POST['teacher_code']) ? htmlspecialchars($_POST['teacher_code']) : $teacher_code; ?>">
                                <small style="color: #666; display: block; margin-top: 8px;">
                                    <i class="fas fa-info-circle"></i> سيتم توليد رقم تلقائي إذا تركت الحقل فارغاً
                                </small>
                            </div>
                          
                            <div class="form-group">
                                <label for="specialization">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span class="required">*</span> التخصص الرئيسي
                                </label>
                                <select id="specialization" name="specialization" class="form-control" required>
                                    <option value="">اختر التخصص</option>
                                    <option value="رياضيات" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'رياضيات') ? 'selected' : ''; ?>>الرياضيات</option>
                                    <option value="علوم" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'علوم') ? 'selected' : ''; ?>>العلوم</option>
                                    <option value="لغة عربية" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'لغة عربية') ? 'selected' : ''; ?>>اللغة العربية</option>
                                    <option value="لغة إنجليزية" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'لغة إنجليزية') ? 'selected' : ''; ?>>اللغة الإنجليزية</option>
                                    <option value="اجتماعيات" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'اجتماعيات') ? 'selected' : ''; ?>>الاجتماعيات</option>
                                    <option value="تكنولوجيا" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'تكنولوجيا') ? 'selected' : ''; ?>>تكنولوجيا المعلومات</option>
                                    <option value="تربية إسلامية" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'تربية إسلامية') ? 'selected' : ''; ?>>التربية الإسلامية</option>
                                    <option value="تربية بدنية" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'تربية بدنية') ? 'selected' : ''; ?>>التربية البدنية</option>
                                    <option value="فنون" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'فنون') ? 'selected' : ''; ?>>الفنون</option>
                                    <option value="موسيقى" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'موسيقى') ? 'selected' : ''; ?>>الموسيقى</option>
                                </select>
                            </div>
                          
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-book"></i>
                                    <span class="required">*</span> المواد التي يمكن تدريسها
                                </label>
                                <?php if($subjects_result && $subjects_result->num_rows > 0): ?>
                                <div class="checkbox-group">
                                    <?php
                                    // إعادة المؤشر لبداية النتيجة
                                    $subjects_result->data_seek(0);
                                    while($subject = $subjects_result->fetch_assoc()):
                                        $checked = '';
                                        if(isset($_POST['subjects']) && in_array($subject['subject_name'], $_POST['subjects'])) {
                                            $checked = 'checked';
                                        }
                                    ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="subject_<?php echo $subject['id']; ?>"
                                               name="subjects[]" value="<?php echo htmlspecialchars($subject['subject_name']); ?>" <?php echo $checked; ?>>
                                        <label for="subject_<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></label>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    لا توجد مواد متاحة في قاعدة البيانات
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                      
                        <!-- العمود الثاني -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-briefcase"></i>
                                المؤهلات والخبرة
                            </h3>
                          
                            <div class="form-group">
                                <label for="qualification">
                                    <i class="fas fa-graduation-cap"></i>
                                    المؤهل العلمي
                                </label>
                                <select id="qualification" name="qualification" class="form-control">
                                    <option value="">اختر المؤهل</option>
                                    <option value="دبلوم" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'دبلوم') ? 'selected' : ''; ?>>دبلوم</option>
                                    <option value="بكالوريوس" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'بكالوريوس') ? 'selected' : ''; ?>>بكالوريوس</option>
                                    <option value="ماجستير" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'ماجستير') ? 'selected' : ''; ?>>ماجستير</option>
                                    <option value="دكتوراه" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'دكتوراه') ? 'selected' : ''; ?>>دكتوراه</option>
                                </select>
                            </div>
                          
                            <div class="form-group">
                                <label for="experience_years">
                                    <i class="fas fa-history"></i>
                                    سنوات الخبرة
                                </label>
                                <input type="number" id="experience_years" name="experience_years"
                                       class="form-control" min="0" max="50" value="<?php echo isset($_POST['experience_years']) ? htmlspecialchars($_POST['experience_years']) : '0'; ?>">
                            </div>
                          
                            <div class="form-group">
                                <label for="salary">
                                    <i class="fas fa-money-bill-wave"></i>
                                    الراتب الشهري (ريال)
                                </label>
                                <input type="number" id="salary" name="salary" class="form-control"
                                       min="0" step="100" placeholder="0" value="<?php echo isset($_POST['salary']) ? htmlspecialchars($_POST['salary']) : ''; ?>">
                            </div>
                          
                            <div class="form-group">
                                <label for="hire_date">
                                    <i class="fas fa-calendar-check"></i>
                                    تاريخ التعيين
                                </label>
                                <input type="date" id="hire_date" name="hire_date" class="form-control"
                                       value="<?php echo isset($_POST['hire_date']) ? htmlspecialchars($_POST['hire_date']) : date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تبويب الواجبات والمهام -->
                <div id="duties" class="tab-content">
                    <div class="form-grid full-width">
                        <!-- العمود الأول -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-chalkboard"></i>
                                الصفوف التي يشرف عليها
                            </h3>
                          
                            <div class="form-group">
                                <label for="assigned_classes">
                                    <i class="fas fa-users-class"></i>
                                    اختيار الصفوف
                                </label>
                                <?php if($classes_result && $classes_result->num_rows > 0): ?>
                                <div class="multi-select-container">
                                    <?php
                                    // إعادة المؤشر لبداية النتيجة
                                    $classes_result->data_seek(0);
                                    while($class = $classes_result->fetch_assoc()):
                                        $checked = '';
                                        if(isset($_POST['assigned_classes']) && in_array($class['id'], $_POST['assigned_classes'])) {
                                            $checked = 'checked';
                                        }
                                    ?>
                                    <div class="multi-select-item">
                                        <input type="checkbox" id="class_<?php echo $class['id']; ?>"
                                               name="assigned_classes[]" value="<?php echo $class['id']; ?>" <?php echo $checked; ?>>
                                        <label for="class_<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['grade'] . ' (' . $class['section'] . ')'); ?>
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <small style="color: #666; display: block; margin-top: 8px;">
                                    <i class="fas fa-info-circle"></i> يمكن اختيار أكثر من صف
                                </small>
                                <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    لا توجد صفوف متاحة في قاعدة البيانات
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                      
                        <!-- العمود الثاني -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-tasks"></i>
                                مهام إضافية
                            </h3>
                          
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user-tie"></i>
                                    أدوار إضافية
                                </label>
                                <div class="checkbox-group">
                                    <?php
                                    $roles = [
                                        'coordinator' => 'منسق الصفوف',
                                        'examiner' => 'مراقب امتحانات',
                                        'activity' => 'مشرف أنشطة',
                                        'committee' => 'عضو لجنة'
                                    ];
                                    
                                    foreach($roles as $value => $label):
                                        $checked = '';
                                        if(isset($_POST['additional_roles']) && in_array($value, $_POST['additional_roles'])) {
                                            $checked = 'checked';
                                        }
                                    ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="role_<?php echo $value; ?>" name="additional_roles[]" value="<?php echo $value; ?>" <?php echo $checked; ?>>
                                        <label for="role_<?php echo $value; ?>"><?php echo $label; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                          
                            <div class="form-group">
                                <label for="additional_notes">
                                    <i class="fas fa-sticky-note"></i>
                                    ملاحظات إضافية
                                </label>
                                <textarea id="additional_notes" name="additional_notes" class="form-control"
                                          rows="4" placeholder="أي ملاحظات إضافية عن المعلم..."><?php echo isset($_POST['additional_notes']) ? htmlspecialchars($_POST['additional_notes']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                  
                    <!-- معاينة المعلومات -->
                    <div class="info-card full-width" style="background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%);">
                        <div class="info-icon" style="background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div>
                            <strong>معاينة سريعة:</strong>
                            <p id="previewInfo" style="margin-top: 10px; color: #666;">
                                أدخل البيانات لتظهر هنا...
                            </p>
                        </div>
                    </div>
                </div>

                <!-- أزرار التنفيذ -->
                <div class="form-actions full-width">
                    <button type="submit" class="btn btn-submit pulse">
                        <i class="fas fa-check-circle"></i>
                        إضافة المعلم
                    </button>
                  
                    <button type="reset" class="btn btn-reset" onclick="resetForm()">
                        <i class="fas fa-redo"></i>
                        مسح النموذج
                    </button>
                  
                    <a href="admin.php?section=teachers" class="btn btn-cancel">
                        <i class="fas fa-times"></i>
                        إلغاء
                    </a>
                </div>
            </form>
        </div>

        <!-- تذييل الصفحة -->
        <div style="text-align: center; margin-top: 40px; color: white; padding: 20px; width: 100%;">
            <p style="opacity: 0.8;">
                <i class="fas fa-copyright"></i> <?php echo date('Y'); ?> نظام إدارة المدرسة |
                <i class="fas fa-clock"></i> آخر تحديث: <?php echo date('h:i A'); ?>
            </p>
        </div>
    </div>

    <script>
        // تبديل التبويبات
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                // إزالة النشاط من جميع الأزرار
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
              
                // إضافة النشاط للزر المحدد
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
              
                // تحديث خطوات التقدم
                updateSteps(button.dataset.tab);
            });
        });
      
        function updateSteps(activeTab) {
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                step.classList.remove('active');
                if ((activeTab === 'basic' && index === 0) ||
                    (activeTab === 'personal' && index <= 1) ||
                    (activeTab === 'professional' && index <= 2) ||
                    (activeTab === 'duties' && index <= 3)) {
                    step.classList.add('active');
                }
            });
        }
      
        // توليد كلمة مرور عشوائية
        function generatePassword() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('password').value = password;
            document.getElementById('confirm_password').value = password;
            checkPasswordStrength(document.getElementById('password'));
            checkPasswordMatch();
          
            // إظهار رسالة نجاح
            showValidation('password_validation', 'تم توليد كلمة مرور قوية بنجاح!', 'success');
        }
      
        // التحقق من قوة كلمة المرور
        function checkPasswordStrength(input) {
            const password = input.value;
            const meter = document.getElementById('passwordStrength');
            let strength = 0;
          
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
          
            meter.style.width = strength + '%';
          
            if (strength < 50) {
                meter.style.background = '#ff4757';
            } else if (strength < 75) {
                meter.style.background = '#ffa502';
            } else {
                meter.style.background = '#2ed573';
            }
        }
      
        // التحقق من تطابق كلمات المرور
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const validation = document.getElementById('password_validation');
          
            if (!password) return;
          
            if (confirm && password !== confirm) {
                showValidation('password_validation', 'كلمات المرور غير متطابقة', 'error');
            } else if (confirm && password === confirm) {
                showValidation('password_validation', 'كلمات المرور متطابقة ✓', 'success');
            }
        }
      
        // التحقق من صحة البريد الإلكتروني
        function validateEmail(input) {
            const email = input.value;
            const validation = document.getElementById('email_validation');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          
            if (!email) return;
          
            if (emailRegex.test(email)) {
                showValidation('email_validation', 'البريد الإلكتروني صحيح ✓', 'success');
            } else {
                showValidation('email_validation', 'البريد الإلكتروني غير صحيح', 'error');
            }
        }
      
        // التحقق من صحة اسم المستخدم
        function validateUsername(input) {
            const username = input.value;
            const validation = document.getElementById('username_validation');
          
            if (!username) return;
          
            if (username.length < 3) {
                showValidation('username_validation', 'يجب أن يكون اسم المستخدم 3 أحرف على الأقل', 'error');
            } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                showValidation('username_validation', 'يمكن أن يحتوي على أحرف وأرقام وشرطة سفلية فقط', 'error');
            } else {
                showValidation('username_validation', 'اسم المستخدم صالح ✓', 'success');
            }
        }
      
        // التحقق من صحة الهاتف
        function validatePhone(input) {
            const phone = input.value;
            const validation = document.getElementById('phone_validation');
          
            if (!phone) return;
          
            const phoneRegex = /^(05\d{8})$/;
            if (phoneRegex.test(phone)) {
                showValidation('phone_validation', 'رقم الهاتف صحيح ✓', 'success');
            } else {
                showValidation('phone_validation', 'يجب أن يبدأ بـ 05 ويحتوي على 10 أرقام', 'error');
            }
        }
      
        // التحقق من صحة الهوية الوطنية
        function validateNationalId(input) {
            const id = input.value;
            const validation = document.getElementById('national_id_validation');
          
            if (!id) return;
          
            const idRegex = /^\d{10}$/;
            if (idRegex.test(id)) {
                showValidation('national_id_validation', 'رقم الهوية صحيح ✓', 'success');
            } else {
                showValidation('national_id_validation', 'يجب أن يحتوي على 10 أرقام فقط', 'error');
            }
        }
      
        // التحقق من الحقول النصية
        function validateField(input, type) {
            const value = input.value;
            const fieldName = input.id + '_validation';
          
            if (!value) return;
          
            if (type === 'text' && value.length < 2) {
                showValidation(fieldName, 'يجب أن يحتوي على حرفين على الأقل', 'error');
            } else {
                showValidation(fieldName, 'حقل صالح ✓', 'success');
            }
        }
      
        // إظهار رسالة التحقق
        function showValidation(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.className = 'validation-message validation-' + type;
          
            // إخفاء الرسالة بعد 5 ثواني
            setTimeout(() => {
                element.className = 'validation-message';
                element.textContent = '';
            }, 5000);
        }
      
        // تحديث المعاينة
        function updatePreview() {
            const name = document.getElementById('full_name').value || 'غير محدد';
            const specialization = document.getElementById('specialization').value || 'غير محدد';
            const code = document.getElementById('teacher_code').value || 'سيتم توليده تلقائياً';
          
            const preview = document.getElementById('previewInfo');
            preview.innerHTML = `
                <strong>الاسم:</strong> ${name}<br>
                <strong>التخصص:</strong> ${specialization}<br>
                <strong>رقم المعلم:</strong> ${code}
            `;
        }
      
        // تحديث المعاينة عند تغيير الحقول
        document.querySelectorAll('#full_name, #specialization, #teacher_code').forEach(input => {
            input.addEventListener('input', updatePreview);
        });
      
        // إعادة تعيين النموذج
        function resetForm() {
            if (confirm('هل أنت متأكد من مسح جميع البيانات؟')) {
                document.getElementById('addTeacherForm').reset();
                document.querySelectorAll('.validation-message').forEach(el => {
                    el.className = 'validation-message';
                    el.textContent = '';
                });
                document.getElementById('passwordStrength').style.width = '0%';
                document.getElementById('previewInfo').textContent = 'أدخل البيانات لتظهر هنا...';
              
                // العودة للتبويب الأول
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.querySelector('[data-tab="basic"]').classList.add('active');
                document.getElementById('basic').classList.add('active');
                updateSteps('basic');
              
                showValidation('password_validation', 'تم مسح النموذج بنجاح', 'success');
            }
        }
      
        // التحقق قبل الإرسال
        document.getElementById('addTeacherForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            let errorMessages = [];
          
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    const label = field.previousElementSibling?.textContent || 'هذا الحقل';
                    errorMessages.push(`الحقل "${label}" مطلوب`);
                    field.style.borderColor = '#d63031';
                } else {
                    field.style.borderColor = '#e0e0e0';
                }
            });
          
            // التحقق من كلمة المرور
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            if (password !== confirmPassword) {
                isValid = false;
                errorMessages.push('كلمات المرور غير متطابقة');
            }
          
            // التحقق من المواد الدراسية
            const subjects = document.querySelectorAll('input[name="subjects[]"]:checked');
            if (subjects.length === 0) {
                isValid = false;
                errorMessages.push('الرجاء اختيار مادة واحدة على الأقل');
            }
          
            if (!isValid) {
                e.preventDefault();
                alert('الرجاء تصحيح الأخطاء التالية:\n\n' + errorMessages.join('\n'));
            } else {
                // عرض رسالة التحميل
                const submitBtn = this.querySelector('.btn-submit');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإضافة...';
                submitBtn.disabled = true;
              
                // استمرار الإرسال - لا يتم إلغاؤه
            }
        });
      
        // تأثيرات الدخول
        document.addEventListener('DOMContentLoaded', function() {
            // تأثيرات البطاقات
            const cards = document.querySelectorAll('.form-section, .info-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
              
                setTimeout(() => {
                    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
          
            // تحديث المعاينة الأولية
            updatePreview();
          
            // تعيين تاريخ اليوم كحد أقصى لتاريخ الميلاد
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('birth_date').max = today;
            document.getElementById('hire_date').max = today;
        });
      
        // تحميل الصفحة بسلاسة
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
          
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>