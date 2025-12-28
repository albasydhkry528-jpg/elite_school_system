<?php
require_once 'includes/config.php';

// التحقق من تسجيل الدخول
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    die('غير مصرح');
}

if (isset($_GET['class_id'])) {
    $class_id = clean_input($_GET['class_id']);
   
    $sql = "SELECT s.id, s.student_code, u.full_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN class_students cs ON s.id = cs.student_id
            WHERE cs.class_id = ? AND cs.is_active = 1
            ORDER BY u.full_name";
   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
   
    if ($result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>اسم الطالب</th>
                        <th>الكود</th>
                        <th>الدرجة (0-100)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><?php echo $row['student_code']; ?></td>
                            <td>
                                <input type="number" class="form-control grade-input"
                                       name="grade_<?php echo $row['id']; ?>"
                                       placeholder="0-100" min="0" max="100"
                                       step="0.01" style="width: 120px;">
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="alert alert-success">
                <i class="fas fa-info-circle"></i> تم تحميل <?php echo $result->num_rows; ?> طالب
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-exclamation-triangle"></i> لا يوجد طلاب مسجلين في هذا الصف
        </div>
    <?php endif;
   
    $stmt->close();
} else {
    echo '<div class="alert alert-danger text-center">لم يتم تحديد الصف</div>';
}
?>