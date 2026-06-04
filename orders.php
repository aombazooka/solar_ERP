<?php
/** orders.php — ใบสั่งขาย (list) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('sales.view');

$orders = Database::all(
    'SELECT so.*, c.name AS customer_name FROM sales_orders so
     JOIN customers c ON c.id=so.customer_id ORDER BY so.id DESC'
);

$pageTitle = 'ใบสั่งซื้อ';
$activeNav = 'orders';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div><h1>ใบสั่งขาย</h1><p>ใบสั่งขายทั้งหมด <?= count($orders) ?> รายการ</p></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ลูกค้า</th><th>ยอดรวม</th><th>สถานะ</th><th>ส่งของเมื่อ</th><th>วันที่</th><th></th></tr></thead>
      <tbody>
        <?php if (!$orders): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีใบสั่งขาย — สร้างได้จากการแปลงใบเสนอราคา</td></tr>
        <?php else: foreach ($orders as $o):
            [$slabel, $scls] = Sales::orderStatus($o['status']); ?>
          <tr>
            <td class="mono text-gold"><?= e($o['doc_no']) ?></td>
            <td style="font-weight:600;"><?= e($o['customer_name']) ?></td>
            <td class="mono" style="font-weight:700;"><?= baht($o['total']) ?></td>
            <td><span class="badge <?= e($scls) ?>"><?= e($slabel) ?></span></td>
            <td class="text-muted"><?= $o['delivered_at'] ? e(thai_date_short($o['delivered_at'])) : '-' ?></td>
            <td class="text-muted"><?= e(thai_date_short($o['created_at'])) ?></td>
            <td style="text-align:right;"><a class="btn btn-ghost" style="padding:5px 10px;font-size:12px;" href="<?= e(url('order_view.php?id='.$o['id'])) ?>">ดู</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
