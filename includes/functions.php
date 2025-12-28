<?php
// ================================================
// ملف الدوال المساعدة
// ================================================

// استيراد اتصال قاعدة البيانات
require_once 'config.php';
require_once __DIR__ . '/config.php';

/**
* التحقق من تسجيل الدخول
*/
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
* إعادة التوجيه بناءً على دور المستخدم
*/
function redirect_based_on_role() {
    if (!is_logged_in()) {
        header("Location: " . APP_URL . "/login.php");
        exit();
    }
  
    $role = $_SESSION['user_type'] ?? '';
  
    switch ($role) {
        case 'admin':
            header("Location: " . APP_URL . "/admin/dashboard.php");
            break;
        case 'teacher':
            header("Location: " . APP_URL . "/teacher/dashboard.php");
            break;
        case 'student':
            header("Location: " . APP_URL . "/student/dashboard.php");
            break;
        case 'parent':
            header("Location: " . APP_URL . "/parent/dashboard.php");
            break;
        default:
            header("Location: " . APP_URL . "/index.php");
            break;
    }
    exit();
}
function isLoggedIn(){
    return isset($_SESSION['user_id'])&&isset($_SESSION['user_type']);
}
/**
* التحقق من صلاحيات المستخدم
*/
function has_permission($required_role) {
    if (!is_logged_in()) {
        return false;
    }
  
    $user_role = $_SESSION['user_type'] ?? '';
    return $user_role === $required_role || $user_role === 'admin';
}

/**
* التحقق من صلاحيات تسجيل الطلاب
*/
function can_register_student() {
    if (!is_logged_in()) {
        return false;
    }
   
    $user_type = $_SESSION['user_type'] ?? '';
    return in_array($user_type, ['admin', 'moderator']);
}

/**
* تسجيل نشاط المستخدم
*/
function log_activity($action, $description) {
    global $conn;
  
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
      
        // تأكد من وجود جدول user_activities
        $sql = "SHOW TABLES LIKE 'user_activities'";
        $result = $conn->query($sql);
      
        if ($result->num_rows > 0) {
            $sql = "INSERT INTO user_activities (user_id, action, description, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?)";
          
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
          
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("issss", $user_id, $action, $description, $ip_address, $user_agent);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

/**
* الحصول على إعدادات المدرسة
*/
function getSchoolSettings() {
    global $conn;
  
    // تحقق من وجود الجدول
    $sql = "SHOW TABLES LIKE 'school_info'";
    $result = $conn->query($sql);
  
    if ($result->num_rows > 0) {
        $sql = "SELECT * FROM school_info LIMIT 1";
        $result = $conn->query($sql);
      
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
  
    // إعدادات افتراضية
    return [
        'school_name' => 'مدرسة النخبة الدولية',
        'motto' => 'نعمل على بناء جيل واعد قادر على مواجهة تحديات المستقبل',
        'vision' => 'الريادة في تقديم تعليم نوعي يواكب متطلبات العصر',
        'address' => 'صنعاء - اليمن',
        'phone' => '+967 123 456 789',
        'email' => 'info@elite-school.edu',
        'website' => 'www.elite-school.edu',
        'working_hours' => 'الأحد - الخميس: 7:00 ص - 2:00 م'
    ];
}

/**
* إعادة توجيه مع رسالة
*/
function redirect_with_message($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = [
            'text' => $message,
            'type' => $type
        ];
    }
    header("Location: $url");
    exit();
}

/**
* عرض رسائل الفلاش
*/
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $message['type'];
        $text = $message['text'];
      
        $class = '';
        $icon = '';
      
        switch ($type) {
            case 'success':
                $class = 'success';
                $icon = 'fa-check-circle';
                break;
            case 'error':
                $class = 'danger';
                $icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                $class = 'warning';
                $icon = 'fa-exclamation-triangle';
                break;
            case 'info':
                $class = 'info';
                $icon = 'fa-info-circle';
                break;
        }
      
        echo '<div class="alert alert-' . $class . ' alert-dismissible fade show" role="alert">';
        echo '<i class="fas ' . $icon . ' me-2"></i>';
        echo $text;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
      
        unset($_SESSION['flash_message']);
    }
}

/**
* تسجيل الخروج
*/
function logout() {
    if (isset($_SESSION['user_id'])) {
        log_activity('تسجيل الخروج', 'قام المستخدم بتسجيل الخروج');
    }
  
    $_SESSION = array();
  
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
  
    session_destroy();
   
    // إعادة التوجيه لصفحة تسجيل الدخول
    header("Location: " . APP_URL . "/login.php");
    exit();
}

/**
* دالة مساعدة لتنفيذ الاستعلامات
*/
function executeQuery($sql, $params = []) {
    global $conn;
  
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
  
    if (!empty($params)) {
        $types = '';
        $values = [];
      
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $values[] = $param;
        }
      
        $stmt->bind_param($types, ...$values);
    }
  
    $stmt->execute();
  
    if (strpos(strtoupper($sql), 'SELECT') === 0) {
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    } else {
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows;
    }
}

/**
* التحقق من البريد الإلكتروني
*/
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
* الحصول على معلومات المستخدم الحالي
*/
function get_current_user_info() {
    if (!is_logged_in()) {
        return null;
    }
  
    global $conn;
    $user_id = $_SESSION['user_id'];
  
    $sql = "SELECT u.*,
                   t.specialization as teacher_specialization,
                   s.student_code,
                   p.parent_code
            FROM users u
            LEFT JOIN teachers t ON u.id = t.user_id
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN parents p ON u.id = p.user_id
            WHERE u.id = ?";
   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
  
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
  
    return null;
}

/**
* دالة للتحقق من وجود الجدول
*/
function table_exists($table_name) {
    global $conn;
    $sql = "SHOW TABLES LIKE '$table_name'";
    $result = $conn->query($sql);
    return $result->num_rows > 0;
}

/**
* تنظيف النص لعرضه بأمان
*/
function safe_output($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
* التحقق من رقم الهاتف
*/
function validate_phone($phone) {
    return preg_match('/^[\+]?[0-9]{10,15}$/', $phone);
}

/**
* تنسيق التاريخ العربي
*/
function format_arabic_date($date) {
    $months = [
        'January' => 'يناير',
        'February' => 'فبراير',
        'March' => 'مارس',
        'April' => 'أبريل',
        'May' => 'مايو',
        'June' => 'يونيو',
        'July' => 'يوليو',
        'August' => 'أغسطس',
        'September' => 'سبتمبر',
        'October' => 'أكتوبر',
        'November' => 'نوفمبر',
        'December' => 'ديسمبر'
    ];
  
    $english_date = date('F d, Y', strtotime($date));
    foreach ($months as $en => $ar) {
        $english_date = str_replace($en, $ar, $english_date);
    }
  
    return $english_date;
}

/**
* حساب العمر من تاريخ الميلاد
*/
function calculate_age($birth_date) {
    $birthday = new DateTime($birth_date);
    $today = new DateTime('today');
    $age = $birthday->diff($today)->y;
    return $age;
}

/**
* تقصير النص مع إضافة نقاط
*/
function truncate_text($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
* إنشاء كود طالب تلقائي
*/
function generate_student_code() {
    global $conn;
    $prefix = 'STU';
    $year = date('y');
    $month = date('m');
   
    // حساب الرقم التسلسلي
    $sql = "SELECT COUNT(*) as count FROM students WHERE YEAR(created_at) = YEAR(CURDATE())";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $sequence = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
   
    return $prefix . $year . $month . $sequence;
}

/**
* إنشاء كود معلم تلقائي
*/
function generate_teacher_code() {
    global $conn;
    $prefix = 'TCH';
    $year = date('y');
    $month = date('m');
   
    $sql = "SELECT COUNT(*) as count FROM teachers WHERE YEAR(created_at) = YEAR(CURDATE())";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $sequence = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
   
    return $prefix . $year . $month . $sequence;
}

/**
* إنشاء كود ولي أمر تلقائي
*/
function generate_parent_code() {
    global $conn;
    $prefix = 'PAR';
    $year = date('y');
    $month = date('m');
   
    $sql = "SELECT COUNT(*) as count FROM parents WHERE YEAR(created_at) = YEAR(CURDATE())";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $sequence = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
   
    return $prefix . $year . $month . $sequence;
}

/**
* التحقق من توفر اسم المستخدم
*/
function is_username_available($username) {
    global $conn;
   
    $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
   
    return $row['count'] == 0;
}

/**
* التحقق من توفر البريد الإلكتروني
*/
function is_email_available($email) {
    global $conn;
   
    $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
   
    return $row['count'] == 0;
}

/**
* الحصول على المستويات الدراسية
*/
function get_academic_levels() {
    global $conn;
   
    // إذا كان هناك جدول للمستويات في قاعدة البيانات
    if (table_exists('academic_levels')) {
        $sql = "SELECT * FROM academic_levels ORDER BY sort_order";
        $result = $conn->query($sql);
       
        $levels = [];
        while ($row = $result->fetch_assoc()) {
            $levels[$row['level_name']] = [
                'price' => $row['price'],
                'age_range' => $row['age_range'],
                'description' => $row['description']
            ];
        }
       
        return $levels;
    }
   
    // استخدام البيانات الثابتة من config.php
    global $academic_levels;
    return $academic_levels;
}

/**
* الحصول على المواد الدراسية لمستوى معين
*/
function get_subjects_by_level($level) {
    global $conn;
   
    if (table_exists('subjects')) {
        $sql = "SELECT s.* FROM subjects s
                JOIN level_subjects ls ON s.id = ls.subject_id
                JOIN academic_levels al ON ls.level_id = al.id
                WHERE al.level_name = ?";
       
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $level);
        $stmt->execute();
        $result = $stmt->get_result();
       
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row['subject_name'];
        }
       
        return $subjects;
    }
   
    // استخدام البيانات الثابتة
    global $academic_levels;
    return $academic_levels[$level]['subjects'] ?? [];
}

/**
* تسجيل عملية التسجيل في قاعدة البيانات
*/
function log_registration_attempt($student_name, $parent_name, $level, $amount, $status) {
    global $conn;
   
    // إنشاء الجدول إذا لم يكن موجوداً
    if (!table_exists('registration_logs')) {
        $sql = "CREATE TABLE registration_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_name VARCHAR(100) NOT NULL,
            parent_name VARCHAR(100) NOT NULL,
            level VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('مكتمل', 'معلق', 'ملغي') DEFAULT 'معلق',
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }
   
    $sql = "INSERT INTO registration_logs (student_name, parent_name, level, amount, status, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
   
    $stmt = $conn->prepare($sql);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $stmt->bind_param("sssdss", $student_name, $parent_name, $level, $amount, $status, $ip_address);
   
    return $stmt->execute();
}

/**
* إرسال بريد إلكتروني تأكيدي
*/
function send_registration_confirmation($to_email, $student_name, $registration_number, $level, $amount) {
    $subject = "تأكيد تسجيل ابنك في مدرسة النخبة الدولية";
   
    $message = "
    <html dir='rtl'>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: linear-gradient(to right, #4c1d95, #7e22ce); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
            .info-box { background: #f0f9ff; border: 2px solid #10b981; border-radius: 10px; padding: 20px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>مدرسة النخبة الدولية</h1>
            <p>تأكيد التسجيل الناجح</p>
        </div>
       
        <div class='content'>
            <h2>مرحباً،</h2>
            <p>تهانينا! تم تسجيل ابنك <strong>{$student_name}</strong> في مدرسة النخبة الدولية بنجاح.</p>
           
            <div class='info-box'>
                <h3>تفاصيل التسجيل:</h3>
                <p><strong>رقم التسجيل:</strong> {$registration_number}</p>
                <p><strong>المستوى الدراسي:</strong> {$level}</p>
                <p><strong>رسوم التسجيل:</strong> {$amount} ريال</p>
                <p><strong>تاريخ التسجيل:</strong> " . date('Y/m/d') . "</p>
            </div>
           
            <p>سيتصل بكم منسق القبول خلال 48 ساعة عمل لإكمال الإجراءات المتبقية.</p>
           
            <p>للحصول على المساعدة، يرجى الاتصال بقسم القبول على الرقم: ٠١٢٣٤٥٦٧٨٩</p>
        </div>
       
        <div class='footer'>
            <p>© " . date('Y') . " مدرسة النخبة الدولية. جميع الحقوق محفوظة.</p>
            <p>هذا البريد الإلكتروني تم إرساله تلقائياً، يرجى عدم الرد عليه.</p>
        </div>
    </body>
    </html>";
   
    // رأس البريد الإلكتروني
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@elite-school.edu" . "\r\n";
   
    return mail($to_email, $subject, $message, $headers);
}

/**
* الحصول على إحصائيات النظام
*/
function get_system_stats() {
    global $conn;
   
    $stats = [];
   
    try {
        // إجمالي الطلاب
        $sql = "SELECT COUNT(*) as total FROM students";
        $result = $conn->query($sql);
        $stats['total_students'] = $result->fetch_assoc()['total'];
       
        // إجمالي المعلمين
        $sql = "SELECT COUNT(*) as total FROM teachers";
        $result = $conn->query($sql);
        $stats['total_teachers'] = $result->fetch_assoc()['total'];
       
        // إجمالي أولياء الأمور
        $sql = "SELECT COUNT(*) as total FROM parents";
        $result = $conn->query($sql);
        $stats['total_parents'] = $result->fetch_assoc()['total'];
       
        // الأنشطة الحالية
        $sql = "SELECT COUNT(*) as total FROM activities WHERE MONTH(activity_date) = MONTH(CURDATE())";
        $result = $conn->query($sql);
        $stats['current_activities'] = $result->fetch_assoc()['total'];
       
        // المسابقات النشطة
        $sql = "SELECT COUNT(*) as total FROM competitions WHERE status != 'منتهية'";
        $result = $conn->query($sql);
        $stats['active_competitions'] = $result->fetch_assoc()['total'];
       
    } catch (Exception $e) {
        // القيم الافتراضية في حالة الخطأ
        $stats = [
            'total_students' => 0,
            'total_teachers' => 0,
            'total_parents' => 0,
            'current_activities' => 0,
            'active_competitions' => 0
        ];
    }
   
    return $stats;
}

/**
* تحميل الصورة الافتراضية إذا لم توجد
*/
function get_profile_image($image_name, $type = 'user') {
    $image_path = '';
   
    if ($type == 'teacher') {
        $image_path = ASSETS_PATH . '/teachers/' . $image_name;
    } elseif ($type == 'student') {
        $image_path = ASSETS_PATH . '/profiles/' . $image_name;
    } else {
        $image_path = ASSETS_PATH . '/profiles/' . $image_name;
    }
   
    // التحقق من وجود الصورة
    if (file_exists($image_path) && !empty($image_name)) {
        return APP_URL . str_replace(ROOT_PATH, '', $image_path);
    }
   
    // الصورة الافتراضية
    if ($type == 'teacher') {
        return 'https://images.unsplash.com/photo-1568602471122-7832951cc4c5?w=800&q=80';
    } else {
        return APP_URL . '/assets/images/profiles/default.png';
    }
}

/**
* تسجيل خطأ في السجلات
*/
function logError($error_message) {
    global $conn;
   
    // إنشاء جدول سجلات الأخطاء إذا لم يكن موجوداً
    if (!table_exists('error_logs')) {
        $sql = "CREATE TABLE error_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            error_message TEXT NOT NULL,
            error_file VARCHAR(255),
            error_line INT,
            user_id INT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }
   
    $sql = "INSERT INTO error_logs (error_message, error_file, error_line, user_id, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
   
    $stmt = $conn->prepare($sql);
    $backtrace = debug_backtrace();
    $file = $backtrace[0]['file'] ?? 'unknown';
    $line = $backtrace[0]['line'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
   
    $stmt->bind_param("ssiis", $error_message, $file, $line, $user_id, $ip_address);
    $stmt->execute();
   
    // تسجيل الخطأ في ملف أيضاً (اختياري)
    $log_file = ROOT_PATH . '/error_log.txt';
    $log_entry = date('Y-m-d H:i:s') . " - " . $error_message . " - IP: " . $ip_address . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
* إنشاء كلمة مرور آمنة
*/
function generate_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
* دالة الرد السريع
*/
function json_response($success = true, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
* التحقق من CSRF Token
*/
function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('رمز التحقق غير صالح أو منتهي الصلاحية');
    }
}

/**
* إنشاء CSRF Token
*/
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
* إعدادات النظام
*/
function get_system_config($key = null) {
    global $conn;
   
    if (table_exists('system_settings')) {
        $sql = "SELECT setting_key, setting_value FROM system_settings";
        $result = $conn->query($sql);
       
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
       
        if ($key) {
            return $settings[$key] ?? null;
        }
       
        return $settings;
    }
   
    return [];
}

// ================================================
// إنشاء الجداول الأساسية إذا لم تكن موجودة
// ================================================

function create_basic_tables() {
    global $conn;
   
    // التحقق من وجود الجداول الأساسية
    $required_tables = ['users', 'students', 'teachers', 'parents', 'classes', 'subjects'];
   
    foreach ($required_tables as $table) {
        if (!table_exists($table)) {
            // هنا يمكن إضافة كود إنشاء الجداول
            log_activity('إنشاء جداول', 'جداول النظام غير موجودة، يرجى تشغيل ملف التهيئة');
        }
    }
}
// تشغيل التحقق من الجداول عند تحميل الملف
create_basic_tables();
/**
* تسجيل الإجراءات في النظام (خاص بإدارة الدرجات)
*/
function log_action($user_id, $action, $description) {
    global $conn;
   
    // التحقق من وجود جدول السجل
    $check_table = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
   
    $conn->query($check_table);
   
    // إدخال السجل
    $sql = "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)";
   
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
   
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $action, $description, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}
?>
           