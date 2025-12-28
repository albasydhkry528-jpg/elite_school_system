<?php
session_start();
require_once 'includes/config.php';

echo "<h2>إعادة ضبط النظام بالكامل</h2>";

// 1. مسح جدول users إذا كان فيه بيانات مشفرة
$conn->query("DROP TABLE IF EXISTS users");

// 2. إعادة إنشاء جدول users بدون تفكير في التشفير
$sql = "CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(100) NOT NULL, -- كلمة مرور عادية
    full_name VARCHAR(100) NOT NULL,
    user_type ENUM('admin', 'teacher', 'student', 'parent') NOT NULL,
    email VARCHAR(100),
    profile_image VARCHAR(255) DEFAULT 'default.png',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login DATETIME,
    last_activity DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ تم إنشاء جدول users بنجاح</p>";
} else {
    echo "<p style='color: red;'>✗ خطأ في إنشاء الجدول: " . $conn->error . "</p>";
}

// 3. إدخال بيانات تجريبية (كلمات مرور عادية)
$users = [
    ['admin', 'admin123', 'المدير العام', 'admin', 'admin@school.com'],
    ['teacher1', 'teacher123', 'أحمد محمد - معلم', 'teacher', 'teacher@school.com'],
    ['student1', 'student123', 'سارة أحمد - طالبة', 'student', 'student@school.com'],
    ['parent1', 'parent123', 'خالد علي - ولي أمر', 'parent', 'parent@school.com'],
    ['teacher2', '123456', 'فاطمة سعيد - معلمة', 'teacher', 'fatima@school.com'],
    ['student2', '123456', 'محمد خالد - طالب', 'student', 'mohamed@school.com']
];

foreach ($users as $user) {
    $sql = "INSERT INTO users (username, password, full_name, user_type, email, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $user[0], $user[1], $user[2], $user[3], $user[4]);
   
    if ($stmt->execute()) {
        echo "<p>✓ تم إضافة: {$user[0]} / {$user[1]}</p>";
    }
}

// 4. إنشاء الجداول الأخرى الأساسية
$tables = [
    "students" => "CREATE TABLE students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        student_code VARCHAR(20) UNIQUE,
        birth_date DATE,
        gender ENUM('ذكر', 'أنثى'),
        class_id INT,
        parent_id INT,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
   
    "teachers" => "CREATE TABLE teachers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        specialization VARCHAR(100),
        experience_years INT,
        qualification VARCHAR(100),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
   
    "parents" => "CREATE TABLE parents (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        phone VARCHAR(20),
        job VARCHAR(100),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )"
];

foreach ($tables as $table_name => $sql) {
    $conn->query("DROP TABLE IF EXISTS $table_name");
    if ($conn->query($sql)) {
        echo "<p>✓ تم إنشاء جدول $table_name</p>";
    }
}

echo "<hr>";
echo "<h3>✅ تم إعادة ضبط النظام بنجاح!</h3>";
echo "<h4>بيانات الدخول:</h4>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>الدور</th><th>اسم المستخدم</th><th>كلمة المرور</th></tr>";
echo "<tr><td>المدير</td><td>admin</td><td>admin123</td></tr>";
echo "<tr><td>معلم</td><td>teacher1</td><td>teacher123</td></tr>";
echo "<tr><td>طالب</td><td>student1</td><td>student123</td></tr>";
echo "<tr><td>ولي أمر</td><td>parent1</td><td>parent123</td></tr>";
echo "</table>";

echo "<br>";
echo "<a href='login.php' style='padding: 15px 30px; background: #4c1d95; color: white; text-decoration: none; border-radius: 8px; font-size: 18px;'>🚀 اذهب لتسجيل الدخول الآن</a>";
?>