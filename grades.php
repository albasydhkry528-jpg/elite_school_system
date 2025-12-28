<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';


// بدء الجلسة إذا لم تكن بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول وصلاحيات المدير
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// معالجة إضافة/تعديل الدرجات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_grade'])) {
        $student_id = clean_input($_POST['student_id']);
        $subject_id = clean_input($_POST['subject_id']);
        $class_id = clean_input($_POST['class_id']);
        $teacher_id = clean_input($_POST['teacher_id']);
        $exam_type = clean_input($_POST['exam_type']);
        $grade = clean_input($_POST['grade']);
        $max_grade = clean_input($_POST['max_grade']);
        $notes = clean_input($_POST['notes']);
       
        // التحقق من وجود الدرجة مسبقاً لنفس الطالب والمادة والنوع
        $check_sql = "SELECT id FROM grades WHERE student_id = ? AND subject_id = ?
                     AND class_id = ? AND exam_type = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iiis", $student_id, $subject_id, $class_id, $exam_type);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
       
        if ($check_result->num_rows > 0) {
            $error = "هناك درجة مسجلة مسبقاً لهذا الطالب في نفس المادة ونوع الاختبار";
        } else {
            // إضافة الدرجة الجديدة
            $sql = "INSERT INTO grades (student_id, subject_id, class_id, teacher_id,
                    exam_type, grade, max_grade, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiisdis", $student_id, $subject_id, $class_id,
                             $teacher_id, $exam_type, $grade, $max_grade, $notes);
           
            if ($stmt->execute()) {
                $message = "تم إضافة الدرجة بنجاح";
                // تسجيل في السجل
                log_action($_SESSION['user_id'], 'add_grade',
                          "تم إضافة درجة للطالب ID: $student_id في المادة ID: $subject_id");
            } else {
                $error = "حدث خطأ أثناء إضافة الدرجة: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
   
    // معالجة تعديل الدرجة
    if (isset($_POST['update_grade'])) {
        $grade_id = clean_input($_POST['grade_id']);
        $grade = clean_input($_POST['grade']);
        $max_grade = clean_input($_POST['max_grade']);
        $notes = clean_input($_POST['notes']);
       
        $sql = "UPDATE grades SET grade = ?, max_grade = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("disi", $grade, $max_grade, $notes, $grade_id);
       
        if ($stmt->execute()) {
            $message = "تم تحديث الدرجة بنجاح";
            log_action($_SESSION['user_id'], 'update_grade', "تم تحديث الدرجة ID: $grade_id");
        } else {
            $error = "حدث خطأ أثناء تحديث الدرجة: " . $conn->error;
        }
        $stmt->close();
    }
   
    // معالجة حذف الدرجة
    if (isset($_POST['delete_grade'])) {
        $grade_id = clean_input($_POST['grade_id']);
       
        $sql = "DELETE FROM grades WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $grade_id);
       
        if ($stmt->execute()) {
            $message = "تم حذف الدرجة بنجاح";
            log_action($_SESSION['user_id'], 'delete_grade', "تم حذف الدرجة ID: $grade_id");
        } else {
            $error = "حدث خطأ أثناء حذف الدرجة: " . $conn->error;
        }
        $stmt->close();
    }
   
    // معالجة إضافة درجات دفعة واحدة
    if (isset($_POST['bulk_add'])) {
        $class_id = clean_input($_POST['class_id']);
        $subject_id = clean_input($_POST['subject_id']);
        $teacher_id = clean_input($_POST['teacher_id']);
        $exam_type = clean_input($_POST['exam_type']);
        $max_grade = clean_input($_POST['max_grade']);
       
        // الحصول على جميع طلاب الصف
        $students_sql = "SELECT s.id, s.student_code, u.full_name
                        FROM students s
                        JOIN users u ON s.user_id = u.id
                        JOIN class_students cs ON s.id = cs.student_id
                        WHERE cs.class_id = ? AND cs.is_active = 1";
        $students_stmt = $conn->prepare($students_sql);
        $students_stmt->bind_param("i", $class_id);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();
       
        $added_count = 0;
       
        while ($student = $students_result->fetch_assoc()) {
            $grade_input_name = "grade_" . $student['id'];
           
            if (isset($_POST[$grade_input_name]) && $_POST[$grade_input_name] !== '') {
                $grade = clean_input($_POST[$grade_input_name]);
               
                // التحقق من عدم وجود درجة مسبقة
                $check_sql = "SELECT id FROM grades WHERE student_id = ? AND subject_id = ?
                            AND class_id = ? AND exam_type = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("iiis", $student['id'], $subject_id, $class_id, $exam_type);
                $check_stmt->execute();
               
                if ($check_stmt->get_result()->num_rows == 0) {
                    // إضافة الدرجة
                    $insert_sql = "INSERT INTO grades (student_id, subject_id, class_id,
                                  teacher_id, exam_type, grade, max_grade)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iiiisdi", $student['id'], $subject_id, $class_id,
                                           $teacher_id, $exam_type, $grade, $max_grade);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    $added_count++;
                }
                $check_stmt->close();
            }
        }
       
        $students_stmt->close();
        $message = "تم إضافة $added_count درجة بنجاح";
        log_action($_SESSION['user_id'], 'bulk_add_grades',
                  "تم إضافة درجات دفعة واحدة للصف ID: $class_id والمادة ID: $subject_id");
    }
   
    // معالجة حساب المجموع والمعدل للطالب
    if (isset($_POST['calculate_grades'])) {
        $student_id = clean_input($_POST['student_id']);
        $class_id = clean_input($_POST['class_id']);
       
        // حساب إجمالي الدرجات للطالب في الصف
        $total_sql = "SELECT
                     SUM(grade) as total_grade,
                     SUM(max_grade) as total_max_grade,
                     AVG(grade) as average_grade,
                     COUNT(*) as total_exams
                     FROM grades
                     WHERE student_id = ? AND class_id = ?";
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bind_param("ii", $student_id, $class_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_data = $total_result->fetch_assoc();
       
        if ($total_data['total_exams'] > 0) {
            $percentage = ($total_data['total_grade'] / $total_data['total_max_grade']) * 100;
           
            $message = "نتائج حساب الدرجات:<br>
                       إجمالي الدرجات: " . number_format($total_data['total_grade'], 2) . "/" . number_format($total_data['total_max_grade'], 2) . "<br>
                       المعدل: " . number_format($total_data['average_grade'], 2) . "<br>
                       النسبة المئوية: " . number_format($percentage, 2) . "%<br>
                       عدد الاختبارات: " . $total_data['total_exams'];
        } else {
            $error = "لا توجد درجات مسجلة لهذا الطالب في هذا الصف";
        }
        $total_stmt->close();
    }
}

// الحصول على البيانات للعرض
$search_query = '';
if (isset($_GET['search'])) {
    $search_query = clean_input($_GET['search']);
}

// بناء الاستعلام للبحث
$sql = "SELECT g.*,
        s.student_code, u.full_name as student_name,
        sub.subject_name,
        c.class_name, c.grade as class_grade,
        t.teacher_code, tu.full_name as teacher_name
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN subjects sub ON g.subject_id = sub.id
        JOIN classes c ON g.class_id = c.id
        JOIN teachers t ON g.teacher_id = t.id
        JOIN users tu ON t.user_id = tu.id
        WHERE 1=1";

if (!empty($search_query)) {
    $sql .= " AND (u.full_name LIKE '%$search_query%'
              OR s.student_code LIKE '%$search_query%'
              OR sub.subject_name LIKE '%$search_query%'
              OR c.class_name LIKE '%$search_query%'
              OR t.teacher_code LIKE '%$search_query%'
              OR tu.full_name LIKE '%$search_query%')";
}

$sql .= " ORDER BY g.recorded_at DESC";
$result = $conn->query($sql);

// الحصول على البيانات للقوائم المنسدلة
$students = $conn->query("SELECT s.id, s.student_code, u.full_name
                         FROM students s
                         JOIN users u ON s.user_id = u.id
                         ORDER BY u.full_name");

$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name");
$classes = $conn->query("SELECT * FROM classes WHERE is_active = 1 ORDER BY grade, class_name");
$teachers = $conn->query("SELECT t.id, t.teacher_code, u.full_name
                         FROM teachers t
                         JOIN users u ON t.user_id = u.id
                         ORDER BY u.full_name");

// حساب إحصائيات عامة
$stats_sql = "SELECT
              COUNT(*) as total_grades,
              AVG(grade) as average_grade,
              COUNT(DISTINCT student_id) as total_students,
              COUNT(DISTINCT subject_id) as total_subjects,
              COUNT(DISTINCT class_id) as total_classes
              FROM grades";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدرجات - نظام مدرسة النخبة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
        }
       
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
       
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
       
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
       
        .card:hover {
            transform: translateY(-5px);
        }
       
        .table th {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
        }
       
        .table td {
            vertical-align: middle;
            text-align: center;
        }
       
        .badge-success {
            background-color: var(--success-color);
        }
       
        .badge-warning {
            background-color: var(--warning-color);
        }
       
        .badge-danger {
            background-color: var(--danger-color);
        }
       
        .btn-primary {
            background-color: var(--secondary-color);
            border: none;
        }
       
        .btn-primary:hover {
            background-color: #2980b9;
        }
       
        .btn-success {
            background-color: var(--success-color);
            border: none;
        }
       
        .search-box {
            position: relative;
        }
       
        .search-box input {
            padding-right: 40px;
        }
       
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
       
        .stat-card {
            border-left: 4px solid var(--secondary-color);
            height: 100%;
        }
       
        .modal-header {
            background-color: var(--primary-color);
            color: white;
        }
       
        .grade-input {
            width: 100px;
            text-align: center;
        }
       
        .action-buttons .btn {
            margin: 0 2px;
            padding: 5px 10px;
        }
       
        .percentage-badge {
            font-size: 0.85em;
            padding: 3px 8px;
            border-radius: 20px;
        }
       
        .student-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- العنوان الرئيسي -->
        <div class="header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-bar"></i> إدارة الدرجات - نظام مدرسة النخبة</h1>
                    <p class="mb-0">مرحباً <?php echo $_SESSION['full_name'] ?? 'المدير'; ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="users.php" class="btn btn-light me-2">
                        <i class="fas fa-home"></i> الرئيسية
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>

        <!-- رسائل التنبيه -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
       
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- بطاقات الإحصائيات -->
        <div class="row mb-4">
            <div class="col-md-2 col-sm-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-list-alt text-primary"></i> إجمالي الدرجات
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_grades']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-line text-success"></i> المعدل العام
                        </h5>
                        <h2 class="card-text"><?php echo number_format($stats['average_grade'] ?? 0, 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-users text-info"></i> عدد الطلاب
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_students']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-book text-warning"></i> عدد المواد
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_subjects']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-school text-danger"></i> عدد الصفوف
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_classes']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-calculator text-secondary"></i> أدوات
                        </h5>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkAddModal">
                                <i class="fas fa-file-import"></i> دفعة
                            </button>
                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#calculateModal">
                                <i class="fas fa-calculator"></i> حساب
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- شريط البحث والإجراءات -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <form method="GET" action="" class="d-flex">
                            <div class="search-box flex-grow-1 me-2">
                                <input type="text" class="form-control" name="search"
                                       placeholder="ابحث عن طريق اسم الطالب، الكود، المادة، المعلم أو الصف..."
                                       value="<?php echo $search_query; ?>">
                                <i class="fas fa-search"></i>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> بحث
                            </button>
                            <?php if (!empty($search_query)): ?>
                                <a href="grades_management.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times"></i> إلغاء البحث
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                            <i class="fas fa-plus"></i> إضافة درجة جديدة
                        </button>
                        <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#bulkAddModal">
                            <i class="fas fa-file-import"></i> إضافة دفعة
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول الدرجات -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-table"></i> قائمة الدرجات
                    <span class="badge bg-primary"><?php echo $result->num_rows; ?> سجل</span>
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الطالب</th>
                                <th>المادة</th>
                                <th>الصف</th>
                                <th>نوع الاختبار</th>
                                <th>الدرجة</th>
                                <th>المعلم</th>
                                <th>التاريخ</th>
                                <th>ملاحظات</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php $counter = 1; ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                    // حساب النسبة المئوية وتحديد اللون
                                    $percentage = ($row['grade'] / $row['max_grade']) * 100;
                                    if ($percentage >= 90) {
                                        $status_class = 'success';
                                        $status_text = 'ممتاز';
                                    } elseif ($percentage >= 80) {
                                        $status_class = 'info';
                                        $status_text = 'جيد جداً';
                                    } elseif ($percentage >= 70) {
                                        $status_class = 'primary';
                                        $status_text = 'جيد';
                                    } elseif ($percentage >= 60) {
                                        $status_class = 'warning';
                                        $status_text = 'مقبول';
                                    } else {
                                        $status_class = 'danger';
                                        $status_text = 'راسب';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <strong><?php echo $row['student_name']; ?></strong><br>
                                            <small class="text-muted"><?php echo $row['student_code']; ?></small>
                                        </td>
                                        <td><?php echo $row['subject_name']; ?></td>
                                        <td>
                                            <?php echo $row['class_name']; ?><br>
                                            <small class="text-muted"><?php echo $row['class_grade']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $row['exam_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="badge bg-<?php echo $status_class; ?> mb-1">
                                                    <?php echo $row['grade']; ?> / <?php echo $row['max_grade']; ?>
                                                </span>
                                                <span class="percentage-badge bg-<?php echo $status_class; ?>">
                                                    <?php echo number_format($percentage, 1); ?>% (<?php echo $status_text; ?>)
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo $row['teacher_name']; ?><br>
                                            <small class="text-muted"><?php echo $row['teacher_code']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('Y-m-d', strtotime($row['recorded_at'])); ?><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($row['recorded_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['notes'])): ?>
                                                <button class="btn btn-sm btn-outline-info"
                                                        data-bs-toggle="popover"
                                                        data-bs-title="ملاحظات"
                                                        data-bs-content="<?php echo htmlspecialchars($row['notes']); ?>"
                                                        data-bs-placement="left">
                                                    <i class="fas fa-eye"></i> عرض
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">لا توجد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-warning"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editGradeModal"
                                                        onclick="editGrade(
                                                            '<?php echo $row['id']; ?>',
                                                            '<?php echo $row['grade']; ?>',
                                                            '<?php echo $row['max_grade']; ?>',
                                                            `<?php echo addslashes($row['notes']); ?>`
                                                        )">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteGradeModal"
                                                        onclick="deleteGrade('<?php echo $row['id']; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                                            <h5>لا توجد درجات مسجلة</h5>
                                            <p class="mb-0">استخدم زر "إضافة درجة جديدة" لبدء إضافة درجات الطلاب</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال إضافة درجة جديدة -->
    <div class="modal fade" id="addGradeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle"></i> إضافة درجة جديدة
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الطالب *</label>
                                <select class="form-select" name="student_id" required>
                                    <option value="">اختر الطالب...</option>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo $student['full_name']; ?> (<?php echo $student['student_code']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المادة *</label>
                                <select class="form-select" name="subject_id" required>
                                    <option value="">اختر المادة...</option>
                                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo $subject['subject_name']; ?> (<?php echo $subject['subject_code'] ?? ''; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الصف *</label>
                                <select class="form-select" name="class_id" required>
                                    <option value="">اختر الصف...</option>
                                    <?php while ($class = $classes->fetch_assoc()): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['grade']; ?> - <?php echo $class['class_name']; ?> (<?php echo $class['section']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المعلم *</label>
                                <select class="form-select" name="teacher_id" required>
                                    <option value="">اختر المعلم...</option>
                                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo $teacher['full_name']; ?> (<?php echo $teacher['teacher_code']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع الاختبار *</label>
                                <select class="form-select" name="exam_type" required>
                                    <option value="">اختر نوع الاختبار...</option>
                                    <option value="اختبار قصير">اختبار قصير</option>
                                    <option value="منتصف الفصل">منتصف الفصل</option>
                                    <option value="نهاية الفصل">نهاية الفصل</option>
                                    <option value="مشروع">مشروع</option>
                                    <option value="نشاط">نشاط</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">الدرجة *</label>
                                <input type="number" class="form-control" name="grade"
                                       step="0.01" min="0" max="100" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">الدرجة القصوى *</label>
                                <input type="number" class="form-control" name="max_grade"
                                       step="0.01" min="0" value="100" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">ملاحظات</label>
                                <textarea class="form-control" name="notes" rows="3"
                                          placeholder="أدخل أي ملاحظات إضافية هنا..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <button type="submit" name="add_grade" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ الدرجة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال إضافة دفعة -->
    <div class="modal fade" id="bulkAddModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-file-import"></i> إضافة درجات دفعة واحدة
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> أدخل درجات جميع طلاب الصف في المادة المحددة
                        </div>
                       
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">الصف *</label>
                                <select class="form-select" name="class_id" id="bulk_class" required>
                                    <option value="">اختر الصف...</option>
                                    <?php
                                    // إعادة تعيين مؤشر النتائج
                                    $classes->data_seek(0);
                                    while ($class = $classes->fetch_assoc()): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['grade']; ?> - <?php echo $class['class_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">المادة *</label>
                                <select class="form-select" name="subject_id" required>
                                    <option value="">اختر المادة...</option>
                                    <?php
                                    $subjects->data_seek(0);
                                    while ($subject = $subjects->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo $subject['subject_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">المعلم *</label>
                                <select class="form-select" name="teacher_id" required>
                                    <option value="">اختر المعلم...</option>
                                    <?php
                                    $teachers->data_seek(0);
                                    while ($teacher = $teachers->fetch_assoc()): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo $teacher['full_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">نوع الاختبار *</label>
                                <select class="form-select" name="exam_type" required>
                                    <option value="">اختر نوع الاختبار...</option>
                                    <option value="اختبار قصير">اختبار قصير</option>
                                    <option value="منتصف الفصل">منتصف الفصل</option>
                                    <option value="نهاية الفصل">نهاية الفصل</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الدرجة القصوى *</label>
                                <input type="number" class="form-control" name="max_grade"
                                       value="100" required>
                            </div>
                        </div>
                       
                        <div id="studentsList">
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle"></i> اختر الصف أولاً لعرض قائمة الطلاب
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <button type="submit" name="bulk_add" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ جميع الدرجات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال تعديل الدرجة -->
    <div class="modal fade" id="editGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit"></i> تعديل الدرجة
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="grade_id" id="edit_grade_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الدرجة *</label>
                                <input type="number" class="form-control" name="grade"
                                       id="edit_grade" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الدرجة القصوى *</label>
                                <input type="number" class="form-control" name="max_grade"
                                       id="edit_max_grade" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">ملاحظات</label>
                                <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <button type="submit" name="update_grade" class="btn btn-primary">
                            <i class="fas fa-save"></i> تحديث
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال حذف الدرجة -->
    <div class="modal fade" id="deleteGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-trash text-danger"></i> تأكيد الحذف
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5>تحذير!</h5>
                            <p class="mb-0">هل أنت متأكد من حذف هذه الدرجة؟ هذا الإجراء لا يمكن التراجع عنه.</p>
                        </div>
                        <input type="hidden" name="grade_id" id="delete_grade_id">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <button type="submit" name="delete_grade" class="btn btn-danger">
                            <i class="fas fa-trash"></i> نعم، احذف
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- مودال حساب المجموع والمعدل -->
    <div class="modal fade" id="calculateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-calculator"></i> حساب المجموع والمعدل
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> احسب إجمالي الدرجات والمعدل للطالب في صف معين
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الطالب *</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">اختر الطالب...</option>
                                <?php
                                $students->data_seek(0);
                                while ($student = $students->fetch_assoc()): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo $student['full_name']; ?> (<?php echo $student['student_code']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الصف *</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">اختر الصف...</option>
                                <?php
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo $class['grade']; ?> - <?php echo $class['class_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <button type="submit" name="calculate_grades" class="btn btn-primary">
                            <i class="fas fa-calculator"></i> حساب النتائج
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // دالة تحميل طلاب الصف لإضافة الدفعة
        document.getElementById('bulk_class').addEventListener('change', function() {
            var classId = this.value;
           
            if (!classId) {
                document.getElementById('studentsList').innerHTML =
                    '<div class="alert alert-warning text-center">' +
                    '<i class="fas fa-exclamation-triangle"></i> اختر الصف أولاً لعرض قائمة الطلاب</div>';
                return;
            }
           
            // إظهار مؤشر التحميل
            document.getElementById('studentsList').innerHTML =
                '<div class="text-center py-4">' +
                '<div class="spinner-border text-primary" role="status"></div>' +
                '<p class="mt-2">جاري تحميل قائمة الطلاب...</p></div>';
           
            fetch('ajax_get_students.php?class_id=' + classId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('studentsList').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('studentsList').innerHTML =
                        '<div class="alert alert-danger text-center">' +
                        '<i class="fas fa-exclamation-circle"></i> حدث خطأ في تحميل البيانات</div>';
                });
        });
       
        // دالة تعديل الدرجة
        function editGrade(id, grade, maxGrade, notes) {
            document.getElementById('edit_grade_id').value = id;
            document.getElementById('edit_grade').value = grade;
            document.getElementById('edit_max_grade').value = maxGrade;
            document.getElementById('edit_notes').value = notes;
        }
       
        // دالة حذف الدرجة
        function deleteGrade(id) {
            document.getElementById('delete_grade_id').value = id;
        }
       
        // تفعيل popover للتعليقات
        document.addEventListener('DOMContentLoaded', function() {
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });
    </script>
</body>
</html>