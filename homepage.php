<!DOCTYPE html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>🚧 أحوال الحواجز في فلسطين - نظام متكامل</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
  </head>
  <body>
    <header>
      <h1><i class="fas fa-road-barrier"></i> نظام متابعة أحوال الحواجز في فلسطين</h1>
      <div class="header-buttons">
        <a href="admin.php" class="btn"><i class="fas fa-cog"></i> لوحة التحكم</a>
            <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
      </div>
    </header>

    <main>
      <div class="container">
        <div class="map-container">
          <!-- شريط البحث -->
          <div class="search-container fade-in">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 ابحث عن حاجز..." />
            <div id="searchResults" class="search-results"></div>
          </div>
          
          <div id="map"></div>
          
          <!-- وسيلة الإيضاح المحسنة -->
          <div class="map-legend slide-in">
            <h4 style="margin-bottom: 1rem; color: var(--secondary); border-bottom: 1px solid #eee; padding-bottom: 0.5rem;">
              <i class="fas fa-key"></i> مفتاح الحالات
            </h4>
            <div class="legend-item">
              <div class="color-box" style="background-color: #00b894;"></div>
              <span>سالك <small>(مفتوحة)</small></span>
            </div>
            <div class="legend-item">
              <div class="color-box" style="background-color: #fdcb6e;"></div>
              <span>مزدحمة <small>(بطيئة)</small></span>
            </div>
            <div class="legend-item">
              <div class="color-box" style="background-color: #d63031;"></div>
              <span>مغلقة <small>(مقطوعة)</small></span>
            </div>
          </div>
        </div>

        <div class="form-container fade-in">
          <!-- احصائيات سريعة -->
         <div class="stats-container" id="statsContainer">
    <div class="stat-card stat-total">
        <div class="stat-number" id="totalCheckpoints">0</div>
        <div class="stat-label">إجمالي الحواجز</div>
    </div>
    <div class="stat-card stat-open">
        <div class="stat-number" id="openCheckpoints">0</div>
        <div class="stat-label">سالكة</div>
    </div>
    <div class="stat-card stat-closed">
        <div class="stat-number" id="closedCheckpoints">0</div>
        <div class="stat-label">مغلقة</div>
    </div>
</div>

          <h2><i class="fas fa-plus-circle"></i> إضافة حاجز جديد</h2>
          
          <form id="checkpointForm">
            <div class="form-group">
              <label for="name"><i class="fas fa-road-barrier"></i> اسم الحاجز</label>
              <input type="text" id="name" name="name" placeholder="أدخل اسم الحاجز" required />
            </div>

            <div class="form-group">
              <label for="location_name"><i class="fas fa-map-marker-alt"></i> الموقع</label>
              <input type="text" id="location_name" name="location_name" placeholder="أدخل موقع الحاجز" required />
            </div>
            <div class="form-group">
              <label for="area"><i class="fas fa-city"></i> المنطقة</label>
              <select id="area" name="area" required>
                <option value="">اختر المنطقة</option>
                <option value="القدس">القدس</option>
                <option value="رام الله">رام الله</option>
                <option value="بيت لحم">بيت لحم</option>
                <option value="الخليل">الخليل</option>
                <option value="نابلس">نابلس</option>
                <option value="أريحا">أريحا</option>
                <option value="طولكرم">طولكرم</option>
                <option value="قلقيلية">قلقيلية</option>
                <option value="سلفيت">سلفيت</option>
                <option value="طوباس">طوباس</option>
                <option value="جنين">جنين</option>
                <option value="غزة">غزة</option>
                <option value="رفح">رفح</option>
                <option value="خان يونس">خان يونس</option>
                <option value="دير البلح">دير البلح</option>
              </select>
            </div>

            <!-- إضافة حقل نوع الحاجز -->
            <div class="form-group">
              <label for="checkpoint_type"><i class="fas fa-tag"></i> نوع الحاجز</label>
              <select id="checkpoint_type" name="checkpoint_type" required>
                <option value="دائم">دائم</option>
                <option value="مؤقت">مؤقت</option>
                <option value="عسكري">عسكري</option>
              </select>
            </div>

            <div class="form-group">
              <label for="status"><i class="fas fa-traffic-light"></i> الحالة</label>
              <select id="status" name="status" required>
                <option value="">اختر حالة الحاجز</option>
                <option value="سالكة">سالكة</option>
                <option value="مزدحمة">مزدحمة</option>
                <option value="مغلقة">مغلقة</option>
              </select>
            </div>

            <button type="submit" class="btn pulse" style="width: 100%; margin-top: 1rem;">
              <i class="fas fa-plus"></i> إضافة الحاجز
            </button>
          </form>

          <!-- معلومات سريعة -->
          <div style="margin-top: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: var(--radius);">
            <h4 style="margin-bottom: 1rem; color: var(--secondary); display: flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-info-circle"></i> معلومات سريعة
            </h4>
            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
              <div style="display: flex; justify-content: space-between;">
                <span>آخر تحديث:</span>
                <span id="lastUpdate" style="font-weight: 600;">--</span>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span>الحواجز النشطة:</span>
                <span id="activeCheckpoints" style="font-weight: 600;">--</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- الفوتر -->
    <footer style="background: var(--secondary); color: white; text-align: center; padding: 1.5rem; margin-top: 2rem;">
      <div style="max-width: 1200px; margin: 0 auto;">
        <p>🚧Safe Route Web Application By Rami Ghazzawi and Ahmed Nasrallah</p>
      </div>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="script.js"></script>
  </body>
</html>

