<?php
/** advances.php — เบิกเงินล่วงหน้า (ยื่น + อนุมัติ) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::require();
$canApprove = Auth::can('hr.advance');
$me = Hr::currentEmployee();
if (!$canApprove && !$me) { http_response_code(403); exit('บัญชีนี้ไม่ได้ผูกกับพนักงาน'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'request') {
            $empId = $canApprove ? (int) input('employee_id') : (int) $me['id'];
            Hr::requestAdvance($empId, (float) input('amount'), input('reason'));
            flash('success', 'ยื่นคำขอเบิกเงินล่วงหน้าเรียบร้อย');
        } elseif ($do === 'decide') {
            if (!$canApprove) throw new RuntimeException('ไม่มีสิทธิ์อนุมัติ');
            Hr::decideAdvance((int) input('id'), input('decision'));
            flash('success', 'บันทึกการพิจารณาเรียบร้อย');
        }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('advances.php');
}

$employees = $canApprove ? Database::all('SELECT id, code, name FROM employees WHERE is_active=1 ORDER BY name') : [];
$rows = $canApprove
    ? Database::all("SELECT a.*, e.name AS emp_name FROM salary_advances a JOIN employees e ON e.id=a.employee_id ORDER BY a.id DESC LIMIT 50")
    : Database::all("SELECT a.*, e.name AS emp_name FROM salary_advances a JOIN employees e ON e.id=a.employee_id WHERE a.employee_id=:e ORDER BY a.id DESC", ['e'=>$me['id']]);
$stLabel = ['pending'=>['รออนุมัติ','badge-gold'],'approved'=>['อนุมัติ (รอหัก)','badge-blue'],'rejected'=>['ปฏิเสธ','badge-red'],'deducted'=>['หักแล้ว','badge-green']];

$pageTitle = 'เบิกเงินล่วงหน้า';
$activeNav = 'advances';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>เบิกเงินล่วงหน้า</h1><p>ยอดที่อนุมัติจะถูกหักจากเงินเดือนงวดถัดไปอัตโนมัติ</p></div>
  <button class="btn btn-primary" onclick="document.getElementById('advForm').classList.toggle('hidden')"><i class="fa-solid fa-hand-holding-dollar"></i> ขอเบิก</button>
</div>

<div class="card hidden" id="advForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">ยื่นขอเบิกเงินล่วงหน้า</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="request">
    <div class="grid g3">
      <?php if ($canApprove): ?>
      <div class="form-group"><label class="form-label">พนักงาน *</label>
        <select class="form-select" name="employee_id" required><?php foreach ($employees as $emp): ?><option value="<?= (int)$emp['id'] ?>"><?= e($emp['code']) ?> · <?= e($emp['name']) ?></option><?php endforeach; ?></select></div>
      <?php else: ?><input type="hidden" name="employee_id" value="<?= (int)$me['id'] ?>"><?php endif; ?>
      <div class="form-group"><label class="form-label">จำนวนเงิน *</label><input class="form-input" type="number" step="0.01" min="1" name="amount" required></div>
      <div class="form-group" style="<?= $canApprove?'':'grid-column:span 2;' ?>"><label class="form-label">เหตุผล</label><input class="form-input" name="reason" placeholder="เช่น ค่ารักษาพยาบาล"></div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> ยื่นคำขอ</button>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><?php if ($canApprove): ?><th>พนักงาน</th><?php endif; ?><th>จำนวน</th><th>เหตุผล</th><th>วันที่</th><th>สถานะ</th><th>งวดที่หัก</th><?php if ($canApprove): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีคำขอเบิก</td></tr>
        <?php else: foreach ($rows as $r): [$sl,$sc]=$stLabel[$r['status']]; ?>
          <tr>
            <td class="mono text-gold"><?= e($r['doc_no']) ?></td>
            <?php if ($canApprove): ?><td style="font-weight:600;"><?= e($r['emp_name']) ?></td><?php endif; ?>
            <td class="mono" style="font-weight:700;"><?= number_format((float)$r['amount'],2) ?></td>
            <td style="white-space:normal;"><?= e($r['reason'] ?? '-') ?></td>
            <td class="text-muted"><?= e(thai_date_short($r['request_date'])) ?></td>
            <td><span class="badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
            <td class="text-muted"><?= e($r['period_deducted'] ?? '-') ?></td>
            <?php if ($canApprove): ?>
            <td style="text-align:right;">
              <?php if ($r['status']==='pending'): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approved"><button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--green)"><i class="fa-solid fa-check"></i></button></form>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="rejected"><button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--red)"><i class="fa-solid fa-xmark"></i></button></form>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
