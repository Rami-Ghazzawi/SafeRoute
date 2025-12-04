<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

header('Content-Type: text/html; charset=utf-8');
include 'db_connect.php'; // مفروض $conn هو mysqli

// GENERATE CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// إعدادات
$perPage = 20; // عدد السجلات بالصفحة
$allowed_statuses = ['سالكة','مزدحمة','مغلقة'];

// Helper: قراءة مدخلات (GET/POST) بأمان
function get_input($key, $default='') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
    } else {
        return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
    }
}

// ---------- AJAX: تحديث الحالة (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (get_input('action') === 'update_status')) {
    header('Content-Type: application/json; charset=utf-8');

    $incoming_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $incoming_csrf)) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'CSRF invalid']);
        exit();
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($id <= 0 || !in_array($status, $allowed_statuses)) {
        echo json_encode(['success'=>false,'message'=>'بيانات غير صالحة']);
        exit();
    }

    $u = $conn->prepare("UPDATE checkpoints_status SET status = ?, updated_at = NOW() WHERE id = ?");
    $u->bind_param("si", $status, $id);
    if ($u->execute()) {
        $u->close();

        // جلب إحصائيات سريعة
        $stats_q = "SELECT 
            COUNT(*) as total,
            SUM(status = 'سالكة') as open,
            SUM(status = 'مزدحمة') as busy,
            SUM(status = 'مغلقة') as closed
            FROM checkpoints_status";
        $s = $conn->prepare($stats_q);
        $s->execute();
        $res = $s->get_result()->fetch_assoc();
        $s->close();

        echo json_encode(['success'=>true,'message'=>'تم التحديث','stats'=>$res]);
    } else {
        $err = $u->error;
        $u->close();
        echo json_encode(['success'=>false,'message'=>"خطأ: $err"]);
    }
    exit();
}

// ---------- AJAX: جلب بيانات (فلتر + pagination) ----------
$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1');

$filter_area = isset($_GET['area']) ? trim($_GET['area']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) && intval($_GET['page'])>0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// بناء WHERE دايناميك مع prepared params
$where = [];
$params = [];
$types = '';

if ($filter_area !== '') {
    $where[] = "area = ?";
    $params[] = $filter_area;
    $types .= 's';
}
if ($filter_status !== '') {
    $where[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$where_sql = '';
if (!empty($where)) $where_sql = ' WHERE ' . implode(' AND ', $where);

// 1) COUNT الكلي
$count_sql = "SELECT COUNT(*) AS cnt FROM checkpoints_status" . $where_sql;
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    if ($isAjax) { echo json_encode(['success'=>false,'message'=>'DB error']); exit(); }
    die('DB prepare failed: ' . $conn->error);
}
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$totalRows = intval($count_stmt->get_result()->fetch_assoc()['cnt']);
$count_stmt->close();

$totalPages = max(1, ceil($totalRows / $perPage));

// 2) SELECT مع LIMIT/OFFSET — اختار أعمدة محددة عشان نخفف النقل
$select_sql = "SELECT id, name, location_name, area, checkpoint_type, status, created_at, updated_at 
               FROM checkpoints_status" . $where_sql . " 
               ORDER BY updated_at DESC, created_at DESC
               LIMIT ? OFFSET ?";

$select_stmt = $conn->prepare($select_sql);
if ($select_stmt === false) {
    if ($isAjax) { echo json_encode(['success'=>false,'message'=>'DB error']); exit(); }
    die('DB prepare failed: ' . $conn->error);
}

// bind params دايناميك (params + ii)
$bind_types = $types . 'ii';
$bind_vals = $params;
$bind_vals[] = $perPage;
$bind_vals[] = $offset;

if (!empty($params)) {
    $select_stmt->bind_param($bind_types, ...$bind_vals);
} else {
    $select_stmt->bind_param('ii', $perPage, $offset);
}

$select_stmt->execute();
$res = $select_stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}
$select_stmt->close();

// إذا طلب AJAX: رجع JSON بدل من HTML
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $rows,
        'pagination' => ['page'=>$page,'totalPages'=>$totalPages,'totalRows'=>$totalRows],
        'stats' => null // optional: client ممكن يطلب stats endpoint لو بدك
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// (initial load)
// ربط شروط ال where 
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
//  HTML OUTPUT 

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>لوحة التحكم - إدارة الحواجز</title>
  <link rel="stylesheet" href="admin-style.css">
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* خفيف للتسريع */
    tbody tr:nth-child(even){ background:#fbfcfe; }
    .status-dot{ display:inline-block; width:10px; height:10px; border-radius:50%; margin-left:8px; vertical-align:middle; }
    .dot-open{ background:#27ae60; } .dot-busy{ background:#f1c40f; } .dot-closed{ background:#e74c3c; }
    table { table-layout: fixed; } /* يساعد على رسم أسرع */
  </style>
</head>
<body>
<div class="admin-container">
  <div class="admin-header">
    <h1><i class="fas fa-road-barrier"></i>Safe Route | تتبع حالة الحواجز</h1>
    <p>إدارة وتتبع حالة الحواجز</p>
    <div class="header-buttons">
      <a href="dashboard.php" class="btn btn-warning"><i class="fas fa-chart-line"></i> الإحصائيات</a>
      <a href="homepage.php" class="btn btn-outline"><i class="fas fa-home"></i> الصفحة الرئيسية</a>
            <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>
  </div>

  <!-- فلترة (client-side triggers AJAX) -->
  <div class="filter-section">
    <form id="filterForm" onsubmit="return false;">
      <div class="filter-grid">
        <div class="form-group">
          <label for="area"><i class="fas fa-map-marker-alt"></i> اختر مدينة</label>
          <select id="area" name="area" class="form-control">
            <option value="">جميع المدن</option>
            <?php
              $areas = ['نابلس','رام الله','القدس','الخليل','جنين','قلقيلية','سلفيت','طولكرم'];
              foreach($areas as $a) {
                $sel = ($filter_area === $a) ? 'selected' : '';
                echo "<option value=\"".htmlspecialchars($a)."\" $sel>".htmlspecialchars($a)."</option>";
              }
            ?>
          </select>
        </div>

        <div class="form-group">
          <label for="status"><i class="fas fa-filter"></i> حالة الحاجز</label>
          <select id="status" name="status" class="form-control">
            <option value="">جميع الحالات</option>
            <?php foreach($allowed_statuses as $s){ $sel = ($filter_status === $s)?'selected':''; echo "<option value=\"".htmlspecialchars($s)."\" $sel>$s</option>"; } ?>
          </select>
        </div>

        <div class="form-group">
          <button id="applyBtn" class="btn btn-primary" style="height:44px;"><i class="fas fa-search"></i> تطبيق</button>
          <button id="resetBtn" class="btn btn-outline" style="height:44px;margin-right:0.5rem;">إعادة تعيين</button>
        </div>
      </div>
    </form>
  </div>

  <!-- الاحصائيات -->
<div class="stats-grid">
    <div class="stat-card stat-total">
        <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
        <div class="stat-label">إجمالي الحواجز</div>
    </div>
    <div class="stat-card stat-open">
        <div class="stat-number"><?= $stats['open'] ?? 0 ?></div>
        <div class="stat-label">سالكة</div>
    </div>
    <div class="stat-card stat-busy">
        <div class="stat-number"><?= $stats['busy'] ?? 0 ?></div>
        <div class="stat-label">مزدحمة</div>
    </div>
    <div class="stat-card stat-closed">
        <div class="stat-number"><?= $stats['closed'] ?? 0 ?></div>
        <div class="stat-label">مغلقة</div>
    </div>
</div>

  <!-- جدول النتائح -->
  <div class="table-container">
    <div class="table-header"><h3><i class="fas fa-list"></i> قائمة الحواجز</h3><p id="resultCount">عدد النتائج: <?= $totalRows ?></p></div>
    <table id="resultsTable">
      <thead>
        <tr>
          <th>اسم الحاجز</th><th>الموقع</th><th>المنطقة</th><th>النوع</th><th>الحالة</th><th>آخر تحديث</th><th>الإجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $row): 
          $dot = ($row['status']==='سالكة')?'dot-open':(($row['status']==='مزدحمة')?'dot-busy':'dot-closed');
        ?>
        <tr id="row-<?= intval($row['id']) ?>">
          <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
          <td><?= htmlspecialchars($row['location_name']) ?></td>
          <td><?= htmlspecialchars($row['area']) ?></td>
          <td><span class="type-badge"><?= htmlspecialchars($row['checkpoint_type']) ?></span></td>
          <td>
            <span class="status-badge"><?= htmlspecialchars($row['status']) ?></span>
            <span class="status-dot <?= $dot ?>"></span>
          </td>
          <td><small class="update-time"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['updated_at'] ?? $row['created_at']))) ?></small></td>
          <td>
            <select class="status-select" data-id="<?= intval($row['id']) ?>">
              <?php foreach($allowed_statuses as $s) { $sel = ($row['status']===$s)?'selected':''; echo "<option value=\"".htmlspecialchars($s)."\" $sel>$s</option>"; } ?>
            </select>
            <button class="btn btn-success btn-small update-btn" data-id="<?= intval($row['id']) ?>"><i class="fas fa-save"></i> تحديث</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- pagination minimal -->
    <div id="pagination" style="padding:1rem;text-align:center;">
      <?php if ($page > 1): ?>
        <button class="btn btn-outline page-btn" data-page="<?= $page-1 ?>">« السابق</button>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <button class="btn btn-outline page-btn" data-page="<?= $page+1 ?>">التالي »</button>
      <?php endif; ?>
      <div style="margin-top:8px;">صفحة <span id="currentPage"><?= $page ?></span> من <span id="totalPages"><?= $totalPages ?></span></div>
    </div>
  </div>
</div>

<script>
/* ======= Helper: debounce ======= */
function debounce(fn, delay){ let t; return function(...args){ clearTimeout(t); t = setTimeout(()=> fn.apply(this,args), delay); }; }

/* ======= AJAX load function ======= */
const perPage = <?= $perPage ?>;
let currentPage = <?= $page ?>;

function buildQuery(params) {
  const q = new URLSearchParams(params);
  return q.toString();
}

function renderRows(data) {
  const tbody = document.querySelector('#resultsTable tbody');
  tbody.innerHTML = ''; // نعيد بناء tbody بأسرع طريقة (DOM replace)
  for (const row of data) {
    const dotClass = row.status === 'سالكة' ? 'dot-open' : (row.status === 'مزدحمة' ? 'dot-busy' : 'dot-closed');
    const updatedAt = row.updated_at ? row.updated_at : row.created_at;
    const tr = document.createElement('tr');
    tr.id = 'row-' + row.id;
    tr.innerHTML = `
      <td><strong>${escapeHTML(row.name)}</strong></td>
      <td>${escapeHTML(row.location_name)}</td>
      <td>${escapeHTML(row.area)}</td>
      <td><span class="type-badge">${escapeHTML(row.checkpoint_type)}</span></td>
      <td><span class="status-badge">${escapeHTML(row.status)}</span><span class="status-dot ${dotClass}"></span></td>
      <td><small class="update-time">${escapeHTML(formatDate(updatedAt))}</small></td>
      <td>
        <select class="status-select" data-id="${row.id}">
          <option value="سالكة" ${row.status==='سالكة'?'selected':''}>سالكة</option>
          <option value="مزدحمة" ${row.status==='مزدحمة'?'selected':''}>مزدحمة</option>
          <option value="مغلقة" ${row.status==='مغلقة'?'selected':''}>مغلقة</option>
        </select>
        <button class="btn btn-success btn-small update-btn" data-id="${row.id}"><i class="fas fa-save"></i> تحديث</button>
      </td>
    `;
    tbody.appendChild(tr);
  }
}

function escapeHTML(s){ return (s===null||s===undefined)?'': String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

function formatDate(datetimeStr){
  if(!datetimeStr) return '';
  return datetimeStr.replace('T',' ').split('.')[0];
}

function ajaxLoad(page=1) {
  const area = document.getElementById('area').value;
  const status = document.getElementById('status').value;
  currentPage = page;

  const q = buildQuery({ ajax: 1, page: page, area: area, status: status });
  fetch('?' + q, { cache: 'no-store' })
    .then(r => r.json())
    .then(json => {
      if (!json.success) { alert('خطأ بجلب البيانات'); return; }
      renderRows(json.data);
      // pagination & counts
      document.getElementById('resultCount').textContent = 'عدد النتائج: ' + json.pagination.totalRows;
      document.getElementById('currentPage').textContent = json.pagination.page;
      document.getElementById('totalPages').textContent = json.pagination.totalPages;
      // إعادة توصيل أزرار التحديث بعد إعادة الرسم
      attachUpdateButtons();
      attachPageButtons();
    })
    .catch(e => console.error(e));
}

/* attach page buttons  */
function attachPageButtons(){
  document.querySelectorAll('.page-btn').forEach(b => {
    b.onclick = function(){ ajaxLoad(parseInt(this.dataset.page)); };
  });
}

/*  تحديث الحالة (AJAX)*/
function attachUpdateButtons(){
  document.querySelectorAll('.update-btn').forEach(btn => {
    btn.onclick = function(){
      const id = this.dataset.id;
      const row = document.getElementById('row-' + id);
      const select = row.querySelector('.status-select');
      const status = select.value;
      const orig = this.innerHTML;
      this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري...';
      this.disabled = true;

      const body = new URLSearchParams();
      body.append('action','update_status');
      body.append('id', id);
      body.append('status', status);
      body.append('csrf_token', '<?= $csrf_token ?>');

      fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            // تحديث المظهر محلياً
            const badge = row.querySelector('.status-badge');
            badge.textContent = status;
            const dot = row.querySelector('.status-dot');
            dot.className = 'status-dot ' + (status === 'سالكة' ? 'dot-open' : (status === 'مزدحمة' ? 'dot-busy' : 'dot-closed'));
            const timeEl = row.querySelector('.update-time');
            const now = new Date();
            timeEl.textContent = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0') + ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');

            // لو رجع السيرفر stats حدثهم
            if (data.stats) {
              document.getElementById('stat-total').textContent = data.stats.total;
              document.getElementById('stat-open').textContent = data.stats.open;
              document.getElementById('stat-busy').textContent = data.stats.busy;
              document.getElementById('stat-closed').textContent = data.stats.closed;
            }
          } else {
            alert('خطأ: ' + (data.message || 'فشل'));
          }
        })
        .catch(()=> alert('خطأ بالشبكة'))
        .finally(()=> { this.innerHTML = orig; this.disabled = false; });
    };
  });
}

/* ======= Bind events ======= */
document.getElementById('applyBtn').addEventListener('click', ()=> ajaxLoad(1));
document.getElementById('resetBtn').addEventListener('click', ()=> {
  document.getElementById('area').value = '';
  document.getElementById('status').value = '';
  ajaxLoad(1);
});

// debounced auto-filter on change (100ms)
document.getElementById('area').addEventListener('change', debounce(()=> ajaxLoad(1), 150));
document.getElementById('status').addEventListener('change', debounce(()=> ajaxLoad(1), 150));

attachUpdateButtons();
attachPageButtons();

</script>
</body>
</html>
<?php
// اغلاق الاتصال
$conn->close();
?>
