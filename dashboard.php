<?php
session_start();

// حماية الصفحة - نفس الدوال
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// بيانات المستخدم - نفس المسارات
$full_name = $_SESSION['full_name'] ?? 'مستخدم';
$user_type = $_SESSION['user_type'] ?? 'طالب';
$user_id = $_SESSION['user_id'];

// الاتصال بقاعدة البيانات
require_once "includes/config.php";

// جلب البيانات الأساسية فقط حسب نوع المستخدم
$user_stats = [
    'join_date' => date('Y-m-d'),
    'login_count' => 1,
    'user_type_display' => $user_type
];

// جلب تاريخ الانضمام فقط
$query = "SELECT created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_stats['join_date'] = $row['created_at'];
}

// للمشرفين فقط: جلب إحصائيات مختصرة
if ($user_type === 'moderator') {
    $moderator_stats = [];
   
    // إجمالي المستخدمين
    $query = "SELECT COUNT(*) as total_users FROM users";
    $result = $conn->query($query);
    $moderator_stats['total_users'] = $result->fetch_assoc()['total_users'];
   
    // عدد المستخدمين النشطين اليوم
    $query = "SELECT COUNT(*) as logged_today FROM users WHERE DATE(last_login) = CURDATE()";
    $result = $conn->query($query);
    $moderator_stats['logged_today'] = $result->fetch_assoc()['logged_today'];
   
    // عدد المستخدمين الجدد هذا الشهر
    $query = "SELECT COUNT(*) as new_this_month FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE())";
    $result = $conn->query($query);
    $moderator_stats['new_this_month'] = $result->fetch_assoc()['new_this_month'];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم | <?php echo htmlspecialchars($full_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
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
            display: flex;
            color: var(--dark);
        }

        /* الشريط الجانبي */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a1a2e, #16213e);
            color: white;
            height: 100vh;
            position: fixed;
            padding: 30px 0;
            box-shadow: 5px 0 25px rgba(0,0,0,0.2);
            z-index: 100;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 0 25px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 2.5rem;
            color: var(--primary);
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo h1 {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        .user-info h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .user-type {
            background: rgba(255,255,255,0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
        }

        /* القائمة الجانبية */
        .nav-menu {
            list-style: none;
            padding: 0 20px;
        }

        .nav-section {
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
            margin: 25px 0 15px 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: linear-gradient(90deg, var(--primary), transparent);
            color: white;
            border-right: 3px solid var(--secondary);
        }

        .nav-link i {
            width: 22px;
            text-align: center;
            font-size: 1.2rem;
        }

        /* زر تسجيل الخروج */
        .logout-btn {
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            color: white;
            border: none;
            width: calc(100% - 40px);
            margin: 30px 20px;
            padding: 16px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 65, 108, 0.3);
        }

        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 30px;
        }

        /* شريط التنقل العلوي */
        .top-bar {
            background: white;
            padding: 25px 35px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-box h1 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .welcome-box p {
            color: #666;
            font-size: 1rem;
        }

        .welcome-box .highlight {
            color: var(--primary);
            font-weight: 700;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .action-icon {
            width: 50px;
            height: 50px;
            background: var(--light);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .action-icon:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        /* إحصائيات المشرفين */
        .moderator-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .moderator-stat-card {
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
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .moderator-stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .moderator-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #fdcb6e, transparent);
        }

        .moderator-stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            background: linear-gradient(45deg, #fdcb6e, #e17055);
        }

        .moderator-stat-content h3 {
            font-size: 2.2rem;
            margin-bottom: 5px;
            color: var(--dark);
            font-weight: 700;
        }

        .moderator-stat-content p {
            color: #777;
            font-size: 1rem;
        }

        /* صفحة الترحيب البسيطة */
        .welcome-content {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
            margin-bottom: 40px;
        }

        .welcome-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .welcome-content h2 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .welcome-content p {
            font-size: 1.2rem;
            color: #666;
            line-height: 1.8;
            max-width: 800px;
            margin: 0 auto 30px;
        }

        /* تذييل الصفحة */
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(100%);
            }
          
            .main-content {
                margin-right: 0;
                padding: 20px;
            }
          
            .sidebar.active {
                transform: translateX(0);
            }
          
            .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .moderator-stats {
                grid-template-columns: 1fr;
            }
          
            .top-bar {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
          
            .welcome-content {
                padding: 30px 20px;
            }
        }

        /* زر القائمة للشاشات الصغيرة */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 25px;
            left: 25px;
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 101;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        @media (max-width: 992px) {
            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        .logout-top {
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .logout-top:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 65, 108, 0.3);
        }
    </style>
</head>
<body>

<!-- زر القائمة للشاشات الصغيرة -->
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- الشريط الجانبي -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-chart-line"></i>
            <h1>لوحة التحكم</h1>
        </div>
      
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($full_name, 0, 1)); ?>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($full_name); ?></h3>
                <span class="user-type">
                    <?php
                    $user_types = [
                        'admin' => '👑 مدير النظام',
                        'teacher' => '👨‍🏫 معلم',
                        'student' => '🎓 طالب',
                        'moderator' => '⚡ مشرف'
                    ];
                    echo $user_types[$user_type] ?? $user_type;
                    ?>
                </span>
            </div>
        </div>
    </div>
  
    <ul class="nav-menu">
        <li class="nav-section">القائمة الرئيسية</li>
      
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-home"></i>
                الرئيسية
            </a>
        </li>
      
        <!-- للمدير فقط: الملف الشخصي -->
        <?php if ($user_type === 'admin'): ?>
        <li class="nav-item">
            <a href="profile.html" class="nav-link">
                <i class="fas fa-user-circle"></i>
                الملف الشخصي
            </a>
        </li>
        <?php endif; ?>
      
        <!-- للمديرين: مع الرسائل -->
        <?php if ($user_type === 'admin'): ?>
        <li class="nav-section">إدارة النظام</li>
      
        <li class="nav-item">
            <a href="users.php" class="nav-link">
                <i class="fas fa-users-cog"></i>
                إدارة المستخدمين
            </a>
        </li>
      
        <li class="nav-item">
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                التقارير التفصيلية
            </a>
        </li>
      
        <li class="nav-section">عام</li>
      
        <li class="nav-item">
            <a href="notifications.php" class="nav-link">
                <i class="fas fa-bell"></i>
                الإشعارات
            </a>
        </li>
      
        
        </li>
      
        <li class="nav-item">
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                الإعدادات
            </a>
        </li>
        <?php endif; ?>
      
        <!-- للمشرفين -->
        <?php if ($user_type === 'moderator'): ?>
        <li class="nav-section">إدارة النظام</li>
      
        <li class="nav-item">
            <a href="users.php" class="nav-link">
                <i class="fas fa-users-cog"></i>
                إدارة المستخدمين
            </a>
        </li>
      
        <li class="nav-item">
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                التقارير التفصيلية
            </a>
        </li>
      
        <li class="nav-item">
            <a href="content.php" class="nav-link">
                <i class="fas fa-edit"></i>
                إدارة المحتوى
            </a>
        </li>
      
        <li class="nav-section">عام</li>
      
        <li class="nav-item">
            <a href="notifications.php" class="nav-link">
                <i class="fas fa-bell"></i>
                الإشعارات
            </a>
        </li>
      
        <li class="nav-item">
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                الإعدادات
            </a>
        </li>
        <?php endif; ?>
      
       
      
        <!-- للطلاب: درجاتي فقط -->
        <?php if ($user_type === 'student'): ?>
        <li class="nav-section">التعلم</li>
      
        <li class="nav-item">
            <a href="my_grades.php" class="nav-link">
                <i class="fas fa-star"></i>
                درجاتي
            </a>
        </li>
        <?php endif; ?>
    </ul>
  
    <!-- زر تسجيل الخروج -->
    <button class="logout-btn" onclick="logout()">
        <i class="fas fa-sign-out-alt"></i>
        تسجيل الخروج
    </button>
</aside>

<!-- المحتوى الرئيسي -->
<main class="main-content">
    <!-- شريط التنقل العلوي -->
    <div class="top-bar">
        <div class="welcome-box">
            <h1>مرحباً بعودتك، <span class="highlight"><?php echo htmlspecialchars($full_name); ?></span>! 👋</h1>
            <p>آخر تحديث: <?php echo date('Y/m/d | h:i A'); ?></p>
        </div>
      
        <div class="top-actions">
            <button class="logout-top" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                خروج
            </button>
        </div>
    </div>
  
    <!-- المحتوى حسب نوع المستخدم -->
    <?php if ($user_type === 'moderator'): ?>
        <!-- إحصائيات المشرفين -->
       
          
            
          
           
      
        <!-- رسالة ترحيب للمشرفين -->
        <div class="welcome-content">
            <div class="welcome-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h2>مرحباً بك في لوحة تحكم المشرفين</h2>
            <p>كمشرف، يمكنك إدارة المستخدمين، مراجعة التقارير، وإدارة محتوى النظام. استخدم القائمة الجانبية للوصول إلى أدوات الإدارة المتاحة لك.</p>
        </div>
      
    <?php elseif ($user_type === 'admin'): ?>
        <!-- رسالة ترحيب للمديرين -->
        <div class="welcome-content">
            <div class="welcome-icon">
                <i class="fas fa-crown"></i>
            </div>
            <h2>مرحباً بك في لوحة تحكم المدير</h2>
            <p>كمدير للنظام، لديك صلاحيات كاملة لإدارة جميع جوانب النظام. يمكنك إدارة المستخدمين، عرض التقارير التفصيلية، ومراقبة أداء النظام.</p>
        </div>
      
 
      
    <?php elseif ($user_type === 'student'): ?>
        <!-- رسالة ترحيب للطلاب -->
        <div class="welcome-content">
            <div class="welcome-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <h2>مرحباً بك في لوحة تحكم الطالب</h2>
            <p>يمكنك عرض درجاتك والتقدم الدراسي من خلال القائمة الجانبية. استخدم أداة "درجاتي" لعرض جميع نتائجك وتقييماتك.</p>
        </div>
      
    <?php else: ?>
        <!-- رسالة افتراضية -->
        <div class="welcome-content">
            <div class="welcome-icon">
                <i class="fas fa-user"></i>
            </div>
            <h2>مرحباً بك في نظامنا التعليمي</h2>
            <p>استخدم القائمة الجانبية للوصول إلى الخدمات المتاحة لك حسب صلاحياتك.</p>
        </div>
    <?php endif; ?>
  
    <!-- تذييل الصفحة -->
    <div class="footer">
        <p>© 2023 النظام التعليمي | نوع حسابك:
            <strong><?php
                $type_names = [
                    'admin' => 'مدير النظام',
                    'teacher' => 'معلم',
                    'student' => 'طالب',
                    'moderator' => 'مشرف'
                ];
                echo $type_names[$user_type] ?? $user_type;
            ?></strong>
        </p>
    </div>
</main>

<script>
    // التحكم في القائمة الجانبية للشاشات الصغيرة
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
  
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        menuToggle.innerHTML = sidebar.classList.contains('active') ?
            '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
    });
  
    // إغلاق القائمة عند النقر خارجها
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }
    });
  
    // دالة تسجيل الخروج
    function logout() {
        if (confirm('هل تريد تسجيل الخروج من النظام؟')) {
            window.location.href = 'logout.php';
        }
    }
  
    // تحديث الوقت الحي
    function updateLiveTime() {
        const now = new Date();
        const timeElement = document.querySelector('.welcome-box p');
        if (timeElement) {
            const timeString = now.toLocaleTimeString('ar-SA');
            const dateString = now.toLocaleDateString('ar-SA');
            timeElement.innerHTML = `آخر تحديث: ${dateString} | ${timeString}`;
        }
    }
  
    setInterval(updateLiveTime, 1000);
</script>

</body>
</html>