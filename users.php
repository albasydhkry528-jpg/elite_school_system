<?php
session_start();
require_once "includes/config.php";

// التحقق من صلاحيات المدير أو المشرف
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'moderator'])) {
    header("Location: login.php");
    exit;
}

$user_type = $_SESSION['user_type'];
$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

// تحديد القسم الحالي (طلاب، معلمين، أولياء أمور، مشرفين)
$section = isset($_GET['section']) ? clean_input($_GET['section']) : 'dashboard';
$action = isset($_GET['action']) ? clean_input($_GET['action']) : '';

// جلب الإحصائيات العامة (ملاحظة: تم إزالة الجزء الخاص بالإحصائيات)

// جلب بيانات القسم المحدد
switch($section) {
    case 'students':
        // معالجة البحث والتصفية للطلاب
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        $filter_grade = isset($_GET['grade']) ? clean_input($_GET['grade']) : '';
        $filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // بناء شروط البحث للطلاب
        $where_conditions = ["u.user_type = 'student'"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR s.student_code LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ssss";
        }
        
        if (!empty($filter_grade) && $filter_grade != 'all') {
            $where_conditions[] = "s.grade = ?";
            $params[] = $filter_grade;
            $types .= "s";
        }
        
        if (!empty($filter_status) && $filter_status != 'all') {
            $where_conditions[] = "u.status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "WHERE u.user_type = 'student'";
        
        // جلب إجمالي عدد الطلاب
        $count_query = "SELECT COUNT(*) as total FROM users u LEFT JOIN students s ON u.id = s.user_id $where_clause";
        $count_stmt = $conn->prepare($count_query);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $limit);
        
        // جلب الطلاب مع بياناتهم
        $query = "SELECT u.*, s.*,
                         COALESCE(SUM(p.amount_paid), 0) as total_paid,
                         COALESCE(SUM(p.amount_due), 0) as total_due
                  FROM users u
                  LEFT JOIN students s ON u.id = s.user_id
                  LEFT JOIN payments p ON u.id = p.user_id
                  $where_clause
                  GROUP BY u.id
                  ORDER BY u.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $section_data = $stmt->get_result();
        $section_title = "إدارة الطلاب";
        break;
        
    case 'teachers':
        // معالجة البحث والتصفية للمعلمين
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        $filter_subject = isset($_GET['subject']) ? clean_input($_GET['subject']) : '';
        $filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // بناء شروط البحث للمعلمين
        $where_conditions = ["u.user_type = 'teacher'"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR t.teacher_code LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ssss";
        }
        
        if (!empty($filter_subject) && $filter_subject != 'all') {
            $where_conditions[] = "t.subjects LIKE ?";
            $params[] = "%$filter_subject%";
            $types .= "s";
        }
        
        if (!empty($filter_status) && $filter_status != 'all') {
            $where_conditions[] = "u.status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "WHERE u.user_type = 'teacher'";
        
        // جلب إجمالي عدد المعلمين
        $count_query = "SELECT COUNT(*) as total FROM users u LEFT JOIN teachers t ON u.id = t.user_id $where_clause";
        $count_stmt = $conn->prepare($count_query);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $limit);
        
        // جلب المعلمين مع بياناتهم
        $query = "SELECT u.*, t.*
                  FROM users u
                  LEFT JOIN teachers t ON u.id = t.user_id
                  $where_clause
                  ORDER BY u.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $section_data = $stmt->get_result();
        $section_title = "إدارة المعلمين";
        break;
        
    case 'parents':
        // معالجة البحث والتصفية لأولياء الأمور
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        $filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // بناء شروط البحث لأولياء الأمور
        $where_conditions = ["u.user_type = 'parent'"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR p.national_id LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ssss";
        }
        
        if (!empty($filter_status) && $filter_status != 'all') {
            $where_conditions[] = "u.status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "WHERE u.user_type = 'parent'";
        
        // جلب إجمالي عدد أولياء الأمور
        $count_query = "SELECT COUNT(*) as total FROM users u LEFT JOIN parents p ON u.id = p.user_id $where_clause";
        $count_stmt = $conn->prepare($count_query);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $limit);
        
        // جلب أولياء الأمور مع بياناتهم
        $query = "SELECT u.*, p.*, 
                         GROUP_CONCAT(CONCAT(s.full_name, ' (', stu.grade, ')') SEPARATOR '، ') as children_names
                  FROM users u
                  LEFT JOIN parents p ON u.id = p.user_id
                  LEFT JOIN parent_student ps ON p.id = ps.parent_id
                  LEFT JOIN students stu ON ps.student_id = stu.id
                  LEFT JOIN users s ON stu.user_id = s.id
                  $where_clause
                  GROUP BY u.id
                  ORDER BY u.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $section_data = $stmt->get_result();
        $section_title = "إدارة أولياء الأمور";
        break;
        
    case 'moderators':
        // معالجة البحث والتصفية للمشرفين
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        $filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // بناء شروط البحث للمشرفين
        $where_conditions = ["u.user_type = 'moderator'"];
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.username LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ssss";
        }
        
        if (!empty($filter_status) && $filter_status != 'all') {
            $where_conditions[] = "u.status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "WHERE u.user_type = 'moderator'";
        
        // جلب إجمالي عدد المشرفين
        $count_query = "SELECT COUNT(*) as total FROM users u $where_clause";
        $count_stmt = $conn->prepare($count_query);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $limit);
        
        // جلب المشرفين
        $query = "SELECT u.*, m.*
                  FROM users u
                  LEFT JOIN moderators m ON u.id = m.user_id
                  $where_clause
                  ORDER BY u.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $section_data = $stmt->get_result();
        $section_title = "إدارة المشرفين";
        break;
        
    default:
        $section_title = "لوحة التحكم";
        break;
}

// معالجة طلبات POST للحذف
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_user'])) {
        $delete_id = clean_input($_POST['user_id']);
        $delete_type = clean_input($_POST['user_type']);
        
        if ($delete_id != 1 && $delete_id != $user_id) {
            // حذف من الجداول المرتبطة
            switch($delete_type) {
                case 'student':
                    $conn->query("DELETE FROM students WHERE user_id = $delete_id");
                    break;
                case 'teacher':
                    $conn->query("DELETE FROM teachers WHERE user_id = $delete_id");
                    break;
                case 'parent':
                    $conn->query("DELETE FROM parents WHERE user_id = $delete_id");
                    break;
                case 'moderator':
                    $conn->query("DELETE FROM moderators WHERE user_id = $delete_id");
                    break;
            }
            
            // حذف المستخدم
            $delete_query = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "تم حذف المستخدم بنجاح";
                header("Location: admin.php?section=$section");
                exit;
            } else {
                $_SESSION['error'] = "حدث خطأ أثناء حذف المستخدم";
            }
        } else {
            $_SESSION['error'] = "لا يمكن حذف هذا المستخدم";
        }
    }
    
    // تغيير الحالة
    elseif (isset($_POST['change_status'])) {
        $status_id = clean_input($_POST['user_id']);
        $new_status = clean_input($_POST['new_status']);
        
        $update_query = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $status_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "تم تغيير حالة المستخدم بنجاح";
            header("Location: admin.php?section=$section");
            exit;
        } else {
            $_SESSION['error'] = "حدث خطأ أثناء تغيير الحالة";
        }
    }
}

// جلب رسائل الجلسة
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $section_title; ?> | نظام الإدارة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6a11cb;
            --primary-light: #8a2be2;
            --secondary: #2575fc;
            --secondary-light: #3b9eff;
            --accent: #ff7e5f;
            --accent-light: #ff9e8a;
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
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
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
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* الشريط الجانبي */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 5px 0 25px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .logo {
            text-align: center;
            padding: 0 25px 40px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 1.8rem;
            background: linear-gradient(45deg, #6a11cb, #2575fc);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 800;
        }

        .logo p {
            color: #b2bec3;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .nav-menu {
            list-style: none;
            padding: 0 20px;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 20px;
            color: #b2bec3;
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .nav-link:before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, transparent, rgba(106, 17, 203, 0.2));
            transition: width 0.3s ease;
        }

        .nav-link:hover:before {
            width: 100%;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(106, 17, 203, 0.1);
            color: white;
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        .nav-link.active {
            border-right: 4px solid var(--primary);
            background: rgba(106, 17, 203, 0.2);
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .user-profile {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            margin: 0 20px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4);
        }

        .user-info small {
            color: #b2bec3;
            font-size: 0.85rem;
        }

        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 30px;
            background: #f8f9fa;
        }

        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark-light);
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--secondary);
        }

        .page-title {
            font-size: 2.2rem;
            color: var(--primary);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* بطاقات الإحصائيات - تم إزالتها */

        /* أدوات البحث */
        .search-section {
            background: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--light-gray);
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(106, 17, 203, 0.1);
        }

        /* الأزرار */
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(106, 17, 203, 0.5);
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success), var(--success-light));
            color: white;
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger), var(--danger-light));
            color: white;
        }

        .btn-warning {
            background: linear-gradient(45deg, var(--warning), var(--warning-light));
            color: var(--dark);
        }

        .btn-info {
            background: linear-gradient(45deg, var(--info), var(--info-light));
            color: white;
        }

        .btn-sm {
            padding: 10px 20px;
            font-size: 0.9rem;
            border-radius: 8px;
        }

        .btn-add {
            background: linear-gradient(45deg, var(--success), #00cec9);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.4);
            margin-bottom: 20px;
        }

        .btn-add:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 184, 148, 0.5);
        }

        /* الجداول */
        .table-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .data-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }

        .data-table th {
            padding: 20px;
            text-align: right;
            font-weight: 600;
            font-size: 1rem;
            position: relative;
        }

        .data-table th:after {
            content: '';
            position: absolute;
            left: 0;
            top: 25%;
            height: 50%;
            width: 1px;
            background: rgba(255,255,255,0.2);
        }

        .data-table th:first-child:after {
            display: none;
        }

        .data-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: var(--transition);
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.002);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        /* البادجات */
        .badge {
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-primary { background: #e3f2fd; color: #1976d2; }
        .badge-success { background: #e8f5e9; color: #2e7d32; }
        .badge-warning { background: #fff3e0; color: #ef6c00; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #0288d1; }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #fff3cd; color: #856404; }
        .status-suspended { background: #f8d7da; color: #721c24; }

        /* رسائل التنبيه */
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideInDown 0.5s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        @keyframes slideInDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background: linear-gradient(45deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #b1dfbb;
        }

        .alert-danger {
            background: linear-gradient(45deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f1b0b7;
        }

        /* الترقيم */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 12px 18px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            min-width: 45px;
            text-align: center;
        }

        .page-link:hover, .page-current {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        /* صفحة لوحة التحكم */
        .welcome-section {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 60px 40px;
            border-radius: 25px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .welcome-section:before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
            background-size: 50px 50px;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .welcome-title {
            font-size: 3rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .welcome-text {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* رسوم بيانية (محاكاة) */
        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .chart-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart {
            height: 300px;
            background: linear-gradient(180deg, #f8f9fa 0%, white 100%);
            border-radius: 15px;
            position: relative;
            overflow: hidden;
        }

        .chart-bar {
            position: absolute;
            bottom: 0;
            width: 40px;
            border-radius: 8px 8px 0 0;
            transition: var(--transition);
        }

        .chart-bar:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        /* التجاوب */
        @media (max-width: 1200px) {
            .sidebar {
                width: 250px;
            }
            .main-content {
                margin-right: 250px;
            }
        }

        @media (max-width: 992px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 20px 0;
            }
            .main-content {
                margin-right: 0;
            }
            .user-profile {
                position: relative;
                bottom: auto;
                margin-top: 20px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            .top-bar {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .search-form {
                grid-template-columns: 1fr;
            }
            .welcome-title {
                font-size: 2rem;
            }
        }

        /* تأثيرات خاصة */
        .glowing-text {
            text-shadow: 0 0 10px rgba(106, 17, 203, 0.5);
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(106, 17, 203, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(106, 17, 203, 0); }
            100% { box-shadow: 0 0 0 0 rgba(106, 17, 203, 0); }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- الشريط الجانبي -->
        <aside class="sidebar">
            <div class="logo">
                <h1>🎓 نظام الإدارة المدرسية</h1>
                <p>الإصدار 3.0 | لوحة التحكم</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="admin.php?section=dashboard" class="nav-link <?php echo $section == 'dashboard' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>لوحة التحكم</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_students.php?section=students" class="nav-link <?php echo $section == 'students' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-user-graduate"></i>
                        <span>إدارة الطلاب</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="teacher management.php?section=teachers" class="nav-link <?php echo $section == 'teachers' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chalkboard-teacher"></i>
                        <span>إدارة المعلمين</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="grades.php?section=grades" class="nav-link <?php echo $section == 'grades' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span>إدارة الدرجات</span>
                    </a>
                </li>
            </ul>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($full_name); ?></strong><br>
                    <small>
                        <?php
                        $user_types = [
                            'admin' => '👑 مدير النظام',
                            'moderator' => '⚡ مشرف النظام'
                        ];
                        echo $user_types[$user_type] ?? $user_type;
                        ?>
                    </small>
                </div>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <!-- شريط العنوان -->
            <div class="top-bar">
                <div>
                    <div class="breadcrumb">
                        <a href="dashboard.php?section=dashboard"><i class="fas fa-home"></i> الرئيسية</a>
                        <i class="fas fa-chevron-left"></i>
                        <span><?php echo $section_title; ?></span>
                    </div>
                    <h1 class="page-title">
                        <?php
                        $section_icons = [
                            'dashboard' => '📊',
                            'students' => '🎓',
                            'teachers' => '👨‍🏫',
                            'parents' => '👪',
                            'moderators' => '🛡️',
                            'grades' => '📈'
                        ];
                        echo ($section_icons[$section] ?? '📋') . ' ' . $section_title;
                        ?>
                    </h1>
                </div>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    تسجيل الخروج
                </a>
            </div>

            <!-- رسائل التنبيه -->
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle fa-2x"></i>
                    <div>
                        <strong>نجاح!</strong>
                        <p><?php echo $success_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                    <div>
                        <strong>خطأ!</strong>
                        <p><?php echo $error_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if($section == 'dashboard'): ?>
                <!-- لوحة التحكم الرئيسية -->
                <div class="welcome-section">
                    <h2 class="welcome-title glowing-text">مرحباً بك <?php echo htmlspecialchars($full_name); ?>! 👋</h2>
                    <p class="welcome-text">مركز التحكم الشامل لإدارة النظام التعليمي | آخر تحديث: <?php echo date('Y/m/d h:i A'); ?></p>
                </div>

                <!-- روابط سريعة -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 40px;">
                    <a href="register_student_admin.php?section=students" class="btn-add">
                        <i class="fas fa-user-plus"></i>
                        إضافة طالب جديد
                    </a>
                    <a href="add_teacher.php?section=teachers" class="btn-add">
                        <i class="fas fa-user-plus"></i>
                        إضافة معلم جديد
                    
                    </a>
                </div>

            <?php else: ?>
                <!-- قسم المستخدمين (طلاب، معلمين، أولياء أمور، مشرفين) -->
                <a href="add_<?php echo $section; ?>.php" class="btn-add pulse">
                    <i class="fas fa-plus-circle"></i>
                    إضافة <?php echo $section_title; ?> جديد
                </a>

                <!-- أدوات البحث والتصفية -->
                <div class="search-section">
                    <form method="GET" class="search-form">
                        <input type="hidden" name="section" value="<?php echo $section; ?>">
                        
                        <div class="form-group">
                            <label for="search"><i class="fas fa-search"></i> بحث</label>
                            <input type="text" id="search" name="search" class="form-control"
                                   placeholder="ابحث بالاسم، الرقم، البريد..."
                                   value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
                        
                        <?php if($section == 'students'): ?>
                        <div class="form-group">
                            <label for="grade"><i class="fas fa-graduation-cap"></i> الصف الدراسي</label>
                            <select id="grade" name="grade" class="form-control">
                                <option value="all">جميع الصفوف</option>
                                <option value="1" <?php echo ($filter_grade ?? '') == '1' ? 'selected' : ''; ?>>الصف الأول</option>
                                <option value="2" <?php echo ($filter_grade ?? '') == '2' ? 'selected' : ''; ?>>الصف الثاني</option>
                                <option value="3" <?php echo ($filter_grade ?? '') == '3' ? 'selected' : ''; ?>>الصف الثالث</option>
                                <option value="4" <?php echo ($filter_grade ?? '') == '4' ? 'selected' : ''; ?>>الصف الرابع</option>
                                <option value="5" <?php echo ($filter_grade ?? '') == '5' ? 'selected' : ''; ?>>الصف الخامس</option>
                                <option value="6" <?php echo ($filter_grade ?? '') == '6' ? 'selected' : ''; ?>>الصف السادس</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($section == 'teachers'): ?>
                        <div class="form-group">
                            <label for="subject"><i class="fas fa-book"></i> المادة الدراسية</label>
                            <select id="subject" name="subject" class="form-control">
                                <option value="all">جميع المواد</option>
                                <option value="رياضيات" <?php echo ($filter_subject ?? '') == 'رياضيات' ? 'selected' : ''; ?>>الرياضيات</option>
                                <option value="علوم" <?php echo ($filter_subject ?? '') == 'علوم' ? 'selected' : ''; ?>>العلوم</option>
                                <option value="لغة عربية" <?php echo ($filter_subject ?? '') == 'لغة عربية' ? 'selected' : ''; ?>>اللغة العربية</option>
                                <option value="لغة إنجليزية" <?php echo ($filter_subject ?? '') == 'لغة إنجليزية' ? 'selected' : ''; ?>>اللغة الإنجليزية</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="status"><i class="fas fa-circle"></i> الحالة</label>
                            <select id="status" name="status" class="form-control">
                                <option value="all">جميع الحالات</option>
                                <option value="active" <?php echo ($filter_status ?? '') == 'active' ? 'selected' : ''; ?>>نشط</option>
                                <option value="inactive" <?php echo ($filter_status ?? '') == 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                <option value="suspended" <?php echo ($filter_status ?? '') == 'suspended' ? 'selected' : ''; ?>>موقوف</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> تطبيق الفلتر
                            </button>
                            <a href="admin.php?section=<?php echo $section; ?>" class="btn" style="background: #f8f9fa; color: #666;">
                                <i class="fas fa-times"></i> مسح الفلتر
                            </a>
                        </div>
                    </form>
                </div>

                <!-- جدول البيانات -->
                <div class="table-container">
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <?php if($section == 'students'): ?>
                                        <th>#</th>
                                        <th>الصورة</th>
                                        <th>الاسم الكامل</th>
                                        <th>رقم الطالب</th>
                                        <th>الصف الدراسي</th>
                                        <th>تاريخ الميلاد</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الهاتف</th>
                                        <th>الرسوم (مدفوع/مستحق)</th>
                                        <th>الحالة</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    <?php elseif($section == 'teachers'): ?>
                                        <th>#</th>
                                        <th>الصورة</th>
                                        <th>الاسم الكامل</th>
                                        <th>رقم المعلم</th>
                                        <th>التخصص</th>
                                        <th>المواد</th>
                                        <th>تاريخ الميلاد</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الهاتف</th>
                                        <th>الراتب</th>
                                        <th>الحالة</th>
                                        <th>تاريخ التعيين</th>
                                        <th>الإجراءات</th>
                                    <?php elseif($section == 'parents'): ?>
                                        <th>#</th>
                                        <th>الصورة</th>
                                        <th>الاسم الكامل</th>
                                        <th>رقم الهوية</th>
                                        <th>صلة القرابة</th>
                                        <th>الأبناء</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الهاتف</th>
                                        <th>المهنة</th>
                                        <th>الحالة</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    <?php elseif($section == 'moderators'): ?>
                                        <th>#</th>
                                        <th>الصورة</th>
                                        <th>الاسم الكامل</th>
                                        <th>اسم المستخدم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الهاتف</th>
                                        <th>الصلاحيات</th>
                                        <th>آخر دخول</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الإضافة</th>
                                        <th>الإجراءات</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(isset($section_data) && $section_data->num_rows > 0): ?>
                                    <?php $counter = ($page - 1) * $limit + 1; ?>
                                    <?php while($row = $section_data->fetch_assoc()): ?>
                                        <tr>
                                            <?php if($section == 'students'): ?>
                                                <td><?php echo $counter++; ?></td>
                                                <td>
                                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(45deg, #6a11cb, #2575fc); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                        <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                                                </td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($row['student_code'] ?? 'غير محدد'); ?></span></td>
                                                <td><span class="badge badge-primary">الصف <?php echo htmlspecialchars($row['grade'] ?? 'غير محدد'); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['birth_date'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone'] ?? 'غير محدد'); ?></td>
                                                <td>
                                                    <span class="badge badge-success"><?php echo number_format($row['total_paid'] ?? 0); ?> ₪</span>
                                                    <span class="badge badge-warning"><?php echo number_format($row['total_due'] ?? 0); ?> ₪</span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                                        <?php echo $row['status'] == 'active' ? '✅ نشط' : ($row['status'] == 'inactive' ? '⏸️ غير نشط' : '❌ موقوف'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('Y/m/d', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 8px;">
                                                        <a href="view_student.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_student.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm" title="تعديل">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="user_type" value="student">
                                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm" title="حذف"
                                                                    onclick="return confirm('هل أنت متأكد من حذف الطالب <?php echo addslashes($row['full_name']); ?>؟')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                            <select name="new_status" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 8px; border: 1px solid #ddd;">
                                                                <option value="active" <?php echo $row['status'] == 'active' ? 'selected' : ''; ?>>نشط</option>
                                                                <option value="inactive" <?php echo $row['status'] == 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                                                <option value="suspended" <?php echo $row['status'] == 'suspended' ? 'selected' : ''; ?>>موقوف</option>
                                                            </select>
                                                            <input type="hidden" name="change_status" value="1">
                                                        </form>
                                                    </div>
                                                </td>
                                                
                                            <?php elseif($section == 'teachers'): ?>
                                                <!-- جدول المعلمين بنفس النمط -->
                                                <td><?php echo $counter++; ?></td>
                                                <td><!-- صورة المعلم --></td>
                                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($row['teacher_code'] ?? 'غير محدد'); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['specialization'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['subjects'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['birth_date'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone'] ?? 'غير محدد'); ?></td>
                                                <td><span class="badge badge-success"><?php echo number_format($row['salary'] ?? 0); ?> ₪</span></td>
                                                <td><!-- الحالة --></td>
                                                <td><?php echo date('Y/m/d', strtotime($row['hire_date'] ?? $row['created_at'])); ?></td>
                                                <td><!-- الإجراءات --></td>
                                                
                                            <?php elseif($section == 'parents'): ?>
                                                <!-- جدول أولياء الأمور بنفس النمط -->
                                                <td><?php echo $counter++; ?></td>
                                                <td><!-- صورة ولي الأمر --></td>
                                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($row['national_id'] ?? 'غير محدد'); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['relationship'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['children_names'] ?? 'لا يوجد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['job'] ?? 'غير محدد'); ?></td>
                                                <td><!-- الحالة --></td>
                                                <td><?php echo date('Y/m/d', strtotime($row['created_at'])); ?></td>
                                                <td><!-- الإجراءات --></td>
                                                
                                            <?php elseif($section == 'moderators'): ?>
                                                <!-- جدول المشرفين بنفس النمط -->
                                                <td><?php echo $counter++; ?></td>
                                                <td><!-- صورة المشرف --></td>
                                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                                <td><span class="badge badge-info">@<?php echo htmlspecialchars($row['username']); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone'] ?? 'غير محدد'); ?></td>
                                                <td><?php echo htmlspecialchars($row['permissions'] ?? 'صلاحيات كاملة'); ?></td>
                                                <td><?php echo htmlspecialchars($row['last_login'] ?? 'لم يسجل دخول'); ?></td>
                                                <td><!-- الحالة --></td>
                                                <td><?php echo date('Y/m/d', strtotime($row['created_at'])); ?></td>
                                                <td><!-- الإجراءات --></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $section == 'students' ? 12 : ($section == 'teachers' ? 13 : ($section == 'parents' ? 12 : 11)); ?>" style="text-align: center; padding: 60px 20px;">
                                            <div style="font-size: 4rem; color: #dfe6e9; margin-bottom: 20px;">
                                                <?php echo $section == 'students' ? '🎓' : ($section == 'teachers' ? '👨‍🏫' : ($section == 'parents' ? '👪' : '🛡️')); ?>
                                            </div>
                                            <h3 style="color: #636e72;">لا توجد بيانات</h3>
                                            <p style="color: #b2bec3;">لم يتم العثور على <?php echo $section_title; ?> مطابقين لبحثك</p>
                                            <a href="add_<?php echo $section; ?>.php" class="btn-add" style="margin-top: 20px;">
                                                <i class="fas fa-plus-circle"></i>
                                                إضافة أول <?php echo $section_title; ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- الترقيم -->
                <?php if(isset($total_pages) && $total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="?section=<?php echo $section; ?>&page=<?php echo $page-1; ?>&search=<?php echo urlencode($search ?? ''); ?>&grade=<?php echo $filter_grade ?? ''; ?>&subject=<?php echo $filter_subject ?? ''; ?>&status=<?php echo $filter_status ?? ''; ?>"
                               class="page-link">
                                <i class="fas fa-chevron-right"></i> السابق
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $start + 4);
                        if($end - $start < 4) $start = max(1, $end - 4);
                        
                        for($i = $start; $i <= $end; $i++): ?>
                            <a href="?section=<?php echo $section; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search ?? ''); ?>&grade=<?php echo $filter_grade ?? ''; ?>&subject=<?php echo $filter_subject ?? ''; ?>&status=<?php echo $filter_status ?? ''; ?>"
                               class="page-link <?php echo $i == $page ? 'page-current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?section=<?php echo $section; ?>&page=<?php echo $page+1; ?>&search=<?php echo urlencode($search ?? ''); ?>&grade=<?php echo $filter_grade ?? ''; ?>&subject=<?php echo $filter_subject ?? ''; ?>&status=<?php echo $filter_status ?? ''; ?>"
                               class="page-link">
                                التالي <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- تذييل الصفحة -->
            <footer style="text-align: center; margin-top: 50px; padding: 30px; color: #636e72; font-size: 0.9rem; border-top: 1px solid #dfe6e9;">
                <p>© <?php echo date('Y'); ?> نظام الإدارة المدرسية المتكامل | الإصدار 3.0 | تطوير: فريق النظام التعليمي</p>
                <p style="margin-top: 10px; font-size: 0.8rem; color: #b2bec3;">
                    <i class="fas fa-server"></i> وقت الاستجابة: <?php echo round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3); ?> ثانية
                    | <i class="fas fa-database"></i> استعلامات SQL: 12
                </p>
            </footer>
        </main>
    </div>

    <script>
        // تأثيرات JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // تأثيرات البطاقات
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // تأثيرات الجدول
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(30px)';
                
                setTimeout(() => {
                    row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, 200 + (index * 50));
            });
            
            // تحديث الوقت الحي
            function updateLiveTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('ar-SA', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                const dateString = now.toLocaleDateString('ar-SA', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                const welcomeText = document.querySelector('.welcome-text');
                if(welcomeText) {
                    welcomeText.innerHTML = `مركز التحكم الشامل لإدارة النظام التعليمي | ${dateString} | ${timeString}`;
                }
            }
            
            // تحديث كل ثانية
            setInterval(updateLiveTime, 1000);
            
            // إظهار إشعارات
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0.9';
                    alert.style.transform = 'translateY(-5px)';
                }, 100);
                
                // إخفاء التلقائي بعد 5 ثواني
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
            
        
        
        // تحديث الصفحة تلقائياً كل دقيقتين
        setTimeout(function() {
            location.reload();
        }, 120000);
    </script>
</body>
</html>