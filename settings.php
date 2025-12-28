<?php
session_start();

// استخدام ملفات المساعدة
require_once "includes/config.php";
require_once "includes/functions.php";

// التحقق من تسجيل الدخول والصلاحيات (للمدير فقط)
if (!is_logged_in() || !has_permission('admin')) {
    header("Location: login.php");
    exit;
}

// بيانات المستخدم
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'المدير';

// تسجيل النشاط
log_activity('view_settings', 'عرض صفحة إعدادات النظام');

// معالجة تحديث الإعدادات
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        try {
            // التحقق من رمز CSRF
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $message = 'رمز الحماية غير صحيح';
                $message_type = 'error';
            } else {
                // تحديث كل الإعدادات
                foreach ($_POST['settings'] as $key => $value) {
                    // تنظيف القيمة
                    $clean_value = clean_input($value);
                   
                    // تحديث الإعداد في قاعدة البيانات
                    $query = "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ? AND is_editable = 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $clean_value, $key);
                    $stmt->execute();
                    $stmt->close();
                }
               
                // تسجيل النشاط
                log_activity('update_settings', 'تم تحديث إعدادات النظام');
               
                $message = 'تم تحديث الإعدادات بنجاح';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'حدث خطأ أثناء تحديث الإعدادات: ' . $e->getMessage();
            $message_type = 'error';
            log_activity('error', 'خطأ في تحديث الإعدادات: ' . $e->getMessage());
        }
    }
   
    if (isset($_POST['add_setting'])) {
        try {
            $key = clean_input($_POST['new_key']);
            $value = clean_input($_POST['new_value']);
            $type = clean_input($_POST['new_type']);
            $category = clean_input($_POST['new_category']);
            $description = clean_input($_POST['new_description']);
           
            // التحقق من عدم وجود المفتاح مسبقاً
            $check_query = "SELECT id FROM system_settings WHERE setting_key = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $key);
            $check_stmt->execute();
           
            if ($check_stmt->get_result()->num_rows > 0) {
                $message = 'مفتاح الإعداد موجود مسبقاً';
                $message_type = 'error';
            } else {
                // إضافة الإعداد الجديد
                $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssi", $key, $value, $type, $category, $description, $user_id);
                $stmt->execute();
                $stmt->close();
               
                log_activity('add_setting', 'تم إضافة إعداد جديد: ' . $key);
                $message = 'تم إضافة الإعداد الجديد بنجاح';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'حدث خطأ أثناء إضافة الإعداد: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
   
    if (isset($_POST['delete_setting'])) {
        $setting_id = clean_input($_POST['setting_id']);
       
        try {
            $query = "DELETE FROM system_settings WHERE id = ? AND is_editable = 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $setting_id);
            $stmt->execute();
           
            if ($stmt->affected_rows > 0) {
                log_activity('delete_setting', 'تم حذف إعداد: ' . $setting_id);
                $message = 'تم حذف الإعداد بنجاح';
                $message_type = 'success';
            } else {
                $message = 'لا يمكن حذف هذا الإعداد';
                $message_type = 'error';
            }
           
            $stmt->close();
        } catch (Exception $e) {
            $message = 'حدث خطأ أثناء حذف الإعداد: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
   
    if (isset($_POST['reset_settings'])) {
        try {
            // إعادة تعيين بعض الإعدادات إلى القيم الافتراضية
            $default_settings = [
                ['maintenance_mode', 'false', 'boolean', 'system'],
                ['registration_open', 'true', 'boolean', 'registration'],
                ['enable_notifications', 'true', 'boolean', 'system']
            ];
           
            foreach ($default_settings as $setting) {
                $query = "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $setting[1], $setting[0]);
                $stmt->execute();
                $stmt->close();
            }
           
            log_activity('reset_settings', 'تم إعادة تعيين الإعدادات إلى الافتراضي');
            $message = 'تم إعادة تعيين الإعدادات بنجاح';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'حدث خطأ أثناء إعادة التعيين: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// إنشاء رمز CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// جلب جميع الإعدادات من قاعدة البيانات
$settings = [];
$categories = [];

try {
    $query = "SELECT * FROM system_settings ORDER BY category, setting_key";
    $result = $conn->query($query);
   
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[] = $row;
            if (!in_array($row['category'], $categories)) {
                $categories[] = $row['category'];
            }
        }
    }
} catch (Exception $e) {
    $error = "حدث خطأ في جلب الإعدادات: " . $e->getMessage();
}

// جلب إحصائيات النظام
$stats = [];
try {
    // عدد الإعدادات
    $query = "SELECT COUNT(*) as total_settings FROM system_settings";
    $result = $conn->query($query);
    $stats['total_settings'] = $result->fetch_assoc()['total_settings'];
   
    // الإعدادات العامة
    $query = "SELECT COUNT(*) as public_settings FROM system_settings WHERE is_public = 1";
    $result = $conn->query($query);
    $stats['public_settings'] = $result->fetch_assoc()['public_settings'];
   
    // الإعدادات القابلة للتعديل
    $query = "SELECT COUNT(*) as editable_settings FROM system_settings WHERE is_editable = 1";
    $result = $conn->query($query);
    $stats['editable_settings'] = $result->fetch_assoc()['editable_settings'];
   
    // آخر تحديث
    $query = "SELECT MAX(updated_at) as last_update FROM system_settings";
    $result = $conn->query($query);
    $stats['last_update'] = $result->fetch_assoc()['last_update'];
   
} catch (Exception $e) {
    // تجاهل الأخطاء في الإحصائيات
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات النظام | <?php echo APP_NAME; ?></title>
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
            --purple: #6c5ce7;
            --pink: #fd79a8;
            --cyan: #00cec9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark);
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* الهيدر */
        .header {
            background: white;
            border-radius: 20px;
            padding: 25px 35px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-content h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-content h1 i {
            color: var(--primary);
        }

        .header-content p {
            color: #666;
            font-size: 1.1rem;
        }

        .highlight {
            color: var(--primary);
            font-weight: 700;
        }

        /* الإحصائيات */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* رسائل التنبيه */
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.5s ease;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.1);
            border: 2px solid #00b894;
            color: #00b894;
        }

        .alert-error {
            background: rgba(214, 48, 49, 0.1);
            border: 2px solid #d63031;
            color: #d63031;
        }

        .alert i {
            font-size: 1.5rem;
        }

        /* تبويب الإعدادات */
        .settings-tabs {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .tabs-header {
            display: flex;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            padding: 0;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 20px 30px;
            background: none;
            border: none;
            color: rgba(255,255,255,0.8);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            position: relative;
        }

        .tab-btn:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }

        .tab-btn.active {
            color: white;
            background: rgba(255,255,255,0.2);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: white;
        }

        .tab-content {
            display: none;
            padding: 30px;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* نموذج الإعدادات */
        .settings-form {
            display: grid;
            gap: 25px;
        }

        .settings-group {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border: 2px solid #e0e0e0;
        }

        .group-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .group-header i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .group-header h3 {
            font-size: 1.4rem;
            color: var(--dark);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .setting-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .setting-item:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.1);
        }

        .setting-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .setting-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .setting-key {
            font-size: 0.85rem;
            color: #666;
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .setting-type {
            font-size: 0.8rem;
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: 600;
        }

        .type-text { background: var(--info); }
        .type-number { background: var(--success); }
        .type-boolean { background: var(--warning); }
        .type-json { background: var(--purple); }
        .type-date { background: var(--pink); }
        .type-time { background: var(--cyan); }

        .setting-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .setting-control {
            margin-top: 15px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }

        .form-control[disabled] {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .checkbox-custom.checked {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-custom.checked::after {
            content: '✓';
            color: white;
            font-size: 14px;
        }

        .form-text {
            display: block;
            margin-top: 8px;
            color: #888;
            font-size: 0.85rem;
        }

        .setting-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 0.85rem;
            color: #888;
        }

        /* أزرار الإجراءات */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 12px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success), var(--cyan));
            color: white;
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger), #ff7675);
            color: white;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: var(--dark);
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(106, 17, 203, 0.2);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* إضافة إعداد جديد */
        .add-setting-form {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* جدول الإعدادات */
        .settings-table-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .settings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .settings-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: right;
            color: var(--dark);
            font-weight: 600;
            border-bottom: 2px solid #f0f0f0;
        }

        .settings-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .settings-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }

        .badge-public { background: var(--success); }
        .badge-private { background: var(--danger); }
        .badge-editable { background: var(--info); }
        .badge-readonly { background: var(--warning); }

        /* الفوتر */
        .footer {
            text-align: center;
            padding: 25px;
            color: #888;
            font-size: 0.9rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
           
            .tabs-header {
                flex-direction: column;
            }
           
            .tab-btn {
                width: 100%;
                text-align: right;
            }
           
            .settings-grid {
                grid-template-columns: 1fr;
            }
           
            .form-grid {
                grid-template-columns: 1fr;
            }
           
            .action-buttons {
                flex-direction: column;
            }
           
            .btn {
                width: 100%;
                justify-content: center;
            }
           
            .settings-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* تأثيرات إضافية */
        .setting-item.editable {
            border-left: 4px solid var(--success);
        }

        .setting-item.readonly {
            border-left: 4px solid var(--warning);
        }

        .setting-item.public {
            border-right: 4px solid var(--info);
        }

        /* أنيميشن للتبويبات */
        @keyframes tabSwitch {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .tab-content.active {
            animation: tabSwitch 0.3s ease;
        }

        /* تنسيق خاص للقيم JSON */
        .json-value {
            font-family: monospace;
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* زر النسخ */
        .copy-btn {
            background: #f0f0f0;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- الهيدر -->
        <header class="header">
            <div class="header-content">
                <h1><i class="fas fa-cogs"></i> إعدادات النظام</h1>
                <p>مرحباً <span class="highlight"><?php echo htmlspecialchars($full_name); ?></span>، هنا يمكنك إدارة جميع إعدادات النظام</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
                </button>
            </div>
        </header>

        <!-- رسائل التنبيه -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <!-- الإحصائيات -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_settings'] ?? 0); ?></div>
                <div class="stat-label">إعدادات النظام</div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['public_settings'] ?? 0); ?></div>
                <div class="stat-label">إعدادات عامة</div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['editable_settings'] ?? 0); ?></div>
                <div class="stat-label">قابلة للتعديل</div>
            </div>
           
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-number">
                    <?php
                    if (isset($stats['last_update']) && $stats['last_update']) {
                        $last_update = new DateTime($stats['last_update']);
                        $now = new DateTime();
                        $interval = $last_update->diff($now);
                       
                        if ($interval->d > 0) {
                            echo $interval->d . ' يوم';
                        } elseif ($interval->h > 0) {
                            echo $interval->h . ' ساعة';
                        } elseif ($interval->i > 0) {
                            echo $interval->i . ' دقيقة';
                        } else {
                            echo 'الآن';
                        }
                    } else {
                        echo 'لم يحدث';
                    }
                    ?>
                </div>
                <div class="stat-label">آخر تحديث</div>
            </div>
        </div>

        <!-- تبويب الإعدادات -->
        <div class="settings-tabs">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="general">
                    <i class="fas fa-sliders-h"></i> إعدادات عامة
                </button>
                <button class="tab-btn" data-tab="academic">
                    <i class="fas fa-graduation-cap"></i> أكاديمية
                </button>
                <button class="tab-btn" data-tab="financial">
                    <i class="fas fa-money-bill-wave"></i> مالية
                </button>
                <button class="tab-btn" data-tab="system">
                    <i class="fas fa-server"></i> النظام
                </button>
                <button class="tab-btn" data-tab="all">
                    <i class="fas fa-list"></i> جميع الإعدادات
                </button>
                <button class="tab-btn" data-tab="add">
                    <i class="fas fa-plus-circle"></i> إضافة جديد
                </button>
            </div>

            <!-- تبويب الإعدادات العامة -->
            <div class="tab-content active" id="general-tab">
                <form method="POST" action="" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                   
                    <div class="settings-group">
                        <div class="group-header">
                            <i class="fas fa-school"></i>
                            <h3>إعدادات المدرسة</h3>
                        </div>
                       
                        <div class="settings-grid">
                            <?php foreach ($settings as $setting): ?>
                                <?php if ($setting['category'] === 'general'): ?>
                                <div class="setting-item <?php echo $setting['is_editable'] ? 'editable' : 'readonly'; ?> <?php echo $setting['is_public'] ? 'public' : ''; ?>">
                                    <div class="setting-header">
                                        <div>
                                            <div class="setting-title"><?php echo htmlspecialchars($setting['description'] ?? $setting['setting_key']); ?></div>
                                            <span class="setting-key"><?php echo $setting['setting_key']; ?></span>
                                        </div>
                                        <span class="setting-type type-<?php echo $setting['setting_type']; ?>">
                                            <?php
                                            $type_names = [
                                                'text' => 'نص',
                                                'number' => 'رقم',
                                                'boolean' => 'نعم/لا',
                                                'json' => 'JSON',
                                                'date' => 'تاريخ',
                                                'time' => 'وقت'
                                            ];
                                            echo $type_names[$setting['setting_type']] ?? $setting['setting_type'];
                                            ?>
                                        </span>
                                    </div>
                                   
                                    <?php if ($setting['description']): ?>
                                    <div class="setting-description">
                                        <?php echo htmlspecialchars($setting['description']); ?>
                                    </div>
                                    <?php endif; ?>
                                   
                                    <div class="setting-control">
                                        <?php if ($setting['is_editable']): ?>
                                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                <label class="checkbox-label">
                                                    <input type="checkbox" name="settings[<?php echo $setting['setting_key']; ?>]"
                                                           value="true" <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>
                                                           class="checkbox-input" style="display: none;">
                                                    <span class="checkbox-custom <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>"></span>
                                                    <span><?php echo $setting['setting_value'] === 'true' ? 'مفعل' : 'معطل'; ?></span>
                                                </label>
                                            <?php elseif ($setting['setting_type'] === 'json'): ?>
                                                <textarea name="settings[<?php echo $setting['setting_key']; ?>]"
                                                          class="form-control" rows="3"
                                                          placeholder="أدخل بيانات JSON..."><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                            <?php else: ?>
                                                <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>"
                                                       name="settings[<?php echo $setting['setting_key']; ?>]"
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                       class="form-control"
                                                       placeholder="أدخل القيمة...">
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                <div class="form-control" style="background: #f5f5f5; padding: 12px 15px;">
                                                    <?php echo $setting['setting_value'] === 'true' ? '✅ مفعل' : '❌ معطل'; ?>
                                                </div>
                                            <?php elseif ($setting['setting_type'] === 'json'): ?>
                                                <div class="json-value">
                                                    <?php echo htmlspecialchars($setting['setting_value']); ?>
                                                    <button type="button" class="copy-btn" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($setting['setting_value']); ?>')">
                                                        <i class="fas fa-copy"></i> نسخ
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <input type="text" value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                       class="form-control" disabled>
                                            <?php endif; ?>
                                            <small class="form-text">هذا الإعداد غير قابل للتعديل</small>
                                        <?php endif; ?>
                                    </div>
                                   
                                    <div class="setting-meta">
                                        <span>
                                            <i class="fas fa-<?php echo $setting['is_public'] ? 'eye' : 'eye-slash'; ?>"></i>
                                            <?php echo $setting['is_public'] ? 'عام' : 'خاص'; ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-<?php echo $setting['is_editable'] ? 'edit' : 'lock'; ?>"></i>
                                            <?php echo $setting['is_editable'] ? 'قابل للتعديل' : 'للقراءة فقط'; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                   
                    <div class="action-buttons">
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-undo"></i> إعادة تعيين
                        </button>
                        <button type="submit" name="reset_settings" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من إعادة تعيين الإعدادات؟')">
                            <i class="fas fa-redo"></i> إعادة تعيين الكل
                        </button>
                    </div>
                </form>
            </div>

            <!-- تبويب الإعدادات الأكاديمية -->
            <div class="tab-content" id="academic-tab">
                <form method="POST" action="" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                   
                    <div class="settings-group">
                        <div class="group-header">
                            <i class="fas fa-book-open"></i>
                            <h3>الإعدادات الأكاديمية</h3>
                        </div>
                       
                        <div class="settings-grid">
                            <?php foreach ($settings as $setting): ?>
                                <?php if ($setting['category'] === 'academic'): ?>
                                <div class="setting-item">
                                    <div class="setting-header">
                                        <div>
                                            <div class="setting-title"><?php echo htmlspecialchars($setting['description'] ?? $setting['setting_key']); ?></div>
                                            <span class="setting-key"><?php echo $setting['setting_key']; ?></span>
                                        </div>
                                        <span class="setting-type type-<?php echo $setting['setting_type']; ?>">
                                            <?php echo $setting['setting_type']; ?>
                                        </span>
                                    </div>
                                   
                                    <div class="setting-control">
                                        <?php if ($setting['is_editable']): ?>
                                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                <label class="checkbox-label">
                                                    <input type="checkbox" name="settings[<?php echo $setting['setting_key']; ?>]"
                                                           value="true" <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>
                                                           class="checkbox-input" style="display: none;">
                                                    <span class="checkbox-custom <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>"></span>
                                                    <span><?php echo $setting['setting_value'] === 'true' ? 'مفعل' : 'معطل'; ?></span>
                                                </label>
                                            <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                <input type="number" name="settings[<?php echo $setting['setting_key']; ?>]"
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                       class="form-control"
                                                       min="0">
                                            <?php else: ?>
                                                <input type="text" name="settings[<?php echo $setting['setting_key']; ?>]"
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                       class="form-control">
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <input type="text" value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                   class="form-control" disabled>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                   
                    <div class="action-buttons">
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>

            <!-- تبويب الإعدادات المالية -->
            <div class="tab-content" id="financial-tab">
                <!-- مشابه للتبويبات السابقة -->
                <p style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-coins fa-3x" style="margin-bottom: 20px; color: #ddd;"></i>
                    <br>
                    سيتم إضافة الإعدادات المالية قريباً
                </p>
            </div>

            <!-- تبويب إعدادات النظام -->
            <div class="tab-content" id="system-tab">
                <!-- مشابه للتبويبات السابقة -->
                <p style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-server fa-3x" style="margin-bottom: 20px; color: #ddd;"></i>
                    <br>
                    سيتم إضافة إعدادات النظام قريباً
                </p>
            </div>

            <!-- تبويب جميع الإعدادات -->
            <div class="tab-content" id="all-tab">
                <div class="settings-table-container">
                    <h3 style="margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list"></i> جميع إعدادات النظام
                    </h3>
                   
                    <table class="settings-table">
                        <thead>
                            <tr>
                                <th>المفتاح</th>
                                <th>القيمة</th>
                                <th>النوع</th>
                                <th>التصنيف</th>
                                <th>الصلاحيات</th>
                                <th>آخر تحديث</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settings as $setting): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $setting['setting_key']; ?></strong>
                                    <?php if ($setting['description']): ?>
                                    <br>
                                    <small style="color: #666;"><?php echo $setting['description']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (strlen($setting['setting_value']) > 50): ?>
                                        <span title="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                            <?php echo htmlspecialchars(substr($setting['setting_value'], 0, 50)) . '...'; ?>
                                        </span>
                                        <button type="button" class="copy-btn" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($setting['setting_value']); ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($setting['setting_value']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge type-<?php echo $setting['setting_type']; ?>">
                                        <?php echo $setting['setting_type']; ?>
                                    </span>
                                </td>
                                <td><?php echo $setting['category']; ?></td>
                                <td>
                                    <span class="badge <?php echo $setting['is_public'] ? 'badge-public' : 'badge-private'; ?>">
                                        <?php echo $setting['is_public'] ? 'عام' : 'خاص'; ?>
                                    </span>
                                    <span class="badge <?php echo $setting['is_editable'] ? 'badge-editable' : 'badge-readonly'; ?>">
                                        <?php echo $setting['is_editable'] ? 'قابل للتعديل' : 'للقراءة فقط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $updated = new DateTime($setting['updated_at']);
                                    echo $updated->format('Y/m/d H:i');
                                    ?>
                                </td>
                                <td>
                                    <?php if ($setting['is_editable']): ?>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا الإعداد؟');">
                                        <input type="hidden" name="setting_id" value="<?php echo $setting['id']; ?>">
                                        <button type="submit" name="delete_setting" class="btn btn-danger btn-sm" style="padding: 5px 10px; font-size: 0.8rem;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- تبويب إضافة إعداد جديد -->
            <div class="tab-content" id="add-tab">
                <div class="add-setting-form">
                    <h3 style="margin-bottom: 25px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-plus-circle"></i> إضافة إعداد جديد
                    </h3>
                   
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                       
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-key"></i>
                                    مفتاح الإعداد
                                </label>
                                <input type="text" name="new_key" class="form-control" required
                                       placeholder="مثال: new_setting_key">
                                <small class="form-text">يجب أن يكون فريداً ولا يحتوي على مسافات</small>
                            </div>
                           
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-font"></i>
                                    قيمة الإعداد
                                </label>
                                <input type="text" name="new_value" class="form-control" required
                                       placeholder="القيمة">
                            </div>
                           
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag"></i>
                                    نوع القيمة
                                </label>
                                <select name="new_type" class="form-control" required>
                                    <option value="text">نص</option>
                                    <option value="number">رقم</option>
                                    <option value="boolean">نعم/لا</option>
                                    <option value="json">JSON</option>
                                    <option value="date">تاريخ</option>
                                    <option value="time">وقت</option>
                                </select>
                            </div>
                           
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-folder"></i>
                                    التصنيف
                                </label>
                                <select name="new_category" class="form-control" required>
                                    <option value="general">عام</option>
                                    <option value="academic">أكاديمي</option>
                                    <option value="financial">مالي</option>
                                    <option value="system">نظام</option>
                                    <option value="registration">تسجيل</option>
                                    <option value="email">بريد إلكتروني</option>
                                    <option value="sms">رسائل نصية</option>
                                </select>
                            </div>
                        </div>
                       
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i>
                                الوصف
                            </label>
                            <textarea name="new_description" class="form-control" rows="3"
                                      placeholder="وصف مختصر للإعداد..."></textarea>
                        </div>
                       
                        <div class="action-buttons">
                            <button type="submit" name="add_setting" class="btn btn-success">
                                <i class="fas fa-plus"></i> إضافة الإعداد
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-eraser"></i> مسح النموذج
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- الفوتر -->
        <footer class="footer">
            <p>© 2023 <?php echo APP_NAME; ?> | صفحة إعدادات النظام</p>
            <p style="margin-top: 10px; font-size: 0.85rem; color: #aaa;">
                <i class="fas fa-info-circle"></i> الإصدار: <?php echo APP_VERSION; ?> | المستخدم: <?php echo htmlspecialchars($full_name); ?>
            </p>
        </footer>
    </div>

    <script>
        // تبديل التبويبات
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // إزالة النشط من جميع الأزرار
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('active');
                });
               
                // إخفاء جميع المحتويات
                document.querySelectorAll('.tab-content').forEach(c => {
                    c.classList.remove('active');
                });
               
                // إضافة النشط للزر المحدد
                this.classList.add('active');
               
                // إظهار المحتوى المناسب
                const tabId = this.dataset.tab;
                document.getElementById(tabId + '-tab').classList.add('active');
            });
        });

        // التحكم في checkboxes
        document.querySelectorAll('.checkbox-input').forEach(checkbox => {
            const customCheckbox = checkbox.nextElementSibling;
           
            checkbox.addEventListener('change', function() {
                customCheckbox.classList.toggle('checked', this.checked);
                customCheckbox.nextElementSibling.textContent = this.checked ? 'مفعل' : 'معطل';
            });
           
            customCheckbox.addEventListener('click', function() {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            });
        });

        // نسخ النص إلى الحافظة
        function copyToClipboard(button, text) {
            navigator.clipboard.writeText(text).then(() => {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> تم النسخ';
                button.style.background = 'var(--success)';
                button.style.color = 'white';
               
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.style.background = '';
                    button.style.color = '';
                }, 2000);
            }).catch(err => {
                alert('فشل النسخ: ' + err);
            });
        }

        // إعادة تعيين النموذج
        function resetForm() {
            if (confirm('هل تريد إعادة تعيين جميع التغييرات؟')) {
                location.reload();
            }
        }

        // تحميل الصفحة مع حفظ حالة التبويب
        document.addEventListener('DOMContentLoaded', function() {
            const savedTab = localStorage.getItem('settings_active_tab');
            if (savedTab) {
                const tabBtn = document.querySelector(`.tab-btn[data-tab="${savedTab}"]`);
                if (tabBtn) {
                    tabBtn.click();
                }
            }
           
            // حفظ التبويب النشط
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    localStorage.setItem('settings_active_tab', this.dataset.tab);
                });
            });
           
            // تنبيه قبل مغادرة الصفحة إذا كانت هناك تغييرات غير محفوظة
            let formChanged = false;
            document.querySelectorAll('input, textarea, select').forEach(input => {
                input.addEventListener('change', () => {
                    formChanged = true;
                });
            });
           
            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟';
                }
            });
           
            // إرسال النموذج يلغي التنبيه
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', () => {
                    formChanged = false;
                });
            });
        });

        // البحث في الإعدادات
        function searchSettings() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const settingItems = document.querySelectorAll('.setting-item');
           
            settingItems.forEach(item => {
                const title = item.querySelector('.setting-title').textContent.toLowerCase();
                const key = item.querySelector('.setting-key').textContent.toLowerCase();
                const description = item.querySelector('.setting-description')?.textContent.toLowerCase() || '';
               
                if (title.includes(searchTerm) || key.includes(searchTerm) || description.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // تصدير الإعدادات
        function exportSettings() {
            const settings = <?php echo json_encode($settings); ?>;
            const dataStr = JSON.stringify(settings, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
           
            const exportFileDefaultName = 'settings_export_' + new Date().toISOString().slice(0,10) + '.json';
           
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        }

        // استيراد الإعدادات
        function importSettings() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
           
            input.onchange = function(event) {
                const file = event.target.files[0];
                const reader = new FileReader();
               
                reader.onload = function(e) {
                    try {
                        const settings = JSON.parse(e.target.result);
                        if (confirm(`سيتم استيراد ${settings.length} إعداد. هل تريد المتابعة؟`)) {
                            // هنا يمكن إضافة كود الاستيراد الفعلي
                            alert('سيتم تنفيذ الاستيراد في النسخة القادمة');
                        }
                    } catch (error) {
                        alert('خطأ في قراءة الملف: ' + error.message);
                    }
                };
               
                reader.readAsText(file);
            };
           
            input.click();
        }
    </script>
</body>
</html>