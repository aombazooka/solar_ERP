<?php
/** vendors.php — ทะเบียนซัพพลายเออร์ (list + เพิ่ม + แก้ไข + ลบ) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('inventory.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('inventory.manage');
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'delete') {
            $id = (int) input('id');
            $used = (int) Database::scalar('SELECT COUNT(*) FROM products WHERE vendor_id=:id', ['id' => $id]);
            if ($used > 0) throw new RuntimeException('ลบไม่ได้ — ยังมีสินค้าผูกกับซัพพลายเออร์นี้');
            Database::run('DELETE FROM vendors WHERE id=:id', ['id' => $id]);
            flash('success', 'ลบซัพพลายเออร์เรียบร้อย');
        } else {
            $name = input('name');
            if ($name === '') throw new RuntimeException('กรุณากรอกชื่อซัพพลายเออร์');
            $data = ['n' => $name, 'c' => input('contact') ?: null, 'p' => input('phone') ?: null, 'e' => input('email') ?: null];
            if ($do === 'update') {
                $data['id'] = (int) input('id');
                Database::run('UPDATE vendors SET name=:n, contact=:c, phone=:p, email=:e WHERE id=:id', $data);
                flash('success', 'แก้ไขซัพพลายเออร์เรียบร้อย');
            } else {
                $maxId = (int) Database::scalar('SELECT COALESCE(MAX(id),0) FROM vendors');
                $code = 'VEN-' . str_pad((string)($maxId+1), 4, '0', STR_PAD_LEFT);
                $data['code'] = $code; $data['cb'] = Auth::id();
                Database::run('INSERT INTO vendors (code, name, contact, phone, email, created_by) VALUES (:code,:n,:c,:p,:e,:cb)', $data);
                flash('success', "เพิ่มซัพพลายเออร์ {$code} เรียบร้อย");
            }
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('vendors.php');
}

$editId = (int) input('edit');
$edit = $editId ? Database::one('SELECT * FROM vendors WHERE id=:id', ['id' => $editId]) : null;
$vendors = Database::all('SELECT v.*, (SELECT COUNT(*) FROM products p WHERE p.vendor_id=v.id) AS product_count FROM vendors v ORDER BY v.id DESC');
$canManage = Auth::can('inventory.manage');

$pageTitle = 'ซัพพลายเออร์';
$activeNav = 'vendors';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ซัพพลายเออร์</h1><p>ทะเบียนผู้จัดจำหน่ายทั้งหมด <?= count($vendors) ?> ราย</p></div>
  <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> เพิ่มซัพพลายเออร์</button>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="card <?= $edit ? '' : 'hidden' ?>" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title"><?= $edit ? 'แก้ไข '.e($edit['code']) : 'เพิ่มซัพพลายเออร์ใหม่' ?></div></div>
  <form method="post"><?= csrf_field() ?>
    <input type="hidden" name="do" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="grid g2">
      <div class="form-group"><label class="form-label">ชื่อบริษัท / ร้าน *</label><input class="form-input" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
      <div class="form-group"><label class="form-label">ผู้ติดต่อ</label><input class="form-input" name="contact" value="<?= e($edit['contact'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">เบอร์โทร</label><input class="form-input" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">อีเมล</label><input class="form-input" type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('vendors.php')) ?>">ยกเลิก</a><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>รหัส</th><th>ชื่อ</th><th>ผู้ติดต่อ</th><th>เบอร์โทร</th><th>อีเมล</th><th>สินค้า</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$vendors): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีข้อมูล</td></tr>
        <?php else: foreach ($vendors as $v): ?>
          <tr>
            <td class="mono text-gold"><?= e($v['code']) ?></td>
            <td style="font-weight:600;"><?= e($v['name']) ?></td>
            <td><?= e($v['contact'] ?? '-') ?></td>
            <td><?= e($v['phone'] ?? '-') ?></td>
            <td class="text-muted"><?= e($v['email'] ?? '-') ?></td>
            <td><span class="badge badge-blue"><?= (int)$v['product_count'] ?> รายการ</span></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <a class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" href="<?= e(url('vendors.php?edit='.$v['id'])) ?>"><i class="fa-solid fa-pen"></i></a>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบ <?= e($v['code']) ?>?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
