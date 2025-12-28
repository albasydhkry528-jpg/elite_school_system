<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// التحقق من صلاحيات المستخدم
if (!has_permission('admin')) {
    header("Location: ../login.php");
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
            t.bank_name,
            t.bank_account,
            t.office_number,
            t.office_hours,
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
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            background: rgba(255, 255, 255, 0.85);
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
            padding: 22px 20px;
            text-align: right;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            border-bottom: 3px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .teachers-table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            transform: translateX(100%);
            transition: transform 0.6s ease;
        }

        .teachers-table thead th:hover::after {
            transform: translateX(-100%);
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
            transform: translateX(-10px);
            box-shadow: 0 5px 15px rgba(0, 176, 155, 0.1);
        }

        .teachers-table tbody td {
            padding: 20px;
            text-align: right;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Teacher Info Card */
        .teacher-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
        }

        .teacher-card:hover {
            background: white;
            box-shadow: var(--shadow-soft);
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
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 20px rgba(0, 176, 155, 0.4);
        }

        .teacher-info-content {
            flex: 1;
        }

        .teacher-name {
            font-weight: 700;
            color: var(--dark-color);
            font-size: 1.2rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .teacher-email {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .teacher-id {
            background: var(--gradient-primary);
            color: white;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        /* Badge Styles */
        .badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .badge-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .badge-success {
            background: var(--gradient-success);
            color: white;
        }

        .badge-warning {
            background: var(--gradient-warning);
            color: #333;
        }

        .badge-danger {
            background: var(--gradient-danger);
            color: white;
        }

        .badge-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .badge-purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge-light {
            background: rgba(248, 249, 250, 0.8);
            color: #333;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Class Badges */
        .class-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 45px;
            align-items: center;
        }

        .class-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .class-badge:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .class-badge.empty {
            background: rgba(240, 240, 240, 0.8);
            color: #666;
            padding: 8px 20px;
            border-radius: 20px;
            font-style: italic;
            box-shadow: none;
        }

        /* Status Badge */
        .status-badge {
            padding: 10px 24px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .status-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }

        .status-active {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
        }

        .status-inactive {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            color: #721c24;
        }

        .status-suspended {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            color: #856404;
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
           
            .teacher-card {
                flex-direction: column;
                text-align: center;
            }
           
            .badge-container {
                justify-content: center;
            }
        }

        /* Loading Skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        /* Hover Effects */
        .hover-lift {
            transition: var(--transition);
        }

        .hover-lift:hover {
            transform: translateY(-5px);
        }

        /* Gradient Text */
        .gradient-text {
            background: var(--gradient-success);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            <div class="alert-message alert-success">
                <i class="fas fa-check-circle fa-lg"></i>
                <span class="ms-2"><?php echo $success_msg; ?></span>
            </div>
        <?php endif; ?>
    
        <?php if (!empty($error_msg)): ?>
            <div class="alert-message alert-error">
                <i class="fas fa-exclamation-circle fa-lg"></i>
                <span class="ms-2"><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-section glass-card">
            <div class="search-wrapper">
                <form method="GET" action="manage_teachers.php" id="searchForm">
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
                    قائمة المعلمين التفصيلية
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
                                <th>المعلومات الأساسية</th>
                                <th>المؤهلات</th>
                                <th>المعلومات الوظيفية</th>
                                <th>التاريخ والمدة</th>
                                <th>الصفوف المشرفة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers_with_classes as $teacher): ?>
                                <?php
                                $full_name = !empty($teacher['full_name']) ? htmlspecialchars($teacher['full_name']) : 'غير محدد';
                                $email = !empty($teacher['email']) ? htmlspecialchars($teacher['email']) : 'غير محدد';
                                $teacher_code = !empty($teacher['teacher_code']) ? htmlspecialchars($teacher['teacher_code']) : 'غير محدد';
                                $qualification = !empty($teacher['qualification']) ? htmlspecialchars($teacher['qualification']) : 'غير محدد';
                                $specialization = !empty($teacher['specialization']) ? htmlspecialchars($teacher['specialization']) : 'غير محدد';
                                $employment_type = !empty($teacher['employment_type']) ? htmlspecialchars($teacher['employment_type']) : 'غير محدد';
                                $salary = !empty($teacher['salary']) ? number_format($teacher['salary'], 0) . ' ر.س' : 'غير محدد';
                                $experience = !empty($teacher['experience_years']) ? $teacher['experience_years'] . ' سنوات' : '0 سنوات';
                                $teaching_classes = $teacher['teaching_classes'] ?? [];
                               
                                // حساب العمر
                                $age = 'غير محدد';
                                if (!empty($teacher['birth_date'])) {
                                    $birth_date = new DateTime($teacher['birth_date']);
                                    $today = new DateTime();
                                    $age_interval = $today->diff($birth_date);
                                    $age = $age_interval->y . ' سنة';
                                }
                               
                                // حساب مدة العمل
                                $work_duration = 'غير محدد';
                                if (!empty($teacher['hire_date'])) {
                                    $hire_date = new DateTime($teacher['hire_date']);
                                    $today = new DateTime();
                                    $interval = $today->diff($hire_date);
                                    if ($interval->y > 0) {
                                        $work_duration = $interval->y . ' سنوات';
                                    } elseif ($interval->m > 0) {
                                        $work_duration = $interval->m . ' أشهر';
                                    } else {
                                        $work_duration = 'أقل من شهر';
                                    }
                                }
                                ?>
                            
                                <tr class="hover-lift">
                                    <!-- المعلومات الأساسية -->
                                    <td>
                                        <div class="teacher-card">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=00b09b&color=fff&size=128&font-size=0.5&bold=true"
                                                 alt="صورة المعلم"
                                                 class="teacher-avatar">
                                            <div class="teacher-info-content">
                                                <div class="teacher-name">
                                                    <?php echo $full_name; ?>
                                                    <span class="teacher-id">
                                                        <i class="fas fa-hashtag"></i> <?php echo $teacher['id']; ?>
                                                    </span>
                                                </div>
                                                <div class="teacher-email">
                                                    <i class="fas fa-envelope"></i>
                                                    <?php echo $email; ?>
                                                </div>
                                                <?php if (!empty($teacher['phone'])): ?>
                                                <div class="mb-2">
                                                    <span class="badge badge-light">
                                                        <i class="fas fa-phone"></i>
                                                        <?php echo htmlspecialchars($teacher['phone']); ?>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <span class="badge badge-purple">
                                                        <i class="fas fa-barcode"></i>
                                                        <?php echo $teacher_code; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                
                                    <!-- المؤهلات -->
                                    <td>
                                        <div class="badge-container">
                                            <span class="badge badge-success mb-2">
                                                <i class="fas fa-book"></i>
                                                <?php echo $specialization; ?>
                                            </span>
                                            <span class="badge badge-primary mb-2">
                                                <i class="fas fa-graduation-cap"></i>
                                                <?php echo $qualification; ?>
                                            </span>
                                            <span class="badge badge-warning mb-2">
                                                <i class="fas fa-star"></i>
                                                <?php echo $experience; ?>
                                            </span>
                                            <span class="badge badge-info">
                                                <i class="fas fa-birthday-cake"></i>
                                                <?php echo $age; ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($teacher['national_id'])): ?>
                                        <div class="mt-2">
                                            <span class="badge badge-light">
                                                <i class="fas fa-id-card"></i>
                                                <?php echo htmlspecialchars($teacher['national_id']); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                
                                    <!-- المعلومات الوظيفية -->
                                    <td>
                                        <div class="badge-container">
                                            <span class="badge badge-success mb-2">
                                                <i class="fas fa-money-bill-wave"></i>
                                                <?php echo $salary; ?>
                                            </span>
                                            <span class="badge badge-primary mb-2">
                                                <i class="fas fa-briefcase"></i>
                                                <?php echo $employment_type; ?>
                                            </span>
                                            <?php if (!empty($teacher['bank_name'])): ?>
                                            <span class="badge badge-info mb-2">
                                                <i class="fas fa-university"></i>
                                                <?php echo htmlspecialchars($teacher['bank_name']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($teacher['office_number'])): ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-door-closed"></i>
                                                مكتب <?php echo htmlspecialchars($teacher['office_number']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                
                                    <!-- التاريخ والمدة -->
                                    <td>
                                        <div class="badge-container">
                                            <span class="badge badge-success mb-2">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php echo !empty($teacher['hire_date']) ? date('d/m/Y', strtotime($teacher['hire_date'])) : 'غير محدد'; ?>
                                            </span>
                                            <span class="badge badge-primary mb-2">
                                                <i class="fas fa-clock"></i>
                                                <?php echo $work_duration; ?>
                                            </span>
                                            <?php if (!empty($teacher['created_at'])): ?>
                                            <span class="badge badge-info">
                                                <i class="fas fa-user-plus"></i>
                                                <?php echo date('d/m/Y', strtotime($teacher['created_at'])); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                
                                    <!-- الصفوف المشرفة -->
                                    <td>
                                        <div class="class-badges">
                                            <?php if (!empty($teaching_classes)): ?>
                                                <?php foreach ($teaching_classes as $class): ?>
                                                    <span class="class-badge">
                                                        <i class="fas fa-chalkboard-teacher"></i>
                                                        <?php echo htmlspecialchars($class); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="class-badge empty">
                                                    <i class="fas fa-times-circle"></i>
                                                    لا يوجد صفوف حالياً
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                
                                    <!-- الحالة -->
                                    <td>
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
                                            <span class="badge badge-light">
                                                غير محدد
                                            </span>
                                        <?php endif; ?>
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

    <script>
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
                if (searchValue !== '') {
                    window.location.href = 'manage_teachers.php?search=' + encodeURIComponent(searchValue);
                } else {
                    window.location.href = 'manage_teachers.php';
                }
            }
        });

        // ============ تأثيرات Hover الإضافية ============
        document.querySelectorAll('.badge, .class-badge, .status-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
           
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // ============ تأثير النقر على البطاقات ============
        document.querySelectorAll('.teacher-card').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
            });
        });

        // ============ Auto-focus على حقل البحث ============
        setTimeout(() => {
            document.querySelector('.search-input').focus();
        }, 500);

        // ============ تحميل سلسل للصور ============
        document.querySelectorAll('.teacher-avatar').forEach(img => {
            img.addEventListener('load', function() {
                this.style.animation = 'fadeIn 0.5s ease';
            });
        });

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
           
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
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