<?php
/** leads.php — Leads / ลูกค้าสนใจ (CRM) — list + เพิ่ม + เปลี่ยนสถานะ + แปลงเป็นลูกค้า */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('sales.view');

const LEAD_STATUS = ['new'=>['ใหม่','badge-blue'],'contacted'=>['ติดต่อแล้ว','badge-gold'],
    'quoted'=>['เสนอราคาแล้ว','badge-purple'],'won'=>['ปิดการขาย','badge-green'],'lost'=>['เสียโอกาส','badge-red']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('sales.create');
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'create') {
            $name = input('name');
            if ($name === '') throw new RuntimeException('กรุณากรอกชื่อ');
            Database::run('INSERT INTO leads (name, phone, source, interest_system, interest_kwp, est_value, note, created_by)
                VALUES (:n,:p,:s,:sys,:kwp,:val,:note,:cb)',
                ['n'=>$name,'p'=>input('phone')?:null,'s'=>input('source')?:null,
                 'sys'=>input('interest_system')?:null,'kwp'=>input('interest_kwp')?:null,
                 'val'=>input('est_value')?:null,'note'=>input('note')?:null,'cb'=>Auth::id()]);
            flash('success', 'เพิ่ม Lead เรียบร้อย');
        } elseif ($do === 'status') {
            $st = input('status');
            if (!array_key_exists($st, LEAD_STATUS)) throw new RuntimeException('สถานะไม่ถูกต้อง');
            Database::run('UPDATE leads SET status=:s WHERE id=:id', ['s'=>$st,'id'=>(int)input('id')]);
            flash('success', 'อัปเดตสถานะแล้ว');
        } elseif ($do === 'convert') {
            $lead = Database::one('SELECT * FROM leads WHERE id=:id', ['id'=>(int)input('id')]);
            if (!$lead) throw new RuntimeException('ไม่พบ Lead');
            if ($lead['converted_customer_id']) throw new RuntimeException('Lead นี้แปลงเป็นลูกค้าแล้ว');
            $pdo = Database::pdo(); $pdo->beginTransaction();
            try {
                $maxId = (int) Database::scalar('SELECT COALESCE(MAX(id),0) FROM customers');
                $code = 'CUS-' . str_pad((string)($maxId+1),4,'0',STR_PAD_LEFT);
                $pdo->prepare('INSERT INTO customers (code,name,type,phone,created_by) VALUES (:c,:n,:t,:p,:cb)')
                    ->execute(['c'=>$code,'n'=>$lead['name'],'t'=>'individual','p'=>$lead['phone'],'cb'=>Auth::id()]);
                $cid = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE leads SET status='won', converted_customer_id=:cid WHERE id=:id")
                    ->execute(['cid'=>$cid,'id'=>$lead['id']]);
                $pdo->commit();
                flash('success', "แปลง Lead เป็นลูกค้า {$code} เรียบร้อย");
            } catch (\Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
        }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('leads.php');
}

$perPage = 15; $page = current_page();
$total = (int) Database::scalar('SELECT COUNT(*) FROM leads');
$leads = Database::all('SELECT l.*, c.code AS cust_code FROM leads l LEFT JOIN customers c ON c.id=l.converted_customer_id ORDER BY l.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page-1)*$perPage));
$canManage = Auth::can('sales.create');
$counts = [];
foreach (LEAD_STATUS as $k=>$v) $counts[$k] = 0;
foreach (Database::all('SELECT status, COUNT(*) AS n FROM leads GROUP BY status') as $r) $counts[$r['status']] = (int) $r['n'];
$sysLabel = ['on_grid'=>'On-Grid','hybrid'=>'Hybrid','off_grid'=>'Off-Grid'];

$pageTitle = 'Leads / ลูกค้าสนใจ';
$activeNav = 'leads';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>Leads / ลูกค้าสนใจ</h1><p>ติดตามลูกค้าที่สนใจและแปลงเป็นลูกค้าจริง <?= $total ?> รายการ</p></div>
  <?php if ($canManage): ?><button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-user-plus"></i> เพิ่ม Lead</button><?php endif; ?>
</div>

<div class="grid g4" style="margin-bottom:20px;">
  <?php foreach (['new'=>'blue','contacted'=>'gold','quoted'=>'purple','won'=>'green'] as $k=>$clr): ?>
    <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon <?= $clr ?>" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-user-tag"></i></div>
      <div class="stat-body"><div class="stat-label"><?= e(LEAD_STATUS[$k][0]) ?></div><div class="stat-value" style="font-size:20px;"><?= (int)$counts[$k] ?></div></div></div>
  <?php endforeach; ?>
</div>

<?php if ($canManage): ?>
<div class="card hidden" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">เพิ่ม Lead ใหม่</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อ *</label><input class="form-input" name="name" required></div>
      <div class="form-group"><label class="form-label">เบอร์โทร</label><input class="form-input" name="phone"></div>
      <div class="form-group"><label class="form-label">แหล่งที่มา</label><input class="form-input" name="source" placeholder="Facebook / LINE / แนะนำ"></div>
      <div class="form-group"><label class="form-label">สนใจระบบ</label><select class="form-select" name="interest_system"><option value="">—</option><option value="on_grid">On-Grid</option><option value="hybrid">Hybrid</option><option value="off_grid">Off-Grid</option></select></div>
      <div class="form-group"><label class="form-label">ขนาด (kWp)</label><input class="form-input" type="number" step="0.01" name="interest_kwp"></div>
      <div class="form-group"><label class="form-label">มูลค่าคาดการณ์</label><input class="form-input" type="number" step="0.01" name="est_value"></div>
      <div class="form-group"><label class="form-label">หมายเหตุ</label><input class="form-input" name="note"></div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>ชื่อ</th><th>ที่มา</th><th>สนใจ</th><th>มูลค่าคาด</th><th>สถานะ</th><?php if ($canManage): ?><th>จัดการ</th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$leads): ?>
          <tr><td colspan="6" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มี Lead</td></tr>
        <?php else: foreach ($leads as $l): [$sl,$sc]=LEAD_STATUS[$l['status']]; ?>
          <tr>
            <td><div style="font-weight:600;"><?= e($l['name']) ?></div><?php if ($l['phone']): ?><div style="font-size:11px;color:var(--text-muted)"><?= e($l['phone']) ?></div><?php endif; ?></td>
            <td class="text-muted"><?= e($l['source'] ?? '-') ?></td>
            <td><?= $l['interest_system'] ? e($sysLabel[$l['interest_system']]) : '-' ?><?= $l['interest_kwp'] ? ' <span class="text-muted">'.rtrim(rtrim($l['interest_kwp'],'0'),'.').'kWp</span>' : '' ?></td>
            <td class="mono"><?= $l['est_value'] ? number_format((float)$l['est_value']) : '-' ?></td>
            <td><span class="badge <?= e($sc) ?>"><?= e($sl) ?></span><?php if ($l['cust_code']): ?> <span class="mono text-gold" style="font-size:10px;"><?= e($l['cust_code']) ?></span><?php endif; ?></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <?php if (!$l['converted_customer_id']): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                  <select name="status" class="form-select" style="padding:4px 8px;font-size:11px;width:auto;" onchange="this.form.submit()">
                    <?php foreach (LEAD_STATUS as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$l['status']?'selected':'' ?>><?= e($v[0]) ?></option><?php endforeach; ?>
                  </select></form>
                <form method="post" style="display:inline;" onsubmit="return confirm('แปลง Lead นี้เป็นลูกค้า?')"><?= csrf_field() ?><input type="hidden" name="do" value="convert"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                  <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--green)" title="แปลงเป็นลูกค้า"><i class="fa-solid fa-user-check"></i></button></form>
              <?php else: ?><span class="text-muted" style="font-size:11px;">แปลงแล้ว</span><?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pager($total, $perPage, $page, url('leads.php')) ?>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
