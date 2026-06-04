<?php
/** invoice_print.php — ใบแจ้งหนี้/ใบกำกับภาษีแบบพิมพ์ (A4 → บันทึก PDF) */
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('finance.view');

$id = (int) input('id');
$inv = Database::one(
    'SELECT i.*, c.name AS customer_name, c.phone, c.address, c.province, c.tax_id, so.doc_no AS order_no, u.name AS by_name
     FROM invoices i JOIN customers c ON c.id=i.customer_id
     LEFT JOIN sales_orders so ON so.id=i.order_id
     LEFT JOIN users u ON u.id=i.created_by WHERE i.id=:id', ['id' => $id]
);
if (!$inv) { http_response_code(404); exit('ไม่พบใบแจ้งหนี้'); }
$items = Database::all('SELECT * FROM invoice_items WHERE invoice_id=:id ORDER BY id', ['id' => $id]);

// แยกฐาน + VAT ตามโหมด VAT ของเอกสาร
if (($inv['vat_mode'] ?? 'exclude') === 'none') {
    $base = (float) $inv['total']; $vat = 0.0;
} else {                                   // exclude/include → ยอดรวมเป็นราคารวม VAT
    $base = round((float)$inv['total'] / 1.07, 2);
    $vat  = round((float)$inv['total'] - $base, 2);
}
$outstanding = (float)$inv['total'] - (float)$inv['paid_amount'];
$stampMap = ['paid'=>['stamp-paid','ชำระแล้ว'],'partial'=>['stamp-partial','ชำระบางส่วน'],'unpaid'=>['stamp-unpaid','ค้างชำระ'],'void'=>['stamp-unpaid','ยกเลิก']];
[$stampCls,$stampTxt] = $stampMap[$inv['status']] ?? ['stamp-unpaid','-'];
$co = company();
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบกำกับภาษี <?= e($inv['doc_no']) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/print.css')) ?>">
</head><body>

<div class="toolbar">
  <a class="btn-back" href="<?= e(url('invoice_view.php?id='.$id)) ?>">← กลับ</a>
  <button class="btn-print" onclick="window.print()">🖨 พิมพ์ / บันทึก PDF</button>
</div>

<div class="doc">
  <div class="doc-head">
    <div class="brand">
      <div class="brand-logo"><?= e($co['logo_emoji']) ?></div>
      <div>
        <div class="brand-name"><?= e($co['name']) ?></div>
        <div class="brand-sub"><?= e($co['legal_name'] ?: 'ใบกำกับภาษี / ใบแจ้งหนี้') ?></div>
        <?php if ($co['address']): ?><div class="brand-sub"><?= e($co['address']) ?></div><?php endif; ?>
        <div class="brand-sub">โทร. <?= e($co['phone'] ?: '-') ?><?= $co['tax_id'] ? ' · เลขภาษี '.e($co['tax_id']) : '' ?></div>
      </div>
    </div>
    <div class="doc-meta">
      <div class="doc-type">ใบกำกับภาษี</div>
      <div class="doc-no"><?= e($inv['doc_no']) ?></div>
      <div class="doc-date">วันที่ออก <?= e(thai_date_short($inv['issued_at'])) ?></div>
      <div class="doc-date">ครบกำหนด <?= e(thai_date_short($inv['due_date'])) ?></div>
    </div>
  </div>

  <div class="parties">
    <div class="party">
      <div class="party-label">ลูกค้า</div>
      <div class="party-name"><?= e($inv['customer_name']) ?></div>
      <?php if ($inv['address']): ?><div class="party-line"><?= e($inv['address']) ?></div><?php endif; ?>
      <?php if ($inv['province']): ?><div class="party-line"><?= e($inv['province']) ?></div><?php endif; ?>
      <?php if ($inv['phone']): ?><div class="party-line">โทร. <?= e($inv['phone']) ?></div><?php endif; ?>
      <?php if ($inv['tax_id']): ?><div class="party-line">เลขประจำตัวผู้เสียภาษี <?= e($inv['tax_id']) ?></div><?php endif; ?>
    </div>
    <div class="party" style="text-align:right;">
      <div class="party-label">อ้างอิง</div>
      <?php if ($inv['order_no']): ?><div class="party-line">ใบสั่งขาย: <?= e($inv['order_no']) ?></div><?php endif; ?>
      <div class="party-line">ผู้ออก: <?= e($inv['by_name'] ?? '-') ?></div>
      <div style="margin-top:10px;"><span class="status-stamp <?= e($stampCls) ?>"><?= e($stampTxt) ?></span></div>
    </div>
  </div>

  <table>
    <thead><tr><th style="width:40px">#</th><th>รายการ</th><th style="width:70px" class="num">จำนวน</th><th style="width:110px" class="num">ราคา/หน่วย</th><th style="width:120px" class="num">จำนวนเงิน</th></tr></thead>
    <tbody>
      <?php foreach ($items as $i => $it): ?>
        <tr><td><?= $i+1 ?></td><td><?= e($it['description']) ?></td>
          <td class="num"><?= rtrim(rtrim($it['qty'],'0'),'.') ?></td>
          <td class="num"><?= number_format((float)$it['unit_price'],2) ?></td>
          <td class="num"><?= number_format((float)$it['line_total'],2) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <div class="row"><span class="muted">มูลค่าก่อนภาษี</span><span><?= number_format($base,2) ?></span></div>
    <div class="row"><span class="muted">ภาษีมูลค่าเพิ่ม 7%</span><span><?= number_format($vat,2) ?></span></div>
    <div class="row grand"><span>ยอดรวมทั้งสิ้น</span><span><?= number_format((float)$inv['total'],2) ?></span></div>
    <?php if ((float)$inv['paid_amount'] > 0): ?>
      <div class="row"><span class="muted">ชำระแล้ว</span><span>-<?= number_format((float)$inv['paid_amount'],2) ?></span></div>
      <div class="row" style="font-weight:700;"><span>คงค้าง</span><span><?= number_format($outstanding,2) ?></span></div>
    <?php endif; ?>
  </div>

  <div class="doc-foot">
    <div class="sign"><div class="sign-line">ผู้รับเงิน</div></div>
    <div class="sign"><div class="sign-line">ผู้รับสินค้า / ลูกค้า</div></div>
  </div>
</div>

<?php if (input('auto') === '1'): ?><script>window.onload=()=>window.print();</script><?php endif; ?>
</body></html>
