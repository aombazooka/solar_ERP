<?php
/** payables.php — เจ้าหนี้การค้า (AP) list + สรุป */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('finance.view');

$bills = Database::all('SELECT b.*, v.name AS vendor_name, (b.total-b.paid_amount) AS outstanding
    FROM vendor_bills b JOIN vendors v ON v.id=b.vendor_id ORDER BY b.id DESC');
$totalAP   = (float) Database::scalar("SELECT COALESCE(SUM(total-paid_amount),0) FROM vendor_bills WHERE status IN ('unpaid','partial')");
$paidMonth = (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM vendor_payments WHERE MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE())");
$overdue   = (int) Database::scalar("SELECT COUNT(*) FROM vendor_bills WHERE status IN ('unpaid','partial') AND due_date < CURDATE()");

$pageTitle = 'เจ้าหนี้ (AP)';
$activeNav = 'payables';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header"><div><h1>เจ้าหนี้การค้า (AP)</h1><p>ใบเจ้าหนี้สร้างอัตโนมัติจากการรับเข้าสินค้า</p></div></div>

<div class="grid g3" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon red" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="stat-body"><div class="stat-label">ยอดเจ้าหนี้คงค้าง</div><div class="stat-value" style="font-size:20px;"><?= baht($totalAP) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon green" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-money-bill-transfer"></i></div><div class="stat-body"><div class="stat-label">จ่ายเดือนนี้</div><div class="stat-value" style="font-size:20px;"><?= baht($paidMonth) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-clock"></i></div><div class="stat-body"><div class="stat-label">เกินกำหนดจ่าย</div><div class="stat-value" style="font-size:20px;"><?= $overdue ?> ใบ</div></div></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ซัพพลายเออร์</th><th>ยอดรวม</th><th>จ่ายแล้ว</th><th>คงค้าง</th><th>ครบกำหนด</th><th>สถานะ</th><th></th></tr></thead>
      <tbody>
        <?php if (!$bills): ?>
          <tr><td colspan="8" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีใบเจ้าหนี้</td></tr>
        <?php else: foreach ($bills as $b):
            [$sl,$sc] = Purchasing::billStatus($b['status']);
            $od = $b['status']!=='paid' && $b['due_date'] && strtotime($b['due_date'])<time(); ?>
          <tr>
            <td class="mono text-gold"><?= e($b['doc_no']) ?></td>
            <td style="font-weight:600;"><?= e($b['vendor_name']) ?></td>
            <td class="mono"><?= number_format((float)$b['total'],2) ?></td>
            <td class="mono text-muted"><?= number_format((float)$b['paid_amount'],2) ?></td>
            <td class="mono" style="font-weight:700;<?= (float)$b['outstanding']>0?'color:var(--red)':'' ?>"><?= number_format((float)$b['outstanding'],2) ?></td>
            <td class="text-muted"><?= e(thai_date_short($b['due_date'])) ?><?php if ($od): ?> <span class="badge badge-red" style="font-size:9px;">เกิน</span><?php endif; ?></td>
            <td><span class="badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
            <td style="text-align:right;"><a class="btn btn-ghost" style="padding:5px 10px;font-size:12px;" href="<?= e(url('vendor_bill_view.php?id='.$b['id'])) ?>">ดู</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
