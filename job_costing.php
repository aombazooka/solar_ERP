<?php
/** job_costing.php — กำไรต่อโปรเจกต์ติดตั้ง (รายได้ − ต้นทุนวัสดุ) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('finance.view');

$jobs = Database::all(
    "SELECT j.doc_no AS job_no, j.status, j.capacity_kwp, c.name AS customer, so.doc_no AS order_no, so.total AS order_total,
            (SELECT COALESCE(SUM(soi.qty * p.cost_price),0)
             FROM sales_order_items soi JOIN products p ON p.id=soi.product_id
             WHERE soi.order_id=so.id AND p.category<>'service') AS material_cost
     FROM installation_jobs j
     JOIN customers c ON c.id=j.customer_id
     LEFT JOIN sales_orders so ON so.id=j.order_id
     ORDER BY j.id DESC"
);
$jobStatus = ['pending'=>['รอ','badge-gold'],'in_progress'=>['กำลังทำ','badge-blue'],'done'=>['เสร็จ','badge-green'],'cancelled'=>['ยกเลิก','badge-muted']];

$sumRev = 0; $sumCost = 0;
foreach ($jobs as $j) {
    if ($j['order_total']) { $sumRev += (float)$j['order_total']/1.07; $sumCost += (float)$j['material_cost']; }
}
$sumProfit = $sumRev - $sumCost;
$sumMargin = $sumRev > 0 ? round($sumProfit/$sumRev*100,1) : 0;

$pageTitle = 'กำไรต่อโปรเจกต์';
$activeNav = 'job_costing';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header"><div><h1>กำไรต่อโปรเจกต์ติดตั้ง</h1><p>รายได้ (ไม่รวม VAT) − ต้นทุนวัสดุ จากใบสั่งขายที่ผูกกับงานติดตั้ง</p></div></div>

<div class="grid g4" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon blue" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-coins"></i></div><div class="stat-body"><div class="stat-label">รายได้รวม (ex VAT)</div><div class="stat-value" style="font-size:18px;"><?= baht($sumRev) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon orange" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-body"><div class="stat-label">ต้นทุนวัสดุรวม</div><div class="stat-value" style="font-size:18px;"><?= baht($sumCost) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon green" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-sack-dollar"></i></div><div class="stat-body"><div class="stat-label">กำไรขั้นต้นรวม</div><div class="stat-value" style="font-size:18px;color:<?= $sumProfit>=0?'var(--green)':'var(--red)' ?>"><?= baht($sumProfit) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:15px;"><i class="fa-solid fa-percent"></i></div><div class="stat-body"><div class="stat-label">อัตรากำไรเฉลี่ย</div><div class="stat-value" style="font-size:18px;"><?= $sumMargin ?>%</div></div></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>งานติดตั้ง</th><th>ลูกค้า</th><th>ใบสั่งขาย</th><th>รายได้ (ex VAT)</th><th>ต้นทุนวัสดุ</th><th>กำไรขั้นต้น</th><th>%</th><th>สถานะ</th></tr></thead>
      <tbody>
        <?php if (!$jobs): ?><tr><td colspan="8" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีงานติดตั้ง</td></tr>
        <?php else: foreach ($jobs as $j):
            [$sl,$sc]=$jobStatus[$j['status']];
            $rev = $j['order_total'] ? (float)$j['order_total']/1.07 : null;
            $cost = (float)$j['material_cost'];
            $profit = $rev !== null ? $rev - $cost : null;
            $margin = ($rev && $rev>0) ? round($profit/$rev*100,1) : null; ?>
          <tr>
            <td class="mono text-gold"><?= e($j['job_no']) ?></td>
            <td style="font-weight:600;"><?= e($j['customer']) ?></td>
            <td><?= $j['order_no'] ? '<span class="mono">'.e($j['order_no']).'</span>' : '<span class="text-muted">ไม่ผูก</span>' ?></td>
            <td class="mono"><?= $rev!==null?number_format($rev,2):'-' ?></td>
            <td class="mono text-muted"><?= $rev!==null?number_format($cost,2):'-' ?></td>
            <td class="mono" style="font-weight:700;<?= $profit!==null?('color:'.($profit>=0?'var(--green)':'var(--red)')):'' ?>"><?= $profit!==null?number_format($profit,2):'-' ?></td>
            <td class="mono"><?= $margin!==null?$margin.'%':'-' ?></td>
            <td><span class="badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
