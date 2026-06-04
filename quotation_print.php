<?php
/** quotation_print.php — ใบเสนอราคาแบบพิมพ์ (A4 → บันทึก PDF) */
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('sales.view');

$id = (int) input('id');
$q = Database::one(
    'SELECT q.*, c.name AS customer_name, c.phone, c.address, c.province, c.tax_id, u.name AS by_name
     FROM quotations q JOIN customers c ON c.id=q.customer_id
     LEFT JOIN users u ON u.id=q.created_by WHERE q.id=:id', ['id' => $id]
);
if (!$q) { http_response_code(404); exit('ไม่พบใบเสนอราคา'); }
$items = Database::all('SELECT * FROM quotation_items WHERE quotation_id=:id ORDER BY id', ['id' => $id]);
$sysLabel = ['on_grid'=>'On-Grid','hybrid'=>'Hybrid','off_grid'=>'Off-Grid'];
$co = company();
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบเสนอราคา <?= e($q['doc_no']) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/print.css')) ?>">
</head><body>

<div class="toolbar">
  <a class="btn-back" href="<?= e(url('quotation_view.php?id='.$id)) ?>">← กลับ</a>
  <button class="btn-print" onclick="window.print()">🖨 พิมพ์ / บันทึก PDF</button>
</div>

<div class="doc">
  <div class="doc-head">
    <div class="brand">
      <div class="brand-logo"><?= e($co['logo_emoji']) ?></div>
      <div>
        <div class="brand-name"><?= e($co['name']) ?></div>
        <div class="brand-sub"><?= e($co['legal_name'] ?: 'ระบบจัดการร้านขายและติดตั้งโซลาร์เซลล์') ?></div>
        <?php if ($co['address']): ?><div class="brand-sub"><?= e($co['address']) ?></div><?php endif; ?>
        <div class="brand-sub">โทร. <?= e($co['phone'] ?: '-') ?><?= $co['tax_id'] ? ' · เลขภาษี '.e($co['tax_id']) : '' ?></div>
      </div>
    </div>
    <div class="doc-meta">
      <div class="doc-type">ใบเสนอราคา</div>
      <div class="doc-no"><?= e($q['doc_no']) ?></div>
      <div class="doc-date">วันที่ <?= e(thai_date_short($q['created_at'])) ?></div>
      <?php if ($q['valid_until']): ?><div class="doc-date">ยืนราคาถึง <?= e(thai_date_short($q['valid_until'])) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="parties">
    <div class="party">
      <div class="party-label">เสนอแก่ (ลูกค้า)</div>
      <div class="party-name"><?= e($q['customer_name']) ?></div>
      <?php if ($q['address']): ?><div class="party-line"><?= e($q['address']) ?></div><?php endif; ?>
      <?php if ($q['province']): ?><div class="party-line"><?= e($q['province']) ?></div><?php endif; ?>
      <?php if ($q['phone']): ?><div class="party-line">โทร. <?= e($q['phone']) ?></div><?php endif; ?>
      <?php if ($q['tax_id']): ?><div class="party-line">เลขภาษี <?= e($q['tax_id']) ?></div><?php endif; ?>
    </div>
    <div class="party" style="text-align:right;">
      <div class="party-label">รายละเอียดระบบ</div>
      <?php if ($q['system_type']): ?><div class="party-line">ประเภท: <?= e($sysLabel[$q['system_type']] ?? $q['system_type']) ?></div><?php endif; ?>
      <?php if ($q['capacity_kwp']): ?><div class="party-line">ขนาด: <?= rtrim(rtrim($q['capacity_kwp'],'0'),'.') ?> kWp</div><?php endif; ?>
      <div class="party-line">ผู้เสนอ: <?= e($q['by_name'] ?? '-') ?></div>
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
    <div class="row"><span class="muted">รวมเป็นเงิน</span><span><?= number_format((float)$q['subtotal'],2) ?></span></div>
    <div class="row"><span class="muted">ส่วนลด</span><span>-<?= number_format((float)$q['discount'],2) ?></span></div>
    <div class="row"><span class="muted">ภาษีมูลค่าเพิ่ม 7%</span><span><?= number_format((float)$q['vat'],2) ?></span></div>
    <div class="row grand"><span>ยอดสุทธิ</span><span><?= number_format((float)$q['total'],2) ?></span></div>
  </div>

  <?php if ($q['note']): ?><div class="note-box"><strong>หมายเหตุ:</strong> <?= e($q['note']) ?></div><?php endif; ?>

  <div class="doc-foot">
    <div class="sign"><div class="sign-line">ผู้เสนอราคา</div></div>
    <div class="sign"><div class="sign-line">ผู้อนุมัติ / ลูกค้า</div></div>
  </div>
</div>

<?php if (input('auto') === '1'): ?><script>window.onload=()=>window.print();</script><?php endif; ?>
</body></html>
