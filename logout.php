<?php
session_start();

// التحقق من وجود جلسة نشطة
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// احتفظ بمعلومات المستخدم قبل حذف الجلسة
$username = $_SESSION['username'] ?? 'مستخدم';
$full_name = $_SESSION['full_name'] ?? 'عزيزي المستخدم';

// تسجيل الخروج
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الخروج | النظام الإلكتروني</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --accent-color: #ff7e5f;
            --dark-color: #2d3436;
            --light-color: #f9f9f9;
            --success-color: #00b894;
            --error-color: #d63031;
            --warning-color: #fdcb6e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23ffffff" opacity="0.03" d="M0,0 L100,0 L100,100 Z" /></svg>');
            background-size: cover;
            z-index: 0;
        }

        .logout-container {
            width: 100%;
            max-width: 900px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .logout-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23ffffff" opacity="0.1" d="M0,0 L100,100 L100,0 Z" /></svg>');
            background-size: cover;
        }

        .logout-icon {
            font-size: 4.5rem;
            margin-bottom: 20px;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .logout-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .logout-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .logout-content {
            padding: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 40px;
            padding: 25px;
            background: linear-gradient(to left, #f8f9fa, #ffffff);
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-right: 5px solid var(--primary-color);
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.3);
        }

        .user-details h3 {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin-bottom: 5px;
            text-align: right;
        }

        .user-details p {
            color: #666;
            font-size: 1rem;
        }

        .logout-message {
            background: linear-gradient(to right, #f0f7ff, #e6f0ff);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 40px;
            width: 100%;
            max-width: 600px;
            border-right: 5px solid var(--secondary-color);
        }

        .logout-message h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.8rem;
        }

        .logout-message p {
            color: #555;
            line-height: 1.6;
            font-size: 1.1rem;
        }

        .logout-actions {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 16px 35px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: var(--dark-color);
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        .logout-stats {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 50px;
            width: 100%;
            max-width: 800px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            flex: 1;
            min-width: 200px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-top: 5px solid var(--primary-color);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .stat-card h4 {
            font-size: 1.2rem;
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
            font-size: 0.95rem;
        }

        .logout-footer {
            margin-top: 50px;
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            padding-top: 20px;
            border-top: 1px solid #eee;
            width: 100%;
        }

        .countdown {
            margin-top: 30px;
            font-size: 1.1rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            right: 0;
            pointer-events: none;
            z-index: -1;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(106, 17, 203, 0.1);
        }

        .shape:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 10%;
            left: 5%;
            animation: float 20s infinite linear;
        }

        .shape:nth-child(2) {
            width: 150px;
            height: 150px;
            bottom: 10%;
            right: 5%;
            animation: float 25s infinite linear reverse;
        }

        .shape:nth-child(3) {
            width: 70px;
            height: 70px;
            top: 60%;
            left: 15%;
            animation: float 18s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
            100% { transform: translateY(0) rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .logout-content {
                padding: 30px 20px;
            }
           
            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
           
            .logout-actions {
                flex-direction: column;
                width: 100%;
            }
           
            .btn {
                width: 100%;
                justify-content: center;
            }
           
            .logout-stats {
                flex-direction: column;
            }
           
            .logout-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<div class="logout-container">
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
   
    <div class="logout-header">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h1>تم تسجيل خروجك بنجاح</h1>
        <p>نأمل أن تكون قد استمتعت بتجربتك في نظامنا الإلكتروني</p>
    </div>
   
    <div class="logout-content">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($full_name); ?></h3>
                <p>اسم المستخدم: <?php echo htmlspecialchars($username); ?></p>
            </div>
        </div>
       
        <div class="logout-message">
            <h2><i class="fas fa-check-circle"></i> تم تسجيل الخروج بنجاح</h2>
            <p>لقد تم تسجيل خروجك من النظام بأمان. يمكنك الآن إغلاق النافذة بأمان أو العودة إلى صفحة تسجيل الدخول للدخول مرة أخرى. نأمل أن نراك قريباً!</p>
        </div>
       
        <div class="logout-actions">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                تسجيل الدخول مرة أخرى
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                العودة للصفحة الرئيسية
            </a>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times-circle"></i>
                إغلاق النافذة
            </button>
        </div>
       
        <div class="logout-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>حماية البيانات</h4>
                <p>تم حماية بيانات جلستك وإزالتها بأمان من النظام</p>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h4>أمان مستمر</h4>
                <p>لضمان أمان حسابك، يوصى بتسجيل الخروج بعد كل استخدام</p>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-redo"></i>
                </div>
                <h4>عودة سريعة</h4>
                <p>يمكنك العودة إلى النظام في أي وقت بتسجيل الدخول مجدداً</p>
            </div>
        </div>
       
        <div class="countdown" id="countdown">
            سيتم توجيهك تلقائياً إلى صفحة تسجيل الدخول خلال <span id="seconds">10</span> ثانية
        </div>
       
        <div class="logout-footer">
            <p>© 2023 جميع الحقوق محفوظة | النظام الإلكتروني</p>
            <p>تم آخر تحديث للجلسة: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</div>

<script>
    // عد تنازلي للتوجيه التلقائي
    let seconds = 10;
    const countdownElement = document.getElementById('seconds');
    const countdownInterval = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;
       
        if (seconds <= 0) {
            clearInterval(countdownInterval);
            window.location.href = 'login.php';
        }
    }, 1000);

    // إيقاف العد التنازلي إذا تفاعل المستخدم
    document.addEventListener('click', () => {
        clearInterval(countdownInterval);
        document.getElementById('countdown').style.opacity = '0.5';
        document.getElementById('countdown').innerHTML = '<i class="fas fa-pause"></i> تم إيقاف التوجيه التلقائي';
    });

    // تأثيرات عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', () => {
        // تأثيرات للبطاقات
        const cards = document.querySelectorAll('.stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
           
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 300 + (index * 200));
        });
       
        // تأثيرات للأزرار
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach((btn, index) => {
            btn.style.opacity = '0';
            btn.style.transform = 'scale(0.9)';
           
            setTimeout(() => {
                btn.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                btn.style.opacity = '1';
                btn.style.transform = 'scale(1)';
            }, 500 + (index * 100));
        });
    });
</script>

</body>
</html>