<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// الاتصال بقاعدة البيانات
require_once "includes/config.php";

// بيانات المستخدم
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'student';
$full_name = $_SESSION['full_name'] ?? 'مستخدم';

// معالجة الإجراءات
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_as_read'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $notification_id);
        if ($stmt->execute()) {
            $message = 'تم وضع علامة كمقروء بنجاح';
            $message_type = 'success';
        }
    }
   
    if (isset($_POST['mark_all_read'])) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_type = ? OR (recipient_type = 'specific' AND FIND_IN_SET(?, recipient_ids))");
        $stmt->bind_param("si", $user_type, $user_id);
        if ($stmt->execute()) {
            $message = 'تم وضع جميع الإشعارات كمقروءة';
            $message_type = 'success';
        }
    }
   
    if (isset($_POST['delete_notification'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $notification_id);
        if ($stmt->execute()) {
            $message = 'تم حذف الإشعار بنجاح';
            $message_type = 'success';
        }
    }
}

// جلب الإشعارات الخاصة بالمستخدم
$query = "SELECT n.*, u.full_name as sender_name, u.profile_image as sender_image
          FROM notifications n
          LEFT JOIN users u ON n.sender_id = u.id
          WHERE ";

// تحديد الإشعارات حسب نوع المستخدم
$recipient_types = [];
switch($user_type) {
    case 'admin':
        $recipient_types = ['all', 'specific', 'admin'];
        break;
    case 'teacher':
        $recipient_types = ['all', 'specific', 'teachers'];
        break;
    case 'student':
        $recipient_types = ['all', 'specific', 'students'];
        break;
    case 'parent':
        $recipient_types = ['all', 'specific', 'parents'];
        break;
}

$placeholders = str_repeat('?,', count($recipient_types) - 1) . '?';
$query .= "n.recipient_type IN ($placeholders)";

// إضافة شرط للمستلمين المحددين
$query .= " OR (n.recipient_type = 'specific' AND FIND_IN_SET(?, n.recipient_ids))";
$recipient_types[] = $user_id;

$query .= " ORDER BY n.created_at DESC";

$stmt = $conn->prepare($query);

// ربط المعاملات ديناميكياً
$types = str_repeat('s', count($recipient_types) - 1) . 's';
$stmt->bind_param($types, ...$recipient_types);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

// حساب الإشعارات غير المقروءة
$unread_count = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات | نظام المدرسة</title>
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
            max-width: 1200px;
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

        .header-stats {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .stat-badge {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* أزرار الإجراءات */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
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

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(106, 17, 203, 0.2);
        }

        /* الفلاتر */
        .filters {
            background: white;
            padding: 20px 25px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .filters h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            background: white;
            color: var(--dark);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
        }

        /* قائمة الإشعارات */
        .notifications-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        /* الإشعارات */
        .notification {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .notification:hover {
            background: #f8f9fa;
            transform: translateX(-5px);
        }

        .notification.unread {
            background: rgba(106, 17, 203, 0.05);
            border-color: rgba(106, 17, 203, 0.1);
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            color: white;
        }

        .icon-system { background: linear-gradient(45deg, var(--info), var(--secondary)); }
        .icon-warning { background: linear-gradient(45deg, var(--warning), #e17055); }
        .icon-success { background: linear-gradient(45deg, var(--success), #00cec9); }
        .icon-user { background: linear-gradient(45deg, var(--purple), var(--pink)); }
        .icon-general { background: linear-gradient(45deg, var(--primary), var(--accent)); }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .notification-meta {
            display: flex;
            gap: 15px;
            color: #888;
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-sender {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: none;
            background: #f0f0f0;
            color: var(--dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }

        .action-btn.read:hover { background: var(--success); color: white; }
        .action-btn.delete:hover { background: var(--danger); color: white; }

        .unread-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--danger);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* رسالة فارغة */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #666;
        }

        /* رسالة نجاح/خطأ */
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

        .alert-danger {
            background: rgba(214, 48, 49, 0.1);
            border: 2px solid #d63031;
            color: #d63031;
        }

        .alert i {
            font-size: 1.5rem;
        }

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
           
            .header-stats {
                flex-direction: column;
                gap: 10px;
            }
           
            .notification {
                flex-direction: column;
                gap: 15px;
            }
           
            .notification-actions {
                align-self: flex-end;
            }
           
            .filter-buttons {
                justify-content: center;
            }
           
            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        /* تأثيرات إضافية */
        .notification {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .notification.read-animation {
            animation: readAnimation 0.5s ease;
        }

        @keyframes readAnimation {
            0% { background: rgba(106, 17, 203, 0.1); }
            100% { background: white; }
        }

        /* ألوان حسب نوع المستلم */
        .type-all { border-right: 4px solid var(--primary); }
        .type-specific { border-right: 4px solid var(--info); }
        .type-admin { border-right: 4px solid var(--purple); }
        .type-teachers { border-right: 4px solid var(--warning); }
        .type-students { border-right: 4px solid var(--success); }
        .type-parents { border-right: 4px solid var(--pink); }
    </style>
</head>
<body>
    <div class="container">
        <!-- رسائل التنبيه -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <!-- الهيدر -->
        <header class="header">
            <div class="header-content">
                <h1><i class="fas fa-bell"></i> الإشعارات</h1>
                <p>مرحباً <span class="highlight"><?php echo htmlspecialchars($full_name); ?></span>، هنا يمكنك إدارة إشعاراتك</p>
            </div>
            <div class="header-stats">
                <div class="stat-badge">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo count($notifications); ?> إشعار</span>
                </div>
                <div class="stat-badge" style="background: linear-gradient(45deg, var(--danger), #ff7675);">
                    <i class="fas fa-envelope-open-text"></i>
                    <span><?php echo $unread_count; ?> غير مقروء</span>
                </div>
            </div>
        </header>

        <!-- أزرار الإجراءات -->
        <div class="action-buttons">
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="btn btn-success">
                    <i class="fas fa-check-double"></i> تعيين الكل كمقروء
                </button>
            </form>
           
            <button class="btn btn-secondary" onclick="refreshPage()">
                <i class="fas fa-sync-alt"></i> تحديث الصفحة
            </button>
           
            <button class="btn btn-primary" onclick="filterNotifications('unread')">
                <i class="fas fa-filter"></i> عرض غير المقروء فقط
            </button>
        </div>

        <!-- الفلاتر -->
        <div class="filters">
            <h3><i class="fas fa-filter"></i> تصفية الإشعارات</h3>
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterNotifications('all')">الكل</button>
                <button class="filter-btn" onclick="filterNotifications('unread')">غير مقروء</button>
                <button class="filter-btn" onclick="filterNotifications('system')">نظام</button>
                <button class="filter-btn" onclick="filterNotifications('<?php echo $user_type; ?>')">
                    <?php
                    $type_names = [
                        'admin' => 'مدير',
                        'teacher' => 'معلم',
                        'student' => 'طالب',
                        'parent' => 'ولي أمر'
                    ];
                    echo $type_names[$user_type] ?? $user_type;
                    ?>
                </button>
            </div>
        </div>

        <!-- قائمة الإشعارات -->
        <div class="notifications-container">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="far fa-bell-slash"></i>
                    <h3>لا توجد إشعارات</h3>
                    <p>لم يتم العثور على أي إشعارات لعرضها</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification):
                    // تحديد الأيقونة حسب نوع الإشعار
                    $icon_class = 'icon-general';
                    $icon = 'fas fa-bell';
                   
                    if (strpos(strtolower($notification['title']), 'تحذير') !== false) {
                        $icon_class = 'icon-warning';
                        $icon = 'fas fa-exclamation-triangle';
                    } elseif (strpos(strtolower($notification['title']), 'نجاح') !== false) {
                        $icon_class = 'icon-success';
                        $icon = 'fas fa-check-circle';
                    } elseif ($notification['sender_id'] === null) {
                        $icon_class = 'icon-system';
                        $icon = 'fas fa-cogs';
                    } else {
                        $icon_class = 'icon-user';
                        $icon = 'fas fa-user';
                    }
                   
                    // حساب الوقت المنقضي
                    $created_at = new DateTime($notification['created_at']);
                    $now = new DateTime();
                    $interval = $created_at->diff($now);
                   
                    $time_ago = '';
                    if ($interval->y > 0) {
                        $time_ago = $interval->y . ' سنة';
                    } elseif ($interval->m > 0) {
                        $time_ago = $interval->m . ' شهر';
                    } elseif ($interval->d > 0) {
                        $time_ago = $interval->d . ' يوم';
                    } elseif ($interval->h > 0) {
                        $time_ago = $interval->h . ' ساعة';
                    } elseif ($interval->i > 0) {
                        $time_ago = $interval->i . ' دقيقة';
                    } else {
                        $time_ago = 'الآن';
                    }
                ?>
               
                <div class="notification <?php echo $notification['is_read'] ? '' : 'unread'; ?> type-<?php echo $notification['recipient_type']; ?>"
                     data-type="<?php echo $notification['recipient_type']; ?>"
                     data-read="<?php echo $notification['is_read'] ? 'true' : 'false'; ?>">
                   
                    <?php if (!$notification['is_read']): ?>
                        <span class="unread-badge"></span>
                    <?php endif; ?>
                   
                    <div class="notification-icon <?php echo $icon_class; ?>">
                        <i class="<?php echo $icon; ?>"></i>
                    </div>
                   
                    <div class="notification-content">
                        <h3 class="notification-title">
                            <?php echo htmlspecialchars($notification['title']); ?>
                        </h3>
                       
                        <p class="notification-message">
                            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                        </p>
                       
                        <div class="notification-meta">
                            <span class="notification-time">
                                <i class="far fa-clock"></i> منذ <?php echo $time_ago; ?>
                            </span>
                           
                            <?php if ($notification['sender_name']): ?>
                            <span class="notification-sender">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($notification['sender_name']); ?>
                            </span>
                            <?php endif; ?>
                           
                            <span class="notification-type">
                                <i class="fas fa-tag"></i>
                                <?php
                                $recipient_names = [
                                    'all' => 'عام',
                                    'specific' => 'مخصص',
                                    'admin' => 'مدير',
                                    'teacher' => 'معلم',
                                    'student' => 'طالب',
                                    'parent' => 'ولي أمر'
                                ];
                                echo $recipient_names[$notification['recipient_type']] ?? $notification['recipient_type'];
                                ?>
                            </span>
                        </div>
                    </div>
                   
                    <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" name="mark_as_read" class="action-btn read" title="تعيين كمقروء">
                                <i class="far fa-check-circle"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                       
                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل تريد حذف هذا الإشعار؟');">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" name="delete_notification" class="action-btn delete" title="حذف">
                                <i class="far fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- الفوتر -->
        <footer class="footer">
            <p>© 2023 نظام المدرسة | صفحة الإشعارات</p>
            <p style="margin-top: 10px; font-size: 0.85rem; color: #aaa;">
                <i class="fas fa-info-circle"></i> تم تحديث الصفحة في: <span id="currentTime"></span>
            </p>
        </footer>
    </div>

    <script>
        // تحديث الوقت الحي
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ar-SA');
            const dateString = now.toLocaleDateString('ar-SA');
            document.getElementById('currentTime').textContent = `${dateString} ${timeString}`;
        }
        setInterval(updateTime, 1000);
        updateTime();

        // تصفية الإشعارات
        function filterNotifications(filter) {
            const notifications = document.querySelectorAll('.notification');
           
            notifications.forEach(notification => {
                if (filter === 'all') {
                    notification.style.display = 'flex';
                } else if (filter === 'unread') {
                    const isRead = notification.dataset.read === 'true';
                    notification.style.display = isRead ? 'none' : 'flex';
                } else {
                    const notificationType = notification.dataset.type;
                    notification.style.display = notificationType === filter ? 'flex' : 'none';
                }
            });
           
            // تحديث أزرار الفلتر
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // تحديث الصفحة
        function refreshPage() {
            location.reload();
        }

        // تأثير عند قراءة الإشعار
        document.addEventListener('DOMContentLoaded', () => {
            const readButtons = document.querySelectorAll('.action-btn.read');
            readButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const notification = this.closest('.notification');
                    notification.classList.add('read-animation');
                    setTimeout(() => {
                        notification.classList.remove('unread', 'read-animation');
                        notification.querySelector('.unread-badge')?.remove();
                    }, 500);
                });
            });
        });
    </script>
</body>
</html>