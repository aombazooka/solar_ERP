<?php
/** invoice_view.php — รายละเอียดใบแจ้งหนี้ + บันทึกรับชำระ */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('finance.view');

$id = (int) input('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('finance.manage');
    csrf_verify();
    try {
        $res = Finance::recordPayment(
            $id, (float) input('amount'), input('method'), input('paid_at'), input('note')
        );
        flash('success', "บันทึกรับชำระ {$res['doc_no']} เรียบร้อย" . ($res['status'] === 'paid' ? ' (ชำระครบแล้ว)' : ''));
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('invoice_view.php?id=' . $id);
}

$inv = Database::one(
    'SELECT i.*, c.name AS customer_name, c.phone, c.province, c.tax_id, so.doc_no AS order_no
     FROM invoices i JOIN customers c ON c.id=i.customer_id
     LEFT JOIN sales_orders so ON so.id=i.order_id WHERE i.id=:id',
    ['id' => $id]
);
if (!$inv) { http_response_code(404); exit('ไม่พบใบแจ้งหนี้'); }

$items    = Database::all('SELECT * FROM invoice_items WHERE invoice_id=:id ORDER BY id', ['id' => $id]);
$payments = Database::all('SELECT * FROM payments WHERE invoice_id=:id ORDER BY id', ['id' => $id]);
[$slabel, $scls] = Finance::invoiceStatus($inv['status']);
$outstanding = (float) $inv['total'] - (float) $inv['paid_amount'];

$pageTitle = 'ใบแจ้งหนี้ ' . $inv['doc_no'];
$activeNav = 'billing';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div>
    <h1><?= e($inv['doc_no']) ?> <span class="badge <?= e($scls) ?>" style="vertical-align:middle;margin-left:8px;"><?= e($slabel) ?></span></h1>
    <p><?php if ($inv['order_no']): ?>จากใบสั่งขาย <?= e($inv['order_no']) ?> · <?php endif; ?>ออกวันที่ <?= e(thai_date_short($inv['issued_at'])) ?> · ครบกำหนด <?= e(thai_date_short($inv['due_date'])) ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-ghost" href="<?= e(url('billing.php')) ?>"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
    <a class="btn btn-ghost" href="<?= e(url('invoice_print.php?id='.$id)) ?>" target="_blank"><i class="fa-solid fa-print"></i> พิมพ์ / PDF</a>
  </div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>รายการ</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>รวม</th></tr></thead>
        <tbody>
          <?php foreach ($items as $i => $it): ?>
            <tr><td class="text-muted"><?= $i+1 ?></td><td style="font-weight:600;white-space:normal;"><?= e($it['description']) ?></td>
              <td class="mono"><?= rtrim(rtrim($it['qty'],'0'),'.') ?></td>
              <td class="mono"><?= number_format((float)$it['unit_price'],2) ?></td>
              <td class="mono" style="font-weight:700;"><?= number_format((float)$it['line_total'],2) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="max-width:300px;margin-left:auto;margin-top:18px;">
      <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px;"><span class="text-muted">ยอดรวมทั้งสิ้น</span><span class="mono" style="font-weight:700;"><?= number_format((float)$inv['total'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;color:var(--green)"><span>ชำระแล้ว</span><span class="mono"><?= number_format((float)$inv['paid_amount'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>คงค้าง</span><span class="mono" style="color:<?= $outstanding>0?'var(--red)':'var(--green)' ?>"><?= number_format($outstanding,2) ?></span></div>
    </div>

    <!-- ประวัติการชำระ -->
    <?php if ($payments): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
      <div class="card-title" style="margin-bottom:10px;">ประวัติการรับชำระ</div>
      <table>
        <thead><tr><th>เลขที่</th><th>วันที่</th><th>วิธี</th><th>จำนวน</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr><td class="mono text-gold"><?= e($p['doc_no']) ?></td><td class="text-muted"><?= e(thai_date_short($p['paid_at'])) ?></td>
              <td><?= e(Finance::methodLabel($p['method'])) ?></td><td class="mono" style="font-weight:700;"><?= number_format((float)$p['amount'],2) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card" style="align-self:start;">
    <div class="card-title" style="margin-bottom:14px;">ลูกค้า</div>
    <div style="font-size:14px;font-weight:600;"><?= e($inv['customer_name']) ?></div>
    <div style="font-size:13px;color:var(--text-soft);line-height:1.8;margin-top:6px;">
      <?php if ($inv['tax_id']): ?><div>เลขภาษี: <?= e($inv['tax_id']) ?></div><?php endif; ?>
      <?php if ($inv['phone']): ?><div><i class="fa-solid fa-phone" style="width:16px;color:var(--text-muted)"></i> <?= e($inv['phone']) ?></div><?php endif; ?>
    </div>

    <?php if ($outstanding > 0 && $inv['status'] !== 'void' && Auth::can('finance.manage')): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
      <div class="card-title" style="margin-bottom:12px;">บันทึกรับชำระ</div>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">จำนวนเงิน</label>
          <input class="form-input" type="number" step="0.01" name="amount" value="<?= number_format($outstanding,2,'.','') ?>" max="<?= number_format($outstanding,2,'.','') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">วิธีชำระ</label>
          <select class="form-select" name="method">
            <option value="transfer">โอนเงิน</option><option value="cash">เงินสด</option>
            <option value="cheque">เช็ค</option><option value="card">บัตร</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">วันที่ชำระ</label>
          <input class="form-input" type="date" name="paid_at" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">หมายเหตุ</label>
          <input class="form-input" name="note" placeholder="เลขอ้างอิงการโอน ฯลฯ">
        </div>
        <button class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> บันทึกรับชำระ</button>
      </form>
    <?php elseif ($inv['status'] === 'paid'): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
      <div class="alert alert-success" style="margin:0;"><i class="fa-solid fa-circle-check"></i> ชำระครบถ้วนแล้ว</div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
