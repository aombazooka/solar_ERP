<?php
/** aging.php — รายงานอายุหนี้ ลูกหนี้ (AR) + เจ้าหนี้ (AP) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('finance.view');

// ช่วงอายุหนี้จาก due_date เทียบวันนี้
$buckets = "
  SUM(CASE WHEN DATEDIFF(CURDATE(), %1\$s.due_date) <= 0 THEN %1\$s.total-%1\$s.paid_amount ELSE 0 END) AS b0,
  SUM(CASE WHEN DATEDIFF(CURDATE(), %1\$s.due_date) BETWEEN 1 AND 30 THEN %1\$s.total-%1\$s.paid_amount ELSE 0 END) AS b1,
  SUM(CASE WHEN DATEDIFF(CURDATE(), %1\$s.due_date) BETWEEN 31 AND 60 THEN %1\$s.total-%1\$s.paid_amount ELSE 0 END) AS b2,
  SUM(CASE WHEN DATEDIFF(CURDATE(), %1\$s.due_date) BETWEEN 61 AND 90 THEN %1\$s.total-%1\$s.paid_amount ELSE 0 END) AS b3,
  SUM(CASE WHEN DATEDIFF(CURDATE(), %1\$s.due_date) > 90 THEN %1\$s.total-%1\$s.paid_amount ELSE 0 END) AS b4,
  SUM(%1\$s.total-%1\$s.paid_amount) AS tot";

$arRows = Database::all(
    "SELECT c.name AS party, " . sprintf($buckets, 'i') . "
     FROM invoices i JOIN customers c ON c.id=i.customer_id
     WHERE i.status IN ('unpaid','partial') GROUP BY c.id HAVING tot > 0.001 ORDER BY tot DESC");
$apRows = Database::all(
    "SELECT v.name AS party, " . sprintf($buckets, 'b') . "
     FROM vendor_bills b JOIN vendors v ON v.id=b.vendor_id
     WHERE b.status IN ('unpaid','partial') GROUP BY v.id HAVING tot > 0.001 ORDER BY tot DESC");

$cols = [['b0','ยังไม่ถึงกำหนด','var(--green)'],['b1','1–30 วัน','var(--solar-gold)'],['b2','31–60 วัน','var(--solar-orange)'],['b3','61–90 วัน','#f97316'],['b4','เกิน 90 วัน','var(--red)']];

function agingTable(array $rows, array $cols, string $partyLabel): string {
    ob_start();
    $sum = ['b0'=>0,'b1'=>0,'b2'=>0,'b3'=>0,'b4'=>0,'tot'=>0];
    ?>
    <div class="table-wrap"><table>
      <thead><tr><th><?= e($partyLabel) ?></th><?php foreach ($cols as $c): ?><th style="text-align:right;color:<?= $c[2] ?>"><?= e($c[1]) ?></th><?php endforeach; ?><th style="text-align:right;">รวม</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-muted" style="text-align:center;padding:24px;">ไม่มียอดคงค้าง</td></tr>
        <?php else: foreach ($rows as $r): foreach ($sum as $k=>$v) $sum[$k]+=(float)$r[$k]; ?>
          <tr><td style="font-weight:600;"><?= e($r['party']) ?></td>
            <?php foreach ($cols as $c): $val=(float)$r[$c[0]]; ?><td class="mono" style="text-align:right;<?= $val>0?'color:'.$c[2]:'color:var(--text-muted)' ?>"><?= $val>0?number_format($val,2):'-' ?></td><?php endforeach; ?>
            <td class="mono" style="text-align:right;font-weight:700;"><?= number_format((float)$r['tot'],2) ?></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot><tr style="border-top:2px solid var(--border);"><td style="font-weight:700;">รวมทั้งหมด</td>
        <?php foreach ($cols as $c): ?><td class="mono" style="text-align:right;font-weight:700;color:<?= $c[2] ?>"><?= number_format($sum[$c[0]],2) ?></td><?php endforeach; ?>
        <td class="mono" style="text-align:right;font-weight:700;"><?= number_format($sum['tot'],2) ?></td></tr></tfoot>
      <?php endif; ?>
    </table></div>
    <?php return ob_get_clean();
}

$pageTitle = 'อายุหนี้ (Aging)';
$activeNav = 'aging';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header"><div><h1>รายงานอายุหนี้</h1><p>วิเคราะห์ลูกหนี้/เจ้าหนี้ตามช่วงเวลาค้างชำระ (จากวันครบกำหนด)</p></div></div>

<div style="display:flex;align-items:center;gap:10px;margin:8px 0 14px;"><div class="stat-icon red" style="width:34px;height:34px;font-size:14px;"><i class="fa-solid fa-hand-holding-dollar"></i></div><div style="font-size:15px;font-weight:700;">ลูกหนี้การค้า (AR)</div></div>
<div class="card"><?= agingTable($arRows, $cols, 'ลูกค้า') ?></div>

<div style="display:flex;align-items:center;gap:10px;margin:26px 0 14px;"><div class="stat-icon gold" style="width:34px;height:34px;font-size:14px;"><i class="fa-solid fa-money-bill-transfer"></i></div><div style="font-size:15px;font-weight:700;">เจ้าหนี้การค้า (AP)</div></div>
<div class="card"><?= agingTable($apRows, $cols, 'ซัพพลายเออร์') ?></div>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
