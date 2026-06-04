<?php
/** customers.php — ทะเบียนลูกค้า (list + เพิ่ม + แก้ไข + ลบ) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('customer.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('customer.manage');
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'delete') {
            $id = (int) input('id');
            $used = (int) Database::scalar('SELECT COUNT(*) FROM quotations WHERE customer_id=:id', ['id' => $id]);
            if ($used > 0) throw new RuntimeException('ลบไม่ได้ — ลูกค้านี้มีใบเสนอราคา/ใบสั่งขายอยู่');
            Database::run('DELETE FROM customers WHERE id=:id', ['id' => $id]);
            flash('success', 'ลบลูกค้าเรียบร้อย');
        } else {
            $name = input('name');
            $type = input('type') === 'company' ? 'company' : 'individual';
            $phone = input('phone'); $province = input('province');
            if ($name === '') throw new RuntimeException('กรุณากรอกชื่อลูกค้า');

            if ($do === 'update') {
                $id = (int) input('id');
                Database::run('UPDATE customers SET name=:n, type=:t, phone=:p, province=:pv WHERE id=:id',
                    ['n' => $name, 't' => $type, 'p' => $phone ?: null, 'pv' => $province ?: null, 'id' => $id]);
                flash('success', 'แก้ไขข้อมูลลูกค้าเรียบร้อย');
            } else {
                $maxId = (int) Database::scalar('SELECT COALESCE(MAX(id),0) FROM customers');
                $code = 'CUS-' . str_pad((string)($maxId+1), 4, '0', STR_PAD_LEFT);
                Database::run('INSERT INTO customers (code, name, type, phone, province, created_by) VALUES (:c,:n,:t,:p,:pv,:cb)',
                    ['c' => $code, 'n' => $name, 't' => $type, 'p' => $phone ?: null, 'pv' => $province ?: null, 'cb' => Auth::id()]);
                Database::run('INSERT INTO audit_log (user_id, created_by, action, entity, entity_id, ip_address) VALUES (:u,:u,:a,:e,:eid,:ip)',
                    ['u' => Auth::id(), 'a' => 'create', 'e' => 'customers', 'eid' => Database::lastId(), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
                flash('success', "เพิ่มลูกค้า {$code} เรียบร้อย");
            }
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('customers.php');
}

$editId = (int) input('edit');
$edit = $editId ? Database::one('SELECT * FROM customers WHERE id=:id', ['id' => $editId]) : null;
$perPage = 15; $page = current_page();
$total = (int) Database::scalar('SELECT COUNT(*) FROM customers');
$customers = Database::all('SELECT id, code, name, type, phone, province, created_at FROM customers ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page-1)*$perPage));
$canManage = Auth::can('customer.manage');

$pageTitle = 'ทะเบียนลูกค้า';
$activeNav = 'customers';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ทะเบียนลูกค้า</h1><p>จัดการข้อมูลลูกค้าทั้งหมด <?= $total ?> ราย</p></div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-ghost" href="<?= e(url('export.php?type=customers')) ?>"><i class="fa-solid fa-file-csv"></i> Export</a>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> เพิ่มลูกค้า</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($canManage): ?>
<div class="card <?= $edit ? '' : 'hidden' ?>" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title"><?= $edit ? 'แก้ไขลูกค้า '.e($edit['code']) : 'เพิ่มลูกค้าใหม่' ?></div></div>
  <form method="post" action="<?= e(url('customers.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="grid g2">
      <div class="form-group"><label class="form-label">ชื่อลูกค้า *</label><input class="form-input" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
      <div class="form-group"><label class="form-label">ประเภท</label>
        <select class="form-select" name="type">
          <option value="individual" <?= ($edit['type'] ?? '')==='individual'?'selected':'' ?>>บุคคลธรรมดา</option>
          <option value="company" <?= ($edit['type'] ?? '')==='company'?'selected':'' ?>>นิติบุคคล</option>
        </select></div>
      <div class="form-group"><label class="form-label">เบอร์โทร</label><input class="form-input" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">จังหวัด</label><input class="form-input" name="province" value="<?= e($edit['province'] ?? '') ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('customers.php')) ?>">ยกเลิก</a><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>รหัส</th><th>ชื่อลูกค้า</th><th>ประเภท</th><th>จังหวัด</th><th>เบอร์โทร</th><th>วันที่เพิ่ม</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$customers): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีข้อมูลลูกค้า</td></tr>
        <?php else: foreach ($customers as $c): ?>
          <tr>
            <td class="mono text-gold"><?= e($c['code']) ?></td>
            <td style="font-weight:600;"><?= e($c['name']) ?></td>
            <td><span class="badge <?= $c['type']==='company'?'badge-blue':'badge-purple' ?>"><?= $c['type']==='company'?'นิติบุคคล':'บุคคลธรรมดา' ?></span></td>
            <td><?= e($c['province'] ?? '-') ?></td>
            <td><?= e($c['phone'] ?? '-') ?></td>
            <td class="text-muted"><?= e(thai_date_short($c['created_at'])) ?></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <a class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" href="<?= e(url('customers.php?edit='.$c['id'])) ?>"><i class="fa-solid fa-pen"></i></a>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบลูกค้า <?= e($c['code']) ?>?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pager($total, $perPage, $page, url('customers.php')) ?>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
