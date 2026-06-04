<?php
/** quotation_view.php — รายละเอียดใบเสนอราคา */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('sales.view');

$id = (int) input('id');
$q = Database::one(
    'SELECT q.*, c.name AS customer_name, c.phone, c.address, c.province, u.name AS by_name
     FROM quotations q JOIN customers c ON c.id=q.customer_id
     LEFT JOIN users u ON u.id=q.created_by WHERE q.id=:id',
    ['id' => $id]
);
if (!$q) { http_response_code(404); exit('ไม่พบใบเสนอราคา'); }

$items = Database::all('SELECT * FROM quotation_items WHERE quotation_id=:id ORDER BY id', ['id' => $id]);
[$slabel, $scls] = Sales::quotationStatus($q['status']);

$pageTitle = 'ใบเสนอราคา ' . $q['doc_no'];
$activeNav = 'quotations';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div>
    <h1><?= e($q['doc_no']) ?> <span class="badge <?= e($scls) ?>" style="vertical-align:middle;margin-left:8px;"><?= e($slabel) ?></span></h1>
    <p>ออกโดย <?= e($q['by_name'] ?? '-') ?> · <?= e(thai_date_short($q['created_at'])) ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-ghost" href="<?= e(url('quotations.php')) ?>"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
    <a class="btn btn-ghost" href="<?= e(url('quotation_print.php?id='.$id)) ?>" target="_blank"><i class="fa-solid fa-print"></i> พิมพ์ / PDF</a>
    <?php if ($q['status'] === 'draft' && Auth::can('sales.edit')): ?>
      <form method="post" action="<?= e(url('quotations.php')) ?>" style="display:inline;">
        <?= csrf_field() ?><input type="hidden" name="do" value="send"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-ghost"><i class="fa-solid fa-paper-plane"></i> ทำเครื่องหมายว่าส่งแล้ว</button>
      </form>
    <?php endif; ?>
    <?php if ($q['status'] !== 'converted' && $q['status'] !== 'rejected' && Auth::can('sales.create')): ?>
      <form method="post" action="<?= e(url('quotations.php')) ?>" style="display:inline;" onsubmit="return confirm('แปลงใบเสนอราคานี้เป็นใบสั่งขาย?')">
        <?= csrf_field() ?><input type="hidden" name="do" value="convert"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-primary"><i class="fa-solid fa-file-export"></i> แปลงเป็นใบสั่งขาย</button>
      </form>
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
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">ยอดรวม</span><span class="mono"><?= number_format((float)$q['subtotal'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">ส่วนลด</span><span class="mono">-<?= number_format((float)$q['discount'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">VAT 7%</span><span class="mono"><?= number_format((float)$q['vat'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>ยอดสุทธิ</span><span class="mono text-gold"><?= baht($q['total']) ?></span></div>
    </div>
  </div>

  <div class="card" style="align-self:start;">
    <div class="card-title" style="margin-bottom:14px;">ข้อมูลลูกค้า</div>
    <div style="font-size:14px;font-weight:600;margin-bottom:4px;"><?= e($q['customer_name']) ?></div>
    <div style="font-size:13px;color:var(--text-soft);line-height:1.8;">
      <?php if ($q['phone']): ?><div><i class="fa-solid fa-phone" style="width:16px;color:var(--text-muted)"></i> <?= e($q['phone']) ?></div><?php endif; ?>
      <?php if ($q['province']): ?><div><i class="fa-solid fa-location-dot" style="width:16px;color:var(--text-muted)"></i> <?= e($q['province']) ?></div><?php endif; ?>
    </div>
    <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
    <div style="font-size:13px;color:var(--text-soft);line-height:1.9;">
      <div><span class="text-muted">ระบบ:</span> <?= $q['system_type'] ? e(str_replace('_','-',ucfirst($q['system_type']))) : '-' ?></div>
      <div><span class="text-muted">ขนาด:</span> <?= $q['capacity_kwp'] ? rtrim(rtrim($q['capacity_kwp'],'0'),'.').' kWp' : '-' ?></div>
      <div><span class="text-muted">ยืนราคาถึง:</span> <?= e(thai_date_short($q['valid_until'])) ?></div>
    </div>
    <?php if ($q['note']): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
      <div style="font-size:12px;color:var(--text-muted);">หมายเหตุ</div>
      <div style="font-size:13px;white-space:normal;"><?= e($q['note']) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
