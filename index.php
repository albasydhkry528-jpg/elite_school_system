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


// ==================== جلب الإحصائيات ====================
$studentsCount = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
$teachersCount = $conn->query("SELECT COUNT(*) as total FROM teachers")->fetch_assoc()['total'];
$activitiesCount = $conn->query("SELECT COUNT(*) as total FROM activities WHERE MONTH(activity_date) = MONTH(CURDATE())")->fetch_assoc()['total'];
$competitionsCount = $conn->query("SELECT COUNT(*) as total FROM competitions WHERE status != 'منتهية'")->fetch_assoc()['total'];

// ==================== جلب آخر الأنشطة ====================
$recentActivities = $conn->query("SELECT a.*, u.full_name as organizer_name
                                  FROM activities a
                                  JOIN users u ON a.organizer_id = u.id
                                  ORDER BY a.activity_date DESC LIMIT 3");


// ==================== جلب المعلمين المتميزين ====================
$featuredTeachers = $conn->query("SELECT t.*, u.full_name, u.profile_image
                                  FROM teachers t
                                  JOIN users u ON t.user_id = u.id
                                  WHERE t.experience_years > 5
                                  ORDER BY RAND() LIMIT 4");

// ==================== جلب جدول الغد ====================
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$dayName = date('l', strtotime($tomorrow));
$daysMap = ['Sunday' => 'الأحد', 'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس'];
$arabicDay = $daysMap[$dayName] ?? 'الأحد';

$schedule = $conn->query("SELECT cs.*, s.subject_name, c.class_name, u.full_name as teacher_name
                         FROM class_subjects cs
                         JOIN subjects s ON cs.subject_id = s.id
                         JOIN classes c ON cs.class_id = c.id
                         JOIN teachers t ON cs.teacher_id = t.id
                         JOIN users u ON t.user_id = u.id
                         WHERE cs.schedule_day = '$arabicDay'
                         ORDER BY cs.time_from LIMIT 5");

// ==================== جلب معلومات المدرسة ====================
$schoolInfo = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
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
   
    <style>
        /* ===== إعدادات أساسية ===== */
        :root {
            --primary: #4c1d95;
            --primary-dark: #38156f;
            --secondary: #a855f7;
            --secondary-light: #c084fc;
            --accent: #10b981;
            --accent-light: #34d399;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --gradient-primary: linear-gradient(135deg, #4c1d95, #7e22ce, #a855f7);
            --gradient-accent: linear-gradient(135deg, #10b981, #34d399);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 25px 80px rgba(0, 0, 0, 0.2);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f7ff 100%);
            color: var(--dark);
            direction: rtl;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ===== تخصيص شريط التمرير ===== */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }

        /* ===== الحاوية الرئيسية ===== */
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ===== رأس الصفحة المذهل ===== */
        header {
            background: linear-gradient(135deg,
                rgba(76, 29, 149, 0.95) 0%,
                rgba(126, 34, 206, 0.95) 50%,
                rgba(168, 85, 247, 0.95) 100%);
            backdrop-filter: blur(15px);
            padding: 20px 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }

        header.scrolled {
            padding: 12px 0;
            background: linear-gradient(135deg,
                rgba(76, 29, 149, 0.98) 0%,
                rgba(126, 34, 206, 0.98) 100%);
            backdrop-filter: blur(20px);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            transform: translateY(-2px);
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fff, #f0f0f0);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .logo-icon::before {
            content: '';
            position: absolute;
            width: 150%;
            height: 150%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-100%) rotate(45deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        .logo-icon i {
            font-size: 2rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            z-index: 1;
        }

        .logo-text h1 {
            color: white;
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .logo-text p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* ===== القائمة ===== */
        .nav-links {
            display: flex;
            gap: 5px;
            list-style: none;
        }

        .nav-links li {
            position: relative;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 12px 25px;
            border-radius: 15px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .nav-links a:hover::before {
            transform: translateX(0);
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-links a.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-links a i {
            font-size: 1.2rem;
        }

        /* ===== زر القائمة للموبايل ===== */
        .mobile-menu-btn {
            display: none;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: rotate(90deg);
        }

        /* ===== قسم البطل المذهل ===== */
        .hero {
            min-height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.9)),
                        url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(76, 29, 149, 0.4) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.3) 0%, transparent 50%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 30px;
            background: linear-gradient(to right, #fff, #a855f7, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeInUp 1s ease-out;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 40px;
            opacity: 0.9;
            line-height: 1.8;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        /* ===== أزرار CTA ===== */
        .cta-buttons {
            display: flex;
            gap: 25px;
            justify-content: center;
            margin-top: 40px;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .btn {
            padding: 18px 45px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .btn:hover::before {
            transform: translateX(100%);
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 20px 40px rgba(168, 85, 247, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(255, 255, 255, 0.2);
        }

        /* ===== العناصر العائمة ===== */
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 1;
        }

        .floating-element {
            position: absolute;
            font-size: 3rem;
            opacity: 0.3;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            top: 40%;
            right: 15%;
            animation-delay: 1s;
        }

        .floating-element:nth-child(3) {
            bottom: 30%;
            left: 20%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(4) {
            bottom: 20%;
            right: 25%;
            animation-delay: 3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(10deg); }
        }

        /* ===== قسم الإحصائيات ===== */
        .stats-section {
            padding: 120px 0;
            background: white;
            position: relative;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f8fafc 0%, #f0f9ff 100%);
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
            z-index: 0;
        }

        .section-title {
            text-align: center;
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 80px;
            position: relative;
            z-index: 1;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 5px;
            background: var(--gradient-primary);
            border-radius: 3px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 60px;
            position: relative;
            z-index: 1;
        }

        .stat-card {
            background: white;
            padding: 50px 30px;
            border-radius: 25px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient-primary);
        }

        .stat-card:hover {
            transform: translateY(-20px) scale(1.03);
            box-shadow: var(--shadow-xl);
        }

        .stat-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .stat-icon::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            opacity: 0.1;
        }

        .stat-icon i {
            font-size: 3.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            z-index: 1;
        }

        .stat-number {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 10px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 1.3rem;
            color: var(--gray);
            font-weight: 600;
        }

        .activities-section {
            padding: 120px 0;
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
            position: relative;
        }

        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }

        .activity-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .activity-card:hover {
            transform: translateY(-20px) scale(1.02);
            box-shadow: var(--shadow-xl);
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .activity-card:hover::before {
            opacity: 1;
        }

        .activity-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .activity-card:hover .activity-img {
            transform: scale(1.1);
        }

        .activity-content {
            padding: 30px;
            position: relative;
            z-index: 2;
            background: white;
        }

        .activity-date {
            background: var(--gradient-primary);
            color: white;
            padding: 10px 20px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(168, 85, 247, 0.3);
        }

        .activity-title {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
        }
        .activities-section {
    padding: 120px 0;
    background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
    position: relative;
}

.section-subtitle {
    text-align: center;
    font-size: 1.3rem;
    color: var(--gray);
    margin-bottom: 60px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.activities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 40px;
    margin-top: 40px;
}

.activity-card {
    background: white;
    border-radius: 25px;
    padding: 40px 30px;
    box-shadow: var(--shadow-lg);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: center;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
}

.activity-card:hover {
    transform: translateY(-20px);
    box-shadow: var(--shadow-xl);
}

.activity-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100%;
    height: 5px;
    background: var(--gradient-primary);
}

.activity-icon {
    width: 90px;
    height: 90px;
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.activity-icon::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: var(--gradient-primary);
    opacity: 0.1;
}

.activity-icon i {
    font-size: 2.5rem;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.activity-date {
    background: rgba(76, 29, 149, 0.1);
    color: var(--primary);
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.activity-title {
    font-size: 1.8rem;
    color: var(--primary);
    margin-bottom: 20px;
    font-weight: 700;
    line-height: 1.4;
}

.activity-description {
    font-size: 1.1rem;
    color: var(--dark);
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
    background: rgba(76, 29, 149, 0.1);
    color: var(--primary);
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1px solid rgba(76, 29, 149, 0.2);
}

.activity-objective {
    font-size: 1rem;
    color: var(--gray);
    line-height: 1.6;
    margin-bottom: 30px;
    text-align: right;
    width: 100%;
    padding-right: 10px;
}

.activity-objective strong {
    color: var(--primary);
}

.activity-btn {
    background: var(--gradient-primary);
    color: white;
    padding: 15px 35px;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 20px rgba(168, 85, 247, 0.2);
    border: none;
    cursor: pointer;
    width: fit-content;
}

.activity-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(168, 85, 247, 0.3);
}

/* تنسيقات للجوّال */
@media (max-width: 1200px) {
    .activities-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .activities-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
   
    .activity-card {
        padding: 30px 20px;
    }
   
    .activity-title {
        font-size: 1.6rem;
    }
}


        /* ===== قسم آراء أولياء الأمور ===== */
        /* تأثيرات خاصة للبطاقات */
.testimonial-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

/* أنيميشن للنجوم */
@keyframes starGlow {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.fa-star, .fa-star-half-alt {
    animation: starGlow 2s infinite;
}

.fa-star:nth-child(1) { animation-delay: 0.1s; }
.fa-star:nth-child(2) { animation-delay: 0.2s; }
.fa-star:nth-child(3) { animation-delay: 0.3s; }
.fa-star:nth-child(4) { animation-delay: 0.4s; }
.fa-star:nth-child(5) { animation-delay: 0.5s; }

/* تصميم متجاوب */
@media (max-width: 1200px) {
    .testimonials-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .testimonials-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
   
    .testimonial-card {
        padding: 25px;
    }
}
        /* ===== قسم الجدول الحي ===== */
        /* تأثيرات خاصة */
.stage-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.subject-item {
    transition: all 0.3s ease;
}

.subject-item:hover {
    transform: scale(1.02);
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.subject-item.current {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)) !important;
    border-left: 3px solid #10b981 !important;
    box-shadow: 0 3px 10px rgba(16, 185, 129, 0.1);
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.subject-item.current span {
    animation: pulse 1.5s infinite;
}

/* تنسيق للأجهزة الصغيرة */
@media (max-width: 768px) {
    .stages-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
   
    .stage-card {
        padding: 15px;
    }
   
    .subjects-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
   
    .subject-item {
        padding: 10px;
    }
}

/* تنسيق للأجهزة الصغيرة جداً */
@media (max-width: 480px) {
    .subjects-grid {
        grid-template-columns: 1fr;
    }
}
        /* ===== قسم المعلمين ===== */
        .teachers-section {
            padding: 120px 0;
            background: white;
            position: relative;
        }

        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }

        .teacher-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            position: relative;
        }

        .teacher-card:hover {
            transform: translateY(-20px);
            box-shadow: var(--shadow-xl);
        }

        .teacher-img-container {
            width: 100%;
            height: 250px;
            overflow: hidden;
            position: relative;
        }

        .teacher-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .teacher-card:hover .teacher-img {
            transform: scale(1.1);
        }

        .teacher-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 30px 20px;
            transform: translateY(100%);
            transition: transform 0.5s ease;
        }

        .teacher-card:hover .teacher-overlay {
            transform: translateY(0);
        }

        .teacher-info {
            padding: 30px;
        }

        .teacher-name {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .teacher-subject {
            color: var(--secondary);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        /* ===== الفوتر ===== */
        footer {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            padding: 80px 0 30px;
            position: relative;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 50px;
            margin-bottom: 50px;
        }

        .footer-section h3 {
            font-size: 1.8rem;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .footer-section p {
            opacity: 0.9;
            line-height: 1.8;
            margin-bottom: 25px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .social-links a:hover {
            background: var(--secondary);
            transform: translateY(-5px) rotate(10deg);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .footer-links {
            list-style: none;
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
            gap: 10px;
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(-10px);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        /* ===== زر العودة للأعلى ===== */
        .scroll-to-top {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: 999;
            opacity: 0;
            transform: translateY(100px);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
        }

        .scroll-to-top.show {
            opacity: 1;
            transform: translateY(0);
        }

        .scroll-to-top:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(168, 85, 247, 0.4);
        }

        /* ===== أنيميشن ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== متجاوب ===== */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 3rem;
            }
           
            .nav-links a {
                padding: 10px 20px;
                font-size: 1rem;
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
                width: 300px;
                background: linear-gradient(135deg, rgba(76, 29, 149, 0.95), rgba(126, 34, 206, 0.95));
                flex-direction: column;
                padding: 30px;
                border-radius: 0 0 0 30px;
                box-shadow: var(--shadow-xl);
                transition: right 0.5s ease;
                backdrop-filter: blur(20px);
                z-index: 1000;
            }
           
            .nav-links.active {
                right: 0;
            }
           
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
           
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
           
            .hero-subtitle {
                font-size: 1.2rem;
            }
           
            .section-title {
                font-size: 2.5rem;
            }
           
            .stats-grid,
            .activities-grid,
            .teachers-grid {
                grid-template-columns: 1fr;
            }
           
            .footer-content {
                grid-template-columns: 1fr;
                gap: 40px;
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
                <li><a href="register_student_admin.php" class="btn-secondary" style="padding: 12px 25px;"><i class="fas fa-user-plus"></i> تسجيل طالب</a></li>
                <li><a href="login.php" class="btn-primary" style="padding: 12px 25px;"><i class="fas fa-sign-in-alt"></i> تسجيل دخول</a></li>
            </ul>
        </div>

        
    </header>

    <!-- ===== قسم البطل ===== -->
    <section class="hero" id="hero">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title animate__animated animate__fadeInUp">
                    مرحباً بكم في <span style="color:#a855f7">مدرسة النخبة الدولية</span>
                </h1>
                <p class="hero-subtitle animate__animated animate__fadeInUp">
                    نصنع جيلاً متميزاً علمياً وأخلاقياً لمستقبل أفضل. نؤمن بأن التعليم هو الأساس لبناء مجتمع متطور ومزدهر.
                    نقدم تعليماً نوعياً باستخدام أحدث الوسائل التكنولوجية وبتوجيه من نخبة المعلمين.
                </p>
                
               
                <div class="cta-buttons">
                    <a href="register_student_parent.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        <span>سجل ابنك الآن</span>
                    </a>1
                    <a href="#activities" class="btn btn-secondary">
                        <i class="fas fa-eye"></i>
                        <span>استكشف الأنشطة</span>
                    </a>
                </div>
            </div>
        </div>
       
        <!-- عناصر عائمة -->
        <div class="floating-elements">
            <div class="floating-element">🎓</div>
            <div class="floating-element">📚</div>
            <div class="floating-element">🏆</div>
            <div class="floating-element">🌟</div>
        </div>
    </section>

    <!-- ===== قسم الإحصائيات ===== -->
   <section class="stats-section" id="stats">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">أرقامنا تتحدث عنا</h2>
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

        <!-- ===== قسم الأنشطة الأخيرة ===== -->
    <!-- ===== قسم الأنشطة ===== -->
<section class="activities-section" id="activities">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">أنشطتنا الإبداعية</h2>
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
                <h3 class="activity-title">استوديو الفنون التشكيلية</h3>
                <p class="activity-description">
                    تنظيم يوم فنون يتضمن ابداعات أنشطة ترفيهية للطلاب.
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
                    عرض الابتكارات ومشاريع الطلاب العلمية.
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
                    نشاط فني لتنمية مهارات الرسم لدى الطلاب.
                </p>
                <div class="activity-tags">
                    <span class="tag">فنون</span>
                    <span class="tag">إبداع</span>
                </div>
                <p class="activity-objective">
                    <strong>الهدف:</strong> دعم المواهب الفنية وتطوير الحس الجمالي.
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
        <h2 class="section-title" data-aos="fade-up">آراء أولياء الأمور</h2>
        <p class="section-subtitle" data-aos="fade-up" style="text-align: center; color: var(--gray); font-size: 1.1rem; margin-bottom: 50px; max-width: 700px; margin-left: auto; margin-right: auto;">
            آراء أولياء أمور طلابنا هي شهادات نجاحنا ودليل رضاهم عن الخدمة التعليمية
        </p>
     
        <div class="testimonials-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
            <!-- الرأي الأول - ابتدائي -->
            <div class="testimonial-card" data-aos="fade-up"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; position: relative; overflow: hidden; border-top: 5px solid #4c1d95;">
               
                <div style="position: absolute; top: 20px; left: 25px; font-size: 5rem; color: rgba(76, 29, 149, 0.05); font-family: serif;">
                    "
                </div>
               
                <!-- التقييم بالنجوم فقط -->
                <div class="rating" style="margin-bottom: 20px; text-align: left;">
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                </div>
               
                <!-- نص الرأي -->
                <div class="review-text" style="margin-bottom: 25px; position: relative; z-index: 1;">
                    <p style="font-size: 1rem; line-height: 1.7; color: var(--dark); font-style: italic;">
                        "أشكر إدارة المدرسة على الجهد الكبير في تعليم أبنائنا. ابنتي تطورت كثيراً في القراءة والكتابة منذ التحاقها بالمدرسة."
                    </p>
                </div>
               
                <!-- معلومات ولي الأمر -->
                <div class="reviewer-info" style="padding-top: 15px; border-top: 2px solid #f1f5f9;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="reviewer-avatar" style="width: 45px; height: 45px; background: linear-gradient(135deg, #4c1d95, #7e22ce); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; font-weight: bold;">
                            م
                        </div>
                        <div>
                            <h4 style="color: var(--primary); font-size: 1.1rem; margin-bottom: 3px;">
                                محمد أحمد
                            </h4>
                            <p style="color: var(--gray); font-size: 0.85rem;">
                                <i class="fas fa-child" style="color: #4c1d95; margin-left: 5px;"></i>
                                الصف الثالث ابتدائي
                            </p>
                        </div>
                    </div>
                </div>
               
                <!-- تأثير زاوي -->
                <div style="position: absolute; bottom: 0; right: 0; width: 80px; height: 80px; background: linear-gradient(45deg, transparent, rgba(76, 29, 149, 0.05)); border-radius: 50%; transform: translate(30%, 30%);"></div>
            </div>
           
            <!-- الرأي الثاني - متوسط -->
            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; position: relative; overflow: hidden; border-top: 5px solid #7e22ce;">
               
                <div style="position: absolute; top: 20px; left: 25px; font-size: 5rem; color: rgba(126, 34, 206, 0.05); font-family: serif;">
                    "
                </div>
               
                <!-- التقييم بالنجوم فقط -->
                <div class="rating" style="margin-bottom: 20px; text-align: left;">
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star-half-alt" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                </div>
               
                <!-- نص الرأي -->
                <div class="review-text" style="margin-bottom: 25px; position: relative; z-index: 1;">
                    <p style="font-size: 1rem; line-height: 1.7; color: var(--dark); font-style: italic;">
                        "المدرسة توفر بيئة تعليمية ممتازة والأنشطة المدرسية متنوعة. المعلمون متعاونون ويقدمون الدعم اللازم للطلاب."
                    </p>
                </div>
               
                <!-- معلومات ولي الأمر -->
                <div class="reviewer-info" style="padding-top: 15px; border-top: 2px solid #f1f5f9;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="reviewer-avatar" style="width: 45px; height: 45px; background: linear-gradient(135deg, #7e22ce, #a855f7); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; font-weight: bold;">
                            ف
                        </div>
                        <div>
                            <h4 style="color: var(--primary); font-size: 1.1rem; margin-bottom: 3px;">
                                فاطمة حسن
                            </h4>
                            <p style="color: var(--gray); font-size: 0.85rem;">
                                <i class="fas fa-user-graduate" style="color: #7e22ce; margin-left: 5px;"></i>
                                الصف الثاني متوسط
                            </p>
                        </div>
                    </div>
                </div>
               
                <!-- تأثير زاوي -->
                <div style="position: absolute; bottom: 0; right: 0; width: 80px; height: 80px; background: linear-gradient(45deg, transparent, rgba(126, 34, 206, 0.05)); border-radius: 50%; transform: translate(30%, 30%);"></div>
            </div>
           
            <!-- الرأي الثالث - ثانوي -->
            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="200"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; position: relative; overflow: hidden; border-top: 5px solid #a855f7;">
               
                <div style="position: absolute; top: 20px; left: 25px; font-size: 5rem; color: rgba(168, 85, 247, 0.05); font-family: serif;">
                    "
                </div>
               
                <!-- التقييم بالنجوم فقط -->
                <div class="rating" style="margin-bottom: 20px; text-align: left;">
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="far fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                </div>
               
                <!-- نص الرأي -->
                <div class="review-text" style="margin-bottom: 25px; position: relative; z-index: 1;">
                    <p style="font-size: 1rem; line-height: 1.7; color: var(--dark); font-style: italic;">
                        "التوجيه الجامعي الذي تقدمه المدرسة ممتاز. ساعد ابني في اختيار تخصصه الجامعي بناءً على ميوله وقدراته."
                    </p>
                </div>
               
                <!-- معلومات ولي الأمر -->
                <div class="reviewer-info" style="padding-top: 15px; border-top: 2px solid #f1f5f9;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="reviewer-avatar" style="width: 45px; height: 45px; background: linear-gradient(135deg, #a855f7, #c084fc); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; font-weight: bold;">
                            خ
                        </div>
                        <div>
                            <h4 style="color: var(--primary); font-size: 1.1rem; margin-bottom: 3px;">
                                خالد سعيد
                            </h4>
                            <p style="color: var(--gray); font-size: 0.85rem;">
                                <i class="fas fa-user-tie" style="color: #a855f7; margin-left: 5px;"></i>
                                الصف الثالث ثانوي
                            </p>
                        </div>
                    </div>
                </div>
               
                <!-- تأثير زاوي -->
                <div style="position: absolute; bottom: 0; right: 0; width: 80px; height: 80px; background: linear-gradient(45deg, transparent, rgba(168, 85, 247, 0.05)); border-radius: 50%; transform: translate(30%, 30%);"></div>
            </div>
           
           <div class="testimonial-card" data-aos="fade-up" data-aos-delay="300"
     style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; position: relative; overflow: hidden; border-top: 5px solid #4c1d95;">
   
    <div style="position: absolute; top: 20px; left: 25px; font-size: 5rem; color: rgba(76, 29, 149, 0.05); font-family: serif;">
        "
    </div>
               
                <!-- التقييم بالنجوم فقط -->
                <div class="rating" style="margin-bottom: 20px; text-align: left;">
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 1.2rem; margin-left: 3px;"></i>
                </div>
               
                <!-- نص الرأي -->
                <div class="review-text" style="margin-bottom: 25px; position: relative; z-index: 1;">
                    <p style="font-size: 1rem; line-height: 1.7; color: var(--dark); font-style: italic;">
                        "الاهتمام بالنظافة والنظام في المدرسة ممتاز. أشعر بالأمان عندما يكون أبنائي في المدرسة."
                    </p>
                </div>
               
                <!-- معلومات ولي الأمر -->
                <div class="reviewer-info" style="padding-top: 15px; border-top: 2px solid #f1f5f9;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="reviewer-avatar" style="width: 45px; height: 45px; background: linear-gradient(135deg, #10b981, #34d399); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; font-weight: bold;">
                            س
                        </div>
                        <div>
                            <h4 style="color: var(--primary); font-size: 1.1rem; margin-bottom: 3px;">
                                سارة علي
                            </h4>
                            <p style="color: var(--gray); font-size: 0.85rem;">
                                <i class="fas fa-child" style="color: #10b981; margin-left: 5px;"></i>
                                الصف الأول ابتدائي
                            </p>
                        </div>
                    </div>
                </div>
               
                <!-- تأثير زاوي -->
                <div style="position: absolute; bottom: 0; right: 0; width: 80px; height: 80px; background: linear-gradient(45deg, transparent, rgba(16, 185, 129, 0.05)); border-radius: 50%; transform: translate(30%, 30%);"></div>
            </div>
        </div>
    </div>
</section>

   <!-- ===== قسم جداول الحصص ===== -->
<section class="live-schedule" id="schedule">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">الجدول الدراسي الأسبوعي</h2>
     
        <div class="schedule-container" data-aos="fade-up">
            <!-- 3 مربعات للمراحل الدراسية -->
            <div class="stages-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px;">
               
                <!-- المربع الأول: المرحلة الابتدائية -->
                <div class="stage-card" style="background: white; border-radius: 20px; padding: 20px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; position: relative; overflow: hidden; border-top: 5px solid #4c1d95;">
                    <div class="stage-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="color: #4c1d95; font-size: 1.3rem; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-child"></i>
                            المرحلة الابتدائية
                        </h3>
                        <span style="background: linear-gradient(135deg, #4c1d95, #7e22ce); color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                            الصفوف 1-6
                        </span>
                    </div>
                   
                    <div class="subjects-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <?php
                        // المواد الدراسية للمرحلة الابتدائية
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
                        <div class="subject-item <?php echo $is_current ? 'current' : ''; ?>"
                             style="background: #f8fafc; padding: 12px; border-radius: 12px; border-left: 3px solid #4c1d95; height: 100%;">
                            <div style="margin-bottom: 8px;">
                                <h4 style="color: #4c1d95; font-size: 0.95rem; font-weight: 600; margin-bottom: 5px; line-height: 1.3;">
                                    <?php echo $subject['name']; ?>
                                </h4>
                                <div style="display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 0.8rem;">
                                    <i class="far fa-clock" style="color: #a855f7; font-size: 0.8rem;"></i>
                                    <span><?php echo $subject['time']; ?></span>
                                </div>
                            </div>
                            <?php if($is_current): ?>
                            <div style="text-align: center;">
                                <span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; display: inline-block;">
                                    <i class="fas fa-play-circle"></i> مباشر
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
               
                <!-- المربع الثاني: المرحلة المتوسطة -->
                <div class="stage-card" style="background: white; border-radius: 20px; padding: 20px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; position: relative; overflow: hidden; border-top: 5px solid #7e22ce;">
                    <div class="stage-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="color: #7e22ce; font-size: 1.3rem; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-user-graduate"></i>
                            المرحلة المتوسطة
                        </h3>
                        <span style="background: linear-gradient(135deg, #7e22ce, #a855f7); color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                            الصفوف 7-9
                        </span>
                    </div>
                   
                    <div class="subjects-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <?php
                        // المواد الدراسية للمرحلة المتوسطة
                        $intermediateSubjects = [
                            ['name' => 'القرآن الكريم', 'time' => '08:00 - 08:45'],
                            ['name' => 'اللغة العربية', 'time' => '08:45 - 09:30'],
                            ['name' => 'الرياضيات', 'time' => '09:45 - 10:30'],
                            ['name' => 'العلوم', 'time' => '10:30 - 11:15'],
                            ['name' => 'اللغة الإنجليزية', 'time' => '11:30 - 12:15'],
                            ['name' => 'الاجتماعيات', 'time' => '12:15 - 13:00'],
                            ['name' => 'التربية الإسلامية', 'time' => '13:15 - 14:00']
                        ];
                       
                        foreach($intermediateSubjects as $subject):
                            $time_parts = explode(' - ', $subject['time']);
                            $start_time = strtotime($time_parts[0]);
                            $end_time = strtotime($time_parts[1]);
                            $current_time = strtotime(date('H:i'));
                            $is_current = ($current_time >= $start_time && $current_time <= $end_time);
                        ?>
                        <div class="subject-item <?php echo $is_current ? 'current' : ''; ?>"
                             style="background: #f8fafc; padding: 12px; border-radius: 12px; border-left: 3px solid #7e22ce; height: 100%;">
                            <div style="margin-bottom: 8px;">
                                <h4 style="color: #7e22ce; font-size: 0.95rem; font-weight: 600; margin-bottom: 5px; line-height: 1.3;">
                                    <?php echo $subject['name']; ?>
                                </h4>
                                <div style="display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 0.8rem;">
                                    <i class="far fa-clock" style="color: #c084fc; font-size: 0.8rem;"></i>
                                    <span><?php echo $subject['time']; ?></span>
                                </div>
                            </div>
                            <?php if($is_current): ?>
                            <div style="text-align: center;">
                                <span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; display: inline-block;">
                                    <i class="fas fa-play-circle"></i> مباشر
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
               
                <!-- المربع الثالث: المرحلة الثانوية -->
                <div class="stage-card" style="background: white; border-radius: 20px; padding: 20px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; position: relative; overflow: hidden; border-top: 5px solid #a855f7;">
                    <div class="stage-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="color: #a855f7; font-size: 1.3rem; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-user-tie"></i>
                            المرحلة الثانوية
                        </h3>
                        <span style="background: linear-gradient(135deg, #a855f7, #c084fc); color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                            الصفوف 10-12
                        </span>
                    </div>
                   
                    <div class="subjects-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <?php
                        // المواد الدراسية للمرحلة الثانوية
                        $secondarySubjects = [
                            ['name' => 'القرآن الكريم', 'time' => '08:00 - 08:45'],
                            ['name' => 'اللغة العربية', 'time' => '08:45 - 09:30'],
                            ['name' => 'الرياضيات', 'time' => '09:45 - 10:30'],
                            ['name' => 'الفيزياء', 'time' => '10:30 - 11:15'],
                            ['name' => 'الكيمياء', 'time' => '11:30 - 12:15'],
                            ['name' => 'اللغة الإنجليزية', 'time' => '12:15 - 13:00'],
                            ['name' => 'التربية الإسلامية', 'time' => '13:15 - 14:00'],
                            ['name' => 'الاجتماعيات (عاشر فقط)', 'time' => '14:15 - 15:00']
                        ];
                       
                        foreach($secondarySubjects as $subject):
                            $time_parts = explode(' - ', $subject['time']);
                            $start_time = strtotime($time_parts[0]);
                            $end_time = strtotime($time_parts[1]);
                            $current_time = strtotime(date('H:i'));
                            $is_current = ($current_time >= $start_time && $current_time <= $end_time);
                            $is_social_studies = (strpos($subject['name'], 'الاجتماعيات') !== false);
                            $border_color = $is_social_studies ? '#f59e0b' : '#a855f7';
                            $text_color = $is_social_studies ? '#f59e0b' : '#a855f7';
                        ?>
                        <div class="subject-item <?php echo $is_current ? 'current' : ''; ?>"
                             style="background: #f8fafc; padding: 12px; border-radius: 12px; border-left: 3px solid <?php echo $border_color; ?>; height: 100%;">
                            <div style="margin-bottom: 8px;">
                                <h4 style="color: <?php echo $text_color; ?>; font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; line-height: 1.3;">
                                    <?php echo $subject['name']; ?>
                                    <?php if($is_social_studies): ?>
                                    <br><small style="font-size: 0.7rem; color: #f59e0b;">(للصف العاشر فقط)</small>
                                    <?php endif; ?>
                                </h4>
                                <div style="display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 0.8rem;">
                                    <i class="far fa-clock" style="color: <?php echo $is_social_studies ? '#fbbf24' : '#e9d5ff'; ?>; font-size: 0.8rem;"></i>
                                    <span><?php echo $subject['time']; ?></span>
                                </div>
                            </div>
                            <?php if($is_current): ?>
                            <div style="text-align: center;">
                                <span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; display: inline-block;">
                                    <i class="fas fa-play-circle"></i> مباشر
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
    <!-- ===== قسم المعلمين ===== -->
   <!-- ===== قسم فريقنا التعليمي ===== -->
<section class="teachers-section" id="teachers">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">فريقنا التعليمي المتميز</h2>
        <p class="section-subtitle" data-aos="fade-up" style="text-align: center; color: var(--gray); font-size: 1.1rem; margin-bottom: 50px; max-width: 800px; margin-left: auto; margin-right: auto;">
            نخبة من أفضل المعلمين المؤهلين لتقديم تعليم متميز لطلابنا
        </p>
     
        <div class="teachers-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;">
            <!-- المعلم 1 -->
            <div class="teacher-card" data-aos="fade-up"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #4c1d95;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #4c1d95, #7e22ce); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    م
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    محمد أحمد
                </h3>
               
                <div class="teacher-qualification" style="color: #4c1d95; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    ماجستير في الفيزياء النووية
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #7e22ce; margin-left: 5px;"></i>
                    الفيزياء - الرياضيات
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>15 سنة خبرة</span>
                </div>
            </div>
           
            <!-- المعلم 2 -->
            <div class="teacher-card" data-aos="fade-up" data-aos-delay="100"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #7e22ce;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #7e22ce, #a855f7); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    ف
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    فاطمة حسن
                </h3>
               
                <div class="teacher-qualification" style="color: #7e22ce; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    ماجستير في اللغة العربية
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #a855f7; margin-left: 5px;"></i>
                    اللغة العربية - التربية الإسلامية
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>12 سنة خبرة</span>
                </div>
            </div>
           
            <!-- المعلم 3 -->
            <div class="teacher-card" data-aos="fade-up" data-aos-delay="200"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #a855f7;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #a855f7, #c084fc); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    خ
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    خالد سعيد
                </h3>
               
                <div class="teacher-qualification" style="color: #a855f7; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    دكتوراه في الرياضيات
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #c084fc; margin-left: 5px;"></i>
                    الرياضيات - الحاسب الآلي
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>18 سنة خبرة</span>
                </div>
            </div>
           
            <!-- المعلم 4 -->
            <div class="teacher-card" data-aos="fade-up" data-aos-delay="300"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #10b981;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #34d399); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    س
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    سارة علي
                </h3>
               
                <div class="teacher-qualification" style="color: #10b981; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    ماجستير في العلوم البيولوجية
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #34d399; margin-left: 5px;"></i>
                    الأحياء - الكيمياء
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>10 سنوات خبرة</span>
                </div>
            </div>
           
            <!-- المعلم 5 -->
            <div class="teacher-card" data-aos="fade-up" data-aos-delay="400"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #f59e0b;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    ن
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    نورا يوسف
                </h3>
               
                <div class="teacher-qualification" style="color: #f59e0b; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    ماجستير في اللغة الإنجليزية
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #fbbf24; margin-left: 5px;"></i>
                    اللغة الإنجليزية - الترجمة
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>8 سنوات خبرة</span>
                </div>
            </div>
           
            <!-- المعلم 6 -->
            <div class="teacher-card" data-aos="fade-up" data-aos-delay="500"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #3b82f6;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6, #60a5fa); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    ي
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    يوسف محمد
                </h3>
               
                <div class="teacher-qualification" style="color: #3b82f6; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    ماجستير في الكيمياء التحليلية
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #60a5fa; margin-left: 5px;"></i>
                    الكيمياء - العلوم
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>14 سنة خبرة</span>
                </div>
            </div>
           
            <!-- المعلم 7 -->
            <div class="teacher-card" data-aos="fade-up" data-aos-delay="600"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #ec4899;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #ec4899, #f472b6); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    ع
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    علي محمود
                </h3>
               
                <div class="teacher-qualification" style="color: #ec4899; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    ماجستير في التربية الإسلامية
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #f472b6; margin-left: 5px;"></i>
                    التربية الإسلامية - القرآن الكريم
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>20 سنة خبرة</span>
                </div>
            </div>
           
            <!-- المعلم 8 -->
            <div class="teacher-card" data-aos="fade-up" data-aos-delay="700"
                 style="background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-lg); transition: all 0.4s ease; text-align: center; border-top: 5px solid #06b6d4;">
               
                <div class="teacher-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, #06b6d4, #22d3ee); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; margin: 0 auto 20px;">
                    ه
                </div>
               
                <h3 style="color: var(--dark); font-size: 1.4rem; margin-bottom: 10px;">
                    هدى أحمد
                </h3>
               
                <div class="teacher-qualification" style="color: #06b6d4; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                    <i class="fas fa-graduation-cap" style="margin-left: 5px;"></i>
                    ماجستير في الحاسب الآلي
                </div>
               
                <div class="teacher-subjects" style="color: var(--dark); margin-bottom: 15px; font-size: 1rem;">
                    <i class="fas fa-book" style="color: #22d3ee; margin-left: 5px;"></i>
                    الحاسب الآلي - البرمجة
                </div>
               
                <div class="teacher-experience" style="background: #f8fafc; padding: 10px 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 8px; color: var(--dark); font-size: 0.9rem;">
                    <i class="fas fa-award" style="color: #f59e0b;"></i>
                    <span>9 سنوات خبرة</span>
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
                        
                </div>
              
                <div class="footer-section">
                    <h3><i class="fas fa-address-card"></i> معلومات الاتصال</h3>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
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
            duration: 1000,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });

        // ===== العدادات المتحركة =====
        function animateCounter(elementId, finalValue) {
            let element = document.getElementById(elementId);
            let current = 0;
            let increment = finalValue / 50;
            let timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    element.textContent = finalValue.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 30);
        }

        // ===== تفعيل العدادات عند التمرير =====
        let statsSection = document.getElementById('stats');
        let animated = false;

        function checkScroll() {
            let position = statsSection.getBoundingClientRect();
            if(position.top <= window.innerHeight && !animated) {
                animateCounter('students-counter', <?php echo $studentsCount; ?>);
                animateCounter('teachers-counter', <?php echo $teachersCount; ?>);
                animateCounter('activities-counter', <?php echo $activitiesCount; ?>);
                animateCounter('competitions-counter', <?php echo $competitionsCount; ?>);
                animated = true;
            }
        }

        // ===== تحديث الوقت الحي =====
        function updateTime() {
            let now = new Date();
            let timeString = now.toLocaleTimeString('ar-SA', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            document.getElementById('current-time').textContent = timeString;
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

        // ===== تحريك آراء أولياء الأمور تلقائياً =====
        let testimonialIndex = 0;
        function rotateTestimonials() {
            let testimonials = document.querySelectorAll('.testimonial');
            testimonials.forEach(t => t.style.display = 'none');
            testimonialIndex = (testimonialIndex + 1) % testimonials.length;
            testimonials[testimonialIndex].style.display = 'block';
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
            // تحديث الوقت كل ثانية
            updateTime();
            setInterval(updateTime, 1000);
           
            // تحديث الشهادات كل 7 ثوان
            let testimonials = document.querySelectorAll('.testimonial');
            if (testimonials.length > 1) {
                testimonials.forEach((t, i) => {
                    t.style.display = i === 0 ? 'block' : 'none';
                });
                setInterval(rotateTestimonials, 7000);
            }
           
            // إخفاء القائمة عند النقر على رابط
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.addEventListener('click', function() {
                    document.getElementById('navLinks').classList.remove('active');
                });
            });
           
            // زر العودة للأعلى
            document.getElementById('scrollToTop').addEventListener('click', scrollToTop);
        };

        // ===== مستمعي الأحداث =====
        window.addEventListener('scroll', function() {
            checkScroll();
            toggleScrollButton();
            handleHeaderScroll();
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                document.getElementById('navLinks').style.display = 'flex';
            } else {
                document.getElementById('navLinks').style.display = 'none';
            }
        });

        // تأثير كتابة النص
        function typeWriter(element, text, speed = 50) {
            let i = 0;
            element.textContent = '';
            function typing() {
                if (i < text.length) {
                    element.textContent += text.charAt(i);
                    i++;
                    setTimeout(typing, speed);
                }
            }
            typing();
        }

       // تحميل الصور المكسورة
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('img').forEach(img => {
                img.onerror = function() {
                    if(this.src.includes('teacher')) {
                        this.src = 'https://images.unsplash.com/photo-1568602471122-7832951cc4c5?w=800&q=80';
                    } else if(this.src.includes('activity')) {
                        this.src = 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800&q=80';
                    } else if(this.src.includes('profile')) {
                        this.src = 'assets/images/profiles/default.png';
                    }
                };
            });
        });

        // تأثير النقر على الأزرار
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                // تأثير الاهتزاز
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
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

        // تأثير الوميض للعناصر
        function addGlowEffect() {
            document.querySelectorAll('.stat-card, .activity-card, .teacher-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 25px 80px rgba(168, 85, 247, 0.3)';
                });
               
                card.addEventListener('mouseleave', function() {
                    this.style.boxShadow = 'var(--shadow-lg)';
                });
            });
        }
        addGlowEffect();
    </script>
</body>
</html>
           