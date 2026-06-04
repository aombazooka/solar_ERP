<?php
/** goods_receipts.php — รับเข้าสินค้า (list + สร้าง → เพิ่มสต็อก + สร้าง AP) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('inventory.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('inventory.manage');
    csrf_verify();
    try {
        $pids = $_POST['item_pid'] ?? []; $qtys = $_POST['item_qty'] ?? [];
        $costs = $_POST['item_cost'] ?? []; $descs = $_POST['item_desc'] ?? [];
        $items = [];
        foreach ($pids as $i => $pid) {
            $items[] = ['product_id' => $pid, 'qty' => $qtys[$i] ?? 0, 'unit_cost' => $costs[$i] ?? 0, 'description' => $descs[$i] ?? ''];
        }
        $res = Purchasing::createGoodsReceipt((int) input('vendor_id'), $items, input('note'), null, input('vat_mode'));
        flash('success', "รับเข้าสินค้า {$res['gr_no']} + สร้างใบเจ้าหนี้ {$res['bill_no']} เรียบร้อย");
        redirect('goods_receipt_view.php?id=' . $res['gr_id']);
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
        redirect('goods_receipts.php');
    }
}

$receipts = Database::all('SELECT gr.*, v.name AS vendor_name FROM goods_receipts gr JOIN vendors v ON v.id=gr.vendor_id ORDER BY gr.id DESC');
$vendors  = Database::all('SELECT id, name FROM vendors WHERE is_active=1 ORDER BY name');
$products = Database::all("SELECT id, sku, name, cost_price, unit FROM products WHERE is_active=1 AND category<>'service' ORDER BY name");
$canManage = Auth::can('inventory.manage');

$pageTitle = 'รับเข้าสินค้า';
$activeNav = 'goods_receipts';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>รับเข้าสินค้า</h1><p>บันทึกรับสินค้าจากซัพพลายเออร์ — ระบบจะเพิ่มสต็อกและสร้างใบเจ้าหนี้ให้อัตโนมัติ</p></div>
  <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="document.getElementById('grForm').classList.toggle('hidden')"><i class="fa-solid fa-truck-ramp-box"></i> รับเข้าใหม่</button>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="card hidden" id="grForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">บันทึกรับเข้าสินค้า</div></div>
  <form method="post"><?= csrf_field() ?>
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ซัพพลายเออร์ *</label>
        <select class="form-select" name="vendor_id" required><option value="">— เลือก —</option>
          <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-group"><label class="form-label">VAT</label><select class="form-select" name="vat_mode" onchange="recalc()"><option value="exclude">ไม่รวม VAT (+7%)</option><option value="include">รวม VAT แล้ว</option><option value="none">ไม่มี VAT</option></select></div>
      <div class="form-group"><label class="form-label">หมายเหตุ</label><input class="form-input" name="note" placeholder="เลขที่ใบส่งของ ฯลฯ"></div>
    </div>
    <table id="itemsTable" style="margin-bottom:10px;">
      <thead><tr><th style="width:44%">สินค้า</th><th style="width:16%">จำนวน</th><th style="width:20%">ต้นทุน/หน่วย</th><th style="width:16%">รวม</th><th></th></tr></thead>
      <tbody id="itemsBody"></tbody>
    </table>
    <button type="button" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;" onclick="addRow()"><i class="fa-solid fa-plus"></i> เพิ่มรายการ</button>

    <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin:16px 0;max-width:320px;margin-left:auto;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">ยอดรวม</span><span class="mono" id="sumSub">0.00</span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">VAT 7%</span><span class="mono" id="sumVat">0.00</span></div>
      <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>รวมทั้งสิ้น</span><span class="mono text-gold" id="sumTotal">0.00</span></div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึกรับเข้า + สร้างเจ้าหนี้</button>
  </form>
</div>
<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
function addRow(){
  const tb = document.getElementById('itemsBody'); const tr = document.createElement('tr');
  let opts = '<option value="">— เลือกสินค้า —</option>';
  PRODUCTS.forEach(p => opts += `<option value="${p.id}" data-cost="${p.cost_price}" data-name="${p.sku} · ${p.name}">${p.sku} · ${p.name}</option>`);
  tr.innerHTML = `
    <td><select class="form-select" name="item_pid[]" style="padding:7px 10px;" onchange="pick(this)">${opts}</select>
        <input type="hidden" name="item_desc[]"></td>
    <td><input class="form-input" type="number" step="1" name="item_qty[]" value="1" style="padding:7px 10px;" oninput="recalc()"></td>
    <td><input class="form-input" type="number" step="0.01" name="item_cost[]" value="0" style="padding:7px 10px;" oninput="recalc()"></td>
    <td class="mono lineTotal" style="font-weight:700;">0.00</td>
    <td><button type="button" class="icon-btn" style="width:30px;height:30px;" onclick="this.closest('tr').remove();recalc()"><i class="fa-solid fa-trash" style="font-size:12px;color:var(--red)"></i></button></td>`;
  tb.appendChild(tr);
}
function pick(sel){const o=sel.selectedOptions[0];const tr=sel.closest('tr');if(o.value){tr.querySelector('[name="item_desc[]"]').value=o.dataset.name;tr.querySelector('[name="item_cost[]"]').value=o.dataset.cost;}recalc();}
function recalc(){let sub=0;document.querySelectorAll('#itemsBody tr').forEach(tr=>{const q=parseFloat(tr.querySelector('[name="item_qty[]"]').value)||0;const c=parseFloat(tr.querySelector('[name="item_cost[]"]').value)||0;const lt=q*c;sub+=lt;tr.querySelector('.lineTotal').textContent=lt.toLocaleString('en-US',{minimumFractionDigits:2});});
  const mode=(document.querySelector('[name="vat_mode"]')||{}).value||'exclude';
  let vat=0,total=sub; if(mode==='exclude'){vat=sub*0.07;total=sub+vat;}else if(mode==='include'){vat=sub-sub/1.07;total=sub;}
  const f=n=>n.toLocaleString('en-US',{minimumFractionDigits:2});document.getElementById('sumSub').textContent=f(sub);document.getElementById('sumVat').textContent=f(vat);document.getElementById('sumTotal').textContent=f(total);}
addRow();
</script>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ซัพพลายเออร์</th><th>ยอดรวม</th><th>วันที่รับ</th><th></th></tr></thead>
      <tbody>
        <?php if (!$receipts): ?>
          <tr><td colspan="5" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีการรับเข้า</td></tr>
        <?php else: foreach ($receipts as $gr): ?>
          <tr>
            <td class="mono text-gold"><?= e($gr['doc_no']) ?></td>
            <td style="font-weight:600;"><?= e($gr['vendor_name']) ?></td>
            <td class="mono" style="font-weight:700;"><?= baht($gr['total']) ?></td>
            <td class="text-muted"><?= e(thai_date_short($gr['received_at'])) ?></td>
            <td style="text-align:right;"><a class="btn btn-ghost" style="padding:5px 10px;font-size:12px;" href="<?= e(url('goods_receipt_view.php?id='.$gr['id'])) ?>">ดู</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
