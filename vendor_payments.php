<?php
/** vendor_payments.php — รายการจ่ายเจ้าหนี้ทั้งหมด + สรุปภาษีหัก ณ ที่จ่าย (ภ.ง.ด.3/53) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('finance.view');

$from = input('from') ?: date('Y-m-01');
$to   = input('to')   ?: date('Y-m-d');
$vendorId = (int) input('vendor_id');

$where = 'WHERE p.paid_at BETWEEN :a AND :b';
$params = ['a' => $from, 'b' => $to];
if ($vendorId > 0) { $where .= ' AND p.vendor_id = :v'; $params['v'] = $vendorId; }

$rows = Database::all(
    "SELECT p.*, v.name AS vendor_name, b.doc_no AS bill_no
     FROM vendor_payments p
     JOIN vendors v ON v.id = p.vendor_id
     JOIN vendor_bills b ON b.id = p.bill_id
     $where ORDER BY p.paid_at DESC, p.id DESC", $params
);

$sumGross = 0; $sumWht = 0;
foreach ($rows as $r) { $sumGross += (float)$r['amount']; $sumWht += (float)$r['wht_amount']; }
$sumNet = $sumGross - $sumWht;

// สรุป WHT ตามซัพพลายเออร์ (สำหรับยื่น ภ.ง.ด.) — เฉพาะที่มีการหัก
$whtByVendor = Database::all(
    "SELECT v.name, v.tax_id, SUM(p.wht_amount) AS wht, SUM(p.amount) AS gross, COUNT(*) AS n
     FROM vendor_payments p JOIN vendors v ON v.id = p.vendor_id
     $where AND p.wht_amount > 0 GROUP BY v.id ORDER BY wht DESC", $params
);
$vendors = Database::all('SELECT id, name FROM vendors ORDER BY name');
$mLabel = ['cash'=>'เงินสด','transfer'=>'โอนเงิน','cheque'=>'เช็ค','card'=>'บัตร'];

$pageTitle = 'จ่ายเจ้าหนี้';
$activeNav = 'vendor_payments';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>รายการจ่ายเจ้าหนี้</h1><p>ประวัติการจ่ายทั้งหมด + สรุปภาษีหัก ณ ที่จ่าย (ภ.ง.ด.3/53)</p></div>
  <form method="get" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
    <div><label class="form-label" style="font-size:11px;">ตั้งแต่</label><input class="form-input" type="date" name="from" value="<?= e($from) ?>" style="padding:8px 12px;"></div>
    <div><label class="form-label" style="font-size:11px;">ถึง</label><input class="form-input" type="date" name="to" value="<?= e($to) ?>" style="padding:8px 12px;"></div>
    <div><label class="form-label" style="font-size:11px;">ซัพพลายเออร์</label>
      <select class="form-select" name="vendor_id" style="padding:8px 12px;"><option value="0">ทั้งหมด</option>
        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $vendorId===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> ดู</button>
  </form>
</div>

<div class="grid g3" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-money-bill-transfer"></i></div><div class="stat-body"><div class="stat-label">ยอดจ่ายรวม (gross)</div><div class="stat-value" style="font-size:20px;"><?= baht($sumGross) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon red" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-scissors"></i></div><div class="stat-body"><div class="stat-label">ภาษีหัก ณ ที่จ่ายรวม</div><div class="stat-value" style="font-size:20px;"><?= baht($sumWht) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon green" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-wallet"></i></div><div class="stat-body"><div class="stat-label">เงินสดจ่ายจริง (net)</div><div class="stat-value" style="font-size:20px;"><?= baht($sumNet) ?></div></div></div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ใบเจ้าหนี้</th><th>ซัพพลายเออร์</th><th>วิธี</th><th>วันที่</th><th>ยอดจ่าย</th><th>WHT</th><th>จ่ายจริง</th><th></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-muted" style="text-align:center;padding:40px;">ไม่มีการจ่ายในช่วงที่เลือก</td></tr>
        <?php else: foreach ($rows as $r): $net=(float)$r['amount']-(float)$r['wht_amount']; ?>
          <tr>
            <td class="mono text-gold"><?= e($r['doc_no']) ?></td>
            <td><a class="mono" style="color:var(--blue)" href="<?= e(url('vendor_bill_view.php?id='.$r['bill_id'])) ?>"><?= e($r['bill_no']) ?></a></td>
            <td style="font-weight:600;"><?= e($r['vendor_name']) ?></td>
            <td><span class="badge badge-blue"><?= e($mLabel[$r['method']] ?? $r['method']) ?></span></td>
            <td class="text-muted"><?= e(thai_date_short($r['paid_at'])) ?></td>
            <td class="mono"><?= number_format((float)$r['amount'],2) ?></td>
            <td class="mono" style="color:var(--red)"><?= (float)$r['wht_amount']>0 ? '-'.number_format((float)$r['wht_amount'],2).' ('.rtrim(rtrim($r['wht_rate'],'0'),'.').'%)' : '-' ?></td>
            <td class="mono" style="font-weight:700;"><?= number_format($net,2) ?></td>
            <td style="text-align:right;"><?php if ((float)$r['wht_amount']>0): ?><a class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" title="พิมพ์ 50 ทวิ" href="<?= e(url('wht_cert.php?id='.$r['id'])) ?>" target="_blank"><i class="fa-solid fa-print"></i> 50 ทวิ</a><?php endif; ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($whtByVendor): ?>
<div class="card">
  <div class="card-head"><div><div class="card-title">สรุปภาษีหัก ณ ที่จ่าย ตามซัพพลายเออร์</div><div class="card-sub">สำหรับยื่น ภ.ง.ด.3/53 · รวม <?= baht($sumWht) ?></div></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ซัพพลายเออร์</th><th>เลขผู้เสียภาษี</th><th>จำนวนครั้ง</th><th>ยอดจ่าย (gross)</th><th>ภาษีหักรวม</th></tr></thead>
      <tbody>
        <?php foreach ($whtByVendor as $w): ?>
          <tr>
            <td style="font-weight:600;"><?= e($w['name']) ?></td>
            <td class="mono text-muted"><?= e($w['tax_id'] ?? '-') ?></td>
            <td class="mono"><?= (int)$w['n'] ?></td>
            <td class="mono"><?= number_format((float)$w['gross'],2) ?></td>
            <td class="mono" style="font-weight:700;color:var(--red)"><?= number_format((float)$w['wht'],2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
