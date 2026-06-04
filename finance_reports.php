<?php
/** finance_reports.php — งบ VAT / กำไรขาดทุน / ฐานะการเงิน (จากข้อมูลจริง) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('finance.view');

$from = input('from') ?: date('Y-m-01');
$to   = input('to')   ?: date('Y-m-d');

// ─── VAT ───
$outVat = (float) Database::scalar(
    "SELECT COALESCE(SUM(total - ROUND(total/1.07,2)),0) FROM invoices WHERE status<>'void' AND issued_at BETWEEN :a AND :b",
    ['a'=>$from,'b'=>$to]);
$inVat = (float) Database::scalar(
    "SELECT COALESCE(SUM(vat),0) FROM goods_receipts WHERE status='received' AND received_at BETWEEN :a AND :b",
    ['a'=>$from,'b'=>$to]);
$netVat = $outVat - $inVat;

// ─── P&L ───
$revenue = (float) Database::scalar(
    "SELECT COALESCE(SUM(ROUND(total/1.07,2)),0) FROM invoices WHERE status<>'void' AND issued_at BETWEEN :a AND :b",
    ['a'=>$from,'b'=>$to]);
$cogs = (float) Database::scalar(
    "SELECT COALESCE(SUM(soi.qty * p.cost_price),0)
     FROM invoices i JOIN sales_orders so ON so.id=i.order_id
     JOIN sales_order_items soi ON soi.order_id=so.id
     JOIN products p ON p.id=soi.product_id
     WHERE i.status<>'void' AND i.issued_at BETWEEN :a AND :b AND p.category<>'service'",
    ['a'=>$from,'b'=>$to]);
$grossProfit = $revenue - $cogs;
// ค่าใช้จ่ายดำเนินงาน = payroll (net) ของงวดที่อยู่ในช่วง + คอมมิชชั่นรวมในนั้น
$payrollExp = (float) Database::scalar(
    "SELECT COALESCE(SUM(pi.net_pay),0) FROM payroll_items pi
     JOIN payroll_periods pp ON pp.id=pi.period_id
     WHERE STR_TO_DATE(CONCAT(CAST(SUBSTRING(pp.period,1,4) AS UNSIGNED)-543,'-',SUBSTRING(pp.period,6,2),'-01'),'%Y-%m-%d') BETWEEN :a AND LAST_DAY(:b)",
    ['a'=>$from,'b'=>$to]);
$netProfit = $grossProfit - $payrollExp;
$margin = $revenue > 0 ? round($netProfit / $revenue * 100, 1) : 0;

// ─── ฐานะการเงิน (snapshot ณ วันนี้) ───
$ar  = (float) Database::scalar("SELECT COALESCE(SUM(total-paid_amount),0) FROM invoices WHERE status IN ('unpaid','partial')");
$ap  = (float) Database::scalar("SELECT COALESCE(SUM(total-paid_amount),0) FROM vendor_bills WHERE status IN ('unpaid','partial')");
$inv = (float) Database::scalar("SELECT COALESCE(SUM(stock_qty*cost_price),0) FROM products WHERE category<>'service'");
$cashIn  = (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments");
$cashOut = (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM vendor_payments");
$cashNet = $cashIn - $cashOut;

$pageTitle = 'งบการเงิน';
$activeNav = 'finance_reports';
require __DIR__ . '/app/layout_header.php';

$rowfn = fn($label,$val,$bold=false,$color='') => '<div style="display:flex;justify-content:space-between;padding:7px 0;'.($bold?'border-top:1px solid var(--border);font-weight:700;font-size:15px;':'font-size:13px;').'"><span class="'.($bold?'':'text-muted').'">'.$label.'</span><span class="mono" style="'.($color?'color:'.$color:'').'">'.number_format($val,2).'</span></div>';
?>
<div class="page-header">
  <div><h1>งบการเงิน</h1><p>VAT · งบกำไรขาดทุน · ฐานะการเงิน (จากข้อมูลจริง)</p></div>
  <form method="get" style="display:flex;gap:8px;align-items:flex-end;">
    <div><label class="form-label" style="font-size:11px;">ตั้งแต่</label><input class="form-input" type="date" name="from" value="<?= e($from) ?>" style="padding:8px 12px;"></div>
    <div><label class="form-label" style="font-size:11px;">ถึง</label><input class="form-input" type="date" name="to" value="<?= e($to) ?>" style="padding:8px 12px;"></div>
    <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> ดูรายงาน</button>
  </form>
</div>

<div class="grid g2" style="margin-bottom:20px;">
  <!-- P&L -->
  <div class="card">
    <div class="card-head"><div><div class="card-title">งบกำไรขาดทุน (P&L)</div><div class="card-sub"><?= e(thai_date_short($from)) ?> – <?= e(thai_date_short($to)) ?></div></div></div>
    <?= $rowfn('รายได้จากการขาย (ไม่รวม VAT)', $revenue) ?>
    <?= $rowfn('หัก: ต้นทุนขาย (COGS)', -$cogs, false, 'var(--red)') ?>
    <?= $rowfn('กำไรขั้นต้น', $grossProfit, true, $grossProfit>=0?'var(--green)':'var(--red)') ?>
    <?= $rowfn('หัก: ค่าใช้จ่ายดำเนินงาน (เงินเดือน/คอม)', -$payrollExp, false, 'var(--red)') ?>
    <?= $rowfn('กำไรสุทธิ', $netProfit, true, $netProfit>=0?'var(--green)':'var(--red)') ?>
    <div style="margin-top:10px;font-size:12px;" class="text-muted">อัตรากำไรสุทธิ: <strong style="color:<?= $netProfit>=0?'var(--green)':'var(--red)' ?>"><?= $margin ?>%</strong></div>
  </div>

  <!-- VAT -->
  <div class="card">
    <div class="card-head"><div><div class="card-title">ภาษีมูลค่าเพิ่ม (VAT)</div><div class="card-sub"><?= e(thai_date_short($from)) ?> – <?= e(thai_date_short($to)) ?></div></div></div>
    <?= $rowfn('ภาษีขาย (Output VAT)', $outVat) ?>
    <?= $rowfn('ภาษีซื้อ (Input VAT)', -$inVat, false, 'var(--green)') ?>
    <?= $rowfn($netVat>=0?'ภาษีที่ต้องนำส่ง':'ภาษีขอคืนได้', abs($netVat), true, $netVat>=0?'var(--solar-gold)':'var(--green)') ?>
    <div style="margin-top:14px;padding:12px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:var(--radius-sm);font-size:12px;color:var(--text-soft);">
      <i class="fa-solid fa-circle-info"></i> ภาษีขายจากใบกำกับ, ภาษีซื้อจากใบรับเข้าสินค้า — ยอดสุทธิคือยอดยื่น ภพ.30
    </div>
  </div>
</div>

<!-- ฐานะการเงิน -->
<div class="card">
  <div class="card-head"><div><div class="card-title">ฐานะการเงินโดยสรุป</div><div class="card-sub">ณ วันที่ <?= e(thai_date_short(date('Y-m-d'))) ?></div></div></div>
  <div class="grid g2">
    <div>
      <div style="font-size:12px;font-weight:600;color:var(--text-soft);margin-bottom:6px;">สินทรัพย์</div>
      <?= $rowfn('เงินสดรับสุทธิ (ลูกค้า−เจ้าหนี้)', $cashNet) ?>
      <?= $rowfn('ลูกหนี้การค้า (AR)', $ar) ?>
      <?= $rowfn('สินค้าคงเหลือ (ราคาทุน)', $inv) ?>
      <?= $rowfn('รวมสินทรัพย์', $cashNet+$ar+$inv, true, 'var(--blue)') ?>
    </div>
    <div>
      <div style="font-size:12px;font-weight:600;color:var(--text-soft);margin-bottom:6px;">หนี้สิน</div>
      <?= $rowfn('เจ้าหนี้การค้า (AP)', $ap) ?>
      <?= $rowfn('ภาษีค้างนำส่ง (VAT สุทธิ)', max(0,$netVat)) ?>
      <?= $rowfn('รวมหนี้สิน', $ap+max(0,$netVat), true, 'var(--red)') ?>
    </div>
  </div>
  <div class="alert alert-info" style="margin-top:16px;font-size:12px;"><i class="fa-solid fa-circle-info"></i> รายงานสรุปจากข้อมูลธุรกรรมจริง (AR/AP/สต็อก/กระแสเงินสด) เพื่อดูภาพรวม — สำหรับงบการเงินตามมาตรฐานบัญชีเต็มรูปแบบ ใช้สมุดรายวันร่วมด้วย</div>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
