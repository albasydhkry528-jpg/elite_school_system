<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// التحقق من صلاحيات المستخدم
if (!has_permission('admin')) {
    header("Location: ../login.php");
    exit();
}

// ============ البحث عن الطلاب ============
$search = '';
$where_clause = '';
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = clean_input($_GET['search']);
    $where_clause = "WHERE (s.student_code LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term, $search_term];
    $types = "ssss";
}

// ============ جلب بيانات الطلاب ============
$sql = "SELECT
            s.id,
            s.student_code,
            s.national_id,
            s.birth_date,
            s.gender,
            s.nationality,
            s.enrollment_date,
            s.class_id,
            s.section,
            u.full_name,
            u.email,
            u.phone,
            u.status as user_status,
            c.class_name,
            c.grade,
            c.section as class_section,
            al.level_name,
            al.level_code,
            p.parent_code,
            pu.full_name as parent_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN academic_levels al ON c.level_id = al.id
        LEFT JOIN parents p ON s.parent_id = p.id
        LEFT JOIN users pu ON p.user_id = pu.id
        $where_clause
        ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ============ جلب جميع الصفوف لاستخدامها في قائمة التعيين ============
$classes_sql = "SELECT c.id, c.class_name, c.grade, al.level_name
                FROM classes c
                LEFT JOIN academic_levels al ON c.level_id = al.id
                WHERE c.is_active = 1
                ORDER BY al.sort_order, c.class_name";
$classes_result = $conn->query($classes_sql);
$classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $classes[$row['id']] = $row;
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
    <title>إدارة الطلاب - نظام النخبة التعليمي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }
     
        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }
     
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            --shadow-light: 0 8px 30px rgba(0,0,0,0.08);
            --shadow-medium: 0 10px 40px rgba(0,0,0,0.12);
            --shadow-dark: 0 15px 50px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }
     
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            width: 100%;
            color: var(--dark-color);
            padding: 0;
        }
     
        .container {
            max-width: 100%;
            width: 100%;
            margin: 0;
            padding: 20px;
        }
     
        /* Header Styles */
        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-medium);
            animation: slideDown 0.5s ease;
            width: 100%;
        }
     
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
     
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
     
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
     
        .logo-icon {
            font-size: 2.5rem;
            background: rgba(255,255,255,0.2);
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
     
        .logo-text h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
     
        .logo-text p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
     
        .header-actions {
            display: flex;
            gap: 15px;
        }
     
        .header-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 12px 25px;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
     
        .header-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
     
        .header-stats {
            display: flex;
            gap: 30px;
        }
     
        .stat-item {
            text-align: center;
        }
     
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 15px;
            margin-bottom: 5px;
        }
     
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
     
        /* Search Section */
        .search-section {
            background: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            animation: fadeIn 0.6s ease;
            width: 100%;
        }
     
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
     
        .search-container {
            position: relative;
            width: 100%;
        }
     
        .search-input {
            width: 100%;
            padding: 18px 25px;
            padding-left: 70px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 1.1rem;
            transition: var(--transition);
            background: #f8f9fa;
        }
     
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            background: white;
        }
     
        .search-icon {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.3rem;
        }
     
        /* Students Table */
        .students-table-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            animation: slideUp 0.7s ease;
            width: 100%;
            margin-bottom: 30px;
        }
     
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
     
        .table-header {
            background: var(--gradient-secondary);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
     
        .table-header h2 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
     
        .table-count {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
     
        /* Table Wrapper for Horizontal Scroll */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
     
        .students-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
     
        .students-table thead {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
        }
     
        .students-table thead th {
            padding: 20px;
            text-align: right;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: 3px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }
     
        .students-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: var(--transition);
        }
     
        .students-table tbody tr:hover {
            background: #f8f9ff;
        }
     
        .students-table tbody td {
            padding: 18px 20px;
            text-align: right;
            vertical-align: middle;
        }
     
        /* Student Info Styles */
        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }
     
        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
     
        .student-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1.1rem;
        }
     
        .student-email {
            color: #666;
            font-size: 0.9rem;
            margin-top: 3px;
        }
     
        .student-id {
            background: var(--gradient-primary);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
     
        .student-code {
            background: var(--gradient-success);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-top: 5px;
        }
     
        .class-display {
            background: var(--gradient-warning);
            color: white;
            padding: 6px 15px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
     
        .section-display {
            background: #e0e0e0;
            color: #333;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            margin-right: 5px;
        }
     
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }
     
        .status-active {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
     
        .status-inactive {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            color: #721c24;
        }
     
        .status-suspended {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #856404;
        }
     
        /* Messages */
        .message {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            animation: slideDown 0.5s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
        }
     
        .message-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #c3e6cb;
        }
     
        .message-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
     
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
     
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
     
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #888;
        }
     
        /* Class Assignment Button */
        .assign-class-btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }
     
        .assign-class-btn:hover {
            background: linear-gradient(135deg, #3a9ce8 0%, #00d9e8 100%);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
     
        .change-class-btn {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-top: 3px;
        }
     
        .change-class-btn:hover {
            background: linear-gradient(135deg, #e082ec 0%, #e4465c 100%);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
     
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
     
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 25px;
            width: 500px;
            max-width: 90%;
            box-shadow: var(--shadow-dark);
            animation: modalSlideIn 0.3s ease;
        }
     
        @keyframes modalSlideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
     
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
     
        .modal-header h3 {
            color: var(--primary-color);
            margin: 0;
        }
     
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
     
        .modal-close:hover {
            color: #666;
        }
     
        .modal-body {
            margin-bottom: 20px;
        }
     
        .form-group {
            margin-bottom: 15px;
        }
     
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
     
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
     
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
     
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
     
        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
     
        .btn-primary:hover {
            background: #3a56d4;
        }
     
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
     
        .btn-secondary:hover {
            background: #5a6268;
        }
     
        /* Responsive Design */
        @media (max-width: 1400px) {
            .students-table {
                min-width: 100%;
            }
        }
     
        @media (max-width: 1200px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
         
            .header-stats {
                justify-content: center;
                flex-wrap: wrap;
            }
         
            .header-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
     
        @media (max-width: 768px) {
            .header {
                padding: 20px;
            }
         
            .search-section {
                padding: 20px;
            }
         
            .table-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 20px;
            }
         
            .modal-content {
                padding: 20px;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="logo-text">
                        <h1>نظام إدارة الطلاب</h1>
                        <p>مدرسة النخبة الدولية</p>
                    </div>
                </div>
             
                <div class="header-actions">
                    <button class="header-btn" onclick="window.location.href='users.php'">
                        <i class="fas fa-home"></i>
                        لوحة التحكم
                    </button>
                    <button class="header-btn" onclick="fixAllClasses()">
                        <i class="fas fa-tools"></i>
                        إصلاح التلقائي
                    </button>
                </div>
            </div>
         
            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $result->num_rows; ?></div>
                    <div class="stat-label">عدد الطلاب</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="assigned-count">
                        <?php
                        // حساب الطلاب الذين لديهم صف
                        $assigned_count = 0;
                        $result->data_seek(0);
                        while ($row = $result->fetch_assoc()) {
                            if (!empty($row['class_id'])) $assigned_count++;
                        }
                        $result->data_seek(0);
                        echo $assigned_count;
                        ?>
                    </div>
                    <div class="stat-label">طلاب معينين لصف</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo date('Y'); ?></div>
                    <div class="stat-label">العام الدراسي</div>
                </div>
            </div>
        </header>

        <!-- Messages -->
        <?php if (!empty($success_msg)): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
     
        <?php if (!empty($error_msg)): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-section">
            <div class="search-container">
                <form method="GET" action="manage_students.php" id="searchForm">
                    <input type="text"
                           name="search"
                           class="search-input"
                           placeholder="🔍 ابحث عن طالب بالاسم، الكود، البريد الإلكتروني أو الهاتف..."
                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </form>
            </div>
        </div>

        <!-- Students Table -->
        <div class="students-table-container">
            <div class="table-header">
                <h2><i class="fas fa-users"></i> قائمة الطلاب المسجلين</h2>
                <div class="table-count">
                    <i class="fas fa-user-check"></i> <?php echo $result->num_rows; ?> طالب
                    <span style="margin: 0 10px;">|</span>
                    <i class="fas fa-graduation-cap"></i> <span id="display-assigned-count"><?php echo $assigned_count; ?></span> معينين لصف
                </div>
            </div>
         
            <?php if ($result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>المعلومات الشخصية</th>
                                <th>ID</th>
                                <th>الكود</th>
                                <th>تاريخ الميلاد</th>
                                <th>الجنس</th>
                                <th>الصف الدراسي</th>
                                <th>الجنسية</th>
                                <th>تاريخ التسجيل</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $result->fetch_assoc()): ?>
                                <?php
                                // حساب العمر
                                $age = '';
                                if (!empty($student['birth_date'])) {
                                    $birth_date = new DateTime($student['birth_date']);
                                    $today = new DateTime();
                                    $age_interval = $today->diff($birth_date);
                                    $age = $age_interval->y . ' سنة';
                                }
                             
                                // تنسيق التواريخ
                                $birth_date_formatted = !empty($student['birth_date']) ? date('d/m/Y', strtotime($student['birth_date'])) : 'غير محدد';
                                $enrollment_date_formatted = !empty($student['enrollment_date']) ? date('d/m/Y', strtotime($student['enrollment_date'])) : 'غير محدد';
                             
                                // تنظيف البيانات للعرض الآمن
                                $full_name = !empty($student['full_name']) ? htmlspecialchars($student['full_name']) : 'غير محدد';
                                $email = !empty($student['email']) ? htmlspecialchars($student['email']) : 'غير محدد';
                                $student_code = !empty($student['student_code']) ? htmlspecialchars($student['student_code']) : 'غير محدد';
                                $gender_display = !empty($student['gender']) ? ($student['gender'] == 'ذكر' ? '👦 ذكر' : '👧 أنثى') : 'غير محدد';
                                $nationality = !empty($student['nationality']) ? htmlspecialchars($student['nationality']) : 'غير محدد';
                             
                                // تحديد عرض الصف الدراسي
                                $has_class = !empty($student['class_id']) && !empty($student['class_name']);
                                $class_display = 'غير محدد';
                               
                                if ($has_class) {
                                    // استخدام level_name إذا متاح، وإلا grade
                                    if (!empty($student['level_name'])) {
                                        $class_display = htmlspecialchars($student['level_name'] . ' - ' . $student['class_name']);
                                    } elseif (!empty($student['grade'])) {
                                        $class_display = htmlspecialchars($student['grade'] . ' - ' . $student['class_name']);
                                    } else {
                                        $class_display = htmlspecialchars($student['class_name']);
                                    }
                                } elseif (!empty($student['class_id']) && empty($student['class_name'])) {
                                    // إذا كان class_id موجوداً ولكن class_name غير موجود (خطأ في الربط)
                                    $class_display = 'خطأ في الربط (كود الصف: ' . htmlspecialchars($student['class_id']) . ')';
                                }
                                ?>
                             
                                <tr id="student-<?php echo $student['id']; ?>" data-student-id="<?php echo $student['id']; ?>" data-student-name="<?php echo $full_name; ?>">
                                    <!-- المعلومات الشخصية -->
                                    <td data-label="المعلومات الشخصية">
                                        <div class="student-info">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=667eea&color=fff"
                                                 alt="صورة الطالب"
                                                 class="student-avatar">
                                            <div>
                                                <div class="student-name"><?php echo $full_name; ?></div>
                                                <div class="student-email"><?php echo $email; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                 
                                    <!-- ID -->
                                    <td data-label="ID">
                                        <span class="student-id">#<?php echo $student['id']; ?></span>
                                    </td>
                                 
                                    <!-- الكود -->
                                    <td data-label="الكود">
                                        <span class="student-code"><?php echo $student_code; ?></span>
                                    </td>
                                 
                                    <!-- تاريخ الميلاد -->
                                    <td data-label="تاريخ الميلاد">
                                        <?php echo $birth_date_formatted; ?>
                                        <?php if (!empty($age)): ?>
                                            <br><small>(<?php echo $age; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                 
                                    <!-- الجنس -->
                                    <td data-label="الجنس">
                                        <?php echo $gender_display; ?>
                                    </td>
                                 
                                    <!-- الصف الدراسي -->
                                    <td data-label="الصف الدراسي">
                                        <?php if ($has_class): ?>
                                            <span class="class-display">
                                                <?php echo $class_display; ?>
                                            </span>
                                            <?php if (!empty($student['section'])): ?>
                                                <br>
                                                <span class="section-display">
                                                    قسم: <?php echo htmlspecialchars($student['section']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <br>
                                            <button class="change-class-btn" onclick="openClassModal(<?php echo $student['id']; ?>, <?php echo $student['class_id']; ?>)">
                                                <i class="fas fa-exchange-alt"></i> تغيير الصف
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted" style="color: #888;">
                                                <i class="fas fa-exclamation-circle" style="margin-left: 5px;"></i>
                                                <?php echo $class_display; ?>
                                            </span>
                                            <br>
                                            <button class="assign-class-btn" onclick="openClassModal(<?php echo $student['id']; ?>, null)">
                                                <i class="fas fa-plus-circle"></i> تعيين صف
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                 
                                    <!-- الجنسية -->
                                    <td data-label="الجنسية">
                                        <?php echo $nationality; ?>
                                    </td>
                                 
                                    <!-- تاريخ التسجيل -->
                                    <td data-label="تاريخ التسجيل">
                                        <?php echo $enrollment_date_formatted; ?>
                                        <?php if (!empty($student['enrollment_date'])):
                                            $enrollment = new DateTime($student['enrollment_date']);
                                            $now = new DateTime();
                                            $interval = $now->diff($enrollment);
                                            if ($interval->y > 0): ?>
                                                <br><small>(<?php echo $interval->y; ?> سنوات)</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                 
                                    <!-- الحالة -->
                                    <td data-label="الحالة">
                                        <?php if (!empty($student['user_status'])): ?>
                                            <?php if ($student['user_status'] == 'active'): ?>
                                                <span class="status-badge status-active">
                                                    <i class="fas fa-check-circle"></i> نشط
                                                </span>
                                            <?php elseif ($student['user_status'] == 'inactive'): ?>
                                                <span class="status-badge status-inactive">
                                                    <i class="fas fa-times-circle"></i> غير نشط
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-suspended">
                                                    <i class="fas fa-ban"></i> موقوف
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">غير محدد</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <h3>لا توجد بيانات للطلاب</h3>
                    <p><?php echo !empty($search) ? 'لم يتم العثور على طلاب مطابقين للبحث' : 'لم يتم تسجيل أي طلاب بعد'; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal لتحديد الصف -->
    <div id="classModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-graduation-cap"></i> تعيين الصف الدراسي</h3>
                <button class="modal-close" onclick="closeClassModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalStudentId">
               
                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> اسم الطالب:</label>
                    <input type="text" id="modalStudentName" readonly>
                </div>
               
                <div class="form-group">
                    <label><i class="fas fa-chalkboard-teacher"></i> اختر الصف الدراسي:</label>
                    <select id="modalClassId">
                        <option value="">-- اختر صف دراسي --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>">
                                <?php
                                $display_name = '';
                                if (!empty($class['level_name'])) {
                                    $display_name = $class['level_name'] . ' - ' . $class['class_name'];
                                } elseif (!empty($class['grade'])) {
                                    $display_name = $class['grade'] . ' - ' . $class['class_name'];
                                } else {
                                    $display_name = $class['class_name'];
                                }
                                echo htmlspecialchars($display_name);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
               
                <div class="form-group">
                    <label><i class="fas fa-bookmark"></i> القسم (اختياري):</label>
                    <input type="text" id="modalSection" placeholder="مثال: أ، ب، ج...">
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeClassModal()">إلغاء</button>
                <button class="btn-primary" onclick="assignClass()">حفظ التغييرات</button>
            </div>
        </div>
    </div>

    <script>
        // ============ البحث الفوري ============
        document.querySelector('.search-input').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.students-table tbody tr[id^="student-"]');
         
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
                    window.location.href = 'manage_students.php?search=' + encodeURIComponent(searchValue);
                } else {
                    window.location.href = 'manage_students.php';
                }
            }
        });

        // ============ Modal Functions ============
        function openClassModal(studentId, currentClassId) {
            const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
            const studentName = row.getAttribute('data-student-name');
           
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('modalStudentName').value = studentName;
            document.getElementById('modalClassId').value = currentClassId || '';
            document.getElementById('modalSection').value = '';
           
            document.getElementById('classModal').style.display = 'flex';
        }
       
        function closeClassModal() {
            document.getElementById('classModal').style.display = 'none';
        }
       
        function assignClass() {
            const studentId = document.getElementById('modalStudentId').value;
            const classId = document.getElementById('modalClassId').value;
            const section = document.getElementById('modalSection').value;
           
            if (!classId) {
                alert('يرجى اختيار صف دراسي');
                return;
            }
           
            // إرسال البيانات عبر AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'assign_class.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
           
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('تم تعيين الصف الدراسي بنجاح');
                        location.reload();
                    } else {
                        alert('حدث خطأ: ' + response.message);
                    }
                }
            };
           
            const data = `student_id=${studentId}&class_id=${classId}&section=${section}`;
            xhr.send(data);
        }
       
        function fixAllClasses() {
            if (confirm('هل تريد تشغيل الإصلاح التلقائي للصفوف؟ هذا سيحدد صفوفاً للطلاب الذين ليس لديهم صف.')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'auto_assign_classes.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
               
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        alert(response.message);
                        if (response.success) {
                            location.reload();
                        }
                    }
                };
               
                xhr.send('action=auto_assign');
            }
        }
       
        // إغلاق Modal عند النقر خارجها
        document.getElementById('classModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeClassModal();
            }
        });
       
        // إغلاق Modal بمفتاح ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeClassModal();
            }
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>