<?php
session_start();

// استخدام ملفات المساعدة
require_once "includes/config.php";
require_once "includes/functions.php";

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// التحقق من الصلاحيات (للمدير والمعلم فقط)
if (!has_permission('admin') && !has_permission('teacher')) {
    header("Location: dashboard.php");
    exit;
}

// بيانات المستخدم من الجلسة
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$full_name = $_SESSION['full_name'] ?? 'مستخدم';

// تسجيل نشاط المستخدم
log_activity('view_reports', 'عرض التقارير التحصيلية');

// معالجة الفلاتر
$academic_year = clean_input($_GET['year'] ?? '2024-2025');
$semester = clean_input($_GET['semester'] ?? '1');
$grade_level = clean_input($_GET['grade'] ?? 'all');
$subject_id = clean_input($_GET['subject'] ?? 'all');

// جلب البيانات الإحصائية
$stats = [];
$grade_distribution = [];
$top_students = [];
$top_subjects = [];
$subject_pass_rates = [];
$all_subjects = [];

try {
    // 1. إحصاءات الطلاب
    $query = "SELECT COUNT(*) as total_students FROM students";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_students'] = $result->fetch_assoc()['total_students'] ?? 0;
    $stmt->close();

    // 2. عدد المعلمين
    $query = "SELECT COUNT(*) as total_teachers FROM teachers";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_teachers'] = $result->fetch_assoc()['total_teachers'] ?? 0;
    $stmt->close();

    // 3. إحصاءات الدرجات
    $query = "SELECT
                COUNT(*) as total_grades,
                AVG(grade) as avg_grade,
                MAX(grade) as max_grade,
                MIN(grade) as min_grade
              FROM grades WHERE grade IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $grades_stats = $result->fetch_assoc();
   
    // ضمان وجود قيم افتراضية
    $stats['total_grades'] = $grades_stats['total_grades'] ?? 0;
    $stats['avg_grade'] = $grades_stats['avg_grade'] ?? 0;
    $stats['max_grade'] = $grades_stats['max_grade'] ?? 0;
    $stats['min_grade'] = $grades_stats['min_grade'] ?? 0;
    $stmt->close();

    // 4. توزيع الدرجات حسب التصنيف
    // أولاً: التحقق من وجود بيانات
    $check_query = "SELECT COUNT(*) as has_data FROM grades WHERE grade IS NOT NULL";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $has_data = $check_result->fetch_assoc()['has_data'] > 0;
    $check_stmt->close();

    if ($has_data) {
        $query = "SELECT
                    CASE
                        WHEN grade >= 90 THEN 'ممتاز'
                        WHEN grade >= 80 THEN 'جيد جداً'
                        WHEN grade >= 70 THEN 'جيد'
                        WHEN grade >= 60 THEN 'مقبول'
                        ELSE 'راسب'
                    END as grade_category,
                    COUNT(*) as count
                  FROM grades
                  WHERE grade IS NOT NULL
                  GROUP BY grade_category
                  ORDER BY FIELD(grade_category, 'ممتاز', 'جيد جداً', 'جيد', 'مقبول', 'راسب')";
       
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $grade_distribution[$row['grade_category']] = $row['count'];
        }
        $stmt->close();
    } else {
        // بيانات افتراضية للعرض
        $grade_distribution = [
            'ممتاز' => 0,
            'جيد جداً' => 0,
            'جيد' => 0,
            'مقبول' => 0,
            'راسب' => 0
        ];
    }

    // 5. أفضل 5 طلاب حسب متوسط درجاتهم
    // ✅ تم التصحيح: استخدام student_id بدلاً من user_id
    $query = "SELECT
                s.id as student_id,
                s.student_code,
                s.user_id,
                u.full_name,
                AVG(g.grade) as average,
                COUNT(g.id) as total_subjects
              FROM students s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN grades g ON s.id = g.student_id AND g.grade IS NOT NULL
              WHERE s.user_id IS NOT NULL
              GROUP BY s.id
              HAVING total_subjects > 0
              ORDER BY average DESC
              LIMIT 5";
   
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $top_students_result = $stmt->get_result();
    $top_students = [];
    while ($row = $top_students_result->fetch_assoc()) {
        $top_students[] = $row;
    }
    $stmt->close();

    // 6. أفضل 5 مواد حسب متوسط الدرجات
    $query = "SELECT
                s.id as subject_id,
                s.subject_name,
                s.subject_code,
                AVG(g.grade) as average_grade,
                COUNT(g.id) as total_grades
              FROM subjects s
              LEFT JOIN grades g ON s.id = g.subject_id AND g.grade IS NOT NULL
              GROUP BY s.id
              HAVING total_grades > 0
              ORDER BY average_grade DESC
              LIMIT 5";
   
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $top_subjects_result = $stmt->get_result();
    $top_subjects = [];
    while ($row = $top_subjects_result->fetch_assoc()) {
        $top_subjects[] = $row;
    }
    $stmt->close();

    // 7. معدل النجاح لكل مادة (50 درجة كحد أدنى للنجاح)
    // ✅ تم التصحيح: استخدام الجداول الصحيحة
    $query = "SELECT
                s.id as subject_id,
                s.subject_name,
                s.subject_code,
                COUNT(CASE WHEN g.grade >= 50 THEN 1 END) as passed,
                COUNT(CASE WHEN g.grade < 50 THEN 1 END) as failed,
                COUNT(*) as total,
                ROUND((COUNT(CASE WHEN g.grade >= 50 THEN 1 END) / NULLIF(COUNT(*), 0)) * 100, 1) as pass_rate
              FROM subjects s
              LEFT JOIN grades g ON s.id = g.subject_id
              WHERE g.grade IS NOT NULL
              GROUP BY s.id
              ORDER BY pass_rate DESC";
   
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $subject_pass_rates_result = $stmt->get_result();
    $subject_pass_rates = [];
    while ($row = $subject_pass_rates_result->fetch_assoc()) {
        $subject_pass_rates[] = $row;
    }
    $stmt->close();

    // 8. جلب جميع المواد للفلتر
    $query = "SELECT id, subject_name FROM subjects";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $all_subjects_result = $stmt->get_result();
    $all_subjects = [];
    while ($row = $all_subjects_result->fetch_assoc()) {
        $all_subjects[] = $row;
    }
    $stmt->close();

    // 9. إحصائيات إضافية
    // عدد الصفوف
    $query = "SELECT COUNT(*) as total_classes FROM classes";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_classes'] = $result->fetch_assoc()['total_classes'] ?? 0;
    $stmt->close();

    // عدد أولياء الأمور
    $query = "SELECT COUNT(*) as total_parents FROM parents";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_parents'] = $result->fetch_assoc()['total_parents'] ?? 0;
    $stmt->close();

} catch (Exception $e) {
    $error = "حدث خطأ في جلب البيانات: " . $e->getMessage();
    log_activity('error', 'خطأ في التقارير: ' . $e->getMessage());
}

// تحضير بيانات المخططات
$chart_data = [];

// بيانات توزيع الدرجات للمخطط
$chart_data['grades'] = [
    'ممتاز' => $grade_distribution['ممتاز'] ?? 0,
    'جيد جداً' => $grade_distribution['جيد جداً'] ?? 0,
    'جيد' => $grade_distribution['جيد'] ?? 0,
    'مقبول' => $grade_distribution['مقبول'] ?? 0,
    'راسب' => $grade_distribution['راسب'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير التحصيلية | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* CSS styles remain the same... */
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
            --accent: #ff7e5f;
            --dark: #2d3436;
            --light: #f8f9fa;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
            --info: #0984e3;
            --purple: #6c5ce7;
            --pink: #fd79a8;
            --cyan: #00cec9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark);
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* الهيدر */
        .header {
            background: white;
            border-radius: 20px;
            padding: 25px 35px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-content h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-content h1 i {
            color: var(--primary);
        }

        .header-content p {
            color: #666;
            font-size: 1.1rem;
        }

        .highlight {
            color: var(--primary);
            font-weight: 700;
        }

        /* رسائل */
        .message {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.5s ease;
        }

        .error-message {
            background: rgba(214, 48, 49, 0.1);
            border: 2px solid var(--danger);
            color: var(--danger);
        }

        .warning-message {
            background: rgba(253, 203, 110, 0.1);
            border: 2px solid var(--warning);
            color: #e17055;
        }

        .message i {
            font-size: 1.5rem;
        }

        /* باقي الأنماط نفسها كما هي... */
        .filters-container {
            background: white;
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-label {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 12px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: var(--dark);
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success), var(--cyan));
            color: white;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(106, 17, 203, 0.2);
        }

        /* البطاقات الإحصائية */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .icon-students { background: linear-gradient(45deg, var(--primary), var(--purple)); }
        .icon-teachers { background: linear-gradient(45deg, var(--info), var(--secondary)); }
        .icon-grades { background: linear-gradient(45deg, var(--success), var(--cyan)); }
        .icon-avg { background: linear-gradient(45deg, var(--warning), #e17055); }
        .icon-max { background: linear-gradient(45deg, #00b894, #00cec9); }
        .icon-min { background: linear-gradient(45deg, #fd79a8, #e84393); }
        .icon-classes { background: linear-gradient(45deg, var(--purple), var(--pink)); }
        .icon-parents { background: linear-gradient(45deg, #0984e3, #74b9ff); }

        .stat-content h3 {
            font-size: 2.2rem;
            margin-bottom: 5px;
            color: var(--dark);
            font-weight: 700;
        }

        .stat-content p {
            color: #777;
            font-size: 1rem;
        }

        /* شبكة المخططات */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1100px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .chart-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chart-title i {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .chart-title h3 {
            font-size: 1.5rem;
            color: var(--dark);
        }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* جداول البيانات */
        .data-tables {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1100px) {
            .data-tables {
                grid-template-columns: 1fr;
            }
        }

        .data-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .data-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .data-header i {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .data-header h3 {
            font-size: 1.5rem;
            color: var(--dark);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: right;
            color: var(--dark);
            font-weight: 600;
            border-bottom: 2px solid #f0f0f0;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .rank-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }

        .rank-1 { background: linear-gradient(45deg, #fdcb6e, #e17055); }
        .rank-2 { background: linear-gradient(45deg, #636e72, #b2bec3); }
        .rank-3 { background: linear-gradient(45deg, #a29bfe, #6c5ce7); }
        .rank-other { background: linear-gradient(45deg, #dfe6e9, #b2bec3); }

        .grade-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }

        .excellent { background: #00b894; }
        .very-good { background: #00cec9; }
        .good { background: #0984e3; }
        .acceptable { background: #fdcb6e; }
        .failed { background: #d63031; }

        /* تحسينات للمخططات */
        .chart-tooltip {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        /* الفوتر */
        .footer {
            text-align: center;
            padding: 25px;
            color: #888;
            font-size: 0.9rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
           
            .filters-grid {
                grid-template-columns: 1fr;
            }
           
            .stats-cards {
                grid-template-columns: 1fr;
            }
           
            .charts-grid {
                grid-template-columns: 1fr;
            }
           
            .data-tables {
                grid-template-columns: 1fr;
            }
           
            .chart-container {
                height: 300px;
            }
           
            .filter-actions {
                justify-content: center;
            }
        }

        /* أنيميشن للبطاقات */
        @keyframes cardAnimation {
            0% { transform: translateY(30px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .stat-card, .chart-card, .data-card {
            animation: cardAnimation 0.6s ease;
        }

        /* تنسيق خاص للأرقام */
        .number {
            font-family: 'Tajawal', sans-serif;
            font-weight: 700;
        }

        /* أزرار التصدير */
        .export-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            justify-content: flex-end;
        }

        .export-btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .export-pdf { background: linear-gradient(45deg, #d63031, #ff7675); color: white; }
        .export-excel { background: linear-gradient(45deg, #00b894, #00cec9); color: white; }
        .export-print { background: linear-gradient(45deg, #636e72, #b2bec3); color: white; }

        /* حالة عدم وجود بيانات */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .no-data h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- رسالة الخطأ -->
        <?php if (isset($error)): ?>
        <div class="message error-message">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <!-- تحذير إذا لم تكن هناك بيانات -->
        <?php if ($stats['total_grades'] == 0): ?>
        <div class="message warning-message">
            <i class="fas fa-exclamation-triangle"></i>
            <span>لا توجد بيانات درجات في النظام. يجب إدخال درجات الطلاب أولاً لعرض التقارير التحصيلية.</span>
        </div>
        <?php endif; ?>

        <!-- الهيدر -->
        <header class="header">
            <div class="header-content">
                <h1><i class="fas fa-chart-line"></i> التقارير التحصيلية</h1>
                <p><?php echo APP_NAME; ?> | تحليل شامل للأداء الأكاديمي والإحصاءات الدراسية</p>
            </div>
            <div class="header-stats">
                <div class="stat-badge" style="background: linear-gradient(45deg, var(--primary), var(--purple)); padding: 8px 20px; border-radius: 20px; color: white; font-weight: 600;">
                    <i class="fas fa-calendar-alt"></i>
                    <span>العام الدراسي: <?php echo $academic_year; ?></span>
                </div>
            </div>
        </header>

        <!-- أزرار التصدير -->
        <div class="export-buttons">
            <button class="export-btn export-pdf" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> تصدير PDF
            </button>
            <button class="export-btn export-excel" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> تصدير Excel
            </button>
            <button class="export-btn export-print" onclick="window.print()">
                <i class="fas fa-print"></i> طباعة التقرير
            </button>
        </div>

        <!-- الفلاتر -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar"></i>
                            السنة الدراسية
                        </label>
                        <select name="year" class="filter-select">
                            <option value="2024-2025" <?php echo $academic_year == '2024-2025' ? 'selected' : ''; ?>>2024-2025</option>
                            <option value="2023-2024" <?php echo $academic_year == '2023-2024' ? 'selected' : ''; ?>>2023-2024</option>
                            <option value="2022-2023" <?php echo $academic_year == '2022-2023' ? 'selected' : ''; ?>>2022-2023</option>
                        </select>
                    </div>
                   
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-graduation-cap"></i>
                            الفصل الدراسي
                        </label>
                        <select name="semester" class="filter-select">
                            <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>الفصل الأول</option>
                            <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>الفصل الثاني</option>
                            <option value="all" <?php echo $semester == 'all' ? 'selected' : ''; ?>>الكل</option>
                        </select>
                    </div>
                   
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-layer-group"></i>
                            المستوى الدراسي
                        </label>
                        <select name="grade" class="filter-select">
                            <option value="all" <?php echo $grade_level == 'all' ? 'selected' : ''; ?>>جميع المستويات</option>
                            <option value="primary" <?php echo $grade_level == 'primary' ? 'selected' : ''; ?>>المرحلة الابتدائية</option>
                            <option value="middle" <?php echo $grade_level == 'middle' ? 'selected' : ''; ?>>المرحلة المتوسطة</option>
                            <option value="secondary" <?php echo $grade_level == 'secondary' ? 'selected' : ''; ?>>المرحلة الثانوية</option>
                        </select>
                    </div>
                   
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-book"></i>
                            المادة الدراسية
                        </label>
                        <select name="subject" class="filter-select">
                            <option value="all" <?php echo $subject_id == 'all' ? 'selected' : ''; ?>>جميع المواد</option>
                            <?php foreach ($all_subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
               
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> تطبيق الفلاتر
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> إعادة تعيين
                    </button>
                </div>
            </form>
        </div>

        <!-- البطاقات الإحصائية -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon icon-students">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['total_students']); ?></h3>
                    <p>الطلاب المسجلين</p>
                </div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon icon-teachers">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['total_teachers']); ?></h3>
                    <p>المعلمين</p>
                </div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon icon-grades">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['total_grades']); ?></h3>
                    <p>الدرجات المسجلة</p>
                </div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon icon-avg">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['avg_grade'], 1); ?></h3>
                    <p>متوسط الدرجات</p>
                </div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon icon-max">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['max_grade'], 1); ?></h3>
                    <p>أعلى درجة</p>
                </div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon icon-min">
                    <i class="fas fa-chart-line-down"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['min_grade'], 1); ?></h3>
                    <p>أقل درجة</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-classes">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['total_classes'] ?? 0); ?></h3>
                    <p>الصفوف الدراسية</p>
                </div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon icon-parents">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-content">
                    <h3 class="number"><?php echo number_format($stats['total_parents'] ?? 0); ?></h3>
                    <p>أولياء الأمور</p>
                </div>
            </div>
        </div>

        <!-- المخططات البيانية -->
        <div class="charts-grid">
            <!-- مخطط توزيع الدرجات -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        <h3>توزيع الدرجات</h3>
                    </div>
                    <div class="chart-actions">
                        <button class="btn btn-secondary btn-sm" onclick="toggleChartView('gradesChart')">
                            <i class="fas fa-exchange-alt"></i> تبديل العرض
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <?php if (array_sum($chart_data['grades']) > 0): ?>
                        <canvas id="gradesChart"></canvas>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-pie"></i>
                            <h3>لا توجد بيانات للعرض</h3>
                            <p>لم يتم تسجيل أي درجات في النظام</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- مخطط معدلات النجاح للمواد -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-bar"></i>
                        <h3>معدلات النجاح للمواد</h3>
                    </div>
                </div>
                <div class="chart-container">
                    <?php if (count($subject_pass_rates) > 0): ?>
                        <canvas id="passRateChart"></canvas>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-bar"></i>
                            <h3>لا توجد بيانات للعرض</h3>
                            <p>لم يتم حساب معدلات النجاح بعد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- جداول البيانات -->
        <div class="data-tables">
            <!-- أفضل 5 طلاب -->
            <div class="data-card">
                <div class="data-header">
                    <i class="fas fa-trophy"></i>
                    <h3>أفضل 5 طلاب</h3>
                </div>
                <?php if (count($top_students) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المرتبة</th>
                            <th>اسم الطالب</th>
                            <th>المعدل</th>
                            <th>عدد المواد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_students as $rank => $student): ?>
                        <tr>
                            <td>
                                <span class="rank-badge rank-<?php echo $rank + 1; ?>">
                                    <?php echo $rank + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($student['full_name'] ?? 'غير معروف'); ?></strong>
                                <br>
                                <small style="color: #666;"><?php echo $student['student_code'] ?? 'N/A'; ?></small>
                            </td>
                            <td>
                                <span class="grade-badge <?php
                                    if ($student['average'] >= 90) echo 'excellent';
                                    elseif ($student['average'] >= 80) echo 'very-good';
                                    elseif ($student['average'] >= 70) echo 'good';
                                    else echo 'acceptable';
                                ?>">
                                    <?php echo isset($student['average']) ? number_format($student['average'], 1) : '0.0'; ?>
                                </span>
                            </td>
                            <td><?php echo $student['total_subjects'] ?? 0; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-user-graduate"></i>
                    <h3>لا توجد بيانات</h3>
                    <p>لم يتم تسجيل درجات للطلاب بعد</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- أفضل 5 مواد -->
            <div class="data-card">
                <div class="data-header">
                    <i class="fas fa-book"></i>
                    <h3>أفضل 5 مواد</h3>
                </div>
                <?php if (count($top_subjects) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المرتبة</th>
                            <th>المادة</th>
                            <th>المعدل</th>
                            <th>عدد الدرجات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_subjects as $rank => $subject): ?>
                        <tr>
                            <td>
                                <span class="rank-badge rank-<?php echo $rank + 1; ?>">
                                    <?php echo $rank + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                <br>
                                <small style="color: #666;"><?php echo $subject['subject_code']; ?></small>
                            </td>
                            <td>
                                <span class="grade-badge <?php
                                    if ($subject['average_grade'] >= 90) echo 'excellent';
                                    elseif ($subject['average_grade'] >= 80) echo 'very-good';
                                    elseif ($subject['average_grade'] >= 70) echo 'good';
                                    else echo 'acceptable';
                                ?>">
                                    <?php echo isset($subject['average_grade']) ? number_format($subject['average_grade'], 1) : '0.0'; ?>
                                </span>
                            </td>
                            <td><?php echo $subject['total_grades']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-book"></i>
                    <h3>لا توجد بيانات</h3>
                    <p>لم يتم تسجيل درجات للمواد بعد</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- جدول معدلات النجاح -->
        <div class="data-card" style="margin-bottom: 40px;">
            <div class="data-header">
                <i class="fas fa-percentage"></i>
                <h3>معدلات النجاح للمواد</h3>
            </div>
            <?php if (count($subject_pass_rates) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المادة</th>
                        <th>عدد الناجحين</th>
                        <th>عدد الراسبين</th>
                        <th>الإجمالي</th>
                        <th>معدل النجاح</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subject_pass_rates as $subject): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                        <td>
                            <span style="color: #00b894; font-weight: 600;">
                                <?php echo $subject['passed']; ?>
                            </span>
                        </td>
                        <td>
                            <span style="color: #d63031; font-weight: 600;">
                                <?php echo $subject['failed']; ?>
                            </span>
                        </td>
                        <td><?php echo $subject['total']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo min($subject['pass_rate'], 100); ?>%; height: 100%; background: linear-gradient(90deg, #00b894, #00cec9);"></div>
                                </div>
                                <span style="font-weight: 600; color: #00b894;">
                                    <?php echo $subject['pass_rate'] ?? 0; ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-chart-line"></i>
                <h3>لا توجد بيانات</h3>
                <p>لم يتم حساب معدلات النجاح بعد</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- الفوتر -->
        <footer class="footer">
            <p>© 2023 <?php echo APP_NAME; ?> | التقارير التحصيلية</p>
            <p style="margin-top: 10px; font-size: 0.85rem; color: #aaa;">
                <i class="fas fa-info-circle"></i> تم إنشاء التقرير في: <span id="reportTime"></span>
            </p>
        </footer>
    </div>

    <script>
        // تحديث الوقت
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ar-SA');
            const dateString = now.toLocaleDateString('ar-SA');
            document.getElementById('reportTime').textContent = `${dateString} ${timeString}`;
        }
        setInterval(updateTime, 1000);
        updateTime();

        // إعادة تعيين الفلاتر
        function resetFilters() {
            window.location.href = 'reports.php';
        }

        // تصدير PDF
        function exportToPDF() {
            alert('سيتم تصدير التقرير بصيغة PDF');
            // هنا يمكن إضافة كود التصدير الفعلي
        }

        // تصدير Excel
        function exportToExcel() {
            alert('سيتم تصدير التقرير بصيغة Excel');
            // هنا يمكن إضافة كود التصدير الفعلي
        }

        // المخططات البيانية
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (array_sum($chart_data['grades']) > 0): ?>
            // 1. مخطط توزيع الدرجات (دونات)
            const gradesCtx = document.getElementById('gradesChart');
            if (gradesCtx) {
                const gradesChart = new Chart(gradesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['ممتاز (90-100)', 'جيد جداً (80-89)', 'جيد (70-79)', 'مقبول (60-69)', 'راسب (أقل من 60)'],
                        datasets: [{
                            data: [
                                <?php echo $chart_data['grades']['ممتاز'] ?? 0; ?>,
                                <?php echo $chart_data['grades']['جيد جداً'] ?? 0; ?>,
                                <?php echo $chart_data['grades']['جيد'] ?? 0; ?>,
                                <?php echo $chart_data['grades']['مقبول'] ?? 0; ?>,
                                <?php echo $chart_data['grades']['راسب'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                '#00b894',  // ممتاز
                                '#00cec9',  // جيد جداً
                                '#0984e3',  // جيد
                                '#fdcb6e',  // مقبول
                                '#d63031'   // راسب
                            ],
                            borderWidth: 2,
                            borderColor: '#fff',
                            hoverOffset: 20
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                rtl: true,
                                labels: {
                                    font: {
                                        family: 'Tajawal',
                                        size: 14
                                    },
                                    padding: 20
                                }
                            },
                            tooltip: {
                                rtl: true,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((context.parsed / total) * 100);
                                        label += context.parsed + ' طالب (' + percentage + '%)';
                                        return label;
                                    }
                                }
                            }
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });

                // تبديل عرض المخطط
                window.toggleChartView = function(chartId) {
                    if (chartId === 'gradesChart') {
                        if (gradesChart.config.type === 'doughnut') {
                            gradesChart.config.type = 'bar';
                        } else {
                            gradesChart.config.type = 'doughnut';
                        }
                        gradesChart.update();
                    }
                };
            }
            <?php endif; ?>

            <?php if (count($subject_pass_rates) > 0): ?>
            // 2. مخطط معدلات النجاح للمواد
            const passRateCtx = document.getElementById('passRateChart');
            if (passRateCtx) {
                // تحضير بيانات المخطط
                const subjectLabels = <?php echo json_encode(array_column($subject_pass_rates, 'subject_name')); ?>;
                const passRateData = <?php echo json_encode(array_column($subject_pass_rates, 'pass_rate')); ?>;
               
                // اختيار أول 7 مواد فقط للعرض الواضح
                const displayLabels = subjectLabels.slice(0, 7);
                const displayData = passRateData.slice(0, 7);
               
                const colorPalette = [
                    'rgba(106, 17, 203, 0.8)',
                    'rgba(37, 117, 252, 0.8)',
                    'rgba(9, 132, 227, 0.8)',
                    'rgba(0, 184, 148, 0.8)',
                    'rgba(108, 92, 231, 0.8)',
                    'rgba(253, 121, 168, 0.8)',
                    'rgba(253, 203, 110, 0.8)'
                ];
               
                const backgroundColors = displayLabels.map((_, index) =>
                    colorPalette[index % colorPalette.length]
                );

                const passRateChart = new Chart(passRateCtx, {
                    type: 'bar',
                    data: {
                        labels: displayLabels,
                        datasets: [{
                            label: 'معدل النجاح %',
                            data: displayData,
                            backgroundColor: backgroundColors,
                            borderColor: backgroundColors.map(color => color.replace('0.8', '1')),
                            borderWidth: 2,
                            borderRadius: 10,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                rtl: true,
                                callbacks: {
                                    label: function(context) {
                                        return 'معدل النجاح: ' + context.parsed.x.toFixed(1) + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
            }
            <?php endif; ?>
        });

        // طباعة التقرير
        window.onbeforeprint = function() {
            document.querySelectorAll('.btn, .export-buttons').forEach(el => {
                el.style.display = 'none';
            });
        };

        window.onafterprint = function() {
            document.querySelectorAll('.btn, .export-buttons').forEach(el => {
                el.style.display = '';
            });
        };
    </script>
</body>
</html>