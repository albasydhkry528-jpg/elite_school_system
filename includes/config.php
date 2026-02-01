<?php
// ================================================
// ملف التهيئة والإعدادات الأساسية
// ================================================

// 1. إعدادات المسار
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));
define('INCLUDES_PATH', __DIR__); // مجلد includes
define('ASSETS_PATH', ROOT_PATH . '/assets');

// 2. إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // غير هذه القيم حسب إعداداتك
define('DB_PASS', ''); // كلمة مرور قاعدة البيانات
define('DB_NAME', 'elite_school_system');

// 3. إعدادات التطبيق
define('APP_NAME', 'مدرسة النخبة الدولية');
define('APP_VERSION', '3.0');
define('APP_URL', 'http://localhost/elite_school_system'); // رابط التطبيق
define('APP_DIR', '/elite_school_system'); // مجلد التطبيق

// 4. إعدادات المجلدات
define('UPLOADS_DIR', ASSETS_PATH . '/uploads');
define('PROFILES_DIR', ASSETS_PATH . '/profiles');
define('ACTIVITIES_DIR', ASSETS_PATH . '/activities');
define('TEACHERS_DIR', ASSETS_PATH . '/teachers');

// 5. إعدادات الدفع
define('PAYMENT_TEST_MODE', true); // وضع الاختبار
define('PAYMENT_SUCCESS_URL', APP_URL . '/registration_confirmation.php');
define('PAYMENT_FAILURE_URL', APP_URL . '/payment.php?status=failed');

// 6. معلومات البنك (للمدفوعات البنكية)
$bank_info = [
    'name' => 'البنك الأهلي السعودي',
    'account_name' => 'مدرسة النخبة الدولية',
    'account_number' => 'SA1234567890123456789012',
    'iban' => 'SA1234567890123456789012',
    'swift_code' => 'NCBJSARI'
];

// 7. المستويات الدراسية والرسوم (يمكن استبدالها بالديناميكية من قاعدة البيانات)
$academic_levels = [
    'الأول ابتدائي' => [
        'price' => 2000,
        'age_range' => '6-7 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'التربية الفنية', 'التربية البدنية']
    ],
    'الثاني ابتدائي' => [
        'price' => 2000,
        'age_range' => '7-8 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'التربية الفنية']
    ],
    'الثالث ابتدائي' => [
        'price' => 2000,
        'age_range' => '8-9 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات']
    ],
    'الرابع ابتدائي' => [
        'price' => 2500,
        'age_range' => '9-10 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية']
    ],
    'الخامس ابتدائي' => [
        'price' => 2500,
        'age_range' => '10-11 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي']
    ],
    'السادس ابتدائي' => [
        'price' => 2500,
        'age_range' => '11-12 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'المهارات الحياتية']
    ],
    'الأول متوسط' => [
        'price' => 3000,
        'age_range' => '12-13 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'التربية الفنية']
    ],
    'الثاني متوسط' => [
        'price' => 3000,
        'age_range' => '13-14 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'التربية البدنية']
    ],
    'الثالث متوسط' => [
        'price' => 3000,
        'age_range' => '14-15 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'المهارات الحياتية']
    ],
    'الأول ثانوي' => [
        'price' => 3500,
        'age_range' => '15-16 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'الفيزياء', 'الكيمياء', 'الأحياء', 'اللغة الإنجليزية', 'الحاسب الآلي']
    ],
    'الثاني ثانوي' => [
        'price' => 3500,
        'age_range' => '16-17 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'الفيزياء', 'الكيمياء', 'الأحياء', 'اللغة الإنجليزية', 'الحاسب الآلي']
    ],
    'الثالث ثانوي' => [
        'price' => 3500,
        'age_range' => '17-18 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'الفيزياء', 'الكيمياء', 'الأحياء', 'اللغة الإنجليزية', 'الحاسب الآلي']
    ]
];

// 8. بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 9. تعيين التوقيت المحلي
date_default_timezone_set('Asia/Riyadh');

// 10. تفعيل عرض الأخطاء للتطوير (تعطيل في الإنتاج)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================================================
// الاتصال بقاعدة البيانات
// ================================================

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
   
    // التحقق من الاتصال
    if ($conn->connect_error) {
        throw new Exception("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
    }
   
    // تعيين الترميز ليدعم العربية
    $conn->set_charset("utf8mb4");
   
} catch (Exception $e) {
    die("
        <div style='text-align: center; padding: 50px; font-family: Arial; direction: rtl;'>
            <h1 style='color: #dc3545;'>خطأ في الاتصال بقاعدة البيانات</h1>
            <p style='color: #666;'>" . $e->getMessage() . "</p>
            <p style='margin-top: 20px;'>
                <strong>تفاصيل الإتصال:</strong><br>
                الخادم: " . DB_HOST . "<br>
                قاعدة البيانات: " . DB_NAME . "<br>
                المستخدم: " . DB_USER . "
            </p>
        </div>
    ");
}

// ================================================
// الدوال الأساسية في config.php
// ================================================

/**
* تنظيف بيانات الإدخال
*/
function clean_input($data) {
    global $conn;
    if(empty($data)) return '';
   
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = $conn->real_escape_string($data);
    return $data;
}

/**
* إعادة التوجيه
*/
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
* تحميل ملف CSS أو JS
*/
function asset($path) {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

/**
* تحميل صفحة
*/
function view($view_name, $data = []) {
    extract($data);
    $view_path = INCLUDES_PATH . '/views/' . $view_name . '.php';
   
    if (file_exists($view_path)) {
        require_once $view_path;
    } else {
        die("الصفحة غير موجودة: " . $view_name);
    }
}
?>
