<?php
/** payroll.php — เงินเดือน + คอมมิชชั่น (ดึงจากยอดขาย) + ปิดงวด */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.payroll');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'generate') {
            $res = Hr::generatePayroll(input('period'));
            flash('success', "คำนวณเงินเดือนงวด " . input('period') . " เรียบร้อย ({$res['count']} คน)");
            redirect('payroll.php?period=' . urlencode(input('period')));
        } elseif ($do === 'lock') {
            Hr::lockPayroll((int) input('period_id'));
            flash('success', 'ปิดงวดและล็อกข้อมูลเรียบร้อย (แก้ไขลงเวลา/ลาในงวดนี้ไม่ได้แล้ว)');
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('payroll.php' . (input('period') ? '?period=' . urlencode(input('period')) : ''));
}

$thisPeriod = (date('Y') + 543) . '-' . date('m');
$period = input('period') ?: $thisPeriod;
$periodRow = Database::one('SELECT * FROM payroll_periods WHERE period=:p', ['p' => $period]);
$items = $periodRow
    ? Database::all('SELECT pi.*, e.code, e.name, e.position FROM payroll_items pi JOIN employees e ON e.id=pi.employee_id WHERE pi.period_id=:id ORDER BY e.code', ['id' => $periodRow['id']])
    : [];
$totalNet = array_sum(array_map(fn($i) => (float) $i['net_pay'], $items));
$allPeriods = Database::all('SELECT period, status FROM payroll_periods ORDER BY period DESC');

$pageTitle = 'เงินเดือน / Payroll';
$activeNav = 'payroll';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>เงินเดือน / Payroll</h1><p>คอมมิชชั่นดึงจากยอดขายอัตโนมัติ · ปิดงวดเพื่อล็อกข้อมูล</p></div>
  <form method="post" style="display:flex;gap:8px;align-items:flex-end;"><?= csrf_field() ?><input type="hidden" name="do" value="generate">
    <div><label class="form-label" style="font-size:11px;">งวด (พ.ศ.-เดือน)</label>
      <input class="form-input" name="period" value="<?= e($period) ?>" style="width:130px;padding:8px 12px;" placeholder="2569-06"></div>
    <button class="btn btn-primary"><i class="fa-solid fa-calculator"></i> คำนวณงวด</button>
  </form>
</div>

<?php if ($allPeriods): ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
  <?php foreach ($allPeriods as $p): ?>
    <a class="btn <?= $p['period']===$period?'btn-primary':'btn-ghost' ?>" style="padding:6px 12px;font-size:12px;" href="<?= e(url('payroll.php?period='.urlencode($p['period']))) ?>">
      <?= e($p['period']) ?> <?php if ($p['status']==='locked'): ?><i class="fa-solid fa-lock" style="font-size:10px;"></i><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$periodRow): ?>
  <div class="card" style="text-align:center;padding:50px;">
    <i class="fa-solid fa-calculator" style="font-size:40px;color:var(--text-muted);margin-bottom:14px;display:block;"></i>
    <div class="text-muted">ยังไม่มีข้อมูลงวด <?= e($period) ?> — กด "คำนวณงวด" เพื่อสร้าง</div>
  </div>
<?php else: ?>
  <div class="grid g3" style="margin-bottom:20px;">
    <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-money-check-dollar"></i></div><div class="stat-body"><div class="stat-label">ยอดจ่ายสุทธิรวม</div><div class="stat-value" style="font-size:20px;"><?= baht($totalNet) ?></div></div></div>
    <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon blue" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-users"></i></div><div class="stat-body"><div class="stat-label">พนักงาน</div><div class="stat-value" style="font-size:20px;"><?= count($items) ?> คน</div></div></div>
    <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon <?= $periodRow['status']==='locked'?'green':'gold' ?>" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-<?= $periodRow['status']==='locked'?'lock':'lock-open' ?>"></i></div><div class="stat-body"><div class="stat-label">สถานะงวด</div><div class="stat-value" style="font-size:18px;"><?= $periodRow['status']==='locked'?'ปิดงวดแล้ว':'เปิดอยู่' ?></div></div></div>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="card-title">รายละเอียดเงินเดือนงวด <?= e($period) ?></div>
      <?php if ($periodRow['status'] === 'open' && Auth::can('hr.payroll')): ?>
        <form method="post" onsubmit="return confirm('ปิดงวดนี้? จะล็อกการลงเวลา/ลาในเดือนนี้ทั้งหมด แก้ไขไม่ได้อีก')"><?= csrf_field() ?>
          <input type="hidden" name="do" value="lock"><input type="hidden" name="period_id" value="<?= (int)$periodRow['id'] ?>"><input type="hidden" name="period" value="<?= e($period) ?>">
          <button class="btn btn-primary" style="padding:7px 14px;font-size:12px;"><i class="fa-solid fa-lock"></i> ปิดงวด</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>รหัส</th><th>พนักงาน</th><th>เงินเดือนฐาน</th><th>คอมมิชชั่น</th><th>วันลา</th><th>หัก</th><th>สุทธิ</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td class="mono text-gold"><?= e($it['code']) ?></td>
              <td style="font-weight:600;"><?= e($it['name']) ?><div style="font-size:11px;color:var(--text-muted)"><?= e($it['position'] ?? '') ?></div></td>
              <td class="mono"><?= number_format((float)$it['base_salary'],2) ?></td>
              <td class="mono" style="color:var(--green)"><?= (float)$it['commission']>0?'+'.number_format((float)$it['commission'],2):'-' ?></td>
              <td class="mono"><?= rtrim(rtrim($it['leave_days'],'0'),'.') ?></td>
              <td class="mono" style="color:var(--red)"><?= (float)$it['deduction']>0?'-'.number_format((float)$it['deduction'],2):'-' ?></td>
              <td class="mono" style="font-weight:700;"><?= number_format((float)$it['net_pay'],2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
