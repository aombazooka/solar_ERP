<?php
/** audit.php — บันทึกการใช้งานระบบ (Audit Log) — admin */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireAdmin();

$actL = ['login'=>'เข้าระบบ','create'=>'สร้าง','update'=>'แก้ไข','delete'=>'ลบ','convert'=>'แปลงเอกสาร','deliver'=>'ส่งของ','payment'=>'รับชำระ','change_password'=>'เปลี่ยนรหัสผ่าน'];
$entL = ['users'=>'ผู้ใช้','quotations'=>'ใบเสนอราคา','sales_orders'=>'ใบสั่งขาย','invoices'=>'ใบแจ้งหนี้','customers'=>'ลูกค้า','products'=>'สินค้า','vendors'=>'ซัพพลายเออร์','goods_receipts'=>'รับเข้าสินค้า','vendor_bills'=>'เจ้าหนี้'];

// ตัวกรอง
$fAction = input('action');
$fEntity = input('entity');
$where = []; $params = [];
if ($fAction !== '') { $where[] = 'a.action = :act'; $params['act'] = $fAction; }
if ($fEntity !== '') { $where[] = 'a.entity = :ent'; $params['ent'] = $fEntity; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = 25; $page = current_page();
$total = (int) Database::scalar("SELECT COUNT(*) FROM audit_log a $wsql", $params);
$rows = Database::all(
    "SELECT a.*, u.name AS by_name, own.name AS owner_name
     FROM audit_log a
     LEFT JOIN users u ON u.id = a.created_by
     LEFT JOIN users own ON own.id = a.user_id
     $wsql ORDER BY a.id DESC LIMIT $perPage OFFSET " . (($page-1)*$perPage),
    $params
);
$actions  = Database::all("SELECT DISTINCT action FROM audit_log ORDER BY action");
$entities = Database::all("SELECT DISTINCT entity FROM audit_log WHERE entity IS NOT NULL ORDER BY entity");

$pageTitle = 'บันทึกการใช้งาน';
$activeNav = 'audit';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>บันทึกการใช้งานระบบ (Audit Log)</h1><p>ติดตามทุกการกระทำในระบบ <?= number_format($total) ?> รายการ</p></div>
  <form method="get" style="display:flex;gap:8px;align-items:flex-end;">
    <div><label class="form-label" style="font-size:11px;">การกระทำ</label>
      <select class="form-select" name="action" style="padding:8px 12px;" onchange="this.form.submit()">
        <option value="">ทั้งหมด</option>
        <?php foreach ($actions as $a): ?><option value="<?= e($a['action']) ?>" <?= $fAction===$a['action']?'selected':'' ?>><?= e($actL[$a['action']] ?? $a['action']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label class="form-label" style="font-size:11px;">เอกสาร</label>
      <select class="form-select" name="entity" style="padding:8px 12px;" onchange="this.form.submit()">
        <option value="">ทั้งหมด</option>
        <?php foreach ($entities as $en): ?><option value="<?= e($en['entity']) ?>" <?= $fEntity===$en['entity']?'selected':'' ?>><?= e($entL[$en['entity']] ?? $en['entity']) ?></option><?php endforeach; ?>
      </select></div>
    <?php if ($fAction!==''||$fEntity!==''): ?><a class="btn btn-ghost" href="<?= e(url('audit.php')) ?>">ล้าง</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เวลา</th><th>การกระทำ</th><th>เอกสาร</th><th>เจ้าของข้อมูล</th><th>ผู้ทำรายการ</th><th>IP</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-muted" style="text-align:center;padding:40px;">ไม่มีบันทึก</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td class="text-muted" style="font-size:12px;white-space:nowrap;"><?= e(date('d/m/y H:i:s', strtotime($r['created_at']))) ?></td>
            <td><span class="badge badge-blue"><?= e($actL[$r['action']] ?? $r['action']) ?></span></td>
            <td><?= $r['entity'] ? e($entL[$r['entity']] ?? $r['entity']) . ($r['entity_id'] ? ' <span class="text-muted">#'.e($r['entity_id']).'</span>' : '') : '<span class="text-muted">-</span>' ?></td>
            <td class="text-muted"><?= e($r['owner_name'] ?? '-') ?></td>
            <td style="font-weight:600;"><?= e($r['by_name'] ?? 'ระบบ') ?></td>
            <td class="mono text-muted" style="font-size:11px;"><?= e($r['ip_address'] ?? '-') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pager($total, $perPage, $page, url('audit.php')) ?>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
