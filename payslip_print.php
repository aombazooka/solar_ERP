<?php
/** payslip_print.php — สลิปเงินเดือนแบบพิมพ์ (เจ้าของ หรือ HR เท่านั้น) */
require_once __DIR__ . '/app/bootstrap.php';
Auth::require();

$id = (int) input('id');
$s = Database::one(
    'SELECT pi.*, pp.period, pp.status, e.code, e.name, e.position, e.department, e.user_id
     FROM payroll_items pi JOIN payroll_periods pp ON pp.id=pi.period_id
     JOIN employees e ON e.id=pi.employee_id WHERE pi.id=:id', ['id' => $id]
);
if (!$s) { http_response_code(404); exit('ไม่พบสลิป'); }

// สิทธิ์: เจ้าของสลิป หรือ ผู้มีสิทธิ์จัดการเงินเดือน
$isOwner = $s['user_id'] !== null && (int)$s['user_id'] === Auth::id();
if (!$isOwner && !Auth::can('hr.payroll')) { http_response_code(403); exit('ไม่มีสิทธิ์ดูสลิปนี้'); }

$earnings = (float)$s['base_salary'] + (float)$s['commission'] + (float)$s['ot_pay'];
$co = company();
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สลิปเงินเดือน <?= e($s['period']) ?> · <?= e($s['code']) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/print.css')) ?>">
</head><body>
<div class="toolbar">
  <a class="btn-back" href="<?= e(url('payslip.php')) ?>">← กลับ</a>
  <button class="btn-print" onclick="window.print()">🖨 พิมพ์ / บันทึก PDF</button>
</div>
<div class="doc">
  <div class="doc-head">
    <div class="brand"><div class="brand-logo"><?= e($co['logo_emoji']) ?></div><div><div class="brand-name"><?= e($co['name']) ?></div><div class="brand-sub"><?= e($co['legal_name'] ?: 'สลิปเงินเดือน (Payslip)') ?></div></div></div>
    <div class="doc-meta"><div class="doc-type">PAYSLIP</div><div class="doc-no">งวด <?= e($s['period']) ?></div></div>
  </div>
  <div class="parties">
    <div class="party">
      <div class="party-label">พนักงาน</div>
      <div class="party-name"><?= e($s['name']) ?></div>
      <div class="party-line">รหัส <?= e($s['code']) ?></div>
      <?php if ($s['position']): ?><div class="party-line"><?= e($s['position']) ?><?= $s['department']?' · '.e($s['department']):'' ?></div><?php endif; ?>
    </div>
    <div class="party" style="text-align:right;">
      <div class="party-label">สถานะงวด</div>
      <div class="party-line"><?= $s['status']==='locked'?'ปิดงวดแล้ว (Final)':'ชั่วคราว (Draft)' ?></div>
    </div>
  </div>

  <table>
    <thead><tr><th>รายการ</th><th class="num">รายได้</th><th class="num">รายการหัก</th></tr></thead>
    <tbody>
      <tr><td>เงินเดือนฐาน</td><td class="num"><?= number_format((float)$s['base_salary'],2) ?></td><td class="num">—</td></tr>
      <tr><td>คอมมิชชั่นจากการขาย</td><td class="num"><?= (float)$s['commission']>0?number_format((float)$s['commission'],2):'—' ?></td><td class="num">—</td></tr>
      <tr><td>ค่าล่วงเวลา (OT)</td><td class="num"><?= (float)$s['ot_pay']>0?number_format((float)$s['ot_pay'],2):'—' ?></td><td class="num">—</td></tr>
      <tr><td>หักลากิจ/ลาไม่รับค่าจ้าง (<?= rtrim(rtrim($s['leave_days'],'0'),'.') ?> วัน)</td><td class="num">—</td><td class="num"><?= (float)$s['deduction']>0?number_format((float)$s['deduction'],2):'—' ?></td></tr>
      <tr><td>หักเบิกเงินล่วงหน้า</td><td class="num">—</td><td class="num"><?= (float)($s['advance_deduct']??0)>0?number_format((float)$s['advance_deduct'],2):'—' ?></td></tr>
    </tbody>
  </table>

  <div class="totals">
    <div class="row"><span class="muted">รวมรายได้</span><span><?= number_format($earnings,2) ?></span></div>
    <div class="row"><span class="muted">รวมรายการหัก</span><span>-<?= number_format((float)$s['deduction'] + (float)($s['advance_deduct']??0),2) ?></span></div>
    <div class="row grand"><span>เงินสุทธิที่ได้รับ</span><span><?= number_format((float)$s['net_pay'],2) ?></span></div>
  </div>

  <div class="doc-foot">
    <div class="sign"><div class="sign-line">ผู้รับเงิน</div></div>
    <div class="sign"><div class="sign-line">ฝ่ายบุคคล / การเงิน</div></div>
  </div>
</div>
</body></html>
