<?php
/** leave.php — การลา (ยื่น + อนุมัติ, รองรับ HR ทำแทน §5.1) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'request') {
            $from = input('date_from'); $to = input('date_to');
            $days = $from && $to ? (strtotime($to) - strtotime($from)) / 86400 + 1 : (float) input('days');
            Hr::requestLeave((int) input('employee_id'), input('leave_type'), $from, $to, max(0.5, $days), input('reason'), input('acted_for_reason'));
            flash('success', 'ยื่นคำขอลาเรียบร้อย');
        } elseif ($do === 'decide') {
            Auth::requireCan('hr.approve');
            Hr::decideLeave((int) input('id'), input('decision'));
            flash('success', 'บันทึกการพิจารณาเรียบร้อย');
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('leave.php');
}

$canManage = Auth::can('hr.manage');
$canApprove = Auth::can('hr.approve');
$me = Hr::currentEmployee();
$employees = $canManage
    ? Database::all('SELECT id, code, name, user_id FROM employees WHERE is_active=1 ORDER BY name')
    : ($me ? [['id' => $me['id'], 'code' => $me['code'], 'name' => $me['name'], 'user_id' => $me['user_id']]] : []);

$rows = $canManage
    ? Database::all("SELECT l.*, e.name AS emp_name FROM leave_requests l JOIN employees e ON e.id=l.employee_id ORDER BY l.id DESC LIMIT 40")
    : ($me ? Database::all("SELECT l.*, e.name AS emp_name FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE l.employee_id=:e ORDER BY l.id DESC", ['e' => $me['id']]) : []);

$leaveTypesList = Hr::leaveTypes();
$typeLabel = array_column($leaveTypesList, 'name', 'code');
$stLabel = ['pending' => ['รออนุมัติ', 'badge-gold'], 'approved' => ['อนุมัติ', 'badge-green'], 'rejected' => ['ปฏิเสธ', 'badge-red'], 'cancelled' => ['ยกเลิก', 'badge-muted']];

$pageTitle = 'การลา';
$activeNav = 'leave';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>การลา</h1><p>ยื่นและติดตามคำขอลา</p></div>
  <?php if ($employees): ?>
    <button class="btn btn-primary" onclick="document.getElementById('lvForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> ยื่นใบลา</button>
  <?php endif; ?>
</div>

<?php if ($employees): ?>
<div class="card hidden" id="lvForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">ยื่นคำขอลา</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="request">
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;">
        <label class="form-label">พนักงาน *</label>
        <select class="form-select" name="employee_id" id="empSel" required onchange="toggleReason()">
          <?php foreach ($employees as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" data-self="<?= ($emp['user_id'] && (int)$emp['user_id']===Auth::id())?'1':'0' ?>"><?= e($emp['code']) ?> · <?= e($emp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ประเภท</label>
        <select class="form-select" name="leave_type">
          <?php foreach ($leaveTypesList as $lt): ?>
            <option value="<?= e($lt['code']) ?>"><?= e($lt['name']) ?><?= (int)$lt['quota_days'] > 0 ? ' ('.((int)$lt['quota_days']).' วัน/ปี)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"></div>
      <div class="form-group"><label class="form-label">ตั้งแต่วันที่ *</label><input class="form-input" type="date" name="date_from" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-group"><label class="form-label">ถึงวันที่ *</label><input class="form-input" type="date" name="date_to" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">เหตุผลการลา</label><input class="form-input" name="reason"></div>
    </div>
    <div class="form-group hidden" id="reasonWrap" style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius-sm);padding:14px;">
      <label class="form-label" style="color:var(--solar-gold)"><i class="fa-solid fa-user-shield"></i> เหตุผลที่ทำแทน (บังคับ — §5.1)</label>
      <input class="form-input" name="acted_for_reason" placeholder="เช่น พนักงานโทรแจ้งลาป่วย">
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> ยื่นใบลา</button>
  </form>
</div>
<script>
function toggleReason(){ const o=document.getElementById('empSel').selectedOptions[0]; document.getElementById('reasonWrap').classList.toggle('hidden', o.dataset.self==='1'); }
toggleReason();
</script>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>พนักงาน</th><th>ประเภท</th><th>ช่วงวันลา</th><th>วัน</th><th>วิธี</th><th>สถานะ</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีคำขอลา</td></tr>
        <?php else: foreach ($rows as $r):
            [$slabel,$scls] = $stLabel[$r['status']];
            [$elabel,$ecls] = Hr::entryBadge($r['entry_method']); ?>
          <tr>
            <td style="font-weight:600;"><?= e($r['emp_name']) ?></td>
            <td><?= e($typeLabel[$r['leave_type']] ?? $r['leave_type']) ?></td>
            <td class="mono" style="font-size:12px;"><?= e(thai_date_short($r['date_from'])) ?> – <?= e(thai_date_short($r['date_to'])) ?></td>
            <td class="mono"><?= rtrim(rtrim($r['days'],'0'),'.') ?></td>
            <td><span class="badge <?= e($ecls) ?>"><?= e($elabel) ?></span></td>
            <td><span class="badge <?= e($scls) ?>"><?= e($slabel) ?></span></td>
            <td style="text-align:right;">
              <?php if ($r['status'] === 'pending' && $canApprove): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approved">
                  <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--green)"><i class="fa-solid fa-check"></i></button></form>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="rejected">
                  <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--red)"><i class="fa-solid fa-xmark"></i></button></form>
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
