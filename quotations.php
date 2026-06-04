<?php
/** quotations.php — ใบเสนอราคา: list + สร้าง + ส่ง + แปลงเป็นใบสั่งขาย */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('sales.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');

    try {
        if ($do === 'create') {
            Auth::requireCan('sales.create');
            // ประกอบรายการจาก array fields
            $descs  = $_POST['item_desc']  ?? [];
            $pids   = $_POST['item_pid']   ?? [];
            $qtys   = $_POST['item_qty']   ?? [];
            $prices = $_POST['item_price'] ?? [];
            $items = [];
            foreach ($descs as $i => $d) {
                $d = trim((string) $d);
                $qty = (float) ($qtys[$i] ?? 0);
                if ($d === '' || $qty <= 0) continue;
                $items[] = [
                    'product_id'  => ($pids[$i] ?? '') !== '' ? (int) $pids[$i] : null,
                    'description' => $d,
                    'qty'         => $qty,
                    'unit_price'  => (float) ($prices[$i] ?? 0),
                ];
            }
            $res = Sales::createQuotation([
                'customer_id'  => (int) input('customer_id'),
                'system_type'  => input('system_type'),
                'capacity_kwp' => input('capacity_kwp'),
                'discount'     => (float) input('discount'),
                'vat_mode'     => input('vat_mode'),
                'valid_until'  => input('valid_until'),
                'note'         => input('note'),
            ], $items);
            flash('success', "สร้างใบเสนอราคา {$res['doc_no']} เรียบร้อย");
            redirect('quotation_view.php?id=' . $res['id']);
        }
        elseif ($do === 'send') {
            Auth::requireCan('sales.edit');
            $id = (int) input('id');
            Database::run("UPDATE quotations SET status='sent' WHERE id=:id AND status='draft'", ['id' => $id]);
            flash('success', 'ทำเครื่องหมายว่าส่งใบเสนอราคาแล้ว');
            redirect('quotations.php');
        }
        elseif ($do === 'convert') {
            Auth::requireCan('sales.create');
            $res = Sales::convertToOrder((int) input('id'));
            flash('success', "แปลงเป็นใบสั่งขาย {$res['doc_no']} เรียบร้อย");
            redirect('order_view.php?id=' . $res['id']);
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
        redirect('quotations.php');
    }
    redirect('quotations.php');
}

$perPage = 15; $page = current_page();
$total = (int) Database::scalar('SELECT COUNT(*) FROM quotations');
$quotations = Database::all(
    'SELECT q.*, c.name AS customer_name FROM quotations q
     JOIN customers c ON c.id = q.customer_id ORDER BY q.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page-1)*$perPage)
);
$customers = Database::all('SELECT id, name FROM customers ORDER BY name');
$products  = Database::all("SELECT id, sku, name, sell_price, unit FROM products WHERE is_active=1 ORDER BY category, name");

// prefill จากใบงานบริการ (?ticket=ID) — ออกใบเสนอราคาจากเคลม/งานซ่อม
$prefill = null;
$ticketId = (int) input('ticket');
if ($ticketId && Auth::can('sales.create')) {
    $prefill = Database::one('SELECT id, doc_no, customer_id, title FROM service_tickets WHERE id=:id', ['id' => $ticketId]);
}

$pageTitle = 'ใบเสนอราคา';
$activeNav = 'quotations';
require __DIR__ . '/app/layout_header.php';
?>

<div class="page-header">
  <div>
    <h1>ใบเสนอราคา</h1>
    <p>ใบเสนอราคาทั้งหมด <?= $total ?> ฉบับ</p>
  </div>
  <?php if (Auth::can('sales.create')): ?>
    <button class="btn btn-primary" onclick="document.getElementById('qForm').classList.toggle('hidden')">
      <i class="fa-solid fa-plus"></i> สร้างใบเสนอราคา
    </button>
  <?php endif; ?>
</div>

<?php if (Auth::can('sales.create')): ?>
<div class="card <?= $prefill ? '' : 'hidden' ?>" id="qForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">สร้างใบเสนอราคาใหม่<?= $prefill ? ' <span class="text-muted" style="font-size:12px;">(จากใบงาน '.e($prefill['doc_no']).')</span>' : '' ?></div></div>
  <form method="post" action="<?= e(url('quotations.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;">
        <label class="form-label">ลูกค้า *</label>
        <select class="form-select" name="customer_id" required>
          <option value="">— เลือกลูกค้า —</option>
          <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($prefill && (int)$prefill['customer_id']===(int)$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ประเภทระบบ</label>
        <select class="form-select" name="system_type">
          <option value="">—</option>
          <option value="on_grid">On-Grid</option>
          <option value="hybrid">Hybrid</option>
          <option value="off_grid">Off-Grid</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ขนาด (kWp)</label>
        <input class="form-input" type="number" step="0.01" name="capacity_kwp" placeholder="เช่น 10">
      </div>
    </div>

    <!-- รายการสินค้า -->
    <div style="margin:8px 0 4px;font-size:13px;font-weight:600;color:var(--text-soft)">รายการสินค้า/บริการ</div>
    <table id="itemsTable" style="margin-bottom:10px;">
      <thead><tr>
        <th style="width:40%">รายการ</th><th style="width:14%">จำนวน</th>
        <th style="width:20%">ราคา/หน่วย</th><th style="width:20%">รวม</th><th></th>
      </tr></thead>
      <tbody id="itemsBody"></tbody>
    </table>
    <button type="button" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;" onclick="addRow()"><i class="fa-solid fa-plus"></i> เพิ่มรายการ</button>

    <div class="grid g4" style="margin-top:16px;">
      <div class="form-group">
        <label class="form-label">ส่วนลด (บาท)</label>
        <input class="form-input" type="number" step="0.01" name="discount" id="discountInput" value="0" oninput="recalc()">
      </div>
      <div class="form-group">
        <label class="form-label">ภาษีมูลค่าเพิ่ม (VAT)</label>
        <select class="form-select" name="vat_mode" onchange="recalc()">
          <option value="exclude">ราคาไม่รวม VAT (+7%)</option>
          <option value="include">ราคารวม VAT แล้ว</option>
          <option value="none">ไม่มี VAT</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ยืนราคาถึง</label>
        <input class="form-input" type="date" name="valid_until">
      </div>
      <div class="form-group" style="grid-column:span 2;">
        <label class="form-label">หมายเหตุ</label>
        <input class="form-input" name="note" placeholder="เงื่อนไขเพิ่มเติม" value="<?= $prefill ? e('อ้างอิงใบงานบริการ '.$prefill['doc_no']) : '' ?>">
      </div>
    </div>

    <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px;max-width:320px;margin-left:auto;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">ยอดรวม</span><span class="mono" id="sumSub">0.00</span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">ส่วนลด</span><span class="mono" id="sumDisc">0.00</span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;"><span class="text-muted">VAT 7%</span><span class="mono" id="sumVat">0.00</span></div>
      <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;"><span>ยอดสุทธิ</span><span class="mono text-gold" id="sumTotal">0.00</span></div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึกใบเสนอราคา</button>
  </form>
</div>

<datalist id="prodList"><?php foreach ($products as $p): ?><option value="<?= e($p['sku'].' · '.$p['name']) ?>"></option><?php endforeach; ?></datalist>
<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const PLABEL = {}; PRODUCTS.forEach(p => PLABEL[p.sku + ' · ' + p.name] = p);
function addRow(){
  const tb = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <input class="form-input" name="item_desc[]" list="prodList" placeholder="พิมพ์ หรือ เลือกสินค้า" style="padding:7px 10px;" oninput="matchProduct(this)">
      <input type="hidden" name="item_pid[]">
    </td>
    <td><input class="form-input" type="number" step="0.01" name="item_qty[]" value="1" style="padding:7px 10px;" oninput="recalc()"></td>
    <td><input class="form-input" type="number" step="0.01" name="item_price[]" value="0" style="padding:7px 10px;" oninput="recalc()"></td>
    <td class="mono lineTotal" style="font-weight:700;">0.00</td>
    <td><button type="button" class="icon-btn" onclick="this.closest('tr').remove();recalc()" style="width:30px;height:30px;"><i class="fa-solid fa-trash" style="font-size:12px;color:var(--red)"></i></button></td>`;
  tb.appendChild(tr);
}
// พิมพ์เอง หรือเลือกจาก datalist — ถ้าตรงกับสินค้า เติมราคาให้
function matchProduct(inp){
  const tr = inp.closest('tr'); const p = PLABEL[inp.value];
  tr.querySelector('[name="item_pid[]"]').value = p ? p.id : '';
  if(p){ const pr = tr.querySelector('[name="item_price[]"]'); if(!parseFloat(pr.value)) pr.value = p.sell_price; }
  recalc();
}
function recalc(){
  let sub = 0;
  document.querySelectorAll('#itemsBody tr').forEach(tr => {
    const q = parseFloat(tr.querySelector('[name="item_qty[]"]').value)||0;
    const p = parseFloat(tr.querySelector('[name="item_price[]"]').value)||0;
    const lt = q*p; sub += lt;
    tr.querySelector('.lineTotal').textContent = lt.toLocaleString('en-US',{minimumFractionDigits:2});
  });
  const disc = parseFloat(document.getElementById('discountInput').value)||0;
  const mode = (document.querySelector('[name="vat_mode"]')||{}).value || 'exclude';
  const fmt = n => n.toLocaleString('en-US',{minimumFractionDigits:2});
  const afterDisc = Math.max(0, sub - disc);
  let vat = 0, total = afterDisc, exVat = afterDisc;
  if (mode === 'exclude') { vat = afterDisc * 0.07; total = afterDisc + vat; }
  else if (mode === 'include') { exVat = afterDisc / 1.07; vat = afterDisc - exVat; total = afterDisc; }
  else { vat = 0; total = afterDisc; }  // none
  document.getElementById('sumSub').textContent = fmt(sub);
  document.getElementById('sumDisc').textContent = fmt(disc);
  document.getElementById('sumVat').textContent = fmt(vat);
  document.getElementById('sumTotal').textContent = fmt(total);
}
addRow();
<?php if ($prefill): ?>
// prefill บรรทัดแรกด้วยหัวข้อจากใบงานบริการ
(function(){ const d=document.querySelector('#itemsBody [name="item_desc[]"]'); if(d) d.value=<?= json_encode($prefill['title'], JSON_UNESCAPED_UNICODE) ?>; })();
<?php endif; ?>
</script>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ลูกค้า</th><th>ระบบ</th><th>ยอดสุทธิ</th><th>สถานะ</th><th>วันที่</th><th></th></tr></thead>
      <tbody>
        <?php if (!$quotations): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีใบเสนอราคา</td></tr>
        <?php else: foreach ($quotations as $q):
            [$slabel, $scls] = Sales::quotationStatus($q['status']); ?>
          <tr>
            <td class="mono text-gold"><?= e($q['doc_no']) ?></td>
            <td style="font-weight:600;"><?= e($q['customer_name']) ?></td>
            <td><?= $q['system_type'] ? e(str_replace('_','-',ucfirst($q['system_type']))) : '<span class="text-muted">-</span>' ?><?= $q['capacity_kwp'] ? ' <span class="text-muted">'.rtrim(rtrim($q['capacity_kwp'],'0'),'.').'kWp</span>' : '' ?></td>
            <td class="mono" style="font-weight:700;"><?= baht($q['total']) ?></td>
            <td><span class="badge <?= e($scls) ?>"><?= e($slabel) ?></span></td>
            <td class="text-muted"><?= e(thai_date_short($q['created_at'])) ?></td>
            <td style="text-align:right;">
              <a class="btn btn-ghost" style="padding:5px 10px;font-size:12px;" href="<?= e(url('quotation_view.php?id='.$q['id'])) ?>">ดู</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pager($total, $perPage, $page, url('quotations.php')) ?>
</div>

<style>.hidden{display:none;}</style>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
