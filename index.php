<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// إذا لم يكن هناك اتصال بقاعدة البيانات، اعرض رسالة
if (!isset($conn) || $conn->connect_error) {
    die("عذراً، هناك مشكلة في الاتصال بالنظام. يرجى المحاولة لاحقاً.");
}
$reviews = null;
$reviews_error = false;

try {
    // تحقق أولاً إذا كانت الجداول موجودة
    $check_tables = $conn->query("SHOW TABLES LIKE 'parent_reviews'");
  
    if ($check_tables && $check_tables->num_rows > 0) {
        // إذا كان الجدول موجود، حاول جلب البيانات
        $reviews = $conn->query("SELECT pr.*, p.user_id, u.full_name as parent_name, s.student_code
                                 FROM parent_reviews pr
                                 JOIN parents p ON pr.parent_id = p.id
                                 JOIN users u ON p.user_id = u.id
                                 JOIN students s ON pr.student_id = s.id
                                 WHERE pr.status = 'مقبول'
                                 ORDER BY pr.created_at DESC LIMIT 5");
    } else {
        $reviews_error = true;
    }
} catch (Exception $e) {
    $reviews_error = true;
    logError("خطأ في جلب آراء أولياء الأمور: " . $e->getMessage());
}

// ==================== جلب الإحصائيات بشكل آمن ====================
function safeQueryCount($conn, $query) {
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return isset($row['total']) ? (int)$row['total'] : 0;
    }
    return 0;
}

// إحصائيات الطلاب
$studentsCount = safeQueryCount($conn, "SELECT COUNT(*) as total FROM students");

// إحصائيات المعلمين
$teachersCount = safeQueryCount($conn, "SELECT COUNT(*) as total FROM teachers");

// إحصائيات الأنشطة (جميع الأنشطة)
$activitiesCount = safeQueryCount($conn, "SELECT COUNT(*) as total FROM activities");

// إحصائيات المسابقات (النشطة فقط)
$competitionsCount = safeQueryCount($conn, "SELECT COUNT(*) as total FROM competitions WHERE status IN ('نشطة', 'معلقة', 'قادمة')");

// ==================== جلب آخر الأنشطة ====================
$recentActivities = $conn->query("SELECT * FROM activities ORDER BY created_at DESC LIMIT 3");

// ==================== جلب المعلمين المتميزين ====================
$featuredTeachers = $conn->query("SELECT * FROM teachers ORDER BY RAND() LIMIT 8");

// ==================== جلب معلومات المدرسة ====================
$schoolInfo = $conn->query("SELECT * FROM school_info LIMIT 1");
if ($schoolInfo && $schoolInfo->num_rows > 0) {
    $schoolInfo = $schoolInfo->fetch_assoc();
} else {
    // معلومات افتراضية في حالة عدم وجود بيانات
    $schoolInfo = [
        'school_name' => 'مدرسة النخبة الدولية',
        'motto' => 'نعمل على بناء جيل واعد',
        'vision' => 'الريادة في تقديم تعليم نوعي',
        'address' => 'صنعاء - اليمن',
        'phone' => '+967 123 456 789',
        'email' => 'info@elite-school.edu',
        'website' => 'www.elite-school.edu'
    ];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $schoolInfo['school_name'] ?? 'مدرسة النخبة الدولية'; ?> - الصفحة الرئيسية</title>
  
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
  
    <style>
        /* ===== إعدادات أساسية ===== */
        :root {
            /* الألوان من صفحتك - تدرج بنفسجي/أخضر */
            --primary-purple: #7b2cbf;     /* بنفسجي رئيسي */
            --primary-purple-dark: #5a189a; /* بنفسجي غامق */
            --primary-purple-light: #9d4edd; /* بنفسجي فاتح */
            --primary-purple-lighter: #c77dff; /* بنفسجي فاتح جداً */
            
            --primary-green: #06d6a0;      /* أخضر فيروزي */
            --primary-green-dark: #04a777;  /* أخضر غامق */
            --primary-green-light: #07f7b5; /* أخضر فاتح */
            
            --accent: #ff9e00;            /* برتقالي ذهبي */
            --accent-light: #ffb74d;      /* برتقالي فاتح */
            
            /* تدرجات الخلفية */
            --gradient-bg: linear-gradient(135deg, #7b2cbf 0%, #06d6a0 100%);
            --gradient-purple: linear-gradient(135deg, #7b2cbf, #9d4edd);
            --gradient-green: linear-gradient(135deg, #06d6a0, #07f7b5);
            --gradient-accent: linear-gradient(135deg, #ff9e00, #ffb74d);
            --gradient-light: linear-gradient(135deg, #f3e5f5, #e8f5e9);
            
            /* الألوان المحايدة */
            --light: #ffffff;
            --dark: #4a148c;              /* بنفسجي داكن للنصوص */
            --gray: #757575;              /* رمادي للنصوص الثانوية */
            --gray-light: #bdbdbd;        /* رمادي فاتح */
            
            /* الظلال */
            --shadow-sm: 0 2px 8px rgba(123, 44, 191, 0.1);
            --shadow-md: 0 4px 16px rgba(123, 44, 191, 0.15);
            --shadow-lg: 0 8px 32px rgba(123, 44, 191, 0.2);
            --shadow-xl: 0 12px 48px rgba(123, 44, 191, 0.25);
            --shadow-card: 0 6px 20px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 12px 36px rgba(123, 44, 191, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background: var(--light);
            color: var(--dark);
            direction: rtl;
            overflow-x: hidden;
            line-height: 1.6;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }

        /* ===== تخصيص شريط التمرير ===== */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gradient-purple);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-purple-dark);
        }

        /* ===== الحاوية الرئيسية ===== */
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ===== رأس الصفحة ===== */
        header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
            padding: 15px 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        header.scrolled {
            padding: 10px 0;
            box-shadow: var(--shadow-lg);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        /* ===== الشعار ===== */
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: translateY(-3px);
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-bg);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
        }

        .logo-icon i {
            font-size: 1.8rem;
            color: white;
        }

        .logo-text h1 {
            color: var(--dark);
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 5px;
            background: var(--gradient-bg);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-text p {
            color: var(--primary-purple);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* ===== القائمة ===== */
        .nav-links {
            display: flex;
            gap: 10px;
            list-style: none;
            align-items: center;
            flex-wrap: wrap;
            margin: 0;
            padding: 0;
        }

        .nav-links li {
            position: relative;
        }

        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            padding: 10px 18px;
            border-radius: 25px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a:hover {
            background: rgba(123, 44, 191, 0.1);
            color: var(--primary-purple);
            transform: translateY(-2px);
        }

        .nav-links a.active {
            background: var(--gradient-bg);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-links a i {
            font-size: 1.1rem;
        }

        /* الأزرار في القائمة */
        .nav-links .btn-secondary {
            background: rgba(6, 214, 160, 0.1);
            border: 2px solid var(--primary-green);
            color: var(--primary-green-dark);
            padding: 8px 18px !important;
        }

        .nav-links .btn-primary {
            background: var(--gradient-bg);
            color: white;
            padding: 8px 22px !important;
            box-shadow: var(--shadow-md);
        }

        /* ===== زر القائمة للموبايل ===== */
        .mobile-menu-btn {
            display: none;
            width: 45px;
            height: 45px;
            background: var(--gradient-bg);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mobile-menu-btn:hover {
            transform: rotate(90deg);
        }

        /* ===== قسم البطل مع الصورة المتحركة ===== */
        .hero {
            min-height: 130vh;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            padding-top: 80px;
            background: #000;
            width: 100%;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
             background-image: url('images/th.jpg');
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
            opacity: 0.4;
            animation: backgroundAnimation 30s infinite alternate ease-in-out;
            filter: brightness(0.7) contrast(1.2);
        }

       

        /* محتوى البطل */
        .hero-content {
            position: relative;
            z-index: 3;
            text-align: center;
            color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: contentGlow 4s infinite alternate;
            width: 100%;
        }
        
        /* حركة الطفو للمحتوى */
        .hero-content-inner {
            animation: floatContent 8s infinite alternate ease-in-out;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 25px;
            text-shadow: 
                0 5px 15px rgba(0, 0, 0, 0.5),
                0 0 30px rgba(123, 44, 191, 0.5),
                0 0 60px rgba(6, 214, 160, 0.3);
            line-height: 1.2;
            background: linear-gradient(45deg, #ffffff, #e0e0ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: titleGlow 3s infinite alternate;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 35px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.8;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        /* ===== أزرار CTA ===== */
        .cta-buttons {
            display: flex;
            gap: 25px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 18px 40px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: none;
            cursor: pointer;
            min-width: 180px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(100%);
            transition: transform 0.6s ease;
        }

        .btn:hover::before {
            transform: translateX(0);
        }

        .btn-primary {
            background: var(--gradient-bg);
            color: white;
            box-shadow: var(--shadow-lg);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: var(--shadow-xl);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-purple);
            border: 2px solid var(--primary-purple);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary:hover {
            background: var(--gradient-bg);
            color: white;
            transform: translateY(-8px);
            border-color: transparent;
            box-shadow: var(--shadow-xl);
        }

        .btn i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .btn:hover i {
            transform: translateX(-8px);
        }

        /* ===== حركات CSS للصورة المتحركة ===== */
        @keyframes backgroundAnimation {
            0% {
                transform: scale(1) rotate(0deg);
                filter: brightness(0.7) contrast(1.2) hue-rotate(0deg);
            }
            50% {
                transform: scale(1.05) rotate(0.5deg);
                filter: brightness(0.8) contrast(1.3) hue-rotate(5deg);
            }
            100% {
                transform: scale(1.1) rotate(-0.5deg);
                filter: brightness(0.75) contrast(1.25) hue-rotate(-5deg);
            }
        }

        @keyframes contentGlow {
            0%, 100% {
                box-shadow: 
                    0 20px 60px rgba(0, 0, 0, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.2),
                    0 0 30px rgba(123, 44, 191, 0.2);
            }
            50% {
                box-shadow: 
                    0 20px 60px rgba(0, 0, 0, 0.4),
                    inset 0 1px 0 rgba(255, 255, 255, 0.3),
                    0 0 40px rgba(6, 214, 160, 0.3);
            }
        }

        @keyframes floatContent {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes titleGlow {
            0%, 100% {
                text-shadow: 
                    0 5px 15px rgba(0, 0, 0, 0.5),
                    0 0 30px rgba(123, 44, 191, 0.5),
                    0 0 60px rgba(6, 214, 160, 0.3);
            }
            50% {
                text-shadow: 
                    0 5px 20px rgba(0, 0, 0, 0.6),
                    0 0 40px rgba(123, 44, 191, 0.7),
                    0 0 80px rgba(6, 214, 160, 0.5);
            }
        }

        /* ===== قسم الإحصائيات ===== */
        .stats-section {
            padding: 80px 0;
            background: white;
            position: relative;
            width: 100%;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(123, 44, 191, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(6, 214, 160, 0.05) 0%, transparent 50%);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 60px;
            position: relative;
            z-index: 1;
        }

        .section-title span {
            color: transparent;
            background: var(--gradient-bg);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -20px;
            right: 50%;
            transform: translateX(50%);
            width: 120px;
            height: 4px;
            background: var(--gradient-bg);
            border-radius: 2px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .stat-card {
            background: white;
            padding: 40px 25px;
            border-radius: 20px;
            box-shadow: var(--shadow-card);
            text-align: center;
            transition: all 0.5s ease;
            position: relative;
            border: 1px solid rgba(123, 44, 191, 0.1);
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-hover);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-bg);
            border-radius: 2px 2px 0 0;
        }

        .stat-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 25px;
            background: var(--gradient-bg);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .stat-icon i {
            font-size: 2.2rem;
            color: white;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 15px;
            color: transparent;
            background: var(--gradient-bg);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 1.2rem;
            color: var(--dark);
            font-weight: 600;
        }

        /* ===== قسم الأنشطة ===== */
        .activities-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #f9f7fd 0%, #f0f9f6 100%);
            position: relative;
            width: 100%;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 60px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.8;
        }

        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 35px;
            margin-top: 40px;
            width: 100%;
        }

        .activity-card {
            background: white;
            border-radius: 25px;
            padding: 35px 30px;
            box-shadow: var(--shadow-card);
            transition: all 0.5s ease;
            text-align: center;
            position: relative;
            border: 1px solid rgba(123, 44, 191, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            overflow: hidden;
        }

        .activity-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-hover);
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-bg);
            border-radius: 2px 2px 0 0;
        }

        .activity-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-bg);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            box-shadow: var(--shadow-lg);
        }

        .activity-icon i {
            font-size: 2rem;
            color: white;
        }

        .activity-date {
            background: rgba(123, 44, 191, 0.1);
            color: var(--primary-purple);
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .activity-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.3;
        }

        .activity-description {
            font-size: 1rem;
            color: var(--gray);
            line-height: 1.7;
            margin-bottom: 25px;
            flex-grow: 1;
        }

        .activity-tags {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .tag {
            background: rgba(123, 44, 191, 0.1);
            color: var(--primary-purple);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid rgba(123, 44, 191, 0.2);
            transition: all 0.3s ease;
        }

        .tag:hover {
            background: var(--primary-purple);
            color: white;
            transform: translateY(-3px);
        }

        .activity-objective {
            font-size: 0.95rem;
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 30px;
            text-align: right;
            width: 100%;
        }

        .activity-objective strong {
            color: var(--primary-purple);
            font-weight: 700;
        }

        .activity-btn {
            background: var(--gradient-bg);
            color: white;
            padding: 14px 35px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-lg);
            border: none;
            cursor: pointer;
            width: fit-content;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .activity-btn:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: var(--shadow-xl);
        }

        /* ===== قسم آراء أولياء الأمور ===== */
        .testimonials-section {
            padding: 80px 0;
            background: white;
            position: relative;
            width: 100%;
        }

        .testimonials-container {
            display: flex;
            gap: 30px;
            overflow-x: auto;
            padding: 20px 10px;
            margin-top: 60px;
            scrollbar-width: thin;
            width: 100%;
        }

        .testimonials-container::-webkit-scrollbar {
            height: 8px;
        }

        .testimonials-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .testimonials-container::-webkit-scrollbar-thumb {
            background: var(--gradient-bg);
            border-radius: 4px;
        }

        .testimonial-card {
            background: white;
            border-radius: 25px;
            padding: 35px 30px;
            box-shadow: var(--shadow-card);
            transition: all 0.4s ease;
            position: relative;
            border: 1px solid rgba(123, 44, 191, 0.1);
            min-width: 300px;
            flex-shrink: 0;
        }

        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .testimonial-card .quote-icon {
            position: absolute;
            top: 20px;
            left: 25px;
            font-size: 3.5rem;
            color: rgba(123, 44, 191, 0.1);
            font-family: serif;
            z-index: 0;
        }

        .rating {
            margin-bottom: 25px;
            text-align: right;
        }

        .rating i {
            color: #ffb74d;
            font-size: 1.2rem;
            margin-right: 4px;
        }

        .review-text {
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }

        .review-text p {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--gray);
            font-style: italic;
            text-align: right;
        }

        .reviewer-info {
            padding-top: 20px;
            border-top: 1px solid rgba(123, 44, 191, 0.1);
        }

        .reviewer-info .reviewer-avatar {
            width: 50px;
            height: 50px;
            background: var(--gradient-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            font-weight: bold;
            box-shadow: var(--shadow-md);
        }

        .reviewer-info h4 {
            color: var(--dark);
            font-size: 1.1rem;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .reviewer-info p {
            color: var(--primary-purple);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== قسم الجداول الدراسية ===== */
        .live-schedule {
            padding: 80px 0;
            background: linear-gradient(135deg, #f9f7fd 0%, #f0f9f6 100%);
            position: relative;
            width: 100%;
        }

        .stages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 60px;
            width: 100%;
        }

        .stage-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: var(--shadow-card);
            transition: all 0.4s ease;
            position: relative;
            border: 1px solid rgba(123, 44, 191, 0.1);
        }

        .stage-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .stage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .stage-header h3 {
            color: var(--dark);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }

        .stage-header span {
            background: rgba(123, 44, 191, 0.1);
            color: var(--primary-purple);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .subject-item {
            background: rgba(123, 44, 191, 0.05);
            padding: 15px;
            border-radius: 15px;
            border-right: 3px solid var(--primary-purple);
            transition: all 0.3s ease;
            border: 1px solid rgba(123, 44, 191, 0.1);
        }

        .subject-item:hover {
            transform: translateY(-5px);
            background: var(--gradient-bg);
            color: white;
        }

        .subject-item:hover .time {
            color: white !important;
        }

        .subject-item.current {
            background: var(--gradient-bg);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .subject-item.current span {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 10px;
        }

        .subject-item h4 {
            color: inherit;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .subject-item .time {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .subject-item.current .time {
            color: white;
        }

        /* ===== قسم المعلمين ===== */
        .teachers-section {
            padding: 80px 0;
            background: white;
            position: relative;
            width: 100%;
        }

        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-top: 60px;
            justify-items: center;
            width: 100%;
        }

        @media (max-width: 1200px) {
            .teachers-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .teachers-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .teachers-grid {
                grid-template-columns: 1fr;
            }
        }

        .teacher-card {
            background: white;
            border-radius: 25px;
            padding: 35px 30px;
            box-shadow: var(--shadow-card);
            transition: all 0.5s ease;
            text-align: center;
            position: relative;
            border: 1px solid rgba(123, 44, 191, 0.1);
            width: 100%;
            max-width: 250px;
        }

        .teacher-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-hover);
        }

        .teacher-avatar {
            width: 80px;
            height: 80px;
            background: var(--gradient-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            font-weight: bold;
            margin: 0 auto 20px;
            box-shadow: var(--shadow-lg);
            border: 3px solid white;
        }

        .teacher-card h3 {
            color: var(--dark);
            font-size: 1.3rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .teacher-qualification {
            color: var(--primary-purple);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .teacher-subjects {
            color: var(--gray);
            margin-bottom: 20px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .teacher-experience {
            background: rgba(123, 44, 191, 0.1);
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-purple);
            font-size: 0.9rem;
            border: 1px solid rgba(123, 44, 191, 0.2);
        }

        /* ===== الفوتر ===== */
        footer {
            background: var(--gradient-bg);
            color: white;
            padding: 60px 0 30px;
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            margin-bottom: 50px;
            position: relative;
            z-index: 1;
        }

        .footer-section h3 {
            font-size: 1.6rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 700;
            color: white;
        }

        .footer-section p {
            opacity: 0.9;
            line-height: 1.8;
            margin-bottom: 25px;
            font-size: 1rem;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .social-links a {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .social-links a:hover {
            background: white;
            color: var(--primary-purple);
            transform: translateY(-5px) rotate(15deg);
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 15px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(-10px);
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .contact-info p {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.95rem;
            position: relative;
            z-index: 1;
        }

        /* ===== زر العودة للأعلى ===== */
        .scroll-to-top {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 55px;
            height: 55px;
            background: var(--gradient-bg);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: 999;
            opacity: 0;
            transform: translateY(100px);
            transition: all 0.4s ease;
            border: none;
            border: 3px solid white;
        }

        .scroll-to-top.show {
            opacity: 1;
            transform: translateY(0);
        }

        .scroll-to-top:hover {
            transform: translateY(-10px) scale(1.1);
            box-shadow: var(--shadow-xl);
        }

        /* ===== متجاوب ===== */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 2.5rem;
            }
          
            .nav-links a {
                padding: 8px 15px;
                font-size: 0.95rem;
            }
          
            .activities-grid {
                grid-template-columns: repeat(2, 1fr);
            }
          
            .hero-subtitle {
                font-size: 1.1rem;
            }
          
            .section-title {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: block;
            }
          
            .nav-links {
                position: fixed;
                top: 80px;
                right: -100%;
                width: 280px;
                background: white;
                flex-direction: column;
                padding: 25px;
                border-radius: 0 0 0 25px;
                box-shadow: var(--shadow-xl);
                transition: right 0.4s ease;
                z-index: 1000;
                gap: 10px;
                border: 1px solid rgba(123, 44, 191, 0.1);
            }
          
            .nav-links.active {
                right: 0;
            }
          
            .nav-links a {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
            }
          
            .cta-buttons {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
          
            .btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }
          
            .hero-title {
                font-size: 2rem;
            }
          
            .hero-subtitle {
                font-size: 1rem;
            }
          
            .section-title {
                font-size: 2rem;
            }
          
            .activities-grid {
                grid-template-columns: 1fr;
            }
          
            .testimonial-card {
                min-width: 260px;
            }
          
            .teachers-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 1.8rem;
            }
          
            .hero-subtitle {
                font-size: 0.95rem;
            }
          
            .section-title {
                font-size: 1.8rem;
            }
          
            .stat-card,
            .activity-card,
            .teacher-card {
                padding: 30px 20px;
            }
          
            .stats-grid,
            .teachers-grid,
            .stages-grid {
                grid-template-columns: 1fr;
            }
          
            .footer-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }
          
            .scroll-to-top {
                bottom: 30px;
                right: 30px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
          
            .hero-content {
                padding: 40px 25px;
                margin: 0 15px;
            }
          
            .btn {
                padding: 16px 30px;
                min-width: 160px;
            }
          
            .footer-section h3 {
                font-size: 1.4rem;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 1.5rem;
            }
          
            .btn {
                padding: 14px 25px;
                font-size: 1rem;
                min-width: 140px;
            }
          
            .section-title {
                font-size: 1.6rem;
            }
          
            .stat-number {
                font-size: 2.5rem;
            }
          
            .subjects-grid {
                grid-template-columns: 1fr;
            }
          
            .teacher-card {
                max-width: 100%;
            }
          
            .logo-text h1 {
                font-size: 1.3rem;
            }
          
            .logo-text p {
                font-size: 0.85rem;
            }
          
            .footer-section h3 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- ===== رأس الصفحة ===== -->
    <header id="header">
        <div class="container header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <h1><?php echo $schoolInfo['school_name'] ?? 'مدرسة النخبة الدولية'; ?></h1>
                    <p>نظام إدارة مدرسي متكامل</p>
                </div>
            </a>
          
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-links" id="navLinks">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="about.html"><i class="fas fa-info-circle"></i> عن المدرسة</a></li>
                <li><a href="activities.html"><i class="fas fa-running"></i> الأنشطة</a></li>
                <li><a href="competitions.html"><i class="fas fa-trophy"></i> المسابقات</a></li>
                <li><a href="contact.html"><i class="fas fa-phone-alt"></i> اتصل بنا</a></li>
                <li><a href="register_student_admin.php" class="btn-secondary"><i class="fas fa-user-plus"></i> تسجيل طالب</a></li>
                <li><a href="login.php" class="btn-primary"><i class="fas fa-sign-in-alt"></i> تسجيل دخول</a></li>
            </ul>
        </div>
    </header>

    <!-- ===== قسم البطل مع الصورة المتحركة ===== -->
   <!-- ===== قسم البطل مع الصورة المتحركة ===== -->
<section class="hero" id="hero" style="
    min-height: 100vh;
    position: relative;
    display: flex;
    align-items: center;
    padding-top: 80px;
    background: linear-gradient(135deg, rgba(123, 44, 191, 0.9), rgba(6, 214, 160, 0.9)),
                url('images/th.jpg');
    background-position: center;
    background-size: cover;
    background-attachment: fixed;
">
    
    <div class="container">
        <div class="hero-content" style="
            position: relative;
            z-index: 3;
            text-align: center;
            color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
        ">
            <div class="hero-content-inner">
                <h1 class="hero-title animate__animated animate__fadeInUp">
                    مرحباً بكم في <span style="display:block;">مدرسة النخبة الدولية</span>
                </h1>
                <p class="hero-subtitle animate__animated animate__fadeInUp">
                    نصنع جيلاً متميزاً علمياً وأخلاقياً لمستقبل أفضل. نؤمن بأن التعليم هو الأساس لبناء مجتمع متطور ومزدهر.
                    نقدم تعليماً نوعياً باستخدام أحدث الوسائل التكنولوجية وبتوجيه من نخبة المعلمين.
                </p>
               
                <div class="cta-buttons">
                    <a href="register_student_parent.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        <span>سجل ابنك الآن</span>
                    </a>
                    <a href="#activities" class="btn btn-secondary">
                        <i class="fas fa-eye"></i>
                        <span>استكشف الأنشطة</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- ===== قسم الإحصائيات ===== -->
    <section class="stats-section" id="stats">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">أرقامنا <span>تتحدث عنا</span></h2>
            <div class="stats-grid">
                <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="stat-number" id="students-counter"><?php echo $studentsCount ?? 0; ?></h3>
                    <p class="stat-label">طالب وطالبة</p>
                </div>

                <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3 class="stat-number" id="teachers-counter"><?php echo $teachersCount ?? 0; ?></h3>
                    <p class="stat-label">معلم ومعلمة</p>
                </div>

                <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-icon">
                        <i class="fas fa-running"></i>
                    </div>
                    <h3 class="stat-number" id="activities-counter"><?php echo $activitiesCount ?? 0; ?></h3>
                    <p class="stat-label">نشاط مدرسي</p>
                </div>

                <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="stat-number" id="competitions-counter"><?php echo $competitionsCount ?? 0; ?></h3>
                    <p class="stat-label">مسابقة</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== قسم الأنشطة ===== -->
    <section class="activities-section" id="activities">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">أنشطتنا <span>الإبداعية</span></h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">اكتشف عالم الإبداع والابتكار في مدرستنا</p>
          
            <div class="activities-grid">
                <!-- بطاقة اليوم الرياضي المفتوح -->
                <div class="activity-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="activity-icon">
                        <i class="fas fa-futbol"></i>
                    </div>
                    <div class="activity-date">
                        <i class="far fa-calendar-alt"></i>
                        15 يناير 2025
                    </div>
                    <h3 class="activity-title">اليوم الرياضي المفتوح</h3>
                    <p class="activity-description">
                        تنظيم يوم رياضي يتضمن العديد من الأنشطة الترفيهية والرياضية للطلاب.
                    </p>
                    <div class="activity-tags">
                        <span class="tag">رياضة</span>
                        <span class="tag">ترفيه</span>
                    </div>
                    <p class="activity-objective">
                        <strong>الهدف:</strong> تعزيز النشاط البدني والتفاعل الاجتماعي بين الطلاب.
                    </p>
                    <a href="activities.html" class="activity-btn">
                        <i class="fas fa-arrow-left"></i>
                        المزيد
                    </a>
                </div>

                <!-- بطاقة المعرض العلمي -->
                <div class="activity-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="activity-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="activity-date">
                        <i class="far fa-calendar-alt"></i>
                        1 فبراير 2025
                    </div>
                    <h3 class="activity-title">المعرض العلمي</h3>
                    <p class="activity-description">
                        عرض الابتكارات ومشاريع الطلاب العلمية المتميزة تحت إشراف معلمي العلوم.
                    </p>
                    <div class="activity-tags">
                        <span class="tag">علوم</span>
                        <span class="tag">ابتكار</span>
                    </div>
                    <p class="activity-objective">
                        <strong>الهدف:</strong> تشجيع التفكير العلمي والإبداع لدى الطلاب.
                    </p>
                    <a href="activities.html" class="activity-btn">
                        <i class="fas fa-arrow-left"></i>
                        المزيد
                    </a>
                </div>

                <!-- بطاقة ورشة الرسم والألوان -->
                <div class="activity-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="activity-icon">
                        <i class="fas fa-paint-brush"></i>
                    </div>
                    <div class="activity-date">
                        <i class="far fa-calendar-alt"></i>
                        20 فبراير 2025
                    </div>
                    <h3 class="activity-title">ورشة الرسم والألوان</h3>
                    <p class="activity-description">
                        نشاط فني لتنمية مهارات الرسم والتعبير الفني لدى الطلاب.
                    </p>
                    <div class="activity-tags">
                        <span class="tag">فنون</span>
                        <span class="tag">إبداع</span>
                    </div>
                    <p class="activity-objective">
                        <strong>الهدف:</strong> دعم المواهب الفنية وتطوير الحس الجمالي للطلاب.
                    </p>
                    <a href="activities.html" class="activity-btn">
                        <i class="fas fa-arrow-left"></i>
                        المزيد
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== قسم آراء أولياء الأمور ===== -->
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">آراء <span>أولياء الأمور</span></h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">آراء أولياء أمور طلابنا هي شهادات نجاحنا ودليل رضاهم عن الخدمة التعليمية</p>
        
            <div class="testimonials-container">
                <!-- الرأي الأول - ابتدائي -->
                <div class="testimonial-card" data-aos="fade-up">
                    <div class="quote-icon">"</div>
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="review-text">
                        <p>"أشكر إدارة المدرسة على الجهد الكبير في تعليم أبنائنا. ابنتي تطورت كثيراً في القراءة والكتابة منذ التحاقها بالمدرسة."</p>
                    </div>
                    <div class="reviewer-info">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="reviewer-avatar">م</div>
                            <div>
                                <h4>محمد أحمد</h4>
                                <p>
                                    <i class="fas fa-child" style="margin-left: 5px;"></i>
                                    الصف الثالث ابتدائي
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الرأي الثاني - متوسط -->
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="quote-icon">"</div>
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <div class="review-text">
                        <p>"المدرسة توفر بيئة تعليمية ممتازة والأنشطة المدرسية متنوعة. المعلمون متعاونون ويقدمون الدعم اللازم للطلاب."</p>
                    </div>
                    <div class="reviewer-info">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="reviewer-avatar">ف</div>
                            <div>
                                <h4>فاطمة حسن</h4>
                                <p>
                                    <i class="fas fa-user-graduate" style="margin-left: 5px;"></i>
                                    الصف الثاني متوسط
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الرأي الثالث - ثانوي -->
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="quote-icon">"</div>
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                    <div class="review-text">
                        <p>"التوجيه الجامعي الذي تقدمه المدرسة ممتاز. ساعد ابني في اختيار تخصصه الجامعي بناءً على ميوله وقدراته."</p>
                    </div>
                    <div class="reviewer-info">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="reviewer-avatar">خ</div>
                            <div>
                                <h4>خالد سعيد</h4>
                                <p>
                                    <i class="fas fa-user-tie" style="margin-left: 5px;"></i>
                                    الصف الثالث ثانوي
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الرأي الرابع -->
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="quote-icon">"</div>
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="review-text">
                        <p>"الاهتمام بالنظافة والنظام في المدرسة ممتاز. أشعر بالأمان عندما يكون أبنائي في المدرسة."</p>
                    </div>
                    <div class="reviewer-info">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="reviewer-avatar">س</div>
                            <div>
                                <h4>سارة علي</h4>
                                <p>
                                    <i class="fas fa-child" style="margin-left: 5px;"></i>
                                    الصف الأول ابتدائي
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== قسم جداول الحصص ===== -->
    <section class="live-schedule" id="schedule">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">الجدول الدراسي <span>الأسبوعي</span></h2>
        
            <div class="stages-grid">
                <!-- المربع الأول: المرحلة الابتدائية -->
                <div class="stage-card" data-aos="fade-up">
                    <div class="stage-header">
                        <h3><i class="fas fa-child"></i> المرحلة الابتدائية</h3>
                        <span>الصفوف 1-6</span>
                    </div>
                  
                    <div class="subjects-grid">
                        <?php
                        $primarySubjects = [
                            ['name' => 'القرآن الكريم', 'time' => '08:00 - 08:45'],
                            ['name' => 'اللغة العربية', 'time' => '08:45 - 09:30'],
                            ['name' => 'الرياضيات', 'time' => '09:45 - 10:30'],
                            ['name' => 'العلوم', 'time' => '10:30 - 11:15'],
                            ['name' => 'التربية الإسلامية', 'time' => '11:30 - 12:15'],
                            ['name' => 'اللغة الإنجليزية', 'time' => '12:15 - 13:00']
                        ];
                      
                        foreach($primarySubjects as $subject):
                            $time_parts = explode(' - ', $subject['time']);
                            $start_time = strtotime($time_parts[0]);
                            $end_time = strtotime($time_parts[1]);
                            $current_time = strtotime(date('H:i'));
                            $is_current = ($current_time >= $start_time && $current_time <= $end_time);
                        ?>
                        <div class="subject-item <?php echo $is_current ? 'current' : ''; ?>">
                            <h4><?php echo $subject['name']; ?></h4>
                            <div class="time">
                                <i class="far fa-clock"></i>
                                <span><?php echo $subject['time']; ?></span>
                            </div>
                            <?php if($is_current): ?>
                            <span><i class="fas fa-play-circle"></i> مباشر</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
              
                <!-- المربع الثاني: المرحلة المتوسطة -->
                <div class="stage-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="stage-header">
                        <h3><i class="fas fa-user-graduate"></i> المرحلة المتوسطة</h3>
                        <span>الصفوف 7-9</span>
                    </div>
                  
                    <div class="subjects-grid">
                        <?php
                        $intermediateSubjects = [
                            ['name' => 'القرآن الكريم', 'time' => '08:00 - 08:45'],
                            ['name' => 'اللغة العربية', 'time' => '08:45 - 09:30'],
                            ['name' => 'الرياضيات', 'time' => '09:45 - 10:30'],
                            ['name' => 'العلوم', 'time' => '10:30 - 11:15'],
                            ['name' => 'اللغة الإنجليزية', 'time' => '11:30 - 12:15'],
                            ['name' => 'الاجتماعيات', 'time' => '12:15 - 13:00']
                        ];
                      
                        foreach($intermediateSubjects as $subject):
                            $time_parts = explode(' - ', $subject['time']);
                            $start_time = strtotime($time_parts[0]);
                            $end_time = strtotime($time_parts[1]);
                            $current_time = strtotime(date('H:i'));
                            $is_current = ($current_time >= $start_time && $current_time <= $end_time);
                        ?>
                        <div class="subject-item <?php echo $is_current ? 'current' : ''; ?>">
                            <h4><?php echo $subject['name']; ?></h4>
                            <div class="time">
                                <i class="far fa-clock"></i>
                                <span><?php echo $subject['time']; ?></span>
                            </div>
                            <?php if($is_current): ?>
                            <span><i class="fas fa-play-circle"></i> مباشر</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
              
                <!-- المربع الثالث: المرحلة الثانوية -->
                <div class="stage-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="stage-header">
                        <h3><i class="fas fa-user-tie"></i> المرحلة الثانوية</h3>
                        <span>الصفوف 10-12</span>
                    </div>
                  
                    <div class="subjects-grid">
                        <?php
                        $secondarySubjects = [
                            ['name' => 'القرآن الكريم', 'time' => '08:00 - 08:45'],
                            ['name' => 'اللغة العربية', 'time' => '08:45 - 09:30'],
                            ['name' => 'الرياضيات', 'time' => '09:45 - 10:30'],
                            ['name' => 'الفيزياء', 'time' => '10:30 - 11:15'],
                            ['name' => 'الكيمياء', 'time' => '11:30 - 12:15'],
                            ['name' => 'اللغة الإنجليزية', 'time' => '12:15 - 13:00']
                        ];
                      
                        foreach($secondarySubjects as $subject):
                            $time_parts = explode(' - ', $subject['time']);
                            $start_time = strtotime($time_parts[0]);
                            $end_time = strtotime($time_parts[1]);
                            $current_time = strtotime(date('H:i'));
                            $is_current = ($current_time >= $start_time && $current_time <= $end_time);
                        ?>
                        <div class="subject-item <?php echo $is_current ? 'current' : ''; ?>">
                            <h4><?php echo $subject['name']; ?></h4>
                            <div class="time">
                                <i class="far fa-clock"></i>
                                <span><?php echo $subject['time']; ?></span>
                            </div>
                            <?php if($is_current): ?>
                            <span><i class="fas fa-play-circle"></i> مباشر</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== قسم المعلمين ===== -->
    <section class="teachers-section" id="teachers">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">فريقنا <span>التعليمي المتميز</span></h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">نخبة من أفضل المعلمين المؤهلين لتقديم تعليم متميز لطلابنا</p>
        
            <div class="teachers-grid">
                <!-- المعلم 1 -->
                <div class="teacher-card" data-aos="fade-up">
                    <div class="teacher-avatar">م</div>
                    <h3>محمد أحمد</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        ماجستير في الفيزياء النووية
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        الفيزياء - الرياضيات
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>15 سنة خبرة</span>
                    </div>
                </div>
              
                <!-- المعلم 2 -->
                <div class="teacher-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="teacher-avatar">ف</div>
                    <h3>فاطمة حسن</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        ماجستير في اللغة العربية
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        اللغة العربية - التربية الإسلامية
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>12 سنة خبرة</span>
                    </div>
                </div>
              
                <!-- المعلم 3 -->
                <div class="teacher-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="teacher-avatar">خ</div>
                    <h3>خالد سعيد</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        دكتوراه في الرياضيات
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        الرياضيات - الحاسب الآلي
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>18 سنة خبرة</span>
                    </div>
                </div>
              
                <!-- المعلم 4 -->
                <div class="teacher-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="teacher-avatar">س</div>
                    <h3>سارة علي</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        ماجستير في العلوم البيولوجية
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        الأحياء - الكيمياء
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>10 سنوات خبرة</span>
                    </div>
                </div>
              
                <!-- المعلم 5 -->
                <div class="teacher-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="teacher-avatar">ن</div>
                    <h3>نورا يوسف</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        ماجستير في اللغة الإنجليزية
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        اللغة الإنجليزية - الترجمة
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>8 سنوات خبرة</span>
                    </div>
                </div>
              
                <!-- المعلم 6 -->
                <div class="teacher-card" data-aos="fade-up" data-aos-delay="500">
                    <div class="teacher-avatar">ي</div>
                    <h3>يوسف محمد</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        ماجستير في الكيمياء التحليلية
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        الكيمياء - العلوم
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>14 سنة خبرة</span>
                    </div>
                </div>

                <!-- المعلم 7 -->
                <div class="teacher-card" data-aos="fade-up" data-aos-delay="600">
                    <div class="teacher-avatar">أ</div>
                    <h3>أحمد محمد</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        ماجستير في علوم الحاسب
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        الحاسب الآلي - البرمجة
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>9 سنوات خبرة</span>
                    </div>
                </div>

                <!-- المعلم 8 -->
                <div class="teacher-card" data-aos="fade-up" data-aos-delay="700">
                    <div class="teacher-avatar">م</div>
                    <h3>مريم عبدالله</h3>
                    <div class="teacher-qualification">
                        <i class="fas fa-graduation-cap"></i>
                        دكتوراه في التاريخ الإسلامي
                    </div>
                    <div class="teacher-subjects">
                        <i class="fas fa-book"></i>
                        التاريخ - الجغرافيا
                    </div>
                    <div class="teacher-experience">
                        <i class="fas fa-award"></i>
                        <span>11 سنة خبرة</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== الفوتر ===== -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-graduation-cap"></i> <?php echo $schoolInfo['school_name'] ?? 'مدرسة النخبة الدولية'; ?></h3>
                    <p><?php echo $schoolInfo['motto'] ?? 'نعمل على بناء جيل واعد قادر على مواجهة تحديات المستقبل بتفوق وتميز.'; ?></p>
                    <p style="margin-top: 20px; opacity: 0.9;">
                        <i class="fas fa-quote-left"></i>
                        <?php echo $schoolInfo['vision'] ?? 'الريادة في تقديم تعليم نوعي يواكب متطلبات العصر.'; ?>
                    </p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
             
                <div class="footer-section">
                    <h3><i class="fas fa-link"></i> روابط سريعة</h3>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="fas fa-chevron-left"></i> الرئيسية</a></li>
                        <li><a href="about.html"><i class="fas fa-chevron-left"></i> عن المدرسة</a></li>
                        <li><a href="activities.php"><i class="fas fa-chevron-left"></i> الأنشطة</a></li>
                        <li><a href="competitions.php"><i class="fas fa-chevron-left"></i> المسابقات</a></li>
                        <li><a href="contact.php"><i class="fas fa-chevron-left"></i> اتصل بنا</a></li>
                        <li><a href="login.php"><i class="fas fa-chevron-left"></i> تسجيل الدخول</a></li>
                    </ul>
                </div>
             
                <div class="footer-section">
                    <h3><i class="fas fa-address-card"></i> معلومات الاتصال</h3>
                    <div class="contact-info">
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo $schoolInfo['address'] ?? 'صنعاء - اليمن'; ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo $schoolInfo['phone'] ?? '+967 123 456 789'; ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo $schoolInfo['email'] ?? 'info@elite-school.edu'; ?></p>
                        <p><i class="fas fa-globe"></i> <?php echo $schoolInfo['website'] ?? 'www.elite-school.edu'; ?></p>
                        <p><i class="fas fa-clock"></i> <?php echo $schoolInfo['working_hours'] ?? 'الأحد - الخميس: 7:00 ص - 2:00 م'; ?></p>
                    </div>
                </div>
            </div>
         
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $schoolInfo['school_name'] ?? 'مدرسة النخبة الدولية'; ?> - جميع الحقوق محفوظة</p>
                <p style="font-size:0.9rem; margin-top:10px; opacity:0.8;">
                    تم التطوير باستخدام PHP & MySQL | الإصدار 3.0
                </p>
            </div>
        </div>
    </footer>

    <!-- ===== زر العودة للأعلى ===== -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- ===== مكتبات JavaScript ===== -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  
    <script>
        // تهيئة AOS للأنيميشن
        AOS.init({
            duration: 800,
            once: true,
            offset: 50,
            easing: 'ease-out-cubic'
        });

        // ===== العدادات المتحركة =====
        function animateCounter(elementId, finalValue) {
            let element = document.getElementById(elementId);
            if(!element) return;
            let current = 0;
            let increment = finalValue / 40;
            let timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    element.textContent = finalValue.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 20);
        }

        // ===== تفعيل العدادات عند التمرير =====
        let statsSection = document.getElementById('stats');
        let animated = false;

        function checkScroll() {
            if(!statsSection) return;
           
            let position = statsSection.getBoundingClientRect();
            if(position.top <= window.innerHeight && !animated) {
                animateCounter('students-counter', <?php echo $studentsCount; ?>);
                animateCounter('teachers-counter', <?php echo $teachersCount; ?>);
                animateCounter('activities-counter', <?php echo $activitiesCount; ?>);
                animateCounter('competitions-counter', <?php echo $competitionsCount; ?>);
                animated = true;
            }
        }

        // ===== التحكم في ظهور زر العودة للأعلى =====
        function toggleScrollButton() {
            let scrollButton = document.getElementById('scrollToTop');
            if (window.scrollY > 500) {
                scrollButton.classList.add('show');
            } else {
                scrollButton.classList.remove('show');
            }
        }

        // ===== تغيير شكل الهيدر عند التمرير =====
        function handleHeaderScroll() {
            let header = document.getElementById('header');
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }

        // ===== القائمة المتحركة للموبايل =====
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('active');
        });

        // ===== وظائف التنقل =====
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // ===== تهيئة الصفحة =====
        window.onload = function() {
            // زر العودة للأعلى
            document.getElementById('scrollToTop').addEventListener('click', scrollToTop);
          
            // إخفاء القائمة عند النقر على رابط
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.addEventListener('click', function() {
                    document.getElementById('navLinks').classList.remove('active');
                });
            });
        };

        // ===== مستمعي الأحداث =====
        window.addEventListener('scroll', function() {
            checkScroll();
            toggleScrollButton();
            handleHeaderScroll();
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                document.getElementById('navLinks').classList.remove('active');
            }
        });

        // تأثير التمرير السلس للروابط
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
              
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // تأثير الكتابة على العنوان الرئيسي
        function typeWriterEffect() {
            const title = document.querySelector('.hero-title');
            if(!title) return;
           
            const text = title.textContent;
            title.textContent = '';
            let i = 0;
           
            function type() {
                if (i < text.length) {
                    title.textContent += text.charAt(i);
                    i++;
                    setTimeout(type, 40);
                }
            }
           
            setTimeout(type, 800);
        }
       
        window.addEventListener('load', typeWriterEffect);
    </script>
</body>
</html>