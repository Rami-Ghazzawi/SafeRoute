<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "checkpoints_db";

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    // محاولة إنشاء قاعدة البيانات إذا لم تكن موجودة
    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) {
        die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
    }
    
    // إنشاء قاعدة البيانات
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === TRUE) {
        $conn->select_db($dbname);
    } else {
        die("فشل في إنشاء قاعدة البيانات: " . $conn->error);
    }
}

// إنشاء جدول الحواجز إذا لم يكن موجوداً
$sql = "CREATE TABLE IF NOT EXISTS checkpoints_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    location_name VARCHAR(255) NOT NULL,
    area VARCHAR(100) NOT NULL,
    status ENUM('سالكة', 'مزدحمة', 'مغلقة') NOT NULL,
    checkpoint_type ENUM('دائم', 'مؤقت', 'عسكري') DEFAULT 'دائم',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sql)) {
    die("خطأ في إنشاء جدول الحواجز: " . $conn->error);
}

// إنشاء جدول المستخدمين إذا لم يكن موجوداً
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sql)) {
    die("خطأ في إنشاء جدول المستخدمين: " . $conn->error);
}

// إضافة المستخدمين الافتراضيين
$sql = "INSERT IGNORE INTO users (username, password) VALUES 
        ('admin', MD5('12345')),
        ('rami', MD5('palestine'))";
$conn->query($sql);

// التحقق مما إذا كانت الحواجز موجودة مسبقاً قبل الإضافة
$check_sql = "SELECT COUNT(*) as count FROM checkpoints_status";
$result = $conn->query($check_sql);
$row = $result->fetch_assoc();

// إضافة الحواجز الافتراضية فقط إذا لم تكن موجودة مسبقاً
// إضافة 52 حاجز افتراضي إذا لم تكن موجودة
$defaultCheckpoints = [
    // نابلس (8 حواجز)
    ['دير شرف', 'دير شرف', 'نابلس', 'سالكة', 'دائم'],
    ['شافي شمرون', 'شافي شمرون', 'نابلس', 'مزدحمة', 'عسكري'],
    ['بيت فوريك', 'بيت فوريك', 'نابلس', 'سالكة', 'دائم'],
    ['المربعة', 'المربعة', 'نابلس', 'مغلقة', 'دائم'],
    ['عورتا', 'عورتا', 'نابلس', 'سالكة', 'دائم'],
    ['عصيرة', 'عصيرة', 'نابلس', 'مزدحمة', 'دائم'],
    ['صرة', 'صرة', 'نابلس', 'سالكة', 'عسكري'],
    ['حوارة', 'حوارة', 'نابلس', 'مغلقة', 'دائم'],
    
    // رام الله (4 حواجز)
    ['الجلزون', 'الجلزون', 'رام الله', 'سالكة', 'عسكري'],
    ['DCO', 'DCO', 'رام الله', 'مزدحمة', 'دائم'],
    ['عين سينيا', 'عين سينيا', 'رام الله', 'سالكة', 'دائم'],
    ['بوابة عطارة', 'بوابة عطارة', 'رام الله', 'مزدحمة', 'دائم'],
    
    // طولكرم (7 حواجز)
    ['بزاريا', 'بزاريا', 'طولكرم', 'سالكة', 'دائم'],
    ['عناب', 'عناب', 'طولكرم', 'مزدحمة', 'دائم'],
    ['سناعوز', 'سناعوز', 'طولكرم', 'سالكة', 'عسكري'],
    ['إيال', 'إيال', 'طولكرم', 'مغلقة', 'دائم'],
    ['جبارة', 'جبارة', 'طولكرم', 'سالكة', 'دائم'],
    ['قفين', 'قفين', 'طولكرم', 'مزدحمة', 'دائم'],
    ['كفر اللبد', 'كفر اللبد', 'طولكرم', 'سالكة', 'دائم'],
    
    // القدس (10 حواجز)
    ['كفر عقب', 'كفر عقب', 'القدس', 'مزدحمة', 'دائم'],
    ['قلنديا', 'قلنديا', 'القدس', 'مزدحمة', 'دائم'],
    ['جبع', 'جبع', 'القدس', 'سالكة', 'دائم'],
    ['حزمة', 'حزمة', 'القدس', 'مغلقة', 'عسكري'],
    ['عناتا', 'عناتا', 'القدس', 'سالكة', 'دائم'],
    ['الرام', 'الرام', 'القدس', 'مزدحمة', 'دائم'],
    ['معالي ادوميم', 'معالي ادوميم', 'القدس', 'مغلقة', 'عسكري'],
    ['العيزرية', 'العيزرية', 'القدس', 'سالكة', 'دائم'],
    ['العيساوية', 'العيساوية', 'القدس', 'مزدحمة', 'دائم'],
    ['شعفاط', 'شعفاط', 'القدس', 'سالكة', 'دائم'],
    
    // الخليل (7 حواجز)
    ['راس الجورة', 'راس الجورة', 'الخليل', 'سالكة', 'دائم'],
    ['فرش الهوى', 'فرش الهوى', 'الخليل', 'مزدحمة', 'دائم'],
    ['الفحص', 'الفحص', 'الخليل', 'مزدحمة', 'دائم'],
    ['جسر حلحول', 'جسر حلحول', 'الخليل', 'سالكة', 'دائم'],
    ['العروب', 'العروب', 'الخليل', 'مغلقة', 'عسكري'],
    ['الظاهرية', 'الظاهرية', 'الخليل', 'سالكة', 'دائم'],
    ['بني نعيم', 'بني نعيم', 'الخليل', 'مزدحمة', 'دائم'],
    
    // جنين (4 حواجز)
    ['دوتان', 'دوتان', 'جنين', 'سالكة', 'عسكري'],
    ['الجلمة', 'الجلمة', 'جنين', 'مغلقة', 'دائم'],
    ['حومش', 'حومش', 'جنين', 'سالكة', 'دائم'],
    ['برطعة', 'برطعة', 'جنين', 'مزدحمة', 'دائم'],
    
    // قلقيلية (6 حواجز)
    ['عزون', 'عزون', 'قلقيلية', 'سالكة', 'دائم'],
    ['الفندق', 'الفندق', 'قلقيلية', 'مزدحمة', 'دائم'],
    ['كدوميم', 'كدوميم', 'قلقيلية', 'مغلقة', 'عسكري'],
    ['كفر لاقف', 'كفر لاقف', 'قلقيلية', 'سالكة', 'دائم'],
    ['النبي الياس', 'النبي الياس', 'قلقيلية', 'مزدحمة', 'دائم'],
    ['حبلة', 'حبلة', 'قلقيلية', 'سالكة', 'دائم'],
    
    // سلفيت (6 حواجز)
    ['ديراستيا', 'ديراستيا', 'سلفيت', 'سالكة', 'دائم'],
    ['بوابة حارس', 'بوابة حارس', 'سلفيت', 'مغلقة', 'عسكري'],
    ['دير بلوط', 'دير بلوط', 'سلفيت', 'سالكة', 'دائم'],
    ['مردا', 'مردا', 'سلفيت', 'مزدحمة', 'دائم'],
    ['ارائيل', 'ارائيل', 'سلفيت', 'مغلقة', 'عسكري'],
    ['زعترة', 'زعترة', 'سلفيت', 'سالكة', 'دائم']
];

// تنظيف البيانات المكررة أولاً
$conn->query("DELETE FROM checkpoints_status WHERE name IN (
    SELECT name FROM (
        SELECT name, COUNT(*) as cnt 
        FROM checkpoints_status 
        GROUP BY name 
        HAVING cnt > 1
    ) AS duplicates
)");

foreach ($defaultCheckpoints as $checkpoint) {
    $sql = "INSERT IGNORE INTO checkpoints_status (name, location_name, area, status, checkpoint_type) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $checkpoint[0], $checkpoint[1], $checkpoint[2], $checkpoint[3], $checkpoint[4]);
    $stmt->execute();
    $stmt->close();
}