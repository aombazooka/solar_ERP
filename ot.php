<?php
/** ot.php — คำขอ OT (ยื่น + อนุมัติ, รองรับ HR ทำแทน §5.1) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'request') {
            Hr::requestOt((int) input('employee_id'), input('ot_date'), (float) input('hours'), input('reason'), input('acted_for_reason'));
            flash('success', 'ยื่นคำขอ OT เรียบร้อย');
        } elseif ($do === 'decide') {
            Auth::requireCan('hr.approve');
            Hr::decideOt((int) input('id'), input('decision'));
            flash('success', 'บันทึกการพิจารณา OT เรียบร้อย');
        }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('ot.php');
}

$canManage = Auth::can('hr.manage');
$canApprove = Auth::can('hr.approve');
$me = Hr::currentEmployee();
$employees = $canManage
    ? Database::all('SELECT id, code, name, user_id FROM employees WHERE is_active=1 ORDER BY name')
    : ($me ? [['id'=>$me['id'],'code'=>$me['code'],'name'=>$me['name'],'user_id'=>$me['user_id']]] : []);
$rows = $canManage
    ? Database::all("SELECT o.*, e.name AS emp_name FROM ot_requests o JOIN employees e ON e.id=o.employee_id ORDER BY o.id DESC LIMIT 40")
    : ($me ? Database::all("SELECT o.*, e.name AS emp_name FROM ot_requests o JOIN employees e ON e.id=o.employee_id WHERE o.employee_id=:e ORDER BY o.id DESC", ['e'=>$me['id']]) : []);
$stLabel = ['pending'=>['รออนุมัติ','badge-gold'],'approved'=>['อนุมัติ','badge-green'],'rejected'=>['ปฏิเสธ','badge-red'],'cancelled'=>['ยกเลิก','badge-muted']];

$pageTitle = 'OT (ล่วงเวลา)';
$activeNav = 'ot';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>คำขอ OT (ทำงานล่วงเวลา)</h1><p>ยื่นและพิจารณาคำขอทำงานล่วงเวลา</p></div>
  <?php if ($employees): ?><button class="btn btn-primary" onclick="document.getElementById('otForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> ยื่น OT</button><?php endif; ?>
</div>

<?php if ($employees): ?>
<div class="card hidden" id="otForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">ยื่นคำขอ OT</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="request">
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">พนักงาน *</label>
        <select class="form-select" name="employee_id" id="empSel" required onchange="tr()">
          <?php foreach ($employees as $emp): ?><option value="<?= (int)$emp['id'] ?>" data-self="<?= ($emp['user_id'] && (int)$emp['user_id']===Auth::id())?'1':'0' ?>"><?= e($emp['code']) ?> · <?= e($emp['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-group"><label class="form-label">วันที่ *</label><input class="form-input" type="date" name="ot_date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-group"><label class="form-label">จำนวนชั่วโมง *</label><input class="form-input" type="number" step="0.5" min="0.5" max="24" name="hours" required></div>
      <div class="form-group" style="grid-column:span 4;"><label class="form-label">เหตุผล</label><input class="form-input" name="reason" placeholder="เช่น ติดตั้งงานเร่งด่วน"></div>
    </div>
    <div class="form-group hidden" id="rw" style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius-sm);padding:14px;">
      <label class="form-label" style="color:var(--solar-gold)"><i class="fa-solid fa-user-shield"></i> เหตุผลที่ทำแทน (บังคับ — §5.1)</label>
      <input class="form-input" name="acted_for_reason">
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> ยื่น OT</button>
  </form>
</div>
<script>function tr(){const o=document.getElementById('empSel').selectedOptions[0];document.getElementById('rw').classList.toggle('hidden',o.dataset.self==='1');}tr();</script>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>พนักงาน</th><th>วันที่</th><th>ชั่วโมง</th><th>เหตุผล</th><th>วิธี</th><th>สถานะ</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีคำขอ OT</td></tr>
        <?php else: foreach ($rows as $r): [$sl,$sc]=$stLabel[$r['status']]; [$el,$ec]=Hr::entryBadge($r['entry_method']); ?>
          <tr>
            <td style="font-weight:600;"><?= e($r['emp_name']) ?></td>
            <td class="mono"><?= e(thai_date_short($r['ot_date'])) ?></td>
            <td class="mono" style="font-weight:700;"><?= rtrim(rtrim($r['hours'],'0'),'.') ?> ชม.</td>
            <td style="white-space:normal;"><?= e($r['reason'] ?? '-') ?></td>
            <td><span class="badge <?= e($ec) ?>"><?= e($el) ?></span></td>
            <td><span class="badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
            <td style="text-align:right;">
              <?php if ($r['status']==='pending' && $canApprove): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approved"><button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--green)"><i class="fa-solid fa-check"></i></button></form>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="rejected"><button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--red)"><i class="fa-solid fa-xmark"></i></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
