<?php
session_start();
require_once 'includes/config.php';

// التحقق من اكتمال التسجيل
if (!isset($_SESSION['registration_data']) || !isset($_SESSION['payment_info'])) {
    header('Location: register_student_parent.php');
    exit();
}

$registration = $_SESSION['registration_data'];
$payment = $_SESSION['payment_info'];

// توليد رقم تسجيل
$registration_number = 'REG' . date('Ymd') . rand(1000, 9999);

// إرسال بريد إلكتروني تأكيدي (يمكن تفعيله لاحقاً)
$to = $registration['parent_email'];
$subject = "تأكيد تسجيل ابنك في مدرسة النخبة الدولية";
$message = "
مرحباً {$registration['parent_name']},

تم تسجيل ابنك {$registration['student_name']} في مدرسة النخبة الدولية بنجاح!

تفاصيل التسجيل:
- رقم التسجيل: {$registration_number}
- المستوى الدراسي: {$registration['level']}
- رسوم التسجيل: {$registration['registration_fee']} ريال
- رقم المعاملة: {$payment['transaction_id']}

سيتصل بكم منسق القبول خلال 48 ساعة عمل.

شكراً لثقتكم بمدرسة النخبة الدولية.

مع تحيات،
إدارة مدرسة النخبة الدولية
";

// هنا يمكن إضافة كود إرسال البريد الإلكتروني فعلياً

// مسح بيانات الجلسة بعد العرض
$show_data = $registration;
$show_payment = $payment;
$show_registration_number = $registration_number;

// unset($_SESSION['registration_data']);
// unset($_SESSION['payment_info']);
// unset($_SESSION['registration_step']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم التسجيل بنجاح - مدرسة النخبة الدولية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4c1d95;
            --secondary: #7e22ce;
            --accent: #10b981;
            --light: #f8fafc;
            --dark: #1e293b;
        }
       
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
       
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f7ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
       
        .confirmation-container {
            background: white;
            border-radius: 25px;
            max-width: 900px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
        }
       
        .confirmation-header {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
       
        .confirmation-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23ffffff" opacity="0.1" d="M0,0 L100,100 L100,0 Z" /></svg>');
            background-size: cover;
        }
       
        .success-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: bounce 1s infinite alternate;
        }
       
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-15px); }
        }
       
        .confirmation-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
       
        .confirmation-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
       
        .confirmation-content {
            padding: 40px;
        }
       
        .registration-card {
            background: linear-gradient(to right, #f0f9ff, #e6f7ff);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            border-right: 5px solid var(--accent);
        }
       
        .registration-number {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
       
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
       
        .info-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
       
        .info-section h3 {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
       
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
       
        .info-item:last-child {
            border-bottom: none;
        }
       
        .actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
        }
       
        .btn {
            padding: 16px 35px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }
       
        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            border: none;
        }
       
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(76, 29, 149, 0.3);
        }
       
        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 2px solid #e2e8f0;
        }
       
        .btn-secondary:hover {
            background: #f8f9fa;
            transform: translateY(-3px);
        }
       
        .confirmation-footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
            color: #666;
        }
       
        @media (max-width: 768px) {
            .confirmation-content {
                padding: 25px;
            }
           
            .info-grid {
                grid-template-columns: 1fr;
            }
           
            .actions {
                flex-direction: column;
            }
           
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <!-- رأس التأكيد -->
        <div class="confirmation-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>تم التسجيل بنجاح!</h1>
            <p>تم تسجيل ابنك في مدرسة النخبة الدولية بنجاح</p>
        </div>
       
        <!-- محتوى التأكيد -->
        <div class="confirmation-content">
            <!-- بطاقة التسجيل -->
            <div class="registration-card">
                <div class="registration-number">
                    <i class="fas fa-hashtag"></i>
                    رقم التسجيل: <strong><?php echo $show_registration_number; ?></strong>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    تهانينا! تم إكمال عملية تسجيل ابنك بنجاح. لقد تم إرسال تفاصيل التسجيل إلى بريدك الإلكتروني.
                    سيتصل بكم منسق القبول خلال 48 ساعة عمل لإكمال الإجراءات المتبقية.
                </p>
            </div>
           
            <!-- شبكة المعلومات -->
            <div class="info-grid">
                <!-- معلومات الطالب -->
                <div class="info-section">
                    <h3><i class="fas fa-user-graduate"></i> معلومات الطالب</h3>
                    <div class="info-item">
                        <span>الاسم الكامل:</span>
                        <strong><?php echo $show_data['student_name']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>تاريخ الميلاد:</span>
                        <strong><?php echo $show_data['birth_date']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>الجنس:</span>
                        <strong><?php echo $show_data['gender']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>المستوى الدراسي:</span>
                        <strong><?php echo $show_data['level']; ?></strong>
                    </div>
                </div>
               
                <!-- معلومات الدفع -->
                <div class="info-section">
                    <h3><i class="fas fa-receipt"></i> معلومات الدفع</h3>
                    <div class="info-item">
                        <span>رقم المعاملة:</span>
                        <strong><?php echo $show_payment['transaction_id']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>طريقة الدفع:</span>
                        <strong><?php echo $show_payment['method']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>المبلغ المدفوع:</span>
                        <strong><?php echo number_format($show_data['registration_fee']); ?> ريال</strong>
                    </div>
                    <div class="info-item">
                        <span>تاريخ الدفع:</span>
                        <strong><?php echo $show_payment['payment_date']; ?></strong>
                    </div>
                </div>
               
                <!-- معلومات ولي الأمر -->
                <div class="info-section">
                    <h3><i class="fas fa-user-friends"></i> معلومات ولي الأمر</h3>
                    <div class="info-item">
                        <span>اسم ولي الأمر:</span>
                        <strong><?php echo $show_data['parent_name']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>البريد الإلكتروني:</span>
                        <strong><?php echo $show_data['parent_email']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>رقم الهاتف:</span>
                        <strong><?php echo $show_data['parent_phone']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>تاريخ التسجيل:</span>
                        <strong><?php echo $show_data['registration_date']; ?></strong>
                    </div>
                </div>
               
                <!-- المواد الدراسية -->
                <div class="info-section">
                    <h3><i class="fas fa-book"></i> المواد الدراسية</h3>
                    <?php foreach ($show_data['subjects'] as $subject): ?>
                        <div class="info-item">
                            <span><?php echo $subject; ?></span>
                            <i class="fas fa-check" style="color: var(--accent);"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
           
            <!-- أزرار الإجراءات -->
            <div class="actions">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i>
                    طباعة التأكيد
                </button>
               
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    العودة للرئيسية
                </a>
               
                <a href="#" class="btn btn-secondary" onclick="sendEmail()">
                    <i class="fas fa-envelope"></i>
                    إرسال إلى بريدي
                </a>
            </div>
           
            <!-- تذييل الصفحة -->
            <div class="confirmation-footer">
                <p><i class="fas fa-info-circle"></i> للحصول على المساعدة، يرجى الاتصال بقسم القبول على الرقم: ٠١٢٣٤٥٦٧٨٩</p>
                <p style="margin-top: 15px; font-size: 0.9rem;">
                    <i class="fas fa-shield-alt"></i> جميع المعلومات محمية وفق سياسة الخصوصية
                </p>
            </div>
        </div>
    </div>
   
    <script>
        // طباعة الصفحة
        function printConfirmation() {
            window.print();
        }
       
        // محاكاة إرسال البريد الإلكتروني
        function sendEmail() {
            alert('تم إرسال تأكيد التسجيل إلى بريدك الإلكتروني');
        }
       
        // نسخ رقم التسجيل
        function copyRegistrationNumber() {
            const regNumber = document.querySelector('.registration-number strong').textContent;
            navigator.clipboard.writeText(regNumber).then(() => {
                alert('تم نسخ رقم التسجيل: ' + regNumber);
            });
        }
       
        // تأثيرات عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            // تأثيرات للبطاقات
            const cards = document.querySelectorAll('.info-section');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
               
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 300 + (index * 200));
            });
        });
    </script>
</body>
</html>