<?php
/** products.php — ทะเบียนสินค้า (list + filter + เพิ่ม + แก้ไข + ลบ/ปิดใช้งาน) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('inventory.view');

const CATEGORIES = [
    'panel'     => ['แผงโซลาร์',    'fa-solar-panel',     'badge-gold'],
    'inverter'  => ['อินเวอร์เตอร์', 'fa-plug-circle-bolt','badge-blue'],
    'battery'   => ['แบตเตอรี่',     'fa-car-battery',     'badge-green'],
    'mounting'  => ['โครงยึด',       'fa-grip-lines',      'badge-purple'],
    'accessory' => ['อุปกรณ์เสริม',  'fa-screwdriver-wrench','badge-muted'],
    'service'   => ['ค่าบริการ',     'fa-handshake-angle', 'badge-red'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('inventory.manage');
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'delete') {
            $id = (int) input('id');
            $moves = (int) Database::scalar('SELECT COUNT(*) FROM stock_movements WHERE product_id=:id', ['id' => $id]);
            $sold  = (int) Database::scalar('SELECT COUNT(*) FROM sales_order_items WHERE product_id=:id', ['id' => $id]);
            if ($moves > 0 || $sold > 0) throw new RuntimeException('ลบไม่ได้ — สินค้านี้มีประวัติเคลื่อนไหว/การขาย ใช้ "ปิดการใช้งาน" แทน');
            Database::run('DELETE FROM products WHERE id=:id', ['id' => $id]);
            flash('success', 'ลบสินค้าเรียบร้อย');
        } elseif ($do === 'toggle') {
            Database::run('UPDATE products SET is_active = 1 - is_active WHERE id=:id', ['id' => (int) input('id')]);
            flash('success', 'เปลี่ยนสถานะสินค้าเรียบร้อย');
        } else {
            $name = input('name');
            $category = array_key_exists(input('category'), CATEGORIES) ? input('category') : 'accessory';
            $brand = input('brand'); $unit = input('unit') ?: 'ชิ้น';
            $powerW = input('power_w'); $cost = (float) input('cost_price'); $sell = (float) input('sell_price');
            $reorder = max(0, (int) input('reorder_level'));
            if ($name === '') throw new RuntimeException('กรุณากรอกชื่อสินค้า');
            if ($sell < 0 || $cost < 0) throw new RuntimeException('ราคาต้องไม่ติดลบ');

            if ($do === 'update') {
                $id = (int) input('id');
                Database::run(
                    'UPDATE products SET name=:n, category=:cat, brand=:b, unit=:u, power_w=:pw, cost_price=:cost, sell_price=:sell, reorder_level=:ro WHERE id=:id',
                    ['n' => $name, 'cat' => $category, 'b' => $brand ?: null, 'u' => $unit,
                     'pw' => $powerW !== '' ? (int) $powerW : null, 'cost' => $cost, 'sell' => $sell, 'ro' => $reorder, 'id' => $id]
                );
                flash('success', 'แก้ไขสินค้าเรียบร้อย (สต็อกปรับผ่านหน้าคลังสินค้า)');
            } else {
                $stock = (int) input('stock_qty');
                $maxId = (int) Database::scalar('SELECT COALESCE(MAX(id),0) FROM products');
                $sku = strtoupper(substr($category, 0, 3)) . '-' . str_pad((string)($maxId+1), 4, '0', STR_PAD_LEFT);
                Database::run(
                    'INSERT INTO products (sku, name, category, brand, unit, power_w, cost_price, sell_price, stock_qty, reorder_level, created_by)
                     VALUES (:sku,:n,:cat,:b,:u,:pw,:cost,:sell,:stock,:ro,:cb)',
                    ['sku' => $sku, 'n' => $name, 'cat' => $category, 'b' => $brand ?: null, 'u' => $unit,
                     'pw' => $powerW !== '' ? (int) $powerW : null, 'cost' => $cost, 'sell' => $sell, 'stock' => $stock, 'ro' => $reorder, 'cb' => Auth::id()]
                );
                $newId = Database::lastId();
                // ถ้ามีสต็อกเริ่มต้น บันทึก ledger ด้วย
                if ($stock > 0 && $category !== 'service') {
                    Database::run('INSERT INTO stock_movements (product_id, type, qty, balance_after, ref_type, note, created_by) VALUES (:p,:t,:q,:b,:rt,:nt,:cb)',
                        ['p' => $newId, 't' => 'adjust', 'q' => $stock, 'b' => $stock, 'rt' => 'manual', 'nt' => 'ยอดเริ่มต้น', 'cb' => Auth::id()]);
                }
                Database::run('INSERT INTO audit_log (user_id, created_by, action, entity, entity_id, ip_address) VALUES (:u,:u,:a,:e,:eid,:ip)',
                    ['u' => Auth::id(), 'a' => 'create', 'e' => 'products', 'eid' => $newId, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
                flash('success', "เพิ่มสินค้า {$sku} เรียบร้อย");
            }
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('products.php');
}

$editId = (int) input('edit');
$edit = $editId ? Database::one('SELECT * FROM products WHERE id=:id', ['id' => $editId]) : null;

$filter = array_key_exists(input('cat'), CATEGORIES) ? input('cat') : '';
$where = $filter !== '' ? 'WHERE p.category = :cat' : '';
$params = $filter !== '' ? ['cat' => $filter] : [];
$perPage = 15; $page = current_page();
$totalRows = (int) Database::scalar("SELECT COUNT(*) FROM products p $where", $params);
$products = Database::all(
    "SELECT p.*, v.name AS vendor_name FROM products p LEFT JOIN vendors v ON v.id=p.vendor_id $where ORDER BY p.category, p.id DESC LIMIT " . $perPage . " OFFSET " . (($page-1)*$perPage), $params
);
$totalSkus  = (int) Database::scalar('SELECT COUNT(*) FROM products');
$lowStock   = (int) Database::scalar('SELECT COUNT(*) FROM products WHERE stock_qty <= reorder_level AND category <> "service" AND is_active=1');
$stockValue = (float) Database::scalar('SELECT COALESCE(SUM(stock_qty * cost_price),0) FROM products');
$canManage = Auth::can('inventory.manage');

$pageTitle = 'สินค้า / วัสดุ';
$activeNav = 'products';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>สินค้า / วัสดุ</h1><p>ทะเบียนสินค้าโซลาร์ทั้งหมด <?= $totalSkus ?> รายการ</p></div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-ghost" href="<?= e(url('export.php?type=products')) ?>"><i class="fa-solid fa-file-csv"></i> Export</a>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> เพิ่มสินค้า</button>
    <?php endif; ?>
  </div>
</div>

<div class="grid g3" style="margin-bottom:20px;">
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon gold" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-body"><div class="stat-label">รายการสินค้า</div><div class="stat-value" style="font-size:20px;"><?= $totalSkus ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon green" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-warehouse"></i></div><div class="stat-body"><div class="stat-label">มูลค่าสต็อก (ทุน)</div><div class="stat-value" style="font-size:20px;"><?= baht($stockValue) ?></div></div></div>
  <div class="stat-card" style="padding:16px 18px;"><div class="stat-icon red" style="width:38px;height:38px;font-size:16px;"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="stat-body"><div class="stat-label">ใกล้หมด</div><div class="stat-value" style="font-size:20px;"><?= $lowStock ?></div></div></div>
</div>

<?php if ($canManage): ?>
<div class="card <?= $edit ? '' : 'hidden' ?>" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title"><?= $edit ? 'แก้ไขสินค้า '.e($edit['sku']) : 'เพิ่มสินค้าใหม่' ?></div></div>
  <form method="post"><?= csrf_field() ?>
    <input type="hidden" name="do" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="grid g3">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อสินค้า *</label><input class="form-input" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
      <div class="form-group"><label class="form-label">หมวดหมู่</label><select class="form-select" name="category">
        <?php foreach (CATEGORIES as $key => $c): ?><option value="<?= e($key) ?>" <?= ($edit['category'] ?? '')===$key?'selected':'' ?>><?= e($c[0]) ?></option><?php endforeach; ?>
      </select></div>
      <div class="form-group"><label class="form-label">ยี่ห้อ</label><input class="form-input" name="brand" value="<?= e($edit['brand'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">หน่วย</label><input class="form-input" name="unit" value="<?= e($edit['unit'] ?? 'ชิ้น') ?>"></div>
      <div class="form-group"><label class="form-label">กำลังไฟ (วัตต์)</label><input class="form-input" type="number" name="power_w" value="<?= e($edit['power_w'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">ราคาทุน</label><input class="form-input" type="number" step="0.01" name="cost_price" value="<?= e($edit['cost_price'] ?? '0') ?>"></div>
      <div class="form-group"><label class="form-label">ราคาขาย</label><input class="form-input" type="number" step="0.01" name="sell_price" value="<?= e($edit['sell_price'] ?? '0') ?>"></div>
      <div class="form-group"><label class="form-label">จุดสั่งซื้อขั้นต่ำ (แจ้งเตือนเมื่อต่ำกว่า)</label><input class="form-input" type="number" min="0" name="reorder_level" value="<?= e($edit['reorder_level'] ?? '0') ?>"></div>
      <?php if (!$edit): ?>
        <div class="form-group"><label class="form-label">จำนวนเริ่มต้น</label><input class="form-input" type="number" name="stock_qty" value="0"></div>
      <?php else: ?>
        <div class="form-group"><label class="form-label">คงเหลือ (แก้ที่หน้าคลัง)</label><input class="form-input" value="<?= (int)$edit['stock_qty'] ?>" disabled></div>
      <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('products.php')) ?>">ยกเลิก</a><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
  <a class="btn <?= $filter===''?'btn-primary':'btn-ghost' ?>" style="padding:7px 14px;font-size:12px;" href="<?= e(url('products.php')) ?>">ทั้งหมด</a>
  <?php foreach (CATEGORIES as $key => $c): ?>
    <a class="btn <?= $filter===$key?'btn-primary':'btn-ghost' ?>" style="padding:7px 14px;font-size:12px;" href="<?= e(url('products.php?cat='.$key)) ?>"><i class="fa-solid <?= e($c[1]) ?>"></i> <?= e($c[0]) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>SKU</th><th>ชื่อสินค้า</th><th>หมวด</th><th>กำลัง</th><th>ทุน</th><th>ขาย</th><th>คงเหลือ</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$products): ?>
          <tr><td colspan="8" class="text-muted" style="text-align:center;padding:40px;">ไม่พบสินค้า</td></tr>
        <?php else: foreach ($products as $p):
            $cat = CATEGORIES[$p['category']] ?? ['?','fa-box','badge-muted'];
            $low = $p['category'] !== 'service' && (int)$p['stock_qty'] <= (int)$p['reorder_level'];
            $inactive = !$p['is_active']; ?>
          <tr style="<?= $inactive?'opacity:.5;':'' ?>">
            <td class="mono text-gold"><?= e($p['sku']) ?></td>
            <td><div style="font-weight:600;"><?= e($p['name']) ?><?php if ($inactive): ?> <span class="badge badge-muted" style="font-size:9px;">ปิดใช้</span><?php endif; ?></div><?php if ($p['brand']): ?><div style="font-size:11px;color:var(--text-muted)"><?= e($p['brand']) ?></div><?php endif; ?></td>
            <td><span class="badge <?= e($cat[2]) ?>"><?= e($cat[0]) ?></span></td>
            <td class="mono"><?= $p['power_w'] ? number_format((int)$p['power_w']).' W' : '<span class="text-muted">-</span>' ?></td>
            <td class="mono text-muted"><?= number_format((float)$p['cost_price']) ?></td>
            <td class="mono" style="font-weight:700;"><?= number_format((float)$p['sell_price']) ?></td>
            <td><?php if ($p['category']==='service'): ?><span class="text-muted">-</span><?php else: ?><span class="mono" style="<?= $low?'color:var(--red)':'' ?>"><?= (int)$p['stock_qty'] ?></span> <span class="text-muted" style="font-size:11px;"><?= e($p['unit']) ?></span><?php if ($low): ?> <span class="badge badge-red" style="font-size:9px;">ใกล้หมด</span><?php endif; ?><?php endif; ?></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <a class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" href="<?= e(url('products.php?edit='.$p['id'])) ?>"><i class="fa-solid fa-pen"></i></a>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" title="เปิด/ปิดการใช้งาน"><i class="fa-solid fa-power-off"></i></button></form>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบ <?= e($p['sku']) ?>?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pager($totalRows, $perPage, $page, url('products.php')) ?>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
