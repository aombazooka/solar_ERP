<?php
/** vendor_bill_view.php — รายละเอียดใบเจ้าหนี้ + บันทึกจ่ายเงิน */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('finance.view');

$id = (int) input('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('finance.manage');
    csrf_verify();
    try {
        $res = Purchasing::payBill($id, (float) input('amount'), input('method'), input('paid_at'), input('note'), (float) input('wht_rate'));
        $whtMsg = $res['wht'] > 0 ? ' · หัก ณ ที่จ่าย ' . number_format($res['wht'],2) . ' (จ่ายจริง ' . number_format($res['net_cash'],2) . ')' : '';
        flash('success', "บันทึกจ่ายเงิน {$res['doc_no']} เรียบร้อย" . ($res['status']==='paid'?' (จ่ายครบแล้ว)':'') . $whtMsg);
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('vendor_bill_view.php?id=' . $id);
}

$bill = Database::one('SELECT b.*, v.name AS vendor_name, v.phone, gr.doc_no AS gr_no
    FROM vendor_bills b JOIN vendors v ON v.id=b.vendor_id
    LEFT JOIN goods_receipts gr ON gr.id=b.gr_id WHERE b.id=:id', ['id' => $id]);
if (!$bill) { http_response_code(404); exit('ไม่พบใบเจ้าหนี้'); }
$items = $bill['gr_id'] ? Database::all('SELECT * FROM goods_receipt_items WHERE gr_id=:id ORDER BY id', ['id' => $bill['gr_id']]) : [];
$payments = Database::all('SELECT * FROM vendor_payments WHERE bill_id=:id ORDER BY id', ['id' => $id]);
[$sl,$sc] = Purchasing::billStatus($bill['status']);
$outstanding = (float)$bill['total'] - (float)$bill['paid_amount'];
$mLabel = ['cash'=>'เงินสด','transfer'=>'โอนเงิน','cheque'=>'เช็ค','card'=>'บัตร'];

$pageTitle = 'เจ้าหนี้ ' . $bill['doc_no'];
$activeNav = 'payables';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1><?= e($bill['doc_no']) ?> <span class="badge <?= e($sc) ?>" style="margin-left:8px;vertical-align:middle;"><?= e($sl) ?></span></h1>
    <p><?php if ($bill['gr_no']): ?>จากใบรับเข้า <?= e($bill['gr_no']) ?> · <?php endif; ?>ครบกำหนด <?= e(thai_date_short($bill['due_date'])) ?></p></div>
  <a class="btn btn-ghost" href="<?= e(url('payables.php')) ?>"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
</div>

<div class="grid g21">
  <div class="card">
    <?php if ($items): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>สินค้า</th><th>จำนวน</th><th>ต้นทุน</th><th>รวม</th></tr></thead>
      <tbody><?php foreach ($items as $i => $it): ?>
        <tr><td class="text-muted"><?= $i+1 ?></td><td style="font-weight:600;white-space:normal;"><?= e($it['description']) ?></td>
          <td class="mono"><?= rtrim(rtrim($it['qty'],'0'),'.') ?></td><td class="mono"><?= number_format((float)$it['unit_cost'],2) ?></td>
          <td class="mono" style="font-weight:700;"><?= number_format((float)$it['line_total'],2) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
    <div style="max-width:300px;margin-left:auto;margin-top:18px;">
      <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px;"><span class="text-muted">ยอดรวมทั้งสิ้น</span><span class="mono" style="font-weight:700;"><?= number_format((float)$bill['total'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;color:var(--green)"><span>จ่ายแล้ว</span><span class="mono"><?= number_format((float)$bill['paid_amount'],2) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>คงค้าง</span><span class="mono" style="color:<?= $outstanding>0?'var(--red)':'var(--green)' ?>"><?= number_format($outstanding,2) ?></span></div>
    </div>
    <?php if ($payments): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
      <div class="card-title" style="margin-bottom:10px;">ประวัติการจ่าย</div>
      <table><thead><tr><th>เลขที่</th><th>วันที่</th><th>วิธี</th><th>WHT</th><th>จำนวน</th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?><tr><td class="mono text-gold"><?= e($p['doc_no']) ?></td><td class="text-muted"><?= e(thai_date_short($p['paid_at'])) ?></td><td><?= e($mLabel[$p['method']] ?? $p['method']) ?></td><td class="mono text-muted"><?= (float)($p['wht_amount'] ?? 0)>0 ? '-'.number_format((float)$p['wht_amount'],2) : '-' ?></td><td class="mono" style="font-weight:700;"><?= number_format((float)$p['amount'],2) ?></td></tr><?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card" style="align-self:start;">
    <div class="card-title" style="margin-bottom:14px;">ซัพพลายเออร์</div>
    <div style="font-size:14px;font-weight:600;"><?= e($bill['vendor_name']) ?></div>
    <?php if ($bill['phone']): ?><div class="text-muted" style="font-size:13px;margin-top:4px;"><i class="fa-solid fa-phone"></i> <?= e($bill['phone']) ?></div><?php endif; ?>
    <?php if ($outstanding > 0 && $bill['status'] !== 'void' && Auth::can('finance.manage')): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
      <div class="card-title" style="margin-bottom:12px;">บันทึกจ่ายเงิน</div>
      <form method="post"><?= csrf_field() ?>
        <div class="form-group"><label class="form-label">จำนวนเงิน (ตัดหนี้)</label><input class="form-input" type="number" step="0.01" name="amount" id="payAmt" value="<?= number_format($outstanding,2,'.','') ?>" max="<?= number_format($outstanding,2,'.','') ?>" oninput="calcWht()" required></div>
        <div class="form-group"><label class="form-label">ภาษีหัก ณ ที่จ่าย (WHT)</label>
          <select class="form-select" name="wht_rate" id="whtRate" onchange="calcWht()">
            <option value="0">ไม่หัก</option><option value="1">1% (ขนส่ง)</option><option value="2">2% (โฆษณา)</option><option value="3">3% (บริการ/รับเหมา)</option><option value="5">5% (เช่า)</option>
          </select></div>
        <div class="form-group" id="whtInfo" style="display:none;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius-sm);padding:10px 12px;font-size:12px;">
          หัก ณ ที่จ่าย: <strong id="whtAmt" class="mono">0.00</strong> · จ่ายจริง: <strong id="netCash" class="mono">0.00</strong>
        </div>
        <div class="form-group"><label class="form-label">วิธีจ่าย</label><select class="form-select" name="method"><option value="transfer">โอนเงิน</option><option value="cash">เงินสด</option><option value="cheque">เช็ค</option><option value="card">บัตร</option></select></div>
        <div class="form-group"><label class="form-label">วันที่จ่าย</label><input class="form-input" type="date" name="paid_at" value="<?= date('Y-m-d') ?>"></div>
        <button class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> บันทึกจ่ายเงิน</button>
      </form>
    <?php elseif ($bill['status'] === 'paid'): ?>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">
      <div class="alert alert-success" style="margin:0;"><i class="fa-solid fa-circle-check"></i> จ่ายครบถ้วนแล้ว</div>
    <?php endif; ?>
  </div>
</div>
<script>
function calcWht(){
  const amt=parseFloat(document.getElementById('payAmt')?.value)||0;
  const rate=parseFloat(document.getElementById('whtRate')?.value)||0;
  const info=document.getElementById('whtInfo'); if(!info) return;
  const base=amt/1.07, wht=Math.round(base*rate/100*100)/100;
  info.style.display = rate>0 ? 'block':'none';
  document.getElementById('whtAmt').textContent=wht.toLocaleString('en-US',{minimumFractionDigits:2});
  document.getElementById('netCash').textContent=(amt-wht).toLocaleString('en-US',{minimumFractionDigits:2});
}
</script>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
