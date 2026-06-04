<?php
/** approvals.php — กล่องรออนุมัติรวม (ลา + OT) สำหรับผู้อนุมัติ */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::require();
if (!Auth::can('hr.approve') && !Auth::can('hr.approve_team')) { http_response_code(403); exit('คุณไม่มีสิทธิ์เข้าหน้านี้'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $decision = input('decision');
        if (input('do') === 'leave') Hr::decideLeave((int) input('id'), $decision);   // เช็ค scope ทีมภายใน
        elseif (input('do') === 'ot') Hr::decideOt((int) input('id'), $decision);
        flash('success', 'บันทึกการพิจารณาเรียบร้อย');
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('approvals.php');
}

// HR/admin เห็นทั้งองค์กร · หัวหน้างานเห็นเฉพาะทีมตัวเอง
$teamScope = !Auth::can('hr.approve');
$myTeam = $teamScope ? Hr::approverTeam() : null;
$lw = $teamScope ? ' AND e.team = :t' : '';
$lp = $teamScope ? ['t' => (string) $myTeam] : [];
$leaves = Database::all("SELECT l.*, e.name AS emp_name FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE l.status='pending'$lw ORDER BY l.id", $lp);
$ots    = Database::all("SELECT o.*, e.name AS emp_name FROM ot_requests o JOIN employees e ON e.id=o.employee_id WHERE o.status='pending'$lw ORDER BY o.id", $lp);
$ltLabel = array_column(Hr::leaveTypes(), 'name', 'code');
$total = count($leaves) + count($ots);

$pageTitle = 'รออนุมัติ';
$activeNav = 'approvals';
require __DIR__ . '/app/layout_header.php';

function decideButtons(string $kind, int $id): string {
    ob_start(); ?>
    <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $kind ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="decision" value="approved">
      <button class="btn btn-primary" style="padding:5px 12px;font-size:12px;"><i class="fa-solid fa-check"></i> อนุมัติ</button></form>
    <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $kind ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="decision" value="rejected">
      <button class="btn btn-ghost" style="padding:5px 12px;font-size:12px;color:var(--red)"><i class="fa-solid fa-xmark"></i> ปฏิเสธ</button></form>
    <?php return ob_get_clean();
}
?>
<div class="page-header">
  <div><h1>กล่องรออนุมัติ</h1><p>คำขอที่รอการพิจารณาทั้งหมด <?= $total ?> รายการ</p></div>
</div>

<?php if ($total === 0): ?>
  <div class="card" style="text-align:center;padding:50px;"><i class="fa-solid fa-circle-check" style="font-size:42px;color:var(--green);opacity:.6;display:block;margin-bottom:12px;"></i><div class="text-muted">ไม่มีคำขอรออนุมัติ — เคลียร์หมดแล้ว 🎉</div></div>
<?php else: ?>

<div class="grid g2">
  <!-- ลา -->
  <div class="card">
    <div class="card-head"><div class="card-title"><i class="fa-solid fa-plane-departure text-gold"></i> ใบลา</div><span class="badge badge-gold"><?= count($leaves) ?></span></div>
    <?php if (!$leaves): ?><div class="text-muted" style="font-size:13px;text-align:center;padding:18px;">ไม่มีใบลารออนุมัติ</div>
    <?php else: foreach ($leaves as $l): ?>
      <div style="padding:12px 0;border-bottom:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:10px;">
          <div><div style="font-weight:600;"><?= e($l['emp_name']) ?></div>
            <div class="text-muted" style="font-size:12px;"><?= e($ltLabel[$l['leave_type']] ?? $l['leave_type']) ?> · <?= e(thai_date_short($l['date_from'])) ?>–<?= e(thai_date_short($l['date_to'])) ?> · <?= rtrim(rtrim($l['days'],'0'),'.') ?> วัน</div>
            <?php if ($l['reason']): ?><div style="font-size:12px;margin-top:3px;">📝 <?= e($l['reason']) ?></div><?php endif; ?>
            <?php if ($l['entry_method']==='on_behalf'): ?><div style="font-size:11px;color:var(--solar-gold);">⚑ HR ทำแทน: <?= e($l['acted_for_reason']) ?></div><?php endif; ?>
          </div>
        </div>
        <div style="margin-top:8px;"><?= decideButtons('leave', (int)$l['id']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- OT -->
  <div class="card">
    <div class="card-head"><div class="card-title"><i class="fa-solid fa-business-time text-gold"></i> OT (ล่วงเวลา)</div><span class="badge badge-gold"><?= count($ots) ?></span></div>
    <?php if (!$ots): ?><div class="text-muted" style="font-size:13px;text-align:center;padding:18px;">ไม่มีคำขอ OT รออนุมัติ</div>
    <?php else: foreach ($ots as $o): ?>
      <div style="padding:12px 0;border-bottom:1px solid var(--border);">
        <div><div style="font-weight:600;"><?= e($o['emp_name']) ?></div>
          <div class="text-muted" style="font-size:12px;"><?= e(thai_date_short($o['ot_date'])) ?> · <strong style="color:var(--text-primary)"><?= rtrim(rtrim($o['hours'],'0'),'.') ?> ชม.</strong></div>
          <?php if ($o['reason']): ?><div style="font-size:12px;margin-top:3px;">📝 <?= e($o['reason']) ?></div><?php endif; ?>
          <?php if ($o['entry_method']==='on_behalf'): ?><div style="font-size:11px;color:var(--solar-gold);">⚑ HR ทำแทน: <?= e($o['acted_for_reason']) ?></div><?php endif; ?>
        </div>
        <div style="margin-top:8px;"><?= decideButtons('ot', (int)$o['id']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
