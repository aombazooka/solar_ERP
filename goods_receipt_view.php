<?php
/** goods_receipt_view.php — รายละเอียดใบรับเข้าสินค้า */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('inventory.view');

$id = (int) input('id');
$gr = Database::one('SELECT gr.*, v.name AS vendor_name, u.name AS by_name FROM goods_receipts gr
    JOIN vendors v ON v.id=gr.vendor_id LEFT JOIN users u ON u.id=gr.created_by WHERE gr.id=:id', ['id' => $id]);
if (!$gr) { http_response_code(404); exit('ไม่พบใบรับเข้า'); }
$items = Database::all('SELECT * FROM goods_receipt_items WHERE gr_id=:id ORDER BY id', ['id' => $id]);
$bill = Database::one('SELECT id, doc_no, status FROM vendor_bills WHERE gr_id=:id', ['id' => $id]);

$pageTitle = 'รับเข้า ' . $gr['doc_no'];
$activeNav = 'goods_receipts';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1><?= e($gr['doc_no']) ?></h1><p>รับเข้าโดย <?= e($gr['by_name'] ?? '-') ?> · <?= e(thai_date_short($gr['received_at'])) ?></p></div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-ghost" href="<?= e(url('goods_receipts.php')) ?>"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
    <?php if ($bill): ?><a class="btn btn-ghost" href="<?= e(url('vendor_bill_view.php?id='.$bill['id'])) ?>"><i class="fa-solid fa-file-invoice-dollar"></i> เจ้าหนี้ <?= e($bill['doc_no']) ?></a><?php endif; ?>
  </div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>สินค้า</th><th>จำนวน</th><th>ต้นทุน/หน่วย</th><th>รวม</th></tr></thead>
        <tbody>
          <?php foreach ($items as $i => $it): ?>
            <tr><td class="text-muted"><?= $i+1 ?></td><td style="font-weight:600;white-space:normal;"><?= e($it['description']) ?></td>
              <td class="mono"><?= rtrim(rtrim($it['qty'],'0'),'.') ?></td>
              <td class="mono"><?= number_format((float)$it['unit_cost'],2) ?></td>
              <td class="mono" style="font-weight:700;"><?= number_format((float)$it['line_total'],2) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="max-width:300px;margin-left:auto;margin-top:18px;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">ยอดรวม</span><span class="mono"><?= number_format((float)$gr['subtotal'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">VAT 7%</span><span class="mono"><?= number_format((float)$gr['vat'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>รวมทั้งสิ้น</span><span class="mono text-gold"><?= baht($gr['total']) ?></span></div>
    </div>
  </div>
  <div class="card" style="align-self:start;">
    <div class="card-title" style="margin-bottom:14px;">ซัพพลายเออร์</div>
    <div style="font-size:14px;font-weight:600;"><?= e($gr['vendor_name']) ?></div>
    <?php if ($gr['note']): ?><hr style="border:none;border-top:1px solid var(--border);margin:14px 0;"><div class="text-muted" style="font-size:12px;">หมายเหตุ</div><div style="font-size:13px;white-space:normal;"><?= e($gr['note']) ?></div><?php endif; ?>
    <div class="alert alert-success" style="margin-top:14px;font-size:12px;"><i class="fa-solid fa-circle-check"></i> เพิ่มสต็อกและสร้างใบเจ้าหนี้แล้ว</div>
  </div>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
