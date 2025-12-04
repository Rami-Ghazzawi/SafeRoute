// ============================
// 1️⃣ الإحداثيات
// ============================
let checkpointCoordinates = {};

function loadCheckpointCoordinates() {
  return fetch("checkpoint_coordinates.php")
    .then((res) => res.json())
    .then((data) => {
      checkpointCoordinates = data;
    })
    .catch(() => {
      checkpointCoordinates = {
        "دير شرف": [32.2856, 35.1987],
        الجلزون: [31.9567, 35.2189],
        قلنديا: [31.8678, 35.2189],
        "راس الجورة": [31.5326, 35.0998],
      };
    });
}

// ============================
// 2️⃣ إنشاء الخريطة
// ============================
var map = L.map("map").setView([31.9466, 35.3027], 10);

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  maxZoom: 18,
}).addTo(map);

// ============================
// 3️⃣ المتغيرات
// ============================
let checkpointsData = [];
let markers = [];

// ============================
// 4️⃣ تحميل الحواجز
// ============================
function loadCheckpoints() {
  fetch("data.php")
    .then((res) => res.json())
    .then((data) => {
      checkpointsData = data;
      drawMarkers(data);
      updateStatistics(data);
      updateLastUpdate();
    });
}

// ============================
// 5️⃣ رسم الماركرز
// ============================
function drawMarkers(data) {
  markers.forEach((marker) => map.removeLayer(marker));
  markers = [];

  data.forEach((cp) => {
    const coords = checkpointCoordinates[cp.name] || [31.9466, 35.3027];

    const marker = L.marker(coords).addTo(map);

    marker.bindPopup(`
      <b>${cp.name}</b><br>
      المنطقة: ${cp.area}<br>
      الحالة: ${cp.status}<br>
      آخر تحديث: ${new Date(cp.created_at).toLocaleString("ar-EG")}
    `);

    markers.push(marker);
  });
}

// ============================
// 6️⃣ الإحصائيات
// ============================
function updateStatistics(data) {
  document.getElementById("totalCheckpoints").textContent = data.length;

  document.getElementById("openCheckpoints").textContent = data.filter(
    (c) => c.status === "سالكة"
  ).length;

  document.getElementById("closedCheckpoints").textContent = data.filter(
    (c) => c.status === "مغلقة"
  ).length;

  document.getElementById("activeCheckpoints").textContent = data.filter(
    (c) => c.status === "سالكة" || c.status === "مزدحمة"
  ).length;
}

// ============================
// 7️⃣ وقت آخر تحديث
// ============================
function updateLastUpdate() {
  document.getElementById("lastUpdate").textContent = new Date().toLocaleString(
    "ar-EG"
  );
}

// ============================
// 8️⃣ الفلترة
// ============================
function applyFilters() {
  const area = document.getElementById("filterArea").value;
  const status = document.getElementById("filterStatus").value;

  let filtered = checkpointsData;

  if (area) filtered = filtered.filter((cp) => cp.area === area);
  if (status) filtered = filtered.filter((cp) => cp.status === status);

  drawMarkers(filtered);
  updateStatistics(filtered);

  document.getElementById("currentArea").textContent = area || "جميع المناطق";
}

function resetFilters() {
  document.getElementById("filterArea").value = "";
  document.getElementById("filterStatus").value = "";

  drawMarkers(checkpointsData);
  updateStatistics(checkpointsData);

  document.getElementById("currentArea").textContent = "جميع المناطق";
}

// ============================
// 9️⃣ إضافة حاجز جديد ✅ (مرة وحدة فقط)
// ============================
const form = document.getElementById("checkpointForm");

form.addEventListener("submit", function (e) {
  e.preventDefault();

  const formData = new FormData();
  formData.append("name", name.value);
  formData.append("location_name", location_name.value);
  formData.append("area", area.value);
  formData.append("checkpoint_type", checkpoint_type.value);
  formData.append("status", status.value);

  fetch("add_checkpoint.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      alert("✅ تم إضافة الحاجز بنجاح");
      loadCheckpoints();
      form.reset();
    })
    .catch(() => {
      alert("❌ حدث خطأ أثناء الإضافة");
    });
});

// ============================
// 🔟 تشغيل عند فتح الصفحة
// ============================
document.addEventListener("DOMContentLoaded", function () {
  loadCheckpointCoordinates().then(() => {
    loadCheckpoints();
  });
});

// ============================
// ⏱️ تحديث تلقائي كل دقيقة
// ============================
setInterval(loadCheckpoints, 60000);
