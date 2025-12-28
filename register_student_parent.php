<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// المستويات الدراسية مع الرسوم والتفاصيل
$levels = [
    'الأول ابتدائي' => [
        'price' => 2000,
        'age_range' => '6-7 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'التربية الفنية', 'التربية البدنية'],
        'description' => 'مرحلة تأسيسية تركز على المهارات الأساسية والقراءة والكتابة'
    ],
    'الثاني ابتدائي' => [
        'price' => 2000,
        'age_range' => '7-8 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'التربية الفنية'],
        'description' => 'بناء على المهارات الأساسية مع إدخال مفاهيم جديدة'
    ],
    'الثالث ابتدائي' => [
        'price' => 2000,
        'age_range' => '8-9 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات'],
        'description' => 'تطوير المهارات الأساسية وإدخال مواد جديدة'
    ],
    'الرابع ابتدائي' => [
        'price' => 2500,
        'age_range' => '9-10 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية'],
        'description' => 'بداية المرحلة المتوسطة مع زيادة المواد الدراسية'
    ],
    'الخامس ابتدائي' => [
        'price' => 2500,
        'age_range' => '10-11 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي'],
        'description' => 'تطوير المهارات في جميع المواد الدراسية'
    ],
    'السادس ابتدائي' => [
        'price' => 2500,
        'age_range' => '11-12 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'المهارات الحياتية'],
        'description' => 'التحضير للمرحلة المتوسطة'
    ],
    'الأول متوسط' => [
        'price' => 3000,
        'age_range' => '12-13 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'التربية الفنية'],
        'description' => 'بداية المرحلة المتوسطة مع زيادة التحديات الدراسية'
    ],
    'الثاني متوسط' => [
        'price' => 3000,
        'age_range' => '13-14 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'التربية البدنية'],
        'description' => 'تطوير المهارات العلمية والأدبية'
    ],
    'الثالث متوسط' => [
        'price' => 3000,
        'age_range' => '14-15 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'اللغة الإنجليزية', 'الحاسب الآلي', 'المهارات الحياتية'],
        'description' => 'التحضير للمرحلة الثانوية'
    ],
    'الأول ثانوي' => [
        'price' => 3500,
        'age_range' => '15-16 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'الفيزياء', 'الكيمياء', 'الأحياء', 'اللغة الإنجليزية', 'الحاسب الآلي'],
        'description' => 'المرحلة الثانوية - مسار علمي'
    ],
    'الثاني ثانوي' => [
        'price' => 3500,
        'age_range' => '16-17 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'الفيزياء', 'الكيمياء', 'الأحياء', 'اللغة الإنجليزية', 'الحاسب الآلي'],
        'description' => 'تخصص في المواد العلمية'
    ],
    'الثالث ثانوي' => [
        'price' => 3500,
        'age_range' => '17-18 سنوات',
        'subjects' => ['القرآن الكريم', 'التربية الإسلامية', 'اللغة العربية', 'الرياضيات', 'الفيزياء', 'الكيمياء', 'الأحياء', 'اللغة الإنجليزية', 'الحاسب الآلي'],
        'description' => 'سنة التخرج والتحضير للجامعة'
    ]
];

// معالجة النموذج
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جمع البيانات
    $student_name = clean_input($_POST['student_name']);
    $birth_date = clean_input($_POST['birth_date']);
    $gender = clean_input($_POST['gender']);
    $level = clean_input($_POST['level']);
    $parent_name = clean_input($_POST['parent_name']);
    $parent_email = clean_input($_POST['parent_email']);
    $parent_phone = clean_input($_POST['parent_phone']);
    $address = clean_input($_POST['address']);
    $medical_notes = clean_input($_POST['medical_notes']);
   
    // التحقق من البيانات
    if (empty($student_name)) $errors[] = 'اسم الطالب مطلوب';
    if (empty($birth_date)) $errors[] = 'تاريخ الميلاد مطلوب';
    if (empty($parent_name)) $errors[] = 'اسم ولي الأمر مطلوب';
    if (empty($parent_email) || !validate_email($parent_email)) $errors[] = 'البريد الإلكتروني غير صالح';
    if (empty($parent_phone)) $errors[] = 'رقم الهاتف مطلوب';
    if (empty($level)) $errors[] = 'يرجى اختيار المستوى الدراسي';
   
    if (empty($errors)) {
        // هنا يمكن إضافة منطق الدفع والتسجيل الفعلي
        // في هذا المثال، سنقوم بحفظ الطلب في جلسة مؤقتة
       
        $registration_data = [
            'student_name' => $student_name,
            'birth_date' => $birth_date,
            'gender' => $gender,
            'level' => $level,
            'parent_name' => $parent_name,
            'parent_email' => $parent_email,
            'parent_phone' => $parent_phone,
            'address' => $address,
            'medical_notes' => $medical_notes,
            'registration_fee' => $levels[$level]['price'],
            'subjects' => $levels[$level]['subjects'],
            'registration_date' => date('Y-m-d H:i:s')
        ];
       
        $_SESSION['registration_data'] = $registration_data;
        $_SESSION['registration_step'] = 'payment';
       
        // توجيه لصفحة الدفع
        header('Location: payment.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل ابنك الآن - مدرسة النخبة الدولية</title>
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
       
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
       
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23ffffff" opacity="0.05" d="M0,0 L100,100 L100,0 Z" /></svg>');
            background-size: cover;
        }
       
        .header-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
       
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
       
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto;
        }
       
        .back-home {
            position: absolute;
            left: 20px;
            top: 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 8px;
        }
       
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }
       
        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
       
        /* قسم المستويات */
        .levels-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
       
        .section-title {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--accent);
            display: flex;
            align-items: center;
            gap: 15px;
        }
       
        .levels-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
       
        .tab-btn {
            padding: 12px 25px;
            background: var(--light);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
       
        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
       
        .tab-btn:hover:not(.active) {
            border-color: var(--primary);
        }
       
        .levels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
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
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
       
        .level-card.selected {
            border-color: var(--primary);
            background: linear-gradient(to right, rgba(76, 29, 149, 0.05), rgba(126, 34, 206, 0.05));
        }
       
        .level-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
       
        .level-price {
            color: var(--accent);
            font-weight: 700;
            font-size: 1.4rem;
            margin: 15px 0;
        }
       
        .level-features {
            list-style: none;
            margin-top: 15px;
        }
       
        .level-features li {
            padding: 5px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }
       
        .level-features li i {
            color: var(--accent);
        }
       
        /* قسم التسجيل */
        .registration-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: sticky;
            top: 30px;
        }
       
        .form-group {
            margin-bottom: 25px;
        }
       
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
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
       
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
       
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(76, 29, 149, 0.3);
        }
       
        .summary-box {
            background: linear-gradient(to right, #f0f9ff, #e6f7ff);
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            border-right: 5px solid var(--accent);
        }
       
        .summary-box h4 {
            color: var(--primary);
            margin-bottom: 15px;
        }
       
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
       
        .total-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--accent);
            margin-top: 10px;
        }
       
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
       
        .alert-danger {
            background: linear-gradient(to right, #fee2e2, #fecaca);
            color: #991b1b;
            border-right: 5px solid #ef4444;
        }
       
        .alert ul {
            margin: 15px 0 0 25px;
        }
       
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
           
            .levels-grid {
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
    <header class="header">
        <a href="index.php" class="back-home">
            <i class="fas fa-arrow-right"></i>
            العودة للرئيسية
        </a>
        <div class="header-content">
            <h1><i class="fas fa-user-graduate"></i> سجل ابنك الآن في مدرسة النخبة الدولية</h1>
            <p>اختر المستوى المناسب لابنك وابدأ رحلة التعليم المتميز مع نخبة من المعلمين وأحدث الوسائل التعليمية</p>
        </div>
    </header>
   
    <!-- المحتوى الرئيسي -->
    <div class="container">
        <!-- قسم المستويات الدراسية -->
        <div class="levels-section">
            <h2 class="section-title">
                <i class="fas fa-layer-group"></i>
                المستويات الدراسية والرسوم
            </h2>
           
            <!-- أزرار التبويب -->
            <div class="levels-tabs">
                <button class="tab-btn active" onclick="filterLevels('الابتدائي')">المرحلة الابتدائية</button>
                <button class="tab-btn" onclick="filterLevels('المتوسط')">المرحلة المتوسطة</button>
                <button class="tab-btn" onclick="filterLevels('الثانوي')">المرحلة الثانوية</button>
                <button class="tab-btn" onclick="filterLevels('all')">جميع المستويات</button>
            </div>
           
            <!-- شبكة المستويات -->
            <div class="levels-grid" id="levelsGrid">
                <?php foreach ($levels as $level_name => $level_data): ?>
                    <div class="level-card" data-category="<?php
                        if (strpos($level_name, 'ابتدائي') !== false) echo 'الابتدائي';
                        elseif (strpos($level_name, 'متوسط') !== false) echo 'المتوسط';
                        elseif (strpos($level_name, 'ثانوي') !== false) echo 'الثانوي';
                    ?>" onclick="selectLevel('<?php echo $level_name; ?>', <?php echo $level_data['price']; ?>, <?php echo htmlspecialchars(json_encode($level_data['subjects'])); ?>)">
                        <h3><?php echo $level_name; ?></h3>
                        <div class="level-price"><?php echo number_format($level_data['price']); ?> ريال سنوياً</div>
                        <p style="color: #666; margin: 10px 0;"><?php echo $level_data['description']; ?></p>
                        <ul class="level-features">
                            <li><i class="fas fa-users"></i> <?php echo $level_data['age_range']; ?></li>
                            <li><i class="fas fa-book"></i> <?php echo count($level_data['subjects']); ?> مادة دراسية</li>
                            <li><i class="fas fa-chalkboard-teacher"></i> نخبة من المعلمين</li>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
       
        <!-- نموذج التسجيل -->
        <div class="registration-section">
            <h2 class="section-title">
                <i class="fas fa-edit"></i>
                نموذج التسجيل
            </h2>
           
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>يرجى تصحيح الأخطاء التالية:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
           
            <form method="POST" action="" id="registrationForm">
                <!-- معلومات الطالب -->
                <div class="form-group">
                    <label for="student_name"><i class="fas fa-child"></i> اسم الطالب *</label>
                    <input type="text" id="student_name" name="student_name" class="form-control" required
                           placeholder="الاسم الكامل للطالب">
                </div>
               
                <div class="form-group">
                    <label for="birth_date"><i class="fas fa-calendar"></i> تاريخ الميلاد *</label>
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
               
       <!-- معلومات ولي الأمر -->
                <div class="form-group">
                    <label for="parent_name"><i class="fas fa-user-friends"></i> اسم ولي الأمر *</label>
                    <input type="text" id="parent_name" name="parent_name" class="form-control" required
                           placeholder="اسم ولي الأمر الكامل">
                </div>
               
                <div class="form-group">
                    <label for="parent_email"><i class="fas fa-envelope"></i> البريد الإلكتروني *</label>
                    <input type="email" id="parent_email" name="parent_email" class="form-control" required
                           placeholder="example@domain.com">
                </div>
               
                <div class="form-group">
                    <label for="parent_phone"><i class="fas fa-phone"></i> رقم الهاتف *</label>
                    <input type="tel" id="parent_phone" name="parent_phone" class="form-control" required
                           placeholder="05xxxxxxxx">
                </div>
               
                <div class="form-group">
                    <label for="address"><i class="fas fa-home"></i> العنوان</label>
                    <input type="text" id="address" name="address" class="form-control"
                           placeholder="العنوان الكامل">
                </div>
               
                <div class="form-group">
                    <label for="medical_notes"><i class="fas fa-heartbeat"></i> ملاحظات صحية</label>
                    <textarea id="medical_notes" name="medical_notes" class="form-control"
                              rows="3" placeholder="أي حالات مرضية أو حساسية..."></textarea>
                </div>
               
                <!-- المستوى المختار -->
                <input type="hidden" id="selected_level" name="level" required>
               
                <!-- ملخص الطلب -->
                <div class="summary-box" id="summaryBox" style="display: none;">
                    <h4><i class="fas fa-file-invoice"></i> ملخص الطلب</h4>
                    <div class="summary-item">
                        <span>المستوى:</span>
                        <span id="summaryLevel">-</span>
                    </div>
                    <div class="summary-item">
                        <span>المواد الدراسية:</span>
                        <span id="summarySubjects">-</span>
                    </div>
                    <div class="summary-item">
                        <span>رسوم التسجيل:</span>
                        <span id="summaryPrice" class="total-price">0 ريال</span>
                    </div>
                </div>
               
                <!-- زر التسجيل -->
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <i class="fas fa-credit-card"></i>
                    المتابعة للدفع
                </button>
               
                <p style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem;">
                    <i class="fas fa-lock"></i> جميع البيانات محمية ومشفرة
                </p>
            </form>
        </div>
    </div>
   
    <script>
        // فلترة المستويات حسب التبويب
        function filterLevels(category) {
            const cards = document.querySelectorAll('.level-card');
            const tabBtns = document.querySelectorAll('.tab-btn');
           
            // تحديث التبويبات النشطة
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.includes(category) || (category === 'all' && btn.textContent === 'جميع المستويات')) {
                    btn.classList.add('active');
                }
            });
           
            // فلترة البطاقات
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
       
        // اختيار مستوى
        let selectedLevel = null;
        let selectedPrice = 0;
        let selectedSubjects = [];
       
        function selectLevel(levelName, price, subjects) {
            selectedLevel = levelName;
            selectedPrice = price;
            selectedSubjects = subjects;
           
            // تحديث النموذج
            document.getElementById('selected_level').value = levelName;
           
            // تحديث الملخص
            document.getElementById('summaryLevel').textContent = levelName;
            document.getElementById('summarySubjects').textContent = subjects.join(', ');
            document.getElementById('summaryPrice').textContent = price.toLocaleString() + ' ريال';
            document.getElementById('summaryBox').style.display = 'block';
           
            // تفعيل زر التسجيل
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-credit-card"></i> المتابعة للدفع (' + price.toLocaleString() + ' ريال)';
           
            // إضافة تأثير التحديد
            document.querySelectorAll('.level-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
           
            // التمرير إلى النموذج
            document.getElementById('registrationForm').scrollIntoView({ behavior: 'smooth' });
        }
       
        // التأكيد قبل الإرسال
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (!selectedLevel) {
                e.preventDefault();
                alert('يرجى اختيار مستوى دراسي أولاً');
                return;
            }
           
            const confirmation = confirm(`هل تريد تأكيد تسجيل الطالب في ${selectedLevel} برسوم ${selectedPrice.toLocaleString()} ريال؟`);
           
            if (!confirmation) {
                e.preventDefault();
            }
        });
       
        // التحقق من صحة البيانات أثناء الكتابة
        document.getElementById('birth_date').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
           
            if (today.getMonth() < birthDate.getMonth() ||
                (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) {
                age--;
            }
           
            if (age < 5) {
                alert('العمر أقل من 5 سنوات، يرجى اختيار روضة الأطفال');
            }
        });
    </script>
</body>
</html>