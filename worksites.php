<?php
/** worksites.php — จุดหน้างานติดตั้ง (สำหรับ geofencing) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('hr.manage');
    csrf_verify();
    $name = input('site_name');
    $lat  = input('latitude');
    $lon  = input('longitude');
    if ($name === '' || $lat === '' || $lon === '') {
        flash('error', 'กรุณากรอกชื่อไซต์และพิกัด');
    } else {
        Database::run(
            'INSERT INTO work_sites (site_name, latitude, longitude, allowed_radius_m, assigned_team)
             VALUES (:n,:lat,:lon,:r,:team)',
            ['n'=>$name,'lat'=>(float)$lat,'lon'=>(float)$lon,
             'r'=>(int)(input('allowed_radius_m')?:150),'team'=>input('assigned_team')?:null]
        );
        flash('success', 'เพิ่มจุดหน้างานเรียบร้อย');
    }
    redirect('worksites.php');
}

$sites = Database::all('SELECT * FROM work_sites ORDER BY id DESC');

$pageTitle = 'จุดหน้างาน';
$activeNav = 'worksites';
require __DIR__ . '/app/layout_header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div class="page-header">
  <div><h1>จุดหน้างาน (Geofencing)</h1><p>กำหนดพิกัดและรัศมีสำหรับการเช็คอินหน้างาน</p></div>
  <?php if (Auth::can('hr.manage')): ?>
    <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-map-pin"></i> เพิ่มจุดหน้างาน</button>
  <?php endif; ?>
</div>

<?php if (Auth::can('hr.manage')): ?>
<div class="card hidden" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">เพิ่มจุดหน้างาน</div></div>
  <form method="post"><?= csrf_field() ?>
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อไซต์ *</label><input class="form-input" name="site_name" required></div>
      <div class="form-group"><label class="form-label">รัศมีที่อนุญาต (เมตร)</label><input class="form-input" type="number" name="allowed_radius_m" value="150"></div>
      <div class="form-group"><label class="form-label">ทีมที่รับผิดชอบ</label><input class="form-input" name="assigned_team" placeholder="A"></div>
      <div class="form-group"><label class="form-label">ละติจูด (latitude) *</label><input class="form-input" name="latitude" placeholder="12.9236" required></div>
      <div class="form-group"><label class="form-label">ลองจิจูด (longitude) *</label><input class="form-input" name="longitude" placeholder="100.8824" required></div>
      <div class="form-group" style="grid-column:span 2;align-self:end;">
        <button type="button" class="btn btn-ghost btn-block" onclick="useMyLocation()"><i class="fa-solid fa-location-crosshairs"></i> ใช้ตำแหน่งปัจจุบันของฉัน</button>
      </div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<script>
function useMyLocation(){
  if(!navigator.geolocation){alert('เบราว์เซอร์ไม่รองรับ GPS');return;}
  navigator.geolocation.getCurrentPosition(p=>{
    document.querySelector('[name=latitude]').value = p.coords.latitude.toFixed(7);
    document.querySelector('[name=longitude]').value = p.coords.longitude.toFixed(7);
  }, e=>alert('ดึงตำแหน่งไม่ได้: '+e.message), {enableHighAccuracy:true});
}
</script>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;padding:0;overflow:hidden;">
  <div id="siteMap" style="height:380px;width:100%;background:var(--bg-base);"></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>ไซต์</th><th>พิกัด</th><th>รัศมี</th><th>ทีม</th><th>สถานะ</th></tr></thead>
      <tbody>
        <?php foreach ($sites as $s): ?>
          <tr>
            <td style="font-weight:600;"><?= e($s['site_name']) ?></td>
            <td class="mono text-muted" style="font-size:12px;"><?= e($s['latitude']) ?>, <?= e($s['longitude']) ?></td>
            <td class="mono"><?= (int)$s['allowed_radius_m'] ?> ม.</td>
            <td><?= $s['assigned_team'] ? '<span class="badge badge-blue">ทีม '.e($s['assigned_team']).'</span>' : '-' ?></td>
            <td><span class="badge <?= $s['is_active']?'badge-green':'badge-muted' ?>"><?= $s['is_active']?'ใช้งาน':'ปิด' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}
#siteMap { border-radius: var(--radius); }
.leaflet-popup-content { font-family: 'Prompt', sans-serif; font-size: 13px; }
</style>
<script>
const SITES = <?= json_encode(array_map(fn($s) => [
    'name' => $s['site_name'], 'lat' => (float)$s['latitude'], 'lng' => (float)$s['longitude'],
    'radius' => (int)$s['allowed_radius_m'], 'team' => $s['assigned_team']
], $sites), JSON_UNESCAPED_UNICODE) ?>;

const map = L.map('siteMap');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19, attribution: '© OpenStreetMap'
}).addTo(map);

const bounds = [];
SITES.forEach(s => {
  L.marker([s.lat, s.lng]).addTo(map)
    .bindPopup(`<strong>${s.name}</strong><br>รัศมี ${s.radius} ม.${s.team?' · ทีม '+s.team:''}`);
  L.circle([s.lat, s.lng], { radius: s.radius, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.12, weight: 2 }).addTo(map);
  bounds.push([s.lat, s.lng]);
});
if (bounds.length) map.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
else map.setView([13.7563, 100.5018], 6); // กรุงเทพฯ

// คลิกบนแผนที่ → เติมพิกัดในฟอร์มเพิ่มจุด (ถ้าเปิดอยู่)
map.on('click', e => {
  const latI = document.querySelector('[name=latitude]'), lonI = document.querySelector('[name=longitude]');
  if (latI && lonI) {
    latI.value = e.latlng.lat.toFixed(7);
    lonI.value = e.latlng.lng.toFixed(7);
    const form = document.getElementById('addForm');
    if (form && form.classList.contains('hidden')) form.classList.remove('hidden');
    L.popup().setLatLng(e.latlng).setContent('📍 ตั้งเป็นจุดใหม่ในฟอร์มแล้ว').openOn(map);
  }
});
</script>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
