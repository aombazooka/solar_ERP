<?php
/** installations.php — งานติดตั้ง (job tracking) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('install.view');

const JOB_STATUS = ['pending'=>['รอดำเนินการ','badge-gold'],'in_progress'=>['กำลังติดตั้ง','badge-blue'],
    'done'=>['เสร็จแล้ว','badge-green'],'cancelled'=>['ยกเลิก','badge-red']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('install.manage');
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'create') {
            $cid = (int) input('customer_id');
            if ($cid <= 0) throw new RuntimeException('กรุณาเลือกลูกค้า');
            $no = next_doc_no('JOB', 'installation_jobs');
            Database::run('INSERT INTO installation_jobs (doc_no, order_id, customer_id, site_id, system_type, capacity_kwp, team, scheduled_date, note, created_by)
                VALUES (:no,:oid,:cid,:sid,:sys,:kwp,:team,:sch,:note,:cb)',
                ['no'=>$no,'oid'=>input('order_id')?:null,'cid'=>$cid,'sid'=>input('site_id')?:null,
                 'sys'=>input('system_type')?:null,'kwp'=>input('capacity_kwp')?:null,'team'=>input('team')?:null,
                 'sch'=>input('scheduled_date')?:null,'note'=>input('note')?:null,'cb'=>Auth::id()]);
            flash('success', "สร้างงานติดตั้ง {$no} เรียบร้อย");
        } elseif ($do === 'update') {
            $st = input('status'); $prog = max(0, min(100, (int) input('progress')));
            if (!array_key_exists($st, JOB_STATUS)) throw new RuntimeException('สถานะไม่ถูกต้อง');
            if ($st === 'done') $prog = 100;
            Database::run('UPDATE installation_jobs SET status=:s, progress=:p WHERE id=:id',
                ['s'=>$st,'p'=>$prog,'id'=>(int)input('id')]);
            flash('success', 'อัปเดตงานติดตั้งแล้ว');
        }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('installations.php');
}

$jobs = Database::all('SELECT j.*, c.name AS customer_name, ws.site_name FROM installation_jobs j
    JOIN customers c ON c.id=j.customer_id LEFT JOIN work_sites ws ON ws.id=j.site_id ORDER BY j.id DESC');
$customers = Database::all('SELECT id, name FROM customers ORDER BY name');
$orders = Database::all("SELECT id, doc_no FROM sales_orders WHERE status<>'cancelled' ORDER BY id DESC");
$sites = Database::all('SELECT id, site_name FROM work_sites WHERE is_active=1 ORDER BY site_name');
$canManage = Auth::can('install.manage');
$sysLabel = ['on_grid'=>'On-Grid','hybrid'=>'Hybrid','off_grid'=>'Off-Grid'];
$counts = ['pending'=>0,'in_progress'=>0,'done'=>0];
foreach ($jobs as $j) if (isset($counts[$j['status']])) $counts[$j['status']]++;

$pageTitle = 'งานติดตั้ง';
$activeNav = 'installations';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>งานติดตั้ง</h1><p>ติดตามความคืบหน้างานติดตั้งทั้งหมด <?= count($jobs) ?> งาน</p></div>
  <?php if ($canManage): ?><button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> สร้างงานติดตั้ง</button><?php endif; ?>
</div>

<div class="grid g3" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-hourglass-half"></i></div><div class="stat-body"><div class="stat-label">รอดำเนินการ</div><div class="stat-value" style="font-size:20px;"><?= $counts['pending'] ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon blue" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-screwdriver-wrench"></i></div><div class="stat-body"><div class="stat-label">กำลังติดตั้ง</div><div class="stat-value" style="font-size:20px;"><?= $counts['in_progress'] ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon green" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-circle-check"></i></div><div class="stat-body"><div class="stat-label">เสร็จแล้ว</div><div class="stat-value" style="font-size:20px;"><?= $counts['done'] ?></div></div></div>
</div>

<?php if ($canManage): ?>
<div class="card hidden" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">สร้างงานติดตั้งใหม่</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ลูกค้า *</label><select class="form-select" name="customer_id" required><option value="">— เลือก —</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">ใบสั่งขาย (ถ้ามี)</label><select class="form-select" name="order_id"><option value="">—</option><?php foreach ($orders as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['doc_no']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">จุดหน้างาน</label><select class="form-select" name="site_id"><option value="">—</option><?php foreach ($sites as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['site_name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">ประเภทระบบ</label><select class="form-select" name="system_type"><option value="">—</option><option value="on_grid">On-Grid</option><option value="hybrid">Hybrid</option><option value="off_grid">Off-Grid</option></select></div>
      <div class="form-group"><label class="form-label">ขนาด (kWp)</label><input class="form-input" type="number" step="0.01" name="capacity_kwp"></div>
      <div class="form-group"><label class="form-label">ทีมช่าง</label><input class="form-input" name="team" placeholder="A / B / C"></div>
      <div class="form-group"><label class="form-label">วันที่นัดติดตั้ง</label><input class="form-input" type="date" name="scheduled_date"></div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ลูกค้า / ไซต์</th><th>ระบบ</th><th>ทีม</th><th>นัดติดตั้ง</th><th>ความคืบหน้า</th><th>สถานะ</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$jobs): ?>
          <tr><td colspan="8" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีงานติดตั้ง</td></tr>
        <?php else: foreach ($jobs as $j): [$sl,$sc]=JOB_STATUS[$j['status']]; ?>
          <tr>
            <td class="mono text-gold"><?= e($j['doc_no']) ?></td>
            <td><div style="font-weight:600;"><?= e($j['customer_name']) ?></div><?php if ($j['site_name']): ?><div style="font-size:11px;color:var(--text-muted)"><?= e($j['site_name']) ?></div><?php endif; ?></td>
            <td><?= $j['system_type'] ? e($sysLabel[$j['system_type']]) : '-' ?><?= $j['capacity_kwp'] ? ' <span class="text-muted">'.rtrim(rtrim($j['capacity_kwp'],'0'),'.').'kWp</span>' : '' ?></td>
            <td><?= $j['team'] ? '<span class="badge badge-blue">ทีม '.e($j['team']).'</span>' : '-' ?></td>
            <td class="text-muted"><?= e(thai_date_short($j['scheduled_date'])) ?></td>
            <td style="min-width:120px;"><div style="display:flex;align-items:center;gap:8px;"><div class="progress-track" style="flex:1;"><div class="progress-fill <?= $j['status']==='done'?'green':'' ?>" style="width:<?= (int)$j['progress'] ?>%"></div></div><span class="mono" style="font-size:11px;"><?= (int)$j['progress'] ?>%</span></div></td>
            <td><span class="badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;">
              <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" onclick="document.getElementById('upd<?= (int)$j['id'] ?>').classList.toggle('hidden')"><i class="fa-solid fa-pen"></i></button>
            </td>
            <?php endif; ?>
          </tr>
          <?php if ($canManage): ?>
          <tr id="upd<?= (int)$j['id'] ?>" class="hidden"><td colspan="8" style="background:rgba(255,255,255,0.02);">
            <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;padding:6px 0;"><?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
              <div><label class="form-label" style="font-size:11px;">สถานะ</label><select class="form-select" name="status" style="padding:6px 10px;font-size:12px;"><?php foreach (JOB_STATUS as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$j['status']?'selected':'' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
              <div><label class="form-label" style="font-size:11px;">ความคืบหน้า %</label><input class="form-input" type="number" name="progress" value="<?= (int)$j['progress'] ?>" min="0" max="100" style="padding:6px 10px;font-size:12px;width:90px;"></div>
              <button class="btn btn-primary" style="padding:7px 14px;font-size:12px;"><i class="fa-solid fa-check"></i> บันทึก</button>
            </form>
          </td></tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
