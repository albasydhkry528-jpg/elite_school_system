<?php
session_start();
require_once 'includes/config.php';

// التحقق من وجود بيانات التسجيل
if (!isset($_SESSION['registration_data']) || $_SESSION['registration_step'] !== 'payment') {
    header('Location: register_student_parent.php');
    exit();
}

$registration = $_SESSION['registration_data'];

// خيارات الدفع
$payment_methods = [
    'credit_card' => ['name' => '💳 بطاقة ائتمان', 'icon' => 'fa-credit-card'],
    'bank_transfer' => ['name' => '🏦 تحويل بنكي', 'icon' => 'fa-university'],
    'sadad' => ['name' => '💰 سداد', 'icon' => 'fa-money-check-alt'],
    'apple_pay' => ['name' => ' Apple Pay', 'icon' => 'fa-apple'],
    'mada' => ['name' => '💳 مدى', 'icon' => 'fa-credit-card']
];

// معالجة الدفع
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = clean_input($_POST['payment_method']);
    $card_number = clean_input($_POST['card_number']);
    $card_name = clean_input($_POST['card_name']);
    $expiry_date = clean_input($_POST['expiry_date']);
    $cvv = clean_input($_POST['cvv']);
   
    // هنا يمكن إضافة منطق معالجة الدفع الحقيقي
    // في هذا المثال، سنقوم بمحاكاة الدفع الناجح
   
    // حفظ معلومات الدفع
    $_SESSION['payment_info'] = [
        'method' => $payment_method,
        'amount' => $registration['registration_fee'],
        'transaction_id' => 'TXN' . date('YmdHis') . rand(1000, 9999),
        'payment_date' => date('Y-m-d H:i:s'),
        'status' => 'completed'
    ];
   
    // تحديث حالة التسجيل
    $_SESSION['registration_step'] = 'completed';
   
    // توجيه لصفحة التأكيد
    header('Location: registration_confirmation.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دفع رسوم التسجيل - مدرسة النخبة الدولية</title>
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
            color: var(--dark);
        }
       
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }
       
        .payment-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        }
       
        .payment-header {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            text-align: center;
        }
       
        .payment-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
       
        .payment-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
       
        @media (max-width: 768px) {
            .payment-content {
                grid-template-columns: 1fr;
            }
        }
       
        .order-summary {
            background: #f8f9fa;
            padding: 40px;
            border-left: 1px solid #e2e8f0;
        }
       
        .payment-form {
            padding: 40px;
        }
       
        .section-title {
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent);
        }
       
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
       
        .total-price {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--accent);
            margin-top: 20px;
        }
       
        .student-info {
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
       
        .form-group {
            margin-bottom: 25px;
        }
       
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
       
        .form-control {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            background: var(--light);
        }
       
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
       
        .payment-method {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
       
        .payment-method.selected {
            border-color: var(--primary);
            background: rgba(76, 29, 149, 0.05);
        }
       
        .payment-method i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }
       
        .payment-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
       
        .security-badge {
            text-align: center;
            margin-top: 30px;
            color: #666;
        }
       
        .security-badge i {
            color: var(--accent);
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-container">
            <!-- رأس الصفحة -->
            <div class="payment-header">
                <h1><i class="fas fa-lock"></i> دفع رسوم التسجيل</h1>
                <p>الخطوة الأخيرة لإكمال تسجيل ابنك</p>
            </div>
           
            <!-- محتوى الدفع -->
            <div class="payment-content">
                <!-- نموذج الدفع -->
                <div class="payment-form">
                    <h2 class="section-title"><i class="fas fa-credit-card"></i> معلومات الدفع</h2>
                   
                    <form method="POST" action="">
                        <!-- طرق الدفع -->
                        <div class="form-group">
                            <label>طريقة الدفع</label>
                            <div class="payment-methods">
                                <?php foreach ($payment_methods as $key => $method): ?>
                                    <div class="payment-method" onclick="selectPaymentMethod('<?php echo $key; ?>')">
                                        <i class="fas <?php echo $method['icon']; ?>"></i>
                                        <div><?php echo $method['name']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="payment_method" name="payment_method" value="credit_card" required>
                        </div>
                       
                        <!-- تفاصيل البطاقة -->
                        <div class="form-group">
                            <label for="card_number"><i class="fas fa-credit-card"></i> رقم البطاقة</label>
                            <input type="text" id="card_number" name="card_number" class="form-control"
                                   placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>
                       
                        <div class="form-group">
                            <label for="card_name"><i class="fas fa-user"></i> اسم حامل البطاقة</label>
                            <input type="text" id="card_name" name="card_name" class="form-control"
                                   placeholder="الاسم كما هو مدون على البطاقة" required>
                        </div>
                       
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label for="expiry_date"><i class="fas fa-calendar"></i> تاريخ الانتهاء</label>
                                <input type="text" id="expiry_date" name="expiry_date" class="form-control"
                                       placeholder="MM/YY" maxlength="5" required>
                            </div>
                           
                            <div class="form-group">
                                <label for="cvv"><i class="fas fa-lock"></i> رمز الأمان (CVV)</label>
                                <input type="text" id="cvv" name="cvv" class="form-control"
                                       placeholder="123" maxlength="3" required>
                            </div>
                        </div>
                       
                        <button type="submit" class="payment-btn">
                            <i class="fas fa-lock"></i>
                            دفع الآن <?php echo number_format($registration['registration_fee']); ?> ريال
                        </button>
                       
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            جميع معاملات الدفع مشفرة وآمنة
                        </div>
                    </form>
                </div>
               
                <!-- ملخص الطلب -->
                <div class="order-summary">
                    <h2 class="section-title"><i class="fas fa-receipt"></i> ملخص الطلب</h2>
                   
                    <div class="summary-item">
                        <span>اسم الطالب:</span>
                        <strong><?php echo $registration['student_name']; ?></strong>
                    </div>
                   
                    <div class="summary-item">
                        <span>المستوى الدراسي:</span>
                        <strong><?php echo $registration['level']; ?></strong>
                    </div>
                   
                    <div class="summary-item">
                        <span>رسوم التسجيل:</span>
                        <strong><?php echo number_format($registration['registration_fee']); ?> ريال</strong>
                    </div>
                   
                    <div class="summary-item">
                        <span>رسوم الخدمة:</span>
                        <strong>0 ريال</strong>
                    </div>
                   
                    <div class="summary-item">
                        <span>الضريبة:</span>
                        <strong>0 ريال</strong>
                    </div>
                   
                    <div class="summary-item" style="border-bottom: none;">
                        <span>المبلغ الإجمالي:</span>
                        <strong class="total-price"><?php echo number_format($registration['registration_fee']); ?> ريال</strong>
                    </div>
                   
                    <!-- معلومات الطالب -->
                    <div class="student-info">
                        <h4><i class="fas fa-user-graduate"></i> معلومات الطالب</h4>
                        <p><strong>الاسم:</strong> <?php echo $registration['student_name']; ?></p>
                        <p><strong>تاريخ الميلاد:</strong> <?php echo $registration['birth_date']; ?></p>
                        <p><strong>الجنس:</strong> <?php echo $registration['gender']; ?></p>
                        <p><strong>ولي الأمر:</strong> <?php echo $registration['parent_name']; ?></p>
                    </div>
                   
                    <!-- شروط الخدمة -->
                    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 12px;">
                        <h4><i class="fas fa-file-contract"></i> شروط الخدمة</h4>
                        <p style="font-size: 0.9rem; color: #666; margin-top: 10px;">
                            بمجرد إتمام الدفع، ستتلقى تأكيداً عبر البريد الإلكتروني مع تفاصيل التسجيل.
                            الرسوم غير قابلة للاسترداد بعد 24 ساعة من التسجيل.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
   
    <script>
        // اختيار طريقة الدفع
        function selectPaymentMethod(method) {
            document.getElementById('payment_method').value = method;
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
       
        // تنسيق رقم البطاقة
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            e.target.value = formatted;
        });
       
        // تنسيق تاريخ الانتهاء
        document.getElementById('expiry_date').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
       
        // التأكيد قبل الدفع
        document.querySelector('form').addEventListener('submit', function(e) {
            const amount = <?php echo $registration['registration_fee']; ?>;
            const studentName = '<?php echo $registration['student_name']; ?>';
           
            if (!confirm(`هل تريد تأكيد دفع ${amount.toLocaleString()} ريال لتسجيل الطالب ${studentName}؟`)) {
                e.preventDefault();
            }
        });
       
        // اختيار طريقة الدفع الافتراضية
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.payment-method').classList.add('selected');
        });
    </script>
</body>
</html>