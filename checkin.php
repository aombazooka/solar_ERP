<?php
/** checkin.php — เช็คอินหน้างาน (Geofencing)
 *  ⚠️ client ส่งแค่พิกัดดิบ → server คำนวณระยะเอง (กันปลอม) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.checkin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $empId  = (int) input('employee_id');
        $siteId = (int) input('site_id');
        $lat    = input('lat');
        $lon    = input('lon');
        $acc    = input('accuracy');

        if ($empId <= 0 || $siteId <= 0 || $lat === '' || $lon === '') {
            throw new RuntimeException('ข้อมูลไม่ครบ — ต้องอนุญาตการเข้าถึง GPS');
        }
        $site = Database::one('SELECT * FROM work_sites WHERE id=:id AND is_active=1', ['id' => $siteId]);
        if (!$site) throw new RuntimeException('ไม่พบจุดหน้างาน');

        // ── คำนวณระยะที่ SERVER เท่านั้น ──
        [$distance, $status] = Geo::evaluate(
            (float) $lat, (float) $lon,
            (float) $site['latitude'], (float) $site['longitude'],
            (int) $site['allowed_radius_m'],
            $acc !== '' ? (float) $acc : null
        );

        // ── จัดเก็บรูป (PDPA: นอก public + จำกัดสิทธิ์) ──
        $photoPath = null;
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            if (isset($allowed[$mime]) && $_FILES['photo']['size'] <= 8 * 1024 * 1024) {
                $dir = __DIR__ . '/storage/uploads/checkins';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $fname = 'chk_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fname)) {
                    $photoPath = 'storage/uploads/checkins/' . $fname;
                }
            }
        }

        Database::run(
            'INSERT INTO site_checkins
                (employee_id, site_id, checkin_lat, checkin_long, distance_from_site_m, gps_accuracy_m, photo_path, status, device_info)
             VALUES (:emp,:site,:lat,:lon,:dist,:acc,:photo,:st,:dev)',
            ['emp'=>$empId,'site'=>$siteId,'lat'=>(float)$lat,'lon'=>(float)$lon,
             'dist'=>$distance,'acc'=>$acc!==''?(float)$acc:null,'photo'=>$photoPath,
             'st'=>$status,'dev'=>substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)]
        );

        [$lbl] = Geo::statusLabel($status);
        flash($status === 'approved' ? 'success' : ($status === 'out_of_range' ? 'error' : 'info'),
            "เช็คอินบันทึกแล้ว: {$lbl} (ห่างจากไซต์ " . number_format($distance) . " ม.)");
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('checkin.php');
}

$employees = Database::all('SELECT id, code, name FROM employees WHERE is_active=1 ORDER BY name');
$sites     = Database::all('SELECT id, site_name, allowed_radius_m FROM work_sites WHERE is_active=1 ORDER BY site_name');
$recent = Database::all(
    'SELECT sc.*, e.name AS emp_name, ws.site_name FROM site_checkins sc
     JOIN employees e ON e.id=sc.employee_id JOIN work_sites ws ON ws.id=sc.site_id
     ORDER BY sc.id DESC LIMIT 15'
);

$pageTitle = 'เช็คอินหน้างาน';
$activeNav = 'checkin';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>เช็คอินหน้างาน</h1><p>ระบบคำนวณระยะที่เซิร์ฟเวอร์ (Haversine) เพื่อความปลอดภัย</p></div>
</div>

<div class="grid g21">
  <div class="card" style="align-self:start;">
    <div class="card-head"><div class="card-title">บันทึกการเช็คอิน</div></div>
    <form method="post" enctype="multipart/form-data" id="checkinForm">
      <?= csrf_field() ?>
      <input type="hidden" name="lat" id="lat">
      <input type="hidden" name="lon" id="lon">
      <input type="hidden" name="accuracy" id="accuracy">

      <div class="form-group">
        <label class="form-label">พนักงาน *</label>
        <select class="form-select" name="employee_id" required>
          <option value="">— เลือกพนักงาน —</option>
          <?php foreach ($employees as $emp): ?><option value="<?= (int)$emp['id'] ?>"><?= e($emp['code']) ?> · <?= e($emp['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">จุดหน้างาน *</label>
        <select class="form-select" name="site_id" required>
          <option value="">— เลือกไซต์ —</option>
          <?php foreach ($sites as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['site_name']) ?> (รัศมี <?= (int)$s['allowed_radius_m'] ?>ม.)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">รูปถ่าย ณ หน้างาน (กล้องสด)</label>
        <input class="form-input" type="file" name="photo" accept="image/*" capture="environment">
        <div class="text-muted" style="font-size:11px;margin-top:6px;">ข้อมูล PDPA — เก็บนอก public และจำกัดสิทธิ์เข้าถึง</div>
      </div>

      <div id="gpsStatus" class="alert alert-info" style="font-size:12px;"><i class="fa-solid fa-location-crosshairs"></i> กำลังขอตำแหน่ง GPS...</div>

      <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>
        <i class="fa-solid fa-location-dot"></i> เช็คอิน
      </button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><div class="card-title">ประวัติเช็คอินล่าสุด</div><div class="card-sub">15 รายการ</div></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>เวลา</th><th>พนักงาน</th><th>ไซต์</th><th>ระยะ</th><th>สถานะ</th></tr></thead>
        <tbody>
          <?php if (!$recent): ?>
            <tr><td colspan="5" class="text-muted" style="text-align:center;padding:30px;">ยังไม่มีการเช็คอิน</td></tr>
          <?php else: foreach ($recent as $c):
              [$lbl,$cls,$ic] = Geo::statusLabel($c['status']); ?>
            <tr>
              <td class="text-muted" style="font-size:11px;"><?= e(date('d/m H:i', strtotime($c['created_at']))) ?></td>
              <td style="font-weight:600;"><?= e($c['emp_name']) ?></td>
              <td style="font-size:12px;"><?= e($c['site_name']) ?></td>
              <td class="mono"><?= number_format((float)$c['distance_from_site_m']) ?> ม.</td>
              <td><span class="badge <?= e($cls) ?>"><i class="fa-solid <?= e($ic) ?>" style="font-size:9px;"></i> <?= e($lbl) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// ดึง GPS ดิบจาก client — ส่งแค่พิกัด ไม่ส่งระยะ (server คำนวณเอง)
const statusEl = document.getElementById('gpsStatus');
const btn = document.getElementById('submitBtn');
function getGPS(){
  if(!navigator.geolocation){ statusEl.className='alert alert-error'; statusEl.innerHTML='เบราว์เซอร์ไม่รองรับ GPS'; return; }
  navigator.geolocation.getCurrentPosition(p=>{
    document.getElementById('lat').value = p.coords.latitude;
    document.getElementById('lon').value = p.coords.longitude;
    document.getElementById('accuracy').value = p.coords.accuracy;
    statusEl.className='alert alert-success';
    statusEl.innerHTML='<i class="fa-solid fa-circle-check"></i> ได้ตำแหน่งแล้ว (ความแม่นยำ ±'+Math.round(p.coords.accuracy)+' ม.)';
    btn.disabled=false;
  }, e=>{
    statusEl.className='alert alert-error';
    statusEl.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i> ดึงตำแหน่งไม่ได้: '+e.message;
  }, {enableHighAccuracy:true, timeout:10000, maximumAge:0});
}
getGPS();
</script>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
