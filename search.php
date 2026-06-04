<?php
/** search.php — ค้นหารวมทั้งระบบ (เคารพสิทธิ์ RBAC) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::require();

$q = trim(input('q'));
$like = '%' . $q . '%';
$groups = [];
// native prepared statement ใช้ placeholder ซ้ำไม่ได้ → แยกชื่อ q1/q2/q3
$p3 = ['q1' => $like, 'q2' => $like, 'q3' => $like];
$p2 = ['q1' => $like, 'q2' => $like];

if ($q !== '' && mb_strlen($q) >= 1) {
    // ลูกค้า
    if (Auth::can('customer.view')) {
        $rows = Database::all('SELECT id, code, name, phone FROM customers WHERE code LIKE :q1 OR name LIKE :q2 OR phone LIKE :q3 ORDER BY id DESC LIMIT 8', $p3);
        foreach ($rows as $r) $groups['ลูกค้า'][] = ['icon'=>'fa-address-book','color'=>'purple','title'=>$r['name'],'sub'=>$r['code'].' · '.($r['phone']??''),'url'=>url('customers.php?edit='.$r['id'])];
    }
    // สินค้า
    if (Auth::can('inventory.view')) {
        $rows = Database::all('SELECT id, sku, name, stock_qty FROM products WHERE sku LIKE :q1 OR name LIKE :q2 OR brand LIKE :q3 ORDER BY id DESC LIMIT 8', $p3);
        foreach ($rows as $r) $groups['สินค้า'][] = ['icon'=>'fa-boxes-stacked','color'=>'gold','title'=>$r['name'],'sub'=>$r['sku'].' · คงเหลือ '.(int)$r['stock_qty'],'url'=>url('products.php?edit='.$r['id'])];
    }
    // ใบเสนอราคา + ใบสั่งขาย + Leads
    if (Auth::can('sales.view')) {
        foreach (Database::all('SELECT q.id, q.doc_no, c.name FROM quotations q JOIN customers c ON c.id=q.customer_id WHERE q.doc_no LIKE :q1 OR c.name LIKE :q2 ORDER BY q.id DESC LIMIT 6', $p2) as $r)
            $groups['ใบเสนอราคา'][] = ['icon'=>'fa-file-invoice','color'=>'blue','title'=>$r['doc_no'],'sub'=>$r['name'],'url'=>url('quotation_view.php?id='.$r['id'])];
        foreach (Database::all('SELECT o.id, o.doc_no, c.name FROM sales_orders o JOIN customers c ON c.id=o.customer_id WHERE o.doc_no LIKE :q1 OR c.name LIKE :q2 ORDER BY o.id DESC LIMIT 6', $p2) as $r)
            $groups['ใบสั่งขาย'][] = ['icon'=>'fa-cart-shopping','color'=>'gold','title'=>$r['doc_no'],'sub'=>$r['name'],'url'=>url('order_view.php?id='.$r['id'])];
        foreach (Database::all('SELECT id, name, phone, status FROM leads WHERE name LIKE :q1 OR phone LIKE :q2 ORDER BY id DESC LIMIT 6', $p2) as $r)
            $groups['Leads'][] = ['icon'=>'fa-user-plus','color'=>'blue','title'=>$r['name'],'sub'=>($r['phone']??'').' · '.$r['status'],'url'=>url('leads.php')];
    }
    // ใบแจ้งหนี้
    if (Auth::can('finance.view')) {
        foreach (Database::all('SELECT i.id, i.doc_no, c.name FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.doc_no LIKE :q1 OR c.name LIKE :q2 ORDER BY i.id DESC LIMIT 6', $p2) as $r)
            $groups['ใบแจ้งหนี้'][] = ['icon'=>'fa-file-invoice-dollar','color'=>'green','title'=>$r['doc_no'],'sub'=>$r['name'],'url'=>url('invoice_view.php?id='.$r['id'])];
    }
}
$totalResults = array_sum(array_map('count', $groups));

$pageTitle = 'ค้นหา';
$activeNav = '';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ผลการค้นหา</h1><p><?= $q!=='' ? 'คำค้น "'.e($q).'" — พบ '.$totalResults.' รายการ' : 'พิมพ์คำค้นในช่องด้านบน' ?></p></div>
</div>

<div class="card">
  <?php if ($q === ''): ?>
    <div class="text-muted" style="text-align:center;padding:40px;"><i class="fa-solid fa-magnifying-glass" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4;"></i>ค้นหาลูกค้า สินค้า เอกสาร และอื่นๆ</div>
  <?php elseif (!$groups): ?>
    <div class="text-muted" style="text-align:center;padding:40px;">ไม่พบรายการที่ตรงกับ "<?= e($q) ?>"</div>
  <?php else: foreach ($groups as $label => $items): ?>
    <div class="search-group">
      <div class="search-group-label"><?= e($label) ?> (<?= count($items) ?>)</div>
      <?php foreach ($items as $it): ?>
        <a class="search-result" href="<?= e($it['url']) ?>">
          <div class="ic stat-icon <?= e($it['color']) ?>"><i class="fa-solid <?= e($it['icon']) ?>"></i></div>
          <div style="flex:1;"><div style="font-weight:600;font-size:13px;"><?= e($it['title']) ?></div><div class="text-muted" style="font-size:11px;"><?= e($it['sub']) ?></div></div>
          <i class="fa-solid fa-chevron-right text-muted" style="font-size:11px;"></i>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
