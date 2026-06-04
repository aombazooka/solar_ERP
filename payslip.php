<?php
/** payslip.php — สลิปเงินเดือนของตัวเอง (self-service ทุกคน) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::require();

$emp = Hr::currentEmployee();
$slips = $emp ? Database::all(
    'SELECT pi.*, pp.period, pp.status FROM payroll_items pi
     JOIN payroll_periods pp ON pp.id=pi.period_id
     WHERE pi.employee_id=:e ORDER BY pp.period DESC', ['e' => $emp['id']]
) : [];

$pageTitle = 'สลิปเงินเดือนของฉัน';
$activeNav = 'payslip';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>สลิปเงินเดือนของฉัน</h1><p><?= $emp ? e($emp['code']).' · '.e($emp['name']) : 'บัญชียังไม่ผูกพนักงาน' ?></p></div>
</div>

<?php if (!$emp): ?>
  <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> บัญชีของคุณยังไม่ได้ผูกกับข้อมูลพนักงาน — ติดต่อ HR</div>
<?php elseif (!$slips): ?>
  <div class="card" style="text-align:center;padding:50px;"><i class="fa-solid fa-receipt" style="font-size:40px;color:var(--text-muted);opacity:.4;display:block;margin-bottom:12px;"></i><div class="text-muted">ยังไม่มีสลิปเงินเดือน — รอ HR ประมวลผลงวด</div></div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>งวด</th><th>เงินเดือนฐาน</th><th>คอมมิชชั่น</th><th>OT</th><th>หักลา</th><th>เบิกล่วงหน้า</th><th>สุทธิ</th><th>สถานะงวด</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($slips as $s): ?>
          <tr>
            <td class="mono text-gold"><?= e($s['period']) ?></td>
            <td class="mono"><?= number_format((float)$s['base_salary'],2) ?></td>
            <td class="mono" style="color:var(--green)"><?= (float)$s['commission']>0?'+'.number_format((float)$s['commission'],2):'-' ?></td>
            <td class="mono" style="color:var(--green)"><?= (float)$s['ot_pay']>0?'+'.number_format((float)$s['ot_pay'],2):'-' ?></td>
            <td class="mono" style="color:var(--red)"><?= (float)$s['deduction']>0?'-'.number_format((float)$s['deduction'],2):'-' ?></td>
            <td class="mono" style="color:var(--red)"><?= (float)($s['advance_deduct']??0)>0?'-'.number_format((float)$s['advance_deduct'],2):'-' ?></td>
            <td class="mono" style="font-weight:700;"><?= number_format((float)$s['net_pay'],2) ?></td>
            <td><span class="badge <?= $s['status']==='locked'?'badge-green':'badge-gold' ?>"><?= $s['status']==='locked'?'ปิดงวดแล้ว':'ชั่วคราว' ?></span></td>
            <td style="text-align:right;"><a class="btn btn-ghost" style="padding:5px 10px;font-size:12px;" href="<?= e(url('payslip_print.php?id='.$s['id'])) ?>" target="_blank"><i class="fa-solid fa-print"></i> สลิป</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
