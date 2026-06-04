<?php
/** stock_count.php — ตรวจนับสต็อก: กรอกยอดนับจริง → ระบบปรับยอดตามผลต่าง */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('inventory.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('inventory.manage');
    csrf_verify();
    $counts = $_POST['counted'] ?? [];
    $adjusted = 0; $errors = 0;
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    try {
        foreach ($counts as $pid => $val) {
            if ($val === '' || $val === null) continue;          // ไม่ได้นับ = ข้าม
            $pid = (int) $pid; $counted = (int) $val;
            $cur = (int) Database::scalar('SELECT stock_qty FROM products WHERE id=:id', ['id'=>$pid]);
            $diff = $counted - $cur;
            if ($diff === 0) continue;
            Stock::move($pid, 'adjust', $diff, ['ref_type'=>'manual', 'note'=>"ตรวจนับ: {$cur} → {$counted}"]);
            $adjusted++;
        }
        $pdo->commit();
        flash($adjusted ? 'success' : 'info', $adjusted ? "ปรับยอดจากการตรวจนับ {$adjusted} รายการเรียบร้อย" : 'ไม่มีรายการที่ต้องปรับ');
    } catch (\Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'ปรับยอดไม่สำเร็จ: ' . $ex->getMessage());
    }
    redirect('stock_count.php');
}

$products = Database::all("SELECT id, sku, name, unit, stock_qty FROM products WHERE category<>'service' AND is_active=1 ORDER BY category, name");

$pageTitle = 'ตรวจนับสต็อก';
$activeNav = 'stock_count';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ตรวจนับสต็อก</h1><p>กรอกยอดที่นับได้จริง — ระบบจะปรับยอดตามผลต่างและบันทึก ledger ให้</p></div>
</div>

<?php if (Auth::can('inventory.manage')): ?>
<form method="post"><?= csrf_field() ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>SKU</th><th>สินค้า</th><th>ยอดในระบบ</th><th>นับได้จริง</th><th>ผลต่าง</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr data-cur="<?= (int)$p['stock_qty'] ?>">
            <td class="mono text-gold"><?= e($p['sku']) ?></td>
            <td style="font-weight:600;"><?= e($p['name']) ?></td>
            <td class="mono"><?= (int)$p['stock_qty'] ?> <span class="text-muted" style="font-size:11px;"><?= e($p['unit']) ?></span></td>
            <td><input class="form-input count-in" type="number" name="counted[<?= (int)$p['id'] ?>]" placeholder="<?= (int)$p['stock_qty'] ?>" style="width:110px;padding:7px 10px;" oninput="diff(this)"></td>
            <td class="mono diff-cell text-muted">—</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;">
    <div class="text-muted" style="font-size:12px;"><i class="fa-solid fa-circle-info"></i> เว้นว่าง = ไม่นับ (ไม่ปรับ) · กรอกเท่ายอดเดิม = ไม่เปลี่ยน</div>
    <button class="btn btn-primary" onclick="return confirm('ยืนยันปรับยอดตามผลการตรวจนับ?')"><i class="fa-solid fa-clipboard-check"></i> บันทึกผลตรวจนับ</button>
  </div>
</div>
</form>
<script>
function diff(inp){
  const tr=inp.closest('tr'); const cur=parseInt(tr.dataset.cur)||0;
  const cell=tr.querySelector('.diff-cell');
  if(inp.value===''){ cell.textContent='—'; cell.style.color='var(--text-muted)'; return; }
  const d=(parseInt(inp.value)||0)-cur;
  cell.textContent=(d>0?'+':'')+d; cell.style.color = d===0?'var(--text-muted)':(d>0?'var(--green)':'var(--red)');
}
</script>
<?php else: ?>
  <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> ต้องมีสิทธิ์จัดการคลังเพื่อตรวจนับ</div>
<?php endif; ?>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
