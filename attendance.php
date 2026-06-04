<?php
/** attendance.php — ลงเวลาทำงาน (รองรับ HR ทำแทน §5.1) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        Hr::saveAttendance(
            (int) input('employee_id'), input('work_date'),
            input('check_in'), input('check_out'), input('note'), input('acted_for_reason')
        );
        flash('success', 'บันทึกการลงเวลาเรียบร้อย');
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('attendance.php');
}

$canManage = Auth::can('hr.manage');
$me = Hr::currentEmployee();
// พนักงานทั่วไปเห็นเฉพาะตัวเอง / HR เห็นทุกคน
$employees = $canManage
    ? Database::all('SELECT id, code, name, user_id FROM employees WHERE is_active=1 ORDER BY name')
    : ($me ? [['id' => $me['id'], 'code' => $me['code'], 'name' => $me['name'], 'user_id' => $me['user_id']]] : []);

$rows = $canManage
    ? Database::all("SELECT a.*, e.name AS emp_name, u.name AS by_name FROM attendance a
         JOIN employees e ON e.id=a.employee_id LEFT JOIN users u ON u.id=a.created_by
         ORDER BY a.work_date DESC, a.id DESC LIMIT 30")
    : ($me ? Database::all("SELECT a.*, e.name AS emp_name, u.name AS by_name FROM attendance a
         JOIN employees e ON e.id=a.employee_id LEFT JOIN users u ON u.id=a.created_by
         WHERE a.employee_id=:e ORDER BY a.work_date DESC LIMIT 30", ['e' => $me['id']]) : []);

$pageTitle = 'ลงเวลาทำงาน';
$activeNav = 'attendance';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ลงเวลาทำงาน</h1><p><?= $canManage ? 'HR บันทึกแทนได้ (ต้องระบุเหตุผล)' : 'บันทึกเวลาเข้า-ออกงานของคุณ' ?></p></div>
  <?php if ($employees): ?>
    <button class="btn btn-primary" onclick="document.getElementById('attForm').classList.toggle('hidden')"><i class="fa-solid fa-clock"></i> บันทึกเวลา</button>
  <?php endif; ?>
</div>

<?php if (!$me && !$canManage): ?>
  <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> บัญชีของคุณยังไม่ได้ผูกกับข้อมูลพนักงาน — ติดต่อ HR</div>
<?php endif; ?>

<?php if ($employees): ?>
<div class="card hidden" id="attForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">บันทึกการลงเวลา</div></div>
  <form method="post"><?= csrf_field() ?>
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;">
        <label class="form-label">พนักงาน *</label>
        <select class="form-select" name="employee_id" id="empSel" required onchange="toggleReason()">
          <?php foreach ($employees as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" data-self="<?= ($emp['user_id'] && (int)$emp['user_id']===Auth::id())?'1':'0' ?>"><?= e($emp['code']) ?> · <?= e($emp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">วันที่ *</label><input class="form-input" type="date" name="work_date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-group"></div>
      <div class="form-group"><label class="form-label">เวลาเข้า</label><input class="form-input" type="time" name="check_in" value="08:00"></div>
      <div class="form-group"><label class="form-label">เวลาออก</label><input class="form-input" type="time" name="check_out" value="17:00"></div>
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">หมายเหตุ</label><input class="form-input" name="note"></div>
    </div>
    <div class="form-group hidden" id="reasonWrap" style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius-sm);padding:14px;">
      <label class="form-label" style="color:var(--solar-gold)"><i class="fa-solid fa-user-shield"></i> เหตุผลที่ทำแทน (บังคับ — §5.1)</label>
      <input class="form-input" name="acted_for_reason" placeholder="เช่น พนักงานลืมลงเวลา / ออกหน้างานไม่มีสัญญาณ">
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<script>
function toggleReason(){
  const opt = document.getElementById('empSel').selectedOptions[0];
  document.getElementById('reasonWrap').classList.toggle('hidden', opt.dataset.self === '1');
}
toggleReason();
</script>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>วันที่</th><th>พนักงาน</th><th>เข้า</th><th>ออก</th><th>วิธีบันทึก</th><th>ผู้บันทึก</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีข้อมูลการลงเวลา</td></tr>
        <?php else: foreach ($rows as $r):
            [$elabel,$ecls] = Hr::entryBadge($r['entry_method']); ?>
          <tr>
            <td class="mono"><?= e(thai_date_short($r['work_date'])) ?></td>
            <td style="font-weight:600;"><?= e($r['emp_name']) ?></td>
            <td class="mono"><?= e($r['check_in'] ? substr($r['check_in'],0,5) : '-') ?></td>
            <td class="mono"><?= e($r['check_out'] ? substr($r['check_out'],0,5) : '-') ?></td>
            <td><span class="badge <?= e($ecls) ?>"><?= e($elabel) ?></span><?php if ($r['is_locked']): ?> <i class="fa-solid fa-lock text-muted" style="font-size:10px;"></i><?php endif; ?></td>
            <td class="text-muted" style="font-size:12px;"><?= e($r['by_name'] ?? '-') ?><?php if ($r['acted_for_reason']): ?><div style="font-size:10px;color:var(--solar-gold)" title="<?= e($r['acted_for_reason']) ?>">⚑ <?= e(mb_strimwidth($r['acted_for_reason'],0,24,'…','UTF-8')) ?></div><?php endif; ?></td>
            <td></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
