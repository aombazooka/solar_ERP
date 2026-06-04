<?php
/** billing.php — ใบแจ้งหนี้ / ใบกำกับ (list + สรุป AR) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('finance.view');

$perPage = 15; $page = current_page();
$total = (int) Database::scalar('SELECT COUNT(*) FROM invoices');
$invoices = Database::all(
    'SELECT i.*, c.name AS customer_name, (i.total - i.paid_amount) AS outstanding
     FROM invoices i JOIN customers c ON c.id=i.customer_id ORDER BY i.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page-1)*$perPage)
);
$totalAR    = (float) Database::scalar("SELECT COALESCE(SUM(total - paid_amount),0) FROM invoices WHERE status IN ('unpaid','partial')");
$paidMonth  = (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE())");
$overdue    = (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','partial') AND due_date < CURDATE()");

$pageTitle = 'ออกบิล / ใบกำกับ';
$activeNav = 'billing';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div><h1>ใบแจ้งหนี้ / ใบกำกับภาษี</h1><p>ออกบิลได้จากใบสั่งขายที่ส่งของแล้ว</p></div>
  <a class="btn btn-ghost" href="<?= e(url('export.php?type=invoices')) ?>"><i class="fa-solid fa-file-csv"></i> Export</a>
</div>

<div class="grid g3" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;">
    <div class="stat-icon red" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    <div class="stat-body"><div class="stat-label">ยอดลูกหนี้คงค้าง (AR)</div><div class="stat-value" style="font-size:20px;"><?= baht($totalAR) ?></div></div>
  </div>
  <div class="stat-card" style="padding:16px 18px;">
    <div class="stat-icon green" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-money-bill-trend-up"></i></div>
    <div class="stat-body"><div class="stat-label">รับชำระเดือนนี้</div><div class="stat-value" style="font-size:20px;"><?= baht($paidMonth) ?></div></div>
  </div>
  <div class="stat-card" style="padding:16px 18px;">
    <div class="stat-icon gold" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-body"><div class="stat-label">เกินกำหนดชำระ</div><div class="stat-value" style="font-size:20px;"><?= $overdue ?> ใบ</div></div>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ลูกค้า</th><th>ยอดรวม</th><th>ชำระแล้ว</th><th>คงค้าง</th><th>ครบกำหนด</th><th>สถานะ</th><th></th></tr></thead>
      <tbody>
        <?php if (!$invoices): ?>
          <tr><td colspan="8" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีใบแจ้งหนี้</td></tr>
        <?php else: foreach ($invoices as $inv):
            [$slabel, $scls] = Finance::invoiceStatus($inv['status']);
            $od = $inv['status'] !== 'paid' && $inv['due_date'] && strtotime($inv['due_date']) < time(); ?>
          <tr>
            <td class="mono text-gold"><?= e($inv['doc_no']) ?></td>
            <td style="font-weight:600;"><?= e($inv['customer_name']) ?></td>
            <td class="mono"><?= number_format((float)$inv['total'],2) ?></td>
            <td class="mono text-muted"><?= number_format((float)$inv['paid_amount'],2) ?></td>
            <td class="mono" style="font-weight:700;<?= (float)$inv['outstanding']>0?'color:var(--red)':'' ?>"><?= number_format((float)$inv['outstanding'],2) ?></td>
            <td class="text-muted"><?= e(thai_date_short($inv['due_date'])) ?><?php if ($od): ?> <span class="badge badge-red" style="font-size:9px;">เกินกำหนด</span><?php endif; ?></td>
            <td><span class="badge <?= e($scls) ?>"><?= e($slabel) ?></span></td>
            <td style="text-align:right;"><a class="btn btn-ghost" style="padding:5px 10px;font-size:12px;" href="<?= e(url('invoice_view.php?id='.$inv['id'])) ?>">ดู</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pager($total, $perPage, $page, url('billing.php')) ?>
</div>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
