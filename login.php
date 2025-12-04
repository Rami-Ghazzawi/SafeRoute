<?php
session_start();
include 'db_connect.php';

// 🔐 إعداد المستخدمين (ممكن تغيرهم حسب الحاجة)
$users = [
    "admin" => "12345", // المستخدم الرئيسي
    "rami"  => "palestine" // مستخدم إضافي (مثلاً إلك)
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (isset($users[$username]) && $users[$username] === $password) {
        $_SESSION["username"] = $username;
        header("Location: admin.php");
        exit();
    } else {
        echo "
        <html lang='ar'><body style='font-family: Cairo; text-align:center; margin-top:50px;'>
          <h2 style='color:red;'>❌ اسم المستخدم أو كلمة المرور غير صحيحة</h2>
          <a href='login.html' style='text-decoration:none; background:#0984e3; color:white; padding:10px 20px; border-radius:8px;'>رجوع</a>
        </body></html>
        ";
    }
}
?>
