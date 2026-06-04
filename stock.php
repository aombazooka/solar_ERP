<?php
/** stock.php — คลังสินค้า: รับเข้า / ปรับยอด + ประวัติการเคลื่อนไหว */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('inventory.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('inventory.manage');
    csrf_verify();

    $action    = input('action');
    $productId = (int) input('product_id');
    $qty       = (int) input('qty');
    $note      = input('note');

    try {
        if ($productId <= 0 || $qty <= 0) {
            throw new RuntimeException('กรุณาเลือกสินค้าและระบุจำนวนที่มากกว่า 0');
        }
        if ($action === 'receive') {
            $cost = input('unit_cost') !== '' ? (float) input('unit_cost') : null;
            $bal = Stock::move($productId, 'receipt', $qty, [
                'unit_cost' => $cost, 'ref_type' => 'manual',
                'note' => $note ?: 'รับเข้าด้วยมือ',
            ]);
            flash('success', "รับเข้าสินค้า {$qty} หน่วย เรียบร้อย (คงเหลือ {$bal})");
        } elseif ($action === 'issue') {
            $bal = Stock::move($productId, 'issue', -$qty, [
                'ref_type' => 'manual', 'note' => $note ?: 'เบิกออกด้วยมือ',
            ]);
            flash('success', "เบิกออกสินค้า {$qty} หน่วย เรียบร้อย (คงเหลือ {$bal})");
        } elseif ($action === 'adjust') {
            // ปรับยอดเป็นค่าที่ระบุ (qty = ยอดเป้าหมาย)
            $current = (int) Database::scalar('SELECT stock_qty FROM products WHERE id=:id', ['id' => $productId]);
            $diff = $qty - $current;
            if ($diff === 0) {
                flash('info', 'ยอดเท่าเดิม ไม่มีการปรับ');
            } else {
                $bal = Stock::move($productId, 'adjust', $diff, [
                    'ref_type' => 'manual', 'note' => $note ?: "ปรับยอดจาก {$current} เป็น {$qty}",
                ]);
                flash('success', "ปรับยอดเป็น {$bal} เรียบร้อย");
            }
        } else {
            throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('stock.php');
}

$products  = Database::all("SELECT id, sku, name, unit, stock_qty, reorder_level FROM products WHERE category <> 'service' ORDER BY name");
$movements = Stock::history(null, 40);

$pageTitle = 'คลังสินค้า';
$activeNav = 'stock';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div>
    <h1>คลังสินค้า</h1>
    <p>จัดการสต็อกและดูประวัติการเคลื่อนไหว</p>
  </div>
  <?php if (Auth::can('inventory.manage')): ?>
    <button class="btn btn-primary" onclick="document.getElementById('moveForm').classList.toggle('hidden')">
      <i class="fa-solid fa-right-left"></i> บันทึกการเคลื่อนไหว
    </button>
  <?php endif; ?>
</div>

<?php if (Auth::can('inventory.manage')): ?>
<div class="card hidden" id="moveForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">รับเข้า / เบิกออก / ปรับยอด</div></div>
  <form method="post" action="<?= e(url('stock.php')) ?>">
    <?= csrf_field() ?>
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;">
        <label class="form-label">สินค้า *</label>
        <select class="form-select" name="product_id" required>
          <option value="">— เลือกสินค้า —</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['sku']) ?> · <?= e($p['name']) ?> (คงเหลือ <?= (int)$p['stock_qty'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ประเภท</label>
        <select class="form-select" name="action" id="actionSel" onchange="document.getElementById('costWrap').style.display=this.value==='receive'?'block':'none';document.getElementById('qtyHint').textContent=this.value==='adjust'?'(ยอดคงเหลือเป้าหมาย)':'(จำนวน)';">
          <option value="receive">รับเข้า</option>
          <option value="issue">เบิกออก</option>
          <option value="adjust">ปรับยอด</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">จำนวน * <span id="qtyHint" class="text-muted" style="font-size:11px;">(จำนวน)</span></label>
        <input class="form-input" type="number" name="qty" min="1" required>
      </div>
      <div class="form-group" id="costWrap" style="grid-column:span 2;">
        <label class="form-label">ต้นทุน/หน่วย (เฉพาะรับเข้า)</label>
        <input class="form-input" type="number" step="0.01" name="unit_cost" placeholder="ปล่อยว่างได้">
      </div>
      <div class="form-group" style="grid-column:span 2;">
        <label class="form-label">หมายเหตุ</label>
        <input class="form-input" name="note" placeholder="เช่น รับจาก PO-2569-001">
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<?php endif; ?>

<div class="grid g21">
  <!-- ประวัติการเคลื่อนไหว -->
  <div class="card">
    <div class="card-head"><div><div class="card-title">ประวัติการเคลื่อนไหว</div><div class="card-sub">40 รายการล่าสุด</div></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>เวลา</th><th>สินค้า</th><th>ประเภท</th><th>จำนวน</th><th>คงเหลือ</th><th>โดย</th></tr></thead>
        <tbody>
          <?php if (!$movements): ?>
            <tr><td colspan="6" class="text-muted" style="text-align:center;padding:30px;">ยังไม่มีการเคลื่อนไหว</td></tr>
          <?php else: foreach ($movements as $m):
              [$label, $cls] = Stock::typeLabel($m['type']);
              $qty = (int)$m['qty']; ?>
            <tr>
              <td class="text-muted" style="font-size:11px;"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></td>
              <td><span class="mono text-gold"><?= e($m['sku']) ?></span><div style="font-size:11px;color:var(--text-muted)"><?= e($m['product_name']) ?></div></td>
              <td><span class="badge <?= e($cls) ?>"><?= e($label) ?></span></td>
              <td class="mono" style="font-weight:700;color:<?= $qty >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= $qty >= 0 ? '+' : '' ?><?= $qty ?></td>
              <td class="mono"><?= (int)$m['balance_after'] ?></td>
              <td class="text-muted" style="font-size:12px;"><?= e($m['by_name'] ?? '-') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ยอดคงเหลือปัจจุบัน -->
  <div class="card">
    <div class="card-head"><div><div class="card-title">ยอดคงเหลือ</div><div class="card-sub"><?= count($products) ?> รายการ</div></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>สินค้า</th><th>คงเหลือ</th></tr></thead>
        <tbody>
          <?php foreach ($products as $p):
              $low = (int)$p['stock_qty'] <= (int)$p['reorder_level']; ?>
            <tr>
              <td><span class="mono text-gold" style="font-size:11px;"><?= e($p['sku']) ?></span><div style="font-size:12px;"><?= e($p['name']) ?></div></td>
              <td class="mono" style="font-weight:700;<?= $low ? 'color:var(--red)' : '' ?>"><?= (int)$p['stock_qty'] ?> <span class="text-muted" style="font-size:11px;font-weight:400;"><?= e($p['unit']) ?></span><?php if ($low): ?><br><span class="badge badge-red" style="font-size:9px;">ใกล้หมด</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>.hidden{display:none;}</style>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
