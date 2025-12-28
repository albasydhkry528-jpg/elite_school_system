<?php
session_start();
require_once "includes/config.php";

$error = "";



// عند إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "⚠️ الرجاء إدخال البريد الإلكتروني وكلمة المرور";
    } else {

        $stmt = $conn->prepare("
            SELECT id, username, password, user_type, status, full_name
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // كلمة المرور (غير مشفرة حسب طلبك)
            if ($password === $user['password']) {

                if ($user['status'] !== 'active') {
                    $error = "❌ هذا الحساب غير مفعل";
                } else {
                    // إنشاء الجلسة
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['full_name'] = $user['full_name'];

                    // تحديث آخر تسجيل دخول
                    $conn->query("
                        UPDATE users
                        SET last_login = NOW()
                        WHERE id = {$user['id']}
                    ");

                    header("Location: dashboard.php");
                    exit;
                }

            } else {
                $error = "❌ كلمة المرور غير صحيحة";
            }

        } else {
            $error = "❌ البريد الإلكتروني غير موجود";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | النظام الإلكتروني</title>
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

        .container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: row-reverse;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            border-radius: 24px;
            overflow: hidden;
            position: relative;
            z-index: 1;
            min-height: 700px;
        }

        /* الجزء الأيمن (التسجيل) */
        .login-section {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(106, 17, 203, 0.05) 0%, transparent 70%);
            z-index: -1;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
        }

        .logo i {
            font-size: 2.5rem;
            color: var(--primary-color);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo-text h1 {
            font-size: 1.8rem;
            color: var(--dark-color);
            font-weight: 700;
        }

        .logo-text p {
            font-size: 0.9rem;
            color: #777;
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 2.2rem;
            color: var(--dark-color);
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .login-header h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            right: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(to left, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        .login-header p {
            color: #666;
            font-size: 1rem;
            margin-top: 20px;
        }

        .login-form {
            margin-top: 20px;
        }

        .input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .input-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .input-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
            outline: none;
            background-color: #fff;
        }

        .password-toggle {
            position: absolute;
            left: 15px;
            top: 40px;
            cursor: pointer;
            color: #999;
        }

        .login-btn {
            width: 100%;
            padding: 17px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(106, 17, 203, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        .error-message {
            background: linear-gradient(to right, #ffeded, #ffe6e6);
            color: var(--error-color);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-right: 4px solid var(--error-color);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        /* الجزء الأيسر (العرض التقديمي) */
        .welcome-section {
            flex: 1;
            background: linear-gradient(135deg, rgba(106, 17, 203, 0.9), rgba(37, 117, 252, 0.9));
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23ffffff" opacity="0.05" d="M0,100 L100,0 L100,100 Z" /></svg>');
            background-size: cover;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-content h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .welcome-content p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .features {
            list-style: none;
            margin-top: 40px;
        }

        .features li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .features i {
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            right: 0;
            overflow: hidden;
            z-index: 0;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation: float 20s infinite linear;
        }

        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            bottom: 15%;
            right: 10%;
            animation: float 25s infinite linear reverse;
        }

        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 60%;
            left: 20%;
            animation: float 18s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
            100% { transform: translateY(0) rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
                max-width: 500px;
            }
           
            .welcome-section {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .login-section {
                padding: 40px 25px;
            }
           
            .login-header h2 {
                font-size: 1.8rem;
            }
        }

        /* تأثيرات إضافية */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(106, 17, 203, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(106, 17, 203, 0); }
            100% { box-shadow: 0 0 0 0 rgba(106, 17, 203, 0); }
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #888;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- الجزء الأيمن: نموذج تسجيل الدخول -->
    <div class="login-section">
        <div class="logo">
            <i class="fas fa-lock"></i>
            <div class="logo-text">
                <h1>النظام الإلكتروني</h1>
                <p>منصة إدارة متكاملة</p>
            </div>
        </div>

        <div class="login-header">
            <h2>تسجيل الدخول</h2>
            <p>مرحباً بعودتك! الرجاء إدخال بيانات حسابك للوصول إلى النظام.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <div class="input-group">
                <label for="email"><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                </div>
                <input type="email" name="email" id="email" placeholder="example@domain.com" required>
            </div>

            <div class="input-group">
                <label for="password"><i class="fas fa-key"></i> كلمة المرور</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <input type="password" name="password" id="password" placeholder="أدخل كلمة المرور" required>
                <div class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye"></i>
                </div>
            </div>

            <button type="submit" class="login-btn pulse">
                <i class="fas fa-sign-in-alt"></i>
                <span>دخول إلى النظام</span>
            </button>

            <div class="forgot-password">
                <a href="#"><i class="fas fa-question-circle"></i> نسيت كلمة المرور؟</a>
            </div>
        </form>

        <div class="footer-text">
            <p>© 2023 جميع الحقوق محفوظة | النظام الإلكتروني</p>
        </div>
    </div>

    <!-- الجزء الأيسر: العرض التقديمي -->
    <div class="welcome-section">
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>

        <div class="welcome-content">
            <h1>مرحباً بك في نظامنا الإلكتروني</h1>
            <p>نظام إلكتروني متكامل يقدم حلولاً ذكية لإدارة عملك بكفاءة وأمان عاليين. تمتع بتجربة مستخدم فريدة مع واجهة حديثة وسهلة الاستخدام.</p>
           
            <ul class="features">
                <li>
                    <i class="fas fa-shield-alt"></i>
                    <span>أمان عالي وحماية للبيانات</span>
                </li>
                <li>
                    <i class="fas fa-bolt"></i>
                    <span>أداء سريع واستجابة فائقة</span>
                </li>
                <li>
                    <i class="fas fa-mobile-alt"></i>
                    <span>واجهة متجاوبة تعمل على جميع الأجهزة</span>
                </li>
                <li>
                    <i class="fas fa-headset"></i>
                    <span>دعم فني متواصل على مدار الساعة</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<script>
    // تبديل رؤية كلمة المرور
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.password-toggle i');
       
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleIcon.className = 'fas fa-eye';
        }
    }

    // تأثيرات عند التركيز على الحقول
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
       
        input.addEventListener('blur', function() {
            if (this.value === '') {
                this.parentElement.classList.remove('focused');
            }
        });
    });

    // تأثير عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.container').style.opacity = '0';
        document.querySelector('.container').style.transform = 'translateY(20px)';
       
        setTimeout(() => {
            document.querySelector('.container').style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            document.querySelector('.container').style.opacity = '1';
            document.querySelector('.container').style.transform = 'translateY(0)';
        }, 100);
    });
</script>

</body>
</html>