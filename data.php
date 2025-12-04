<?php
header('Content-Type: application/json; charset=utf-8');
include 'db_connect.php';

// جلب أحدث البيانات
$result = $conn->query("SELECT * FROM checkpoints_status ORDER BY created_at DESC");

if (!$result) {
    // إذا كان هناك خطأ في الاستعلام
    http_response_code(500);
    echo json_encode(["error" => "خطأ في جلب البيانات"]);
    exit();
}

$checkpoints = [];

while ($row = $result->fetch_assoc()) {
    $checkpoints[] = $row;
}

echo json_encode($checkpoints, JSON_UNESCAPED_UNICODE);
$conn->close();
?>