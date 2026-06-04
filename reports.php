<?php
/** reports.php — ศูนย์รายงาน (ผู้บริหาร / ฝ่ายขาย / การเงิน / คลัง) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('dashboard.view');

// ── ขอบเขตการมองเห็นรายงานตามบทบาท ──
// ผู้บริหาร + แอดมิน = เห็นทุกรายงาน · ฝ่ายอื่นเห็นเฉพาะรายงานของแผนกตน
// อิงตาม "บทบาทหลัก" ไม่ใช่สิทธิ์ดูทั่วไป (เช่น การเงินมี sales.view ไว้ออกบิล แต่ไม่ควรเห็นรายงานฝ่ายขาย)
$myRole   = Auth::user()['role_slug'] ?? '';
$seeAll   = in_array($myRole, ['admin', 'executive'], true);
$canSales = $seeAll || $myRole === 'sales';
$canFin   = $seeAll || $myRole === 'finance';
$canInv   = $seeAll || $myRole === 'sales';   // ในระบบนี้ฝ่ายขายดูแลคลัง/จัดซื้อด้วย
$hasAny   = $seeAll || $canSales || $canFin || $canInv;

// ═══ ผู้บริหาร (Executive) ═══
$revenueMonth = (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE())");
$arOutstanding = (float) Database::scalar("SELECT COALESCE(SUM(total-paid_amount),0) FROM invoices WHERE status IN ('unpaid','partial')");
$quotationCount = (int) Database::scalar('SELECT COUNT(*) FROM quotations');
$deliveredCount = (int) Database::scalar("SELECT COUNT(*) FROM sales_orders WHERE status IN ('delivered','invoiced')");
$totalKwp = (float) Database::scalar("SELECT COALESCE(SUM(capacity_kwp),0) FROM quotations WHERE status='converted'");
$customerCount = (int) Database::scalar('SELECT COUNT(*) FROM customers');
$converted = (int) Database::scalar("SELECT COUNT(*) FROM quotations WHERE status='converted'");
$convRate = $quotationCount > 0 ? round($converted / $quotationCount * 100) : 0;

// กราฟรับชำระ 6 เดือน
$revByMonth = [];
foreach (Database::all("SELECT DATE_FORMAT(paid_at,'%Y-%m') AS ym, SUM(amount) AS total FROM payments WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY ym") as $r) $revByMonth[$r['ym']] = (float)$r['total'];
$months = []; $maxRev = 0;
$thMo = ['01'=>'ม.ค.','02'=>'ก.พ.','03'=>'มี.ค.','04'=>'เม.ย.','05'=>'พ.ค.','06'=>'มิ.ย.','07'=>'ก.ค.','08'=>'ส.ค.','09'=>'ก.ย.','10'=>'ต.ค.','11'=>'พ.ย.','12'=>'ธ.ค.'];
for ($i = 5; $i >= 0; $i--) { $ym = date('Y-m', strtotime("-$i month")); $v = $revByMonth[$ym] ?? 0; $maxRev = max($maxRev, $v); $months[] = ['label'=>$thMo[date('m', strtotime("-$i month"))], 'val'=>$v]; }

// donut ประเภทระบบ
$sys = ['on_grid'=>0,'hybrid'=>0,'off_grid'=>0];
foreach (Database::all("SELECT system_type, COUNT(*) AS n FROM quotations WHERE system_type IS NOT NULL GROUP BY system_type") as $r) $sys[$r['system_type']] = (int)$r['n'];
$sysTotal = array_sum($sys) ?: 1;
$circ = 339.29;
$dash = fn($pct) => round($circ * $pct, 1) . ' ' . round($circ * (1 - $pct), 1);

// ═══ ฝ่ายขาย ═══
$quotedTotal = (float) Database::scalar('SELECT COALESCE(SUM(total),0) FROM quotations');
$orderedTotal = (float) Database::scalar("SELECT COALESCE(SUM(total),0) FROM sales_orders WHERE status<>'cancelled'");
$invoicedTotal = (float) Database::scalar("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status<>'void'");
$collected = (float) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM payments');
$topProducts = Database::all("SELECT COALESCE(p.name, soi.description) AS name, SUM(soi.qty) AS qty, SUM(soi.line_total) AS revenue
    FROM sales_order_items soi LEFT JOIN products p ON p.id=soi.product_id
    JOIN sales_orders so ON so.id=soi.order_id AND so.status<>'cancelled' GROUP BY name ORDER BY revenue DESC LIMIT 8");

// ═══ การเงิน ═══
$apOutstanding = (float) Database::scalar("SELECT COALESCE(SUM(total-paid_amount),0) FROM vendor_bills WHERE status IN ('unpaid','partial')");
$arByCustomer = Database::all("SELECT c.name, SUM(i.total-i.paid_amount) AS outstanding FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.status IN ('unpaid','partial') GROUP BY c.id ORDER BY outstanding DESC LIMIT 8");

// ═══ คลัง ═══
$stockByCat = Database::all("SELECT category, COUNT(*) AS items, SUM(stock_qty*cost_price) AS value FROM products WHERE category<>'service' GROUP BY category ORDER BY value DESC");
$catNames = ['panel'=>'แผงโซลาร์','inverter'=>'อินเวอร์เตอร์','battery'=>'แบตเตอรี่','mounting'=>'โครงยึด','accessory'=>'อุปกรณ์เสริม'];
$maxCat = 0; foreach ($stockByCat as $r) $maxCat = max($maxCat, (float)$r['value']);

// กิจกรรมล่าสุด
$recent = Database::all("SELECT a.action, a.entity, a.entity_id, a.created_at, u.name AS by_name FROM audit_log a LEFT JOIN users u ON u.id=a.created_by WHERE a.action<>'login' ORDER BY a.id DESC LIMIT 8");
$actL = ['create'=>'สร้าง','convert'=>'แปลงเอกสาร','deliver'=>'ส่งของ','payment'=>'รับชำระ','update'=>'แก้ไข'];
$entL = ['quotations'=>'ใบเสนอราคา','sales_orders'=>'ใบสั่งขาย','invoices'=>'ใบแจ้งหนี้','customers'=>'ลูกค้า','products'=>'สินค้า','vendors'=>'ซัพพลายเออร์','goods_receipts'=>'รับเข้าสินค้า','vendor_bills'=>'เจ้าหนี้'];

$pageTitle = 'รายงาน';
$activeNav = 'reports';
require __DIR__ . '/app/layout_header.php';

function secHead(string $icon, string $title, string $sub=''): void {
  echo '<div style="display:flex;align-items:center;gap:10px;margin:26px 0 14px;"><div class="stat-icon gold" style="width:34px;height:34px;font-size:14px;"><i class="fa-solid '.$icon.'"></i></div><div><div style="font-size:15px;font-weight:700;">'.e($title).'</div>'.($sub?'<div class="card-sub">'.e($sub).'</div>':'').'</div></div>';
}
?>
<div class="page-header">
  <div><h1>รายงาน</h1><p>ภาพรวมเชิงวิเคราะห์แยกตามแผนก</p></div>
  <?php if ($canFin): ?><a class="btn btn-ghost" href="<?= e(url('finance_reports.php')) ?>"><i class="fa-solid fa-file-contract"></i> งบการเงิน</a><?php endif; ?>
</div>

<?php if (!$hasAny): ?>
  <div class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fa-solid fa-chart-pie" style="font-size:32px;opacity:.4;"></i>
    <div style="margin-top:12px;font-size:14px;">ยังไม่มีรายงานสำหรับบทบาทของคุณ</div>
  </div>
<?php endif; ?>

<?php if ($seeAll): ?>
<?php secHead('fa-crown', 'ภาพรวมผู้บริหาร', 'ประจำเดือน '.thai_date()); ?>
<div class="grid g4" style="margin-bottom:20px;">
  <div class="stat-card"><div class="stat-icon gold"><i class="fa-solid fa-baht-sign"></i></div><div class="stat-body"><div class="stat-label">รับชำระเดือนนี้</div><div class="stat-value" style="font-size:22px;"><?= baht($revenueMonth) ?></div><div class="stat-change up"><i class="fa-solid fa-receipt"></i> AR คงค้าง <?= baht($arOutstanding) ?></div></div><div class="stat-glow gold"></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-truck"></i></div><div class="stat-body"><div class="stat-label">ส่งของแล้ว</div><div class="stat-value"><?= $deliveredCount ?><small> รายการ</small></div><div class="stat-change up"><i class="fa-solid fa-users"></i> ลูกค้า <?= $customerCount ?> ราย</div></div><div class="stat-glow green"></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-file-invoice"></i></div><div class="stat-body"><div class="stat-label">ใบเสนอราคา</div><div class="stat-value"><?= $quotationCount ?><small> ฉบับ</small></div><div class="stat-change up"><i class="fa-solid fa-percent"></i> Conversion <?= $convRate ?>%</div></div><div class="stat-glow blue"></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-bolt"></i></div><div class="stat-body"><div class="stat-label">กำลังผลิตที่ขายได้</div><div class="stat-value"><?= number_format($totalKwp, $totalKwp==(int)$totalKwp?0:2) ?><small> kWp</small></div><div class="stat-change up"><i class="fa-solid fa-solar-panel"></i> ดีลที่ปิดแล้ว</div></div><div class="stat-glow orange"></div></div>
</div>
<div class="grid g21" style="margin-bottom:8px;">
  <div class="card">
    <div class="card-head"><div><div class="card-title">รับชำระรายเดือน</div><div class="card-sub">6 เดือนล่าสุด</div></div></div>
    <div class="chart-bars">
      <?php foreach ($months as $i => $mo): $h = $maxRev>0 ? max(4, round($mo['val']/$maxRev*100)) : 4; ?>
        <div class="bar-wrap"><div class="bar" style="height:<?= $h ?>%;animation-delay:<?= 0.05*($i+1) ?>s" data-val="<?= e(baht($mo['val'])) ?>"></div><div class="bar-label"><?= e($mo['label']) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><div><div class="card-title">ประเภทระบบ</div><div class="card-sub">จากใบเสนอราคา</div></div></div>
    <div class="donut-wrap"><svg width="140" height="140" viewBox="0 0 140 140">
      <circle cx="70" cy="70" r="54" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="18"/>
      <?php $off=0; foreach ([['#f59e0b',$sys['on_grid']/$sysTotal],['#3b82f6',$sys['hybrid']/$sysTotal],['#10b981',$sys['off_grid']/$sysTotal]] as $seg): if ($seg[1]<=0) continue; ?>
        <circle cx="70" cy="70" r="54" fill="none" stroke="<?= $seg[0] ?>" stroke-width="18" stroke-dasharray="<?= e($dash($seg[1])) ?>" stroke-dashoffset="<?= -round($circ*$off,1) ?>"/>
      <?php $off+=$seg[1]; endforeach; ?>
    </svg><div class="donut-center"><div class="val"><?= array_sum($sys) ?></div><div class="lbl">ใบเสนอราคา</div></div></div>
    <div class="legend">
      <div class="legend-item"><div class="legend-dot" style="background:var(--solar-gold)"></div><span>On-Grid</span><span class="legend-val text-gold"><?= $sys['on_grid'] ?></span></div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--blue)"></div><span>Hybrid</span><span class="legend-val" style="color:var(--blue)"><?= $sys['hybrid'] ?></span></div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--green)"></div><span>Off-Grid</span><span class="legend-val" style="color:var(--green)"><?= $sys['off_grid'] ?></span></div>
    </div>
  </div>
</div>
<?php endif; /* end ภาพรวมผู้บริหาร */ ?>

<?php if ($canSales): secHead('fa-cart-shopping', 'ฝ่ายขาย', 'กระบวนการขายและสินค้าขายดี'); ?>
<div class="grid g4" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon blue" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-file-invoice"></i></div><div class="stat-body"><div class="stat-label">เสนอราคารวม</div><div class="stat-value" style="font-size:17px;"><?= baht($quotedTotal) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-cart-shopping"></i></div><div class="stat-body"><div class="stat-label">สั่งขายรวม</div><div class="stat-value" style="font-size:17px;"><?= baht($orderedTotal) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon purple" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-file-invoice-dollar"></i></div><div class="stat-body"><div class="stat-label">ออกบิลรวม</div><div class="stat-value" style="font-size:17px;"><?= baht($invoicedTotal) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon green" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-body"><div class="stat-label">เก็บเงินได้</div><div class="stat-value" style="font-size:17px;"><?= baht($collected) ?></div></div></div>
</div>
<div class="card">
  <div class="card-head"><div class="card-title">สินค้าขายดี (ตามยอดเงิน)</div></div>
  <div class="table-wrap"><table><thead><tr><th>สินค้า</th><th>จำนวน</th><th>ยอดขาย</th></tr></thead><tbody>
    <?php if (!$topProducts): ?><tr><td colspan="3" class="text-muted" style="text-align:center;padding:24px;">ยังไม่มีการขาย</td></tr>
    <?php else: foreach ($topProducts as $r): ?><tr><td style="font-weight:600;white-space:normal;"><?= e($r['name']) ?></td><td class="mono"><?= rtrim(rtrim($r['qty'],'0'),'.') ?></td><td class="mono" style="font-weight:700;"><?= number_format((float)$r['revenue'],2) ?></td></tr><?php endforeach; endif; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($canFin): secHead('fa-coins', 'การเงิน', 'ลูกหนี้ / เจ้าหนี้'); ?>
<div class="grid g2">
  <div class="card">
    <div class="card-head"><div><div class="card-title">ลูกหนี้คงค้าง (AR)</div><div class="card-sub">รวม <?= baht($arOutstanding) ?></div></div></div>
    <div class="table-wrap"><table><thead><tr><th>ลูกค้า</th><th>คงค้าง</th></tr></thead><tbody>
      <?php if (!$arByCustomer): ?><tr><td colspan="2" class="text-muted" style="text-align:center;padding:24px;">ไม่มีลูกหนี้</td></tr>
      <?php else: foreach ($arByCustomer as $r): ?><tr><td style="font-weight:600;"><?= e($r['name']) ?></td><td class="mono" style="font-weight:700;color:var(--red)"><?= number_format((float)$r['outstanding'],2) ?></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
  </div>
  <div class="card">
    <div class="card-head"><div class="card-title">สรุปการเงิน</div></div>
    <div class="progress-row" style="margin-top:6px;"><div class="progress-label"><span>ลูกหนี้คงค้าง (AR)</span><span><?= baht($arOutstanding) ?></span></div></div>
    <div class="progress-row"><div class="progress-label"><span>เจ้าหนี้คงค้าง (AP)</span><span><?= baht($apOutstanding) ?></span></div></div>
    <div class="progress-row"><div class="progress-label"><span>เก็บเงินได้สะสม</span><span style="color:var(--green)"><?= baht($collected) ?></span></div></div>
    <a class="btn btn-ghost btn-block" style="margin-top:10px;" href="<?= e(url('finance_reports.php')) ?>"><i class="fa-solid fa-file-contract"></i> ดูงบการเงินเต็ม</a>
  </div>
</div>
<?php endif; ?>

<?php if ($canInv): secHead('fa-warehouse', 'คลังสินค้า', 'มูลค่าสต็อกตามหมวด'); ?>
<div class="card">
  <?php foreach ($stockByCat as $r): $pct = $maxCat>0 ? round((float)$r['value']/$maxCat*100) : 0; ?>
    <div class="progress-row"><div class="progress-label"><span><?= e($catNames[$r['category']] ?? $r['category']) ?> <span class="text-muted">(<?= (int)$r['items'] ?>)</span></span><span><?= baht($r['value']) ?></span></div><div class="progress-track"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($seeAll): ?>
<?php secHead('fa-clock-rotate-left', 'กิจกรรมล่าสุด'); ?>
<div class="card">
  <div class="table-wrap"><table><thead><tr><th>เวลา</th><th>การกระทำ</th><th>เอกสาร</th><th>โดย</th></tr></thead><tbody>
    <?php if (!$recent): ?><tr><td colspan="4" class="text-muted" style="text-align:center;padding:24px;">ยังไม่มีกิจกรรม</td></tr>
    <?php else: foreach ($recent as $a): ?>
      <tr><td class="text-muted" style="font-size:12px;"><?= e(date('d/m H:i', strtotime($a['created_at']))) ?></td>
        <td><span class="badge badge-blue"><?= e($actL[$a['action']] ?? $a['action']) ?></span></td>
        <td><?= e($entL[$a['entity']] ?? $a['entity']) ?> <span class="text-muted">#<?= e($a['entity_id']) ?></span></td>
        <td class="text-muted"><?= e($a['by_name'] ?? '-') ?></td></tr>
    <?php endforeach; endif; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
