<?php
/** purchase_order_view.php — รายละเอียดใบสั่งซื้อ + รับเข้า */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('inventory.view');

$id = (int) input('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (input('do') === 'receive') {
            Auth::requireCan('inventory.manage');
            $res = Purchasing::receivePurchaseOrder($id);
            flash('success', "รับเข้าตาม PO เรียบร้อย — สร้างใบรับ {$res['gr_no']} + เจ้าหนี้ {$res['bill_no']}");
            redirect('goods_receipt_view.php?id=' . $res['gr_id']);
        } elseif (input('do') === 'cancel') {
            Auth::requireCan('inventory.manage');
            Database::run("UPDATE purchase_orders SET status='cancelled' WHERE id=:id AND status='open'", ['id'=>$id]);
            flash('success', 'ยกเลิกใบสั่งซื้อแล้ว');
        }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('purchase_order_view.php?id=' . $id);
}

$po = Database::one('SELECT po.*, v.name AS vendor_name, v.phone, u.name AS by_name FROM purchase_orders po
    JOIN vendors v ON v.id=po.vendor_id LEFT JOIN users u ON u.id=po.created_by WHERE po.id=:id', ['id' => $id]);
if (!$po) { http_response_code(404); exit('ไม่พบใบสั่งซื้อ'); }
$items = Database::all('SELECT * FROM purchase_order_items WHERE po_id=:id ORDER BY id', ['id' => $id]);
[$sl,$sc] = Purchasing::poStatus($po['status']);
$gr = Database::one('SELECT id, doc_no FROM goods_receipts WHERE po_id=:id ORDER BY id DESC LIMIT 1', ['id' => $id]);

$pageTitle = 'ใบสั่งซื้อ ' . $po['doc_no'];
$activeNav = 'purchase_orders';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1><?= e($po['doc_no']) ?> <span class="badge <?= e($sc) ?>" style="margin-left:8px;vertical-align:middle;"><?= e($sl) ?></span></h1>
    <p>สร้างโดย <?= e($po['by_name'] ?? '-') ?> · <?= e(thai_date_short($po['created_at'])) ?><?= $po['expected_date'] ? ' · กำหนดรับ '.e(thai_date_short($po['expected_date'])) : '' ?></p></div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-ghost" href="<?= e(url('purchase_orders.php')) ?>"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
    <?php if (in_array($po['status'], ['open','partial']) && Auth::can('inventory.manage')): ?>
      <form method="post" style="display:inline;" onsubmit="return confirm('รับเข้าสินค้าตามใบสั่งซื้อนี้? ระบบจะเพิ่มสต็อกและสร้างใบเจ้าหนี้')">
        <?= csrf_field() ?><input type="hidden" name="do" value="receive">
        <button class="btn btn-primary"><i class="fa-solid fa-truck-ramp-box"></i> รับเข้าทั้งหมด</button></form>
      <form method="post" style="display:inline;" onsubmit="return confirm('ยกเลิกใบสั่งซื้อนี้?')">
        <?= csrf_field() ?><input type="hidden" name="do" value="cancel">
        <button class="btn btn-ghost" style="color:var(--red)"><i class="fa-solid fa-ban"></i> ยกเลิก</button></form>
    <?php endif; ?>
    <?php if ($gr): ?><a class="btn btn-ghost" href="<?= e(url('goods_receipt_view.php?id='.$gr['id'])) ?>"><i class="fa-solid fa-truck-ramp-box"></i> ใบรับ <?= e($gr['doc_no']) ?></a><?php endif; ?>
  </div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>สินค้า</th><th>สั่ง</th><th>รับแล้ว</th><th>ราคา/หน่วย</th><th>รวม</th></tr></thead>
      <tbody>
        <?php foreach ($items as $i => $it): $remain = (int)$it['qty'] - (int)$it['qty_received']; ?>
          <tr><td class="text-muted"><?= $i+1 ?></td><td style="font-weight:600;white-space:normal;"><?= e($it['description']) ?></td>
            <td class="mono"><?= (int)$it['qty'] ?></td>
            <td class="mono"><?= (int)$it['qty_received'] ?><?php if ($remain>0): ?> <span class="badge badge-gold" style="font-size:9px;">ค้าง <?= $remain ?></span><?php endif; ?></td>
            <td class="mono"><?= number_format((float)$it['unit_cost'],2) ?></td>
            <td class="mono" style="font-weight:700;"><?= number_format((float)$it['line_total'],2) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="max-width:300px;margin-left:auto;margin-top:18px;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">ยอดรวม</span><span class="mono"><?= number_format((float)$po['subtotal'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">VAT 7%</span><span class="mono"><?= number_format((float)$po['vat'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>รวมทั้งสิ้น</span><span class="mono text-gold"><?= baht($po['total']) ?></span></div>
    </div>
  </div>
  <div class="card" style="align-self:start;">
    <div class="card-title" style="margin-bottom:14px;">ซัพพลายเออร์</div>
    <div style="font-size:14px;font-weight:600;"><?= e($po['vendor_name']) ?></div>
    <?php if ($po['phone']): ?><div class="text-muted" style="font-size:13px;margin-top:4px;"><i class="fa-solid fa-phone"></i> <?= e($po['phone']) ?></div><?php endif; ?>
    <?php if ($po['note']): ?><hr style="border:none;border-top:1px solid var(--border);margin:14px 0;"><div class="text-muted" style="font-size:12px;">หมายเหตุ</div><div style="font-size:13px;white-space:normal;"><?= e($po['note']) ?></div><?php endif; ?>
    <?php if ($po['status']==='open'): ?><div class="alert alert-info" style="margin-top:14px;font-size:12px;"><i class="fa-solid fa-circle-info"></i> ยังไม่รับของ — กด "รับเข้าทั้งหมด" เพื่อเพิ่มสต็อก</div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
