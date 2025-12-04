<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

include 'db_connect.php';

// جلب الإحصائيات
$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = " WHERE " . implode(" AND ", $where_conditions);
}

$stats_sql = "SELECT
    COUNT(DISTINCT name) as total,
    COUNT(DISTINCT CASE WHEN status = 'سالكة' THEN name END) as open,
    COUNT(DISTINCT CASE WHEN status = 'مزدحمة' THEN name END) as busy,
    COUNT(DISTINCT CASE WHEN status = 'مغلقة' THEN name END) as closed
    FROM checkpoints_status";


$stats_stmt = $conn->prepare($stats_sql);

if ($stats_stmt) {
    if (!empty($params)) $stats_stmt->bind_param($types, ...$params);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    $stats_stmt->close();
} else {
    $stats = ['total'=>0,'open'=>0,'busy'=>0,'closed'=>0];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - إحصائيات إضافية</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1><i class="fas fa-chart-line"></i> لوحة التحكم - الإحصائيات إضافية</h1>
        <div class="header-buttons">
            <a href="homepage.php" class="btn"><i class="fas fa-map"></i> الخريطة</a>
            <a href="admin.php" class="btn"><i class="fas fa-cog"></i> إدارة الحواجز</a>
            <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
        </div>
    </header>

    <div class="container" style="flex-direction: column; padding: 2rem;">
        <!-- بطاقات الإحصائيات -->
 <div class="stats-container" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 2rem;">
    <div class="stat-card stat-total">
        <div class="stat-number"><?= $stats['total'] ?></div>
        <div class="stat-label">إجمالي الحواجز</div>
    </div>
    <div class="stat-card stat-open">
        <div class="stat-number"><?= $stats['open'] ?></div>
        <div class="stat-label">سالكة</div>
    </div>
    <div class="stat-card stat-busy">
        <div class="stat-number"><?= $stats['busy'] ?></div>
        <div class="stat-label">مزدحمة</div>
    </div>
    <div class="stat-card stat-closed">
        <div class="stat-number"><?= $stats['closed'] ?></div>
        <div class="stat-label">مغلقة</div>
    </div>
</div>

        <!-- الرسوم البيانية -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div class="table-wrapper">
                <div style="padding: 1.5rem;">
                    <h3 style=
                    "margin-bottom: 1rem; color: var(--secondary);">
                        <i class="fas fa-chart-pie"></i> توزيع الحالات
                    </h3>
                    <canvas id="statusChart" width="400" height="300"></canvas>
                </div>
            </div>
            <div class="table-wrapper">
                <div style="padding: 1.5rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--secondary);">
                        <i class="fas fa-chart-bar"></i> النسب المئوية
                    </h3>
                    <canvas id="percentageChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- آخر التحديثات -->
        <div class="table-wrapper">
            <div style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem; color: var(--secondary);">
                    <i class="fas fa-history"></i> آخر التحديثات
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>اسم الحاجز</th>
                            <th>الحالة</th>
                            <th>آخر تحديث</th>
                            <th>المدة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent = $conn->query("SELECT * FROM checkpoints_status ORDER BY created_at DESC LIMIT 10");
                        while ($row = $recent->fetch_assoc()):
                            $timeAgo = time_elapsed_string($row['created_at']);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td>
                                <span class="status-<?= 
                                    $row['status'] === 'سالكة' ? 'open' : 
                                    ($row['status'] === 'مزدحمة' ? 'busy' : 'closed') 
                                ?>"><?= $row['status'] ?></span>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                            <td><?= $timeAgo ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // الدائرة البيانية
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['سالكة', 'مزدحمة', 'مغلقة'],
                datasets: [{
                    data: [<?= $stats['open'] ?>, <?= $stats['busy'] ?>, <?= $stats['closed'] ?>],
                    backgroundColor: ['#00b894', '#fdcb6e', '#d63031'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        rtl: true
                    }
                }
            }
        });

       // الجدول البياني 
const percentageCtx = document.getElementById('percentageChart').getContext('2d');
const percentageChart = new Chart(percentageCtx, {
    type: 'bar',
    data: {
        labels: ['سالكة', 'مزدحمة', 'مغلقة'],
        datasets: [{
            label: 'النسبة المئوية',
            data: [
                <?= $stats['total'] > 0 ? round(($stats['open'] / $stats['total']) * 100) : 0 ?>,
                <?= $stats['total'] > 0 ? round(($stats['busy'] / $stats['total']) * 100) : 0 ?>,
                <?= $stats['total'] > 0 ? round(($stats['closed'] / $stats['total']) * 100) : 0 ?>
            ],
            backgroundColor: ['#00b894', '#fdcb6e', '#d63031'],
            borderWidth: 0
        }]
    }, // ← COMMA ADDED HERE
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});
    </script>
</body>
</html>

<?php
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    //$diff->d -= $diff->w *7;

    $string = array(
        'y' => 'سنة',
        'm' => 'شهر',
        'w' => 'أسبوع',
        'd' => 'يوم',
        'h' => 'ساعة',
        'i' => 'دقيقة',
        's' => 'ثانية',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'منذ ' . implode(', ', $string) : 'الآن';
}
?>