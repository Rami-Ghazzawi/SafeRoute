<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول. يرجى تسجيل الدخول أولاً.']);
    exit();
}

include 'db_connect.php';

// التحقق من البيانات المرسلة
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit();
}

// جمع البيانات
$name = trim($_POST['name'] ?? '');
$location_name = trim($_POST['location_name'] ?? '');
$area = trim($_POST['area'] ?? '');
$checkpoint_type = trim($_POST['checkpoint_type'] ?? 'دائم');
$status = trim($_POST['status'] ?? '');

// التحقق من البيانات المطلوبة
if (empty($name) || empty($location_name) || empty($area) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة']);
    exit();
}

// التحقق من صحة الحالة
$allowed_statuses = ['سالكة', 'مزدحمة', 'مغلقة'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'حالة غير صالحة']);
    exit();
}

// التحقق من صحة نوع الحاجز
$allowed_types = ['دائم', 'مؤقت', 'عسكري'];
if (!in_array($checkpoint_type, $allowed_types)) {
    $checkpoint_type = 'دائم'; // القيمة الافتراضية
}

try {
    // التحقق مما إذا كان الحاجز موجوداً مسبقاً
    $check_sql = "SELECT id FROM checkpoints_status WHERE name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // تحديث الحاجز الموجود بدلاً من إضافة جديد
        $update_sql = "UPDATE checkpoints_status SET 
                      location_name = ?, 
                      area = ?, 
                      status = ?, 
                      checkpoint_type = ?, 
                      updated_at = NOW() 
                      WHERE name = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssss", $location_name, $area, $status, $checkpoint_type, $name);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'تم تحديث الحاجز بنجاح']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطأ في تحديث الحاجز: ' . $update_stmt->error]);
        }
        $update_stmt->close();
    } else {
        // إضافة حاجز جديد
        $insert_sql = "INSERT INTO checkpoints_status 
                      (name, location_name, area, status, checkpoint_type, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssss", $name, $location_name, $area, $status, $checkpoint_type);
        
        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'تم إضافة الحاجز بنجاح']);
        } else {
            // تحقق من خطأ المفتاح المكرر
            if ($insert_stmt->errno == 1062) {
                echo json_encode(['success' => false, 'message' => 'الحاجز موجود بالفعل في قاعدة البيانات']);
            } else {
                echo json_encode(['success' => false, 'message' => 'خطأ في إضافة الحاجز: ' . $insert_stmt->error]);
            }
        }
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()]);
}

$conn->close();
?>