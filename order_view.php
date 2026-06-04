<?php
/** order_view.php — รายละเอียดใบสั่งขาย + ส่งของ (ตัดสต็อก) + ออกบิล */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('sales.view');

$id = (int) input('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'deliver') {
            Auth::requireCan('sales.edit');
            Sales::deliver($id);
            flash('success', 'บันทึกการส่งของและตัดสต็อกเรียบร้อย');
        } elseif ($do === 'invoice') {
            Auth::requireCan('finance.manage');
            $res = Finance::createInvoiceFromOrder($id);
            flash('success', "ออกใบแจ้งหนี้ {$res['doc_no']} เรียบร้อย");
            redirect('invoice_view.php?id=' . $res['id']);
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('order_view.php?id=' . $id);
}

$o = Database::one(
    'SELECT so.*, c.name AS customer_name, c.phone, c.province, q.doc_no AS quote_no
     FROM sales_orders so JOIN customers c ON c.id=so.customer_id
     LEFT JOIN quotations q ON q.id=so.quotation_id WHERE so.id=:id',
    ['id' => $id]
);
if (!$o) { http_response_code(404); exit('ไม่พบใบสั่งขาย'); }

$items = Database::all('SELECT * FROM sales_order_items WHERE order_id=:id ORDER BY id', ['id' => $id]);
[$slabel, $scls] = Sales::orderStatus($o['status']);
$invoice = Database::one('SELECT id, doc_no FROM invoices WHERE order_id=:id', ['id' => $id]);

$pageTitle = 'ใบสั่งขาย ' . $o['doc_no'];
$activeNav = 'orders';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div>
    <h1><?= e($o['doc_no']) ?> <span class="badge <?= e($scls) ?>" style="vertical-align:middle;margin-left:8px;"><?= e($slabel) ?></span></h1>
    <p><?php if ($o['quote_no']): ?>จากใบเสนอราคา <?= e($o['quote_no']) ?> · <?php endif; ?><?= e(thai_date_short($o['created_at'])) ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-ghost" href="<?= e(url('orders.php')) ?>"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
    <?php if ($o['status'] === 'pending' && Auth::can('sales.edit')): ?>
      <form method="post" style="display:inline;" onsubmit="return confirm('ยืนยันการส่งของ? ระบบจะตัดสต็อกสินค้าตามรายการ')">
        <?= csrf_field() ?><input type="hidden" name="do" value="deliver">
        <button class="btn btn-primary"><i class="fa-solid fa-truck"></i> บันทึกส่งของ (ตัดสต็อก)</button>
      </form>
    <?php endif; ?>
    <?php if (in_array($o['status'], ['delivered','invoiced']) && !$invoice && Auth::can('finance.manage')): ?>
      <form method="post" style="display:inline;">
        <?= csrf_field() ?><input type="hidden" name="do" value="invoice">
        <button class="btn btn-primary"><i class="fa-solid fa-file-invoice-dollar"></i> ออกใบแจ้งหนี้</button>
      </form>
    <?php endif; ?>
    <?php if ($invoice): ?>
      <a class="btn btn-ghost" href="<?= e(url('invoice_view.php?id='.$invoice['id'])) ?>"><i class="fa-solid fa-file-invoice-dollar"></i> <?= e($invoice['doc_no']) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>รายการ</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>รวม</th></tr></thead>
        <tbody>
          <?php foreach ($items as $i => $it): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td style="font-weight:600;white-space:normal;"><?= e($it['description']) ?></td>
              <td class="mono"><?= rtrim(rtrim($it['qty'],'0'),'.') ?></td>
              <td class="mono"><?= number_format((float)$it['unit_price'],2) ?></td>
              <td class="mono" style="font-weight:700;"><?= number_format((float)$it['line_total'],2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="max-width:300px;margin-left:auto;margin-top:18px;">
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>ยอดรวม</span><span class="mono text-gold"><?= baht($o['total']) ?></span></div>
    </div>
  </div>

  <div class="card" style="align-self:start;">
    <div class="card-title" style="margin-bottom:14px;">ข้อมูลลูกค้า</div>
    <div style="font-size:14px;font-weight:600;margin-bottom:4px;"><?= e($o['customer_name']) ?></div>
    <div style="font-size:13px;color:var(--text-soft);line-height:1.8;">
      <?php if ($o['phone']): ?><div><i class="fa-solid fa-phone" style="width:16px;color:var(--text-muted)"></i> <?= e($o['phone']) ?></div><?php endif; ?>
      <?php if ($o['province']): ?><div><i class="fa-solid fa-location-dot" style="width:16px;color:var(--text-muted)"></i> <?= e($o['province']) ?></div><?php endif; ?>
    </div>
    <?php if ($o['status'] === 'pending'): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
      <div class="alert alert-info" style="margin:0;font-size:12px;"><i class="fa-solid fa-circle-info"></i> ยังไม่ส่งของ — กด "บันทึกส่งของ" เพื่อตัดสต็อก</div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
