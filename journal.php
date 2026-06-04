<?php
/** journal.php — สมุดรายวันทั่วไป (บันทึกบัญชีคู่ debit=credit) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('finance.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('finance.manage');
    csrf_verify();
    try {
        $date = input('entry_date') ?: date('Y-m-d');
        $desc = input('description');
        if ($desc === '') throw new RuntimeException('กรุณากรอกคำอธิบายรายการ');

        $accs = $_POST['line_account'] ?? []; $debs = $_POST['line_debit'] ?? []; $creds = $_POST['line_credit'] ?? [];
        $lines = []; $sumD = 0.0; $sumC = 0.0;
        foreach ($accs as $i => $code) {
            $code = trim((string)$code);
            $d = (float) ($debs[$i] ?? 0); $c = (float) ($creds[$i] ?? 0);
            if ($code === '' || ($d == 0 && $c == 0)) continue;
            if ($d > 0 && $c > 0) throw new RuntimeException('แต่ละบรรทัดใส่ได้เฉพาะเดบิตหรือเครดิต');
            $lines[] = ['code'=>$code,'debit'=>$d,'credit'=>$c]; $sumD += $d; $sumC += $c;
        }
        if (count($lines) < 2) throw new RuntimeException('ต้องมีอย่างน้อย 2 บรรทัด');
        if (abs($sumD - $sumC) > 0.001) throw new RuntimeException('เดบิตรวม ('.number_format($sumD,2).') ไม่เท่ากับเครดิตรวม ('.number_format($sumC,2).')');

        $pdo = Database::pdo(); $pdo->beginTransaction();
        try {
            $no = next_doc_no('JV', 'journal_entries');
            $pdo->prepare('INSERT INTO journal_entries (doc_no, entry_date, description, total, created_by) VALUES (:no,:d,:desc,:t,:cb)')
                ->execute(['no'=>$no,'d'=>$date,'desc'=>$desc,'t'=>$sumD,'cb'=>Auth::id()]);
            $eid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO journal_lines (entry_id, account_code, debit, credit) VALUES (:e,:a,:d,:c)');
            foreach ($lines as $ln) $ins->execute(['e'=>$eid,'a'=>$ln['code'],'d'=>$ln['debit'],'c'=>$ln['credit']]);
            $pdo->commit();
            flash('success', "บันทึกรายการ {$no} เรียบร้อย");
        } catch (\Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('journal.php');
}

$entries = Database::all('SELECT je.*, u.name AS by_name FROM journal_entries je LEFT JOIN users u ON u.id=je.created_by ORDER BY je.id DESC LIMIT 30');
$linesByEntry = [];
if ($entries) {
    $ids = array_column($entries, 'id');
    $in = implode(',', array_map('intval', $ids));
    $allLines = Database::all("SELECT jl.*, coa.name AS account_name FROM journal_lines jl LEFT JOIN chart_of_accounts coa ON coa.code=jl.account_code WHERE jl.entry_id IN ($in) ORDER BY jl.id");
    foreach ($allLines as $l) $linesByEntry[$l['entry_id']][] = $l;
}
$accounts = Database::all('SELECT code, name FROM chart_of_accounts ORDER BY code');
$canManage = Auth::can('finance.manage');

$pageTitle = 'สมุดรายวัน';
$activeNav = 'journal';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>สมุดรายวันทั่วไป</h1><p>บันทึกบัญชีคู่ (เดบิต = เครดิต) สำหรับรายการปรับปรุง</p></div>
  <?php if ($canManage): ?><button class="btn btn-primary" onclick="document.getElementById('jvForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> บันทึกรายการ</button><?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="card hidden" id="jvForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">บันทึกรายการบัญชี</div></div>
  <form method="post"><?= csrf_field() ?>
    <div class="grid g4">
      <div class="form-group"><label class="form-label">วันที่</label><input class="form-input" type="date" name="entry_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group" style="grid-column:span 3;"><label class="form-label">คำอธิบาย *</label><input class="form-input" name="description" required></div>
    </div>
    <table style="margin-bottom:10px;">
      <thead><tr><th style="width:50%">บัญชี</th><th style="width:22%">เดบิต</th><th style="width:22%">เครดิต</th><th></th></tr></thead>
      <tbody id="jvBody"></tbody>
    </table>
    <button type="button" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;" onclick="addLine()"><i class="fa-solid fa-plus"></i> เพิ่มบรรทัด</button>
    <div style="display:flex;gap:24px;justify-content:flex-end;margin:14px 0;font-size:13px;">
      <span class="text-muted">เดบิตรวม: <span class="mono" id="sumD" style="color:var(--text-primary);font-weight:700;">0.00</span></span>
      <span class="text-muted">เครดิตรวม: <span class="mono" id="sumC" style="color:var(--text-primary);font-weight:700;">0.00</span></span>
      <span id="balChk"></span>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<script>
const ACCOUNTS = <?= json_encode($accounts, JSON_UNESCAPED_UNICODE) ?>;
function addLine(){
  const tb=document.getElementById('jvBody');const tr=document.createElement('tr');
  let opts='<option value="">— เลือกบัญชี —</option>';
  ACCOUNTS.forEach(a=>opts+=`<option value="${a.code}">${a.code} · ${a.name}</option>`);
  tr.innerHTML=`<td><select class="form-select" name="line_account[]" style="padding:7px 10px;">${opts}</select></td>
    <td><input class="form-input" type="number" step="0.01" name="line_debit[]" value="0" style="padding:7px 10px;" oninput="this.value>0&&(this.closest('tr').querySelector('[name=\\'line_credit[]\\']').value=0);recalc()"></td>
    <td><input class="form-input" type="number" step="0.01" name="line_credit[]" value="0" style="padding:7px 10px;" oninput="this.value>0&&(this.closest('tr').querySelector('[name=\\'line_debit[]\\']').value=0);recalc()"></td>
    <td><button type="button" class="icon-btn" style="width:30px;height:30px;" onclick="this.closest('tr').remove();recalc()"><i class="fa-solid fa-trash" style="font-size:12px;color:var(--red)"></i></button></td>`;
  tb.appendChild(tr);
}
function recalc(){let d=0,c=0;document.querySelectorAll('#jvBody tr').forEach(tr=>{d+=parseFloat(tr.querySelector('[name="line_debit[]"]').value)||0;c+=parseFloat(tr.querySelector('[name="line_credit[]"]').value)||0;});const f=n=>n.toLocaleString('en-US',{minimumFractionDigits:2});document.getElementById('sumD').textContent=f(d);document.getElementById('sumC').textContent=f(c);document.getElementById('balChk').innerHTML=Math.abs(d-c)<0.001&&d>0?'<span style="color:var(--green)">✓ สมดุล</span>':'<span style="color:var(--red)">✗ ไม่สมดุล</span>';}
addLine();addLine();
</script>
<?php endif; ?>

<div class="card">
  <?php if (!$entries): ?>
    <div class="text-muted" style="text-align:center;padding:40px;">ยังไม่มีรายการในสมุดรายวัน</div>
  <?php else: foreach ($entries as $je): ?>
    <div style="border-bottom:1px solid var(--border);padding:14px 0;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <div><span class="mono text-gold"><?= e($je['doc_no']) ?></span> <span style="font-weight:600;margin-left:8px;"><?= e($je['description']) ?></span></div>
        <div class="text-muted" style="font-size:12px;"><?= e(thai_date_short($je['entry_date'])) ?></div>
      </div>
      <table style="font-size:12px;">
        <?php foreach (($linesByEntry[$je['id']] ?? []) as $ln): ?>
          <tr>
            <td style="padding:4px 12px;"><span class="mono text-muted"><?= e($ln['account_code']) ?></span> <?= e($ln['account_name'] ?? '') ?></td>
            <td class="mono" style="padding:4px 12px;text-align:right;width:140px;"><?= (float)$ln['debit']>0?number_format((float)$ln['debit'],2):'' ?></td>
            <td class="mono" style="padding:4px 12px;text-align:right;width:140px;color:var(--text-muted)"><?= (float)$ln['credit']>0?number_format((float)$ln['credit'],2):'' ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endforeach; endif; ?>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
