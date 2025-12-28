<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// التحقق من أن المستخدم مدير
if (!is_logged_in() || $_SESSION['user_type'] !== 'admin') {
    redirect('login.php', 'غير مصرح لك بالوصول إلى هذه الصفحة', 'error');
}

// جلب المستويات الدراسية مع الرسوم
$levels = [
    'الأول ابتدائي' => 2000,
    'الثاني ابتدائي' => 2000,
    'الثالث ابتدائي' => 2000,
    'الرابع ابتدائي' => 2500,
    'الخامس ابتدائي' => 2500,
    'السادس ابتدائي' => 2500,
    'الأول متوسط' => 3000,
    'الثاني متوسط' => 3000,
    'الثالث متوسط' => 3000,
    'الأول ثانوي' => 3500,
    'الثاني ثانوي' => 3500,
    'الثالث ثانوي' => 3500
];

// المواد الدراسية حسب المستوى
$subjects_by_level = [
    'الأول ابتدائي' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'التربية الفنية'],
    'الأول ثانوي' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'اللغة الإنجليزية', 'الرياضيات', 'الفيزياء', 'الكيمياء', 'الأحياء']
];

// معالجة نموذج التسجيل
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تنظيف البيانات
    $full_name = clean_input($_POST['full_name']);
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $birth_date = clean_input($_POST['birth_date']);
    $gender = clean_input($_POST['gender']);
    $nationality = clean_input($_POST['nationality']);
    $level = clean_input($_POST['level']);
    $parent_name = clean_input($_POST['parent_name']);
    $parent_phone = clean_input($_POST['parent_phone']);
    $parent_email = clean_input($_POST['parent_email']);
    $address = clean_input($_POST['address']);
    $medical_conditions = clean_input($_POST['medical_conditions']);
    $registration_fee = $levels[$level];

    // التحقق من البيانات
    if (empty($full_name)) $errors[] = 'الاسم الكامل مطلوب';
    if (empty($username)) $errors[] = 'اسم المستخدم مطلوب';
    if (empty($password)) $errors[] = 'كلمة المرور مطلوبة';
    if (!validate_email($email)) $errors[] = 'البريد الإلكتروني غير صالح';
    if (empty($birth_date)) $errors[] = 'تاريخ الميلاد مطلوب';
    if (empty($level)) $errors[] = 'المستوى الدراسي مطلوب';

    if (empty($errors)) {
        // بدء معاملة قاعدة البيانات
        $conn->begin_transaction();
       
        try {
            // 1. إنشاء حساب المستخدم للطالب
            $user_sql = "INSERT INTO users (username, password, email, user_type, full_name, phone, address, created_at)
                         VALUES (?, ?, ?, 'student', ?, ?, ?, NOW())";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("ssssss", $username, $password, $email, $full_name, $phone, $address);
            $user_stmt->execute();
            $user_id = $conn->insert_id;
           
            // 2. إنشاء سجل الطالب
            $student_code = 'STU' . date('Ym') . str_pad($user_id, 4, '0', STR_PAD_LEFT);
            $student_sql = "INSERT INTO students (user_id, student_code, birth_date, gender, nationality, enrollment_date, medical_conditions)
                            VALUES (?, ?, ?, ?, ?, CURDATE(), ?)";
            $student_stmt = $conn->prepare($student_sql);
            $student_stmt->bind_param("isssss", $user_id, $student_code, $birth_date, $gender, $nationality, $medical_conditions);
            $student_stmt->execute();
            $student_id = $conn->insert_id;
           
            // 3. إنشاء حساب ولي الأمر إذا لم يكن موجوداً
            $parent_user_sql = "SELECT id FROM users WHERE email = ?";
            $parent_stmt = $conn->prepare($parent_user_sql);
            $parent_stmt->bind_param("s", $parent_email);
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();
           
            $parent_user_id = null;
            if ($parent_result->num_rows > 0) {
                $parent_user = $parent_result->fetch_assoc();
                $parent_user_id = $parent_user['id'];
            } else {
                // إنشاء حساب جديد لولي الأمر
                $parent_username = 'parent_' . time();
                $parent_password = 'parent123'; // يمكن تغيير هذا
                $parent_user_sql = "INSERT INTO users (username, password, email, user_type, full_name, phone)
                                    VALUES (?, ?, ?, 'parent', ?, ?)";
                $parent_user_stmt = $conn->prepare($parent_user_sql);
                $parent_user_stmt->bind_param("sssss", $parent_username, $parent_password, $parent_email, $parent_name, $parent_phone);
                $parent_user_stmt->execute();
                $parent_user_id = $conn->insert_id;
               
                // إنشاء سجل ولي الأمر
                $parent_code = 'PAR' . str_pad($parent_user_id, 4, '0', STR_PAD_LEFT);
                $parent_sql = "INSERT INTO parents (user_id, parent_code, occupation)
                               VALUES (?, ?, 'ولي أمر طالب')";
                $parent_stmt2 = $conn->prepare($parent_sql);
                $parent_stmt2->bind_param("is", $parent_user_id, $parent_code);
                $parent_stmt2->execute();
            }
           
            // 4. تحديث سجل الطالب برابط ولي الأمر
            if ($parent_user_id) {
                $update_student_sql = "UPDATE students SET parent_id = (SELECT id FROM parents WHERE user_id = ?) WHERE id = ?";
                $update_stmt = $conn->prepare($update_student_sql);
                $update_stmt->bind_param("ii", $parent_user_id, $student_id);
                $update_stmt->execute();
            }
           
            // 5. تسجيل رسوم التسجيل
            $payment_sql = "INSERT INTO student_payments (student_id, payment_type, amount, academic_year, status, created_by)
                            VALUES (?, 'رسوم تسجيل', ?, YEAR(CURDATE()), 'مدفوعة', ?)";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param("idi", $student_id, $registration_fee, $_SESSION['user_id']);
            $payment_stmt->execute();
           
            // تأكيد المعاملة
            $conn->commit();
           
            $success = true;
            log_activity('تسجيل طالب', "تم تسجيل طالب جديد: $full_name ($student_code)");
           
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'حدث خطأ في عملية التسجيل: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل طالب جديد - لوحة المدير</title>
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
       
        .admin-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
       
        .header-content h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
       
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
       
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
       
        .registration-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 40px;
        }
       
        .form-header {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            padding: 25px 40px;
        }
       
        .form-header h2 {
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
       
        .form-content {
            padding: 40px;
        }
       
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
       
        .form-section {
            margin-bottom: 40px;
        }
       
        .section-title {
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--accent);
            display: flex;
            align-items: center;
            gap: 15px;
        }
       
        .form-group {
            margin-bottom: 25px;
        }
       
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
       
        .form-control {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light);
        }
       
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76, 29, 149, 0.1);
            outline: none;
        }
       
        .select-wrapper {
            position: relative;
        }
       
        .select-wrapper select {
            appearance: none;
            padding-right: 50px;
        }
       
        .select-arrow {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            pointer-events: none;
        }
       
        .radio-group {
            display: flex;
            gap: 30px;
            margin-top: 10px;
        }
       
        .radio-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
       
        .radio-label input[type="radio"] {
            width: 20px;
            height: 20px;
        }
       
        .price-badge {
            background: linear-gradient(to right, var(--accent), #34d399);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
       
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
       
        .alert-success {
            background: linear-gradient(to right, #d1fae5, #a7f3d0);
            color: #065f46;
            border-right: 5px solid var(--accent);
        }
       
        .alert-danger {
            background: linear-gradient(to right, #fee2e2, #fecaca);
            color: #991b1b;
            border-right: 5px solid #ef4444;
        }
       
        .alert ul {
            margin: 15px 0 0 25px;
        }
       
        .submit-btn {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 18px 45px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 40px auto 0;
        }
       
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(76, 29, 149, 0.3);
        }
       
        .levels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
       
        .level-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
       
        .level-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
       
        .level-card.selected {
            border-color: var(--primary);
            background: linear-gradient(to right, rgba(76, 29, 149, 0.05), rgba(126, 34, 206, 0.05));
        }
       
        .level-card h4 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
       
        .level-price {
            color: var(--accent);
            font-weight: 700;
            font-size: 1.3rem;
            margin-top: 10px;
        }
       
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
           
            .form-content {
                padding: 25px;
            }
           
            .form-grid {
                grid-template-columns: 1fr;
            }
           
            .radio-group {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- رأس الصفحة -->
    <header class="admin-header">
        <div class="header-content">
            <h1><i class="fas fa-user-graduate"></i> تسجيل طالب جديد</h1>
            <p>لوحة تحكم المدير | نظام إدارة المدرسة</p>
        </div>
        <a href="users.php" class="back-btn">
            <i class="fas fa-arrow-right"></i>
            العودة للوحة التحكم
        </a>
    </header>
   
    <!-- المحتوى الرئيسي -->
    <div class="container">
        <div class="registration-container">
            <!-- رسائل التنبيه -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>تم بنجاح!</strong> تم تسجيل الطالب بنجاح وتم إنشاء الحسابات اللازمة.
                </div>
            <?php endif; ?>
           
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>حدثت الأخطاء التالية:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
           
            <!-- رأس النموذج -->
            <div class="form-header">
                <h2>
                    <i class="fas fa-user-plus"></i>
                    نموذج تسجيل طالب جديد
                </h2>
            </div>
           
            <!-- النموذج -->
            <form method="POST" action="" class="form-content">
                <!-- معلومات الطالب -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>
                        المعلومات الشخصية للطالب
                    </h3>
                   
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name"><i class="fas fa-signature"></i> الاسم الكامل *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required
                                   placeholder="أدخل الاسم الكامل للطالب">
                        </div>
                       
                        <div class="form-group">
                            <label for="username"><i class="fas fa-user-circle"></i> اسم المستخدم *</label>
                            <input type="text" id="username" name="username" class="form-control" required
                                   placeholder="سيتم استخدامه لتسجيل الدخول">
                        </div>
                       
                        <div class="form-group">
                            <label for="password"><i class="fas fa-key"></i> كلمة المرور *</label>
                            <input type="password" id="password" name="password" class="form-control" required
                                   placeholder="أدخل كلمة المرور">
                        </div>
                       
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> البريد الإلكتروني *</label>
                            <input type="email" id="email" name="email" class="form-control" required
                                   placeholder="example@school.com">
                        </div>
                       
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> رقم الهاتف</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   placeholder="05xxxxxxxx">
                        </div>
                       
                        <div class="form-group">
                            <label for="birth_date"><i class="fas fa-birthday-cake"></i> تاريخ الميلاد *</label>
                            <input type="date" id="birth_date" name="birth_date" class="form-control" required>
                        </div>
                       
                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> الجنس *</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="gender" value="ذكر" required>
                                    ذكر
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="gender" value="أنثى">
                                    أنثى
                                </label>
                            </div>
                        </div>
                       
                        <div class="form-group">
                            <label for="nationality"><i class="fas fa-globe"></i> الجنسية</label>
                            <input type="text" id="nationality" name="nationality" class="form-control"
                                   value="سعودي" placeholder="الجنسية">
                        </div>
                    </div>
                </div>
               
                <!-- المعلومات الدراسية -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        المعلومات الدراسية
                    </h3>
                   
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="level"><i class="fas fa-school"></i> المستوى الدراسي *</label>
                            <div class="select-wrapper">
                                <select id="level" name="level" class="form-control" required>
                                    <option value="">اختر المستوى الدراسي</option>
                                    <?php foreach ($levels as $level_name => $price): ?>
                                        <option value="<?php echo $level_name; ?>" data-price="<?php echo $price; ?>">
                                            <?php echo $level_name; ?> - <?php echo number_format($price); ?> ريال
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="select-arrow">▼</span>
                            </div>
                        </div>
                       
                        <div class="form-group">
                            <label><i class="fas fa-money-bill-wave"></i> رسوم التسجيل</label>
                            <div class="price-badge">
                                <i class="fas fa-tag"></i>
                                <span id="price-display">0 ريال</span>
                            </div>
                        </div>
                       
                        <div class="form-group">
                            <label for="medical_conditions"><i class="fas fa-heartbeat"></i> الحالة الصحية</label>
                            <textarea id="medical_conditions" name="medical_conditions" class="form-control"
                                      rows="3" placeholder="أي حالات مرضية أو حساسية..."></textarea>
                        </div>
                    </div>
                   
                    <!-- عرض المستويات -->
                    <div class="levels-preview">
                        <h4 style="margin-bottom: 20px; color: var(--dark);">المستويات المتاحة:</h4>
                        <div class="levels-grid">
                            <?php foreach ($levels as $level_name => $price): ?>
                                <div class="level-card" onclick="selectLevel('<?php echo $level_name; ?>', <?php echo $price; ?>)">
                                    <h4><?php echo $level_name; ?></h4>
                                    <p>الرسوم الدراسية:</p>
                                    <div class="level-price"><?php echo number_format($price); ?> ريال</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
               
                <!-- معلومات ولي الأمر -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i>
                        معلومات ولي الأمر
                    </h3>
                   
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="parent_name"><i class="fas fa-user-friends"></i> اسم ولي الأمر</label>
                            <input type="text" id="parent_name" name="parent_name" class="form-control"
                                   placeholder="اسم ولي الأمر الكامل">
                        </div>
                       
                        <div class="form-group">
                            <label for="parent_phone"><i class="fas fa-phone-alt"></i> هاتف ولي الأمر</label>
                            <input type="tel" id="parent_phone" name="parent_phone" class="form-control"
                                   placeholder="05xxxxxxxx">
                        </div>
                       
                        <div class="form-group">
                            <label for="parent_email"><i class="fas fa-envelope-open"></i> بريد ولي الأمر</label>
                            <input type="email" id="parent_email" name="parent_email" class="form-control"
                                   placeholder="parent@example.com">
                        </div>
                    </div>
                   
                    <div class="form-group">
                        <label for="address"><i class="fas fa-home"></i> العنوان</label>
                        <textarea id="address" name="address" class="form-control" rows="2"
                                  placeholder="العنوان الكامل"></textarea>
                    </div>
                </div>
               
                <!-- زر التسجيل -->
                <button type="submit" class="submit-btn">
                    <i class="fas fa-user-plus"></i>
                    تسجيل الطالب
                </button>
            </form>
        </div>
    </div>
   
    <script>
        // تحديث عرض السعر عند تغيير المستوى
        document.getElementById('level').addEventListener('change', function() {
            const price = this.options[this.selectedIndex].dataset.price;
            document.getElementById('price-display').textContent = Number(price).toLocaleString() + ' ريال';
        });
       
        // اختيار مستوى من البطاقات
        function selectLevel(levelName, price) {
            document.getElementById('level').value = levelName;
            document.getElementById('price-display').textContent = price.toLocaleString() + ' ريال';
           
            // إضافة تأثير التحديد
            document.querySelectorAll('.level-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
       
        // تأكيد قبل الإرسال
        document.querySelector('form').addEventListener('submit', function(e) {
            const level = document.getElementById('level').value;
            const price = document.getElementById('price-display').textContent;
           
            if (!confirm(`هل تريد تأكيد تسجيل الطالب في ${level} برسوم ${price}؟`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>

