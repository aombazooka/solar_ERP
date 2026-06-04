<?php
/** warranties.php — ทะเบียนอุปกรณ์ที่ติดตั้ง + การรับประกัน */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('service.view');
$canManage = Auth::can('service.manage');

const ASSET_CAT = ['panel'=>'แผงโซลาร์','inverter'=>'อินเวอร์เตอร์','battery'=>'แบตเตอรี่','other'=>'อื่นๆ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('service.manage');
    csrf_verify();

    // ─── สแกนอุปกรณ์ใกล้หมดประกัน → แจ้งทีมขายให้เสนอต่อประกัน/แพ็กเกจ O&M ───
    if (input('do') === 'alert') {
        $rows = Database::all(
            "SELECT a.id, a.serial_no, a.warranty_end, c.name AS customer_name
             FROM installed_assets a JOIN customers c ON c.id=a.customer_id
             WHERE a.warranty_end IS NOT NULL AND a.renewal_alerted_at IS NULL
               AND a.warranty_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)"
        );
        $n = 0;
        foreach ($rows as $r) {
            $msg = "⏰ ประกันใกล้หมด: {$r['customer_name']} ({$r['serial_no']}) หมด " . thai_date_short($r['warranty_end'])
                 . " — เสนอต่อประกัน/แพ็กเกจดูแล O&M";
            Database::run(
                "INSERT INTO notifications (employee_id, message)
                 SELECT DISTINCT e.id, :m FROM employees e
                 JOIN users u ON u.id=e.user_id JOIN roles r ON r.id=u.role_id
                 LEFT JOIN role_permissions rp ON rp.role_id=r.id LEFT JOIN permissions p ON p.id=rp.permission_id
                 WHERE e.is_active=1 AND u.is_active=1 AND (r.slug='admin' OR p.slug='sales.view')",
                ['m' => $msg]
            );
            Database::run('UPDATE installed_assets SET renewal_alerted_at=NOW() WHERE id=:id', ['id' => $r['id']]);
            $n++;
        }
        flash($n ? 'success' : 'info', $n ? "แจ้งทีมขายแล้ว {$n} รายการที่ประกันใกล้หมด" : 'ไม่มีอุปกรณ์ใหม่ที่ใกล้หมดประกัน (แจ้งไปแล้วทั้งหมด)');
        redirect('warranties.php');
    }

    try {
        $cid = (int) input('customer_id'); $serial = input('serial_no');
        if ($cid <= 0 || $serial === '') throw new RuntimeException('กรุณาเลือกลูกค้าและกรอก Serial');
        $cat = array_key_exists(input('category'), ASSET_CAT) ? input('category') : 'other';
        $install = input('install_date') ?: null;
        $months = (int) input('warranty_months');
        $end = ($install && $months > 0) ? date('Y-m-d', strtotime("$install +$months months")) : null;
        Database::run('INSERT INTO installed_assets (customer_id, job_id, product_id, category, brand, serial_no, install_date, warranty_months, warranty_end, note, created_by)
            VALUES (:c,:j,:p,:cat,:b,:sn,:id,:wm,:we,:note,:cb)',
            ['c'=>$cid,'j'=>input('job_id')?:null,'p'=>input('product_id')?:null,'cat'=>$cat,'b'=>input('brand')?:null,
             'sn'=>$serial,'id'=>$install,'wm'=>$months,'we'=>$end,'note'=>input('note')?:null,'cb'=>Auth::id()]);
        flash('success', 'บันทึกอุปกรณ์ + การรับประกันเรียบร้อย');
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('warranties.php');
}

$assets = Database::all('SELECT a.*, c.name AS customer_name, p.name AS product_name FROM installed_assets a
    JOIN customers c ON c.id=a.customer_id LEFT JOIN products p ON p.id=a.product_id ORDER BY a.warranty_end IS NULL, a.warranty_end');
$customers = Database::all('SELECT id, name FROM customers ORDER BY name');
$jobs = Database::all("SELECT id, doc_no FROM installation_jobs ORDER BY id DESC");
$products = Database::all("SELECT id, sku, name FROM products WHERE category IN ('panel','inverter','battery') ORDER BY name");
$expiring = (int) Database::scalar("SELECT COUNT(*) FROM installed_assets WHERE warranty_end IS NOT NULL AND warranty_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)");
$expired  = (int) Database::scalar("SELECT COUNT(*) FROM installed_assets WHERE warranty_end IS NOT NULL AND warranty_end < CURDATE()");

$pageTitle = 'การรับประกัน';
$activeNav = 'warranties';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ทะเบียนอุปกรณ์ & การรับประกัน</h1><p>เก็บ Serial + วันหมดประกันของอุปกรณ์ที่ติดตั้งให้ลูกค้า</p></div>
  <?php if ($canManage): ?>
  <div style="display:flex;gap:8px;">
    <form method="post" style="display:inline;" onsubmit="return confirm('แจ้งทีมขายเรื่องอุปกรณ์ที่ประกันใกล้หมด (90 วัน)?')">
      <?= csrf_field() ?><input type="hidden" name="do" value="alert">
      <button class="btn btn-ghost"<?= $expiring>0?'':' disabled' ?>><i class="fa-solid fa-bell"></i> แจ้งทีมขาย<?= $expiring>0?' ('.$expiring.')':'' ?></button>
    </form>
    <button class="btn btn-primary" onclick="document.getElementById('aForm').classList.toggle('hidden')"><i class="fa-solid fa-shield-halved"></i> เพิ่มอุปกรณ์</button>
  </div>
  <?php endif; ?>
</div>

<div class="grid g3" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon blue" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-microchip"></i></div><div class="stat-body"><div class="stat-label">อุปกรณ์ทั้งหมด</div><div class="stat-value" style="font-size:20px;"><?= count($assets) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-hourglass-half"></i></div><div class="stat-body"><div class="stat-label">ใกล้หมดประกัน (90 วัน)</div><div class="stat-value" style="font-size:20px;"><?= $expiring ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon red" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-circle-xmark"></i></div><div class="stat-body"><div class="stat-label">หมดประกันแล้ว</div><div class="stat-value" style="font-size:20px;"><?= $expired ?></div></div></div>
</div>

<?php if ($canManage): ?>
<div class="card hidden" id="aForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">เพิ่มอุปกรณ์ที่ติดตั้ง</div></div>
  <form method="post"><?= csrf_field() ?>
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ลูกค้า *</label><select class="form-select" name="customer_id" required><option value="">— เลือก —</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">งานติดตั้ง</label><select class="form-select" name="job_id"><option value="">—</option><?php foreach ($jobs as $j): ?><option value="<?= (int)$j['id'] ?>"><?= e($j['doc_no']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">ประเภท</label><select class="form-select" name="category"><?php foreach (ASSET_CAT as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">สินค้า (อ้างอิง)</label><select class="form-select" name="product_id"><option value="">—</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['sku']) ?> · <?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">ยี่ห้อ</label><input class="form-input" name="brand"></div>
      <div class="form-group"><label class="form-label">Serial No. *</label><input class="form-input" name="serial_no" required></div>
      <div class="form-group"><label class="form-label">วันที่ติดตั้ง</label><input class="form-input" type="date" name="install_date"></div>
      <div class="form-group"><label class="form-label">ระยะประกัน (เดือน)</label><input class="form-input" type="number" name="warranty_months" placeholder="เช่น 120 = 10 ปี"></div>
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">หมายเหตุ</label><input class="form-input" name="note"></div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>ลูกค้า</th><th>ประเภท</th><th>ยี่ห้อ / สินค้า</th><th>Serial</th><th>ติดตั้ง</th><th>หมดประกัน</th><th>สถานะ</th></tr></thead>
      <tbody>
        <?php if (!$assets): ?><tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีข้อมูลอุปกรณ์</td></tr>
        <?php else: foreach ($assets as $a):
            $end = $a['warranty_end'] ? strtotime($a['warranty_end']) : null;
            if (!$end) { $wl='ไม่ระบุ'; $wc='badge-muted'; }
            elseif ($end < time()) { $wl='หมดประกัน'; $wc='badge-red'; }
            elseif ($end < strtotime('+90 days')) { $wl='ใกล้หมด'; $wc='badge-gold'; }
            else { $wl='อยู่ในประกัน'; $wc='badge-green'; } ?>
          <tr>
            <td style="font-weight:600;"><?= e($a['customer_name']) ?></td>
            <td><span class="badge badge-blue"><?= e(ASSET_CAT[$a['category']] ?? $a['category']) ?></span></td>
            <td><?= e($a['brand'] ?? '-') ?><?php if ($a['product_name']): ?><div style="font-size:11px;color:var(--text-muted)"><?= e($a['product_name']) ?></div><?php endif; ?></td>
            <td class="mono"><?= e($a['serial_no']) ?></td>
            <td class="text-muted"><?= e(thai_date_short($a['install_date'])) ?></td>
            <td class="text-muted"><?= e(thai_date_short($a['warranty_end'])) ?></td>
            <td><span class="badge <?= $wc ?>"><?= $wl ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
