<?php
/** payments.php — รายการรับชำระทั้งหมด */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('finance.view');

$payments = Database::all(
    'SELECT p.*, c.name AS customer_name, i.doc_no AS invoice_no
     FROM payments p JOIN customers c ON c.id=p.customer_id
     JOIN invoices i ON i.id=p.invoice_id ORDER BY p.id DESC'
);
$totalReceived = (float) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM payments');

$pageTitle = 'รับชำระ';
$activeNav = 'payments';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div><h1>รับชำระ</h1><p>ประวัติการรับชำระทั้งหมด รวม <?= baht($totalReceived) ?></p></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ใบแจ้งหนี้</th><th>ลูกค้า</th><th>วิธี</th><th>วันที่</th><th>จำนวน</th></tr></thead>
      <tbody>
        <?php if (!$payments): ?>
          <tr><td colspan="6" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีการรับชำระ</td></tr>
        <?php else: foreach ($payments as $p): ?>
          <tr>
            <td class="mono text-gold"><?= e($p['doc_no']) ?></td>
            <td><a class="mono" style="color:var(--blue)" href="<?= e(url('invoice_view.php?id='.$p['invoice_id'])) ?>"><?= e($p['invoice_no']) ?></a></td>
            <td style="font-weight:600;"><?= e($p['customer_name']) ?></td>
            <td><span class="badge badge-blue"><?= e(Finance::methodLabel($p['method'])) ?></span></td>
            <td class="text-muted"><?= e(thai_date_short($p['paid_at'])) ?></td>
            <td class="mono" style="font-weight:700;color:var(--green)"><?= number_format((float)$p['amount'],2) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
