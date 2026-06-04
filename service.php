<?php
/** service.php — งานบริการหลังการขาย (O&M / Service Tickets) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('service.view');
$canManage = Auth::can('service.manage');
$canQuote  = Auth::can('sales.create');   // ออกใบเสนอราคาจากใบงานบริการได้

const TICKET_TYPES = ['maintenance'=>'บำรุงรักษา','repair'=>'ซ่อม','claim'=>'เคลมประกัน','inspection'=>'ตรวจเช็ค'];
const TICKET_PRIO  = ['low'=>['ต่ำ','badge-muted'],'normal'=>['ปกติ','badge-blue'],'high'=>['สูง','badge-gold'],'urgent'=>['ด่วนมาก','badge-red']];
const TICKET_STAT  = ['open'=>['เปิดงาน','badge-gold'],'in_progress'=>['กำลังดำเนินการ','badge-blue'],'resolved'=>['แก้ไขแล้ว','badge-green'],'closed'=>['ปิดงาน','badge-muted'],'cancelled'=>['ยกเลิก','badge-muted']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('service.manage');
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'create') {
            $cid = (int) input('customer_id'); $title = input('title');
            if ($cid <= 0 || $title === '') throw new RuntimeException('กรุณาเลือกลูกค้าและกรอกหัวข้อ');
            $type = array_key_exists(input('ticket_type'), TICKET_TYPES) ? input('ticket_type') : 'repair';
            $prio = array_key_exists(input('priority'), TICKET_PRIO) ? input('priority') : 'normal';

            // เชื่อมอุปกรณ์ + เช็คสถานะประกัน ณ ตอนเปิดงาน
            $assetId = input('asset_id') ?: null; $wStatus = null;
            if ($assetId) {
                $asset = Database::one('SELECT warranty_end FROM installed_assets WHERE id=:id AND customer_id=:c', ['id'=>$assetId,'c'=>$cid]);
                if (!$asset) { $assetId = null; }
                elseif (!$asset['warranty_end']) { $wStatus = 'unknown'; }
                elseif (strtotime($asset['warranty_end']) < time()) { $wStatus = 'expired'; }
                else { $wStatus = 'active'; }
            }

            $no = next_doc_no('SRV', 'service_tickets');
            Database::run('INSERT INTO service_tickets (doc_no, customer_id, job_id, asset_id, warranty_status, ticket_type, priority, title, description, assigned_team, scheduled_date, created_by)
                VALUES (:no,:cid,:job,:asset,:ws,:t,:p,:title,:desc,:team,:sch,:cb)',
                ['no'=>$no,'cid'=>$cid,'job'=>input('job_id')?:null,'asset'=>$assetId,'ws'=>$wStatus,'t'=>$type,'p'=>$prio,'title'=>$title,
                 'desc'=>input('description')?:null,'team'=>input('assigned_team')?:null,'sch'=>input('scheduled_date')?:null,'cb'=>Auth::id()]);
            $warn = ($type==='claim' && $wStatus==='expired') ? ' ⚠ อุปกรณ์หมดประกันแล้ว — เคลมอาจมีค่าใช้จ่าย'
                  : (($type==='claim' && $wStatus==='active') ? ' ✓ อุปกรณ์อยู่ในประกัน' : '');
            flash(($type==='claim' && $wStatus==='expired') ? 'info' : 'success', "เปิดใบงานบริการ {$no} เรียบร้อย{$warn}");
        } elseif ($do === 'update') {
            $st = input('status');
            if (!array_key_exists($st, TICKET_STAT)) throw new RuntimeException('สถานะไม่ถูกต้อง');
            $resolvedAt = in_array($st,['resolved','closed']) ? date('Y-m-d H:i:s') : null;
            Database::run('UPDATE service_tickets SET status=:s, assigned_team=:team, scheduled_date=:sch, resolution=:res,
                   resolved_at=COALESCE(resolved_at,:ra) WHERE id=:id',
                ['s'=>$st,'team'=>input('assigned_team')?:null,'sch'=>input('scheduled_date')?:null,
                 'res'=>input('resolution')?:null,'ra'=>$resolvedAt,'id'=>(int)input('id')]);
            flash('success', 'อัปเดตงานบริการแล้ว');
        }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('service.php');
}

$filter = input('status');
$where = array_key_exists($filter, TICKET_STAT) ? 'WHERE t.status=:st' : '';
$params = $where ? ['st'=>$filter] : [];
$tickets = Database::all("SELECT t.*, c.name AS customer_name, a.serial_no, a.brand AS asset_brand
    FROM service_tickets t JOIN customers c ON c.id=t.customer_id
    LEFT JOIN installed_assets a ON a.id=t.asset_id $where
    ORDER BY FIELD(t.status,'open','in_progress','resolved','closed','cancelled'), FIELD(t.priority,'urgent','high','normal','low'), t.id DESC", $params);
$customers = Database::all('SELECT id, name FROM customers ORDER BY name');
$jobs = Database::all("SELECT id, doc_no FROM installation_jobs ORDER BY id DESC");
// อุปกรณ์ทั้งหมด (สำหรับ dropdown แบบ filter ตามลูกค้า + เช็คประกัน)
$assetList = array_map(function($a){
    $end = $a['warranty_end'] ? strtotime($a['warranty_end']) : null;
    $a['wstate'] = !$end ? 'unknown' : ($end < time() ? 'expired' : 'active');
    $a['wend_th'] = thai_date_short($a['warranty_end']);
    return $a;
}, Database::all("SELECT id, customer_id, serial_no, brand, category, warranty_end FROM installed_assets ORDER BY id DESC"));
$wsLabel = ['active'=>['อยู่ในประกัน','badge-green'],'expired'=>['หมดประกัน','badge-red'],'unknown'=>['ไม่ระบุประกัน','badge-muted']];
$counts = []; foreach (TICKET_STAT as $k=>$v) $counts[$k]=0;
foreach (Database::all("SELECT status, COUNT(*) n FROM service_tickets GROUP BY status") as $r) $counts[$r['status']]=(int)$r['n'];

$pageTitle = 'งานบริการ (O&M)';
$activeNav = 'service';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>งานบริการหลังการขาย</h1><p>บำรุงรักษา · ซ่อม · เคลมประกัน · ตรวจเช็ค</p></div>
  <?php if ($canManage): ?><button class="btn btn-primary" onclick="document.getElementById('svForm').classList.toggle('hidden')"><i class="fa-solid fa-headset"></i> เปิดใบงาน</button><?php endif; ?>
</div>

<div class="grid g4" style="margin-bottom:20px;">
  <?php foreach (['open'=>'gold','in_progress'=>'blue','resolved'=>'green','urgent'=>'red'] as $k=>$clr):
    $val = $k==='urgent' ? (int)Database::scalar("SELECT COUNT(*) FROM service_tickets WHERE priority='urgent' AND status IN ('open','in_progress')") : (int)$counts[$k];
    $lbl = $k==='urgent' ? 'ด่วนค้างอยู่' : TICKET_STAT[$k][0]; ?>
    <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon <?= $clr ?>" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-<?= $k==='urgent'?'triangle-exclamation':'screwdriver-wrench' ?>"></i></div><div class="stat-body"><div class="stat-label"><?= e($lbl) ?></div><div class="stat-value" style="font-size:20px;"><?= $val ?></div></div></div>
  <?php endforeach; ?>
</div>

<?php if ($canManage): ?>
<div class="card hidden" id="svForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">เปิดใบงานบริการ</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ลูกค้า *</label><select class="form-select" id="custSel" name="customer_id" required onchange="filterAssets()"><option value="">— เลือก —</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">งานติดตั้งอ้างอิง</label><select class="form-select" name="job_id"><option value="">—</option><?php foreach ($jobs as $j): ?><option value="<?= (int)$j['id'] ?>"><?= e($j['doc_no']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">ประเภท</label><select class="form-select" id="typeSel" name="ticket_type" onchange="toggleAsset()"><?php foreach (TICKET_TYPES as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">หัวข้อ *</label><input class="form-input" name="title" required placeholder="เช่น อินเวอร์เตอร์ขึ้น error E013"></div>
      <div class="form-group"><label class="form-label">ความเร่งด่วน</label><select class="form-select" name="priority"><?php foreach (TICKET_PRIO as $k=>$v): ?><option value="<?= $k ?>" <?= $k==='normal'?'selected':'' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">ทีม</label><input class="form-input" name="assigned_team" placeholder="A"></div>
      <div class="form-group" id="assetWrap" style="grid-column:span 2;"><label class="form-label">อุปกรณ์ (Serial) <span id="assetHintLbl" class="text-muted" style="font-size:11px;"></span></label>
        <select class="form-select" id="assetSel" name="asset_id" onchange="checkWarranty()"><option value="">— เลือกลูกค้าก่อน —</option></select>
        <div id="warrBox" style="font-size:12px;margin-top:6px;"></div>
      </div>
      <div class="form-group"><label class="form-label">วันนัด</label><input class="form-input" type="date" name="scheduled_date"></div>
      <div class="form-group" style="grid-column:span 4;"><label class="form-label">รายละเอียด</label><input class="form-input" name="description"></div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> เปิดใบงาน</button>
  </form>
</div>
<script>
const ASSETS = <?= json_encode($assetList, JSON_UNESCAPED_UNICODE) ?>;
const WMETA = {active:['อยู่ในประกัน','#10b981'],expired:['หมดประกัน','#ef4444'],unknown:['ไม่ระบุประกัน','#64748b']};
function filterAssets(){
  const cid = document.getElementById('custSel').value;
  const sel = document.getElementById('assetSel');
  sel.innerHTML = '<option value="">— ไม่ระบุอุปกรณ์ —</option>';
  ASSETS.filter(a=>String(a.customer_id)===String(cid)).forEach(a=>{
    const o=document.createElement('option'); o.value=a.id;
    o.textContent = a.serial_no + (a.brand?(' · '+a.brand):'') + ' ['+WMETA[a.wstate][0]+']';
    o.dataset.w=a.wstate; o.dataset.wend=a.wend_th; sel.appendChild(o);
  });
  checkWarranty();
}
function checkWarranty(){
  const o=document.getElementById('assetSel').selectedOptions[0]; const box=document.getElementById('warrBox');
  if(!o||!o.value){ box.innerHTML=''; return; }
  const w=o.dataset.w, m=WMETA[w];
  box.innerHTML = '<span style="color:'+m[1]+';font-weight:600;"><i class="fa-solid fa-shield-halved"></i> '+m[0]+'</span>'
    + (o.dataset.wend && o.dataset.wend!=='-' ? ' <span class="text-muted">(ถึง '+o.dataset.wend+')</span>' : '')
    + (w==='expired' ? ' — เคลมอาจมีค่าใช้จ่าย' : '');
}
function toggleAsset(){
  const claim = document.getElementById('typeSel').value==='claim';
  document.getElementById('assetHintLbl').textContent = claim ? '(แนะนำให้เลือกเพื่อเช็คประกัน)' : '';
  document.getElementById('assetWrap').style.outline = claim ? '2px solid rgba(245,158,11,.4)' : 'none';
  document.getElementById('assetWrap').style.borderRadius = '8px';
}
toggleAsset();
</script>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
  <a class="btn <?= $filter===''?'btn-primary':'btn-ghost' ?>" style="padding:7px 14px;font-size:12px;" href="<?= e(url('service.php')) ?>">ทั้งหมด</a>
  <?php foreach (TICKET_STAT as $k=>$v): ?><a class="btn <?= $filter===$k?'btn-primary':'btn-ghost' ?>" style="padding:7px 14px;font-size:12px;" href="<?= e(url('service.php?status='.$k)) ?>"><?= e($v[0]) ?> (<?= (int)$counts[$k] ?>)</a><?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ลูกค้า</th><th>ประเภท</th><th>หัวข้อ</th><th>ด่วน</th><th>ทีม</th><th>นัด</th><th>สถานะ</th><?php if ($canManage || $canQuote): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$tickets): ?><tr><td colspan="9" class="text-muted" style="text-align:center;padding:40px;">ไม่มีใบงานบริการ</td></tr>
        <?php else: foreach ($tickets as $t): [$pl,$pc]=TICKET_PRIO[$t['priority']]; [$sl,$sc]=TICKET_STAT[$t['status']]; ?>
          <tr>
            <td class="mono text-gold"><?= e($t['doc_no']) ?></td>
            <td style="font-weight:600;"><?= e($t['customer_name']) ?></td>
            <td><?= e(TICKET_TYPES[$t['ticket_type']] ?? $t['ticket_type']) ?></td>
            <td style="white-space:normal;max-width:240px;"><?= e($t['title']) ?>
              <?php if ($t['serial_no']): [$wl,$wc]=$wsLabel[$t['warranty_status']] ?? ['',''];  ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><i class="fa-solid fa-microchip"></i> <?= e($t['serial_no']) ?><?php if ($wl): ?> <span class="badge <?= e($wc) ?>" style="font-size:9px;"><?= e($wl) ?></span><?php endif; ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= e($pc) ?>"><?= e($pl) ?></span></td>
            <td><?= $t['assigned_team']?'<span class="badge badge-blue">ทีม '.e($t['assigned_team']).'</span>':'-' ?></td>
            <td class="text-muted"><?= e(thai_date_short($t['scheduled_date'])) ?></td>
            <td><span class="badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
            <?php if ($canManage || $canQuote): ?><td style="text-align:right;white-space:nowrap;">
              <?php if ($canQuote): ?><a class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" title="ออกใบเสนอราคา" href="<?= e(url('quotations.php?ticket='.$t['id'])) ?>"><i class="fa-solid fa-file-invoice"></i></a><?php endif; ?>
              <?php if ($canManage): ?><button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" onclick="document.getElementById('u<?= (int)$t['id'] ?>').classList.toggle('hidden')"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
            </td><?php endif; ?>
          </tr>
          <?php if ($canManage): ?>
          <tr id="u<?= (int)$t['id'] ?>" class="hidden"><td colspan="9" style="background:rgba(255,255,255,0.02);">
            <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;padding:6px 0;"><?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <div><label class="form-label" style="font-size:11px;">สถานะ</label><select class="form-select" name="status" style="padding:6px 10px;font-size:12px;"><?php foreach (TICKET_STAT as $k=>$v): ?><option value="<?= $k ?>" <?= $k===$t['status']?'selected':'' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
              <div><label class="form-label" style="font-size:11px;">ทีม</label><input class="form-input" name="assigned_team" value="<?= e($t['assigned_team']??'') ?>" style="padding:6px 10px;font-size:12px;width:80px;"></div>
              <div><label class="form-label" style="font-size:11px;">วันนัด</label><input class="form-input" type="date" name="scheduled_date" value="<?= e($t['scheduled_date']??'') ?>" style="padding:6px 10px;font-size:12px;"></div>
              <div style="flex:1;min-width:160px;"><label class="form-label" style="font-size:11px;">ผลการแก้ไข</label><input class="form-input" name="resolution" value="<?= e($t['resolution']??'') ?>" style="padding:6px 10px;font-size:12px;"></div>
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
