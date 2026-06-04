<?php
/** accounts.php — ผังบัญชี (Chart of Accounts) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('finance.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('finance.manage');
    csrf_verify();
    $code = input('code'); $name = input('name'); $type = input('type');
    $types = ['asset','liability','equity','revenue','expense'];
    if ($code === '' || $name === '' || !in_array($type, $types, true)) {
        flash('error', 'กรอกข้อมูลไม่ครบ');
    } else {
        try {
            Database::run('INSERT INTO chart_of_accounts (code, name, type, parent_code) VALUES (:c,:n,:t,:p)',
                ['c' => $code, 'n' => $name, 't' => $type, 'p' => input('parent_code') ?: null]);
            flash('success', "เพิ่มบัญชี {$code} เรียบร้อย");
        } catch (\Throwable $ex) {
            flash('error', 'รหัสบัญชีซ้ำหรือผิดพลาด');
        }
    }
    redirect('accounts.php');
}

$accounts = Database::all('SELECT * FROM chart_of_accounts ORDER BY code');
$typeLabel = ['asset' => ['สินทรัพย์', 'badge-blue'], 'liability' => ['หนี้สิน', 'badge-red'],
    'equity' => ['ส่วนของเจ้าของ', 'badge-purple'], 'revenue' => ['รายได้', 'badge-green'], 'expense' => ['ค่าใช้จ่าย', 'badge-gold']];

$pageTitle = 'ผังบัญชี';
$activeNav = 'accounts';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ผังบัญชี (Chart of Accounts)</h1><p>โครงสร้างบัญชีสำหรับงานการเงิน <?= count($accounts) ?> บัญชี</p></div>
  <?php if (Auth::can('finance.manage')): ?>
    <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> เพิ่มบัญชี</button>
  <?php endif; ?>
</div>

<?php if (Auth::can('finance.manage')): ?>
<div class="card hidden" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">เพิ่มบัญชีใหม่</div></div>
  <form method="post"><?= csrf_field() ?>
    <div class="grid g4">
      <div class="form-group"><label class="form-label">เลขที่บัญชี *</label><input class="form-input" name="code" placeholder="1400"></div>
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อบัญชี *</label><input class="form-input" name="name"></div>
      <div class="form-group"><label class="form-label">ประเภท</label>
        <select class="form-select" name="type"><option value="asset">สินทรัพย์</option><option value="liability">หนี้สิน</option><option value="equity">ส่วนของเจ้าของ</option><option value="revenue">รายได้</option><option value="expense">ค่าใช้จ่าย</option></select></div>
      <div class="form-group"><label class="form-label">บัญชีแม่ (parent code)</label><input class="form-input" name="parent_code" placeholder="1000"></div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>เลขที่</th><th>ชื่อบัญชี</th><th>ประเภท</th></tr></thead>
      <tbody>
        <?php foreach ($accounts as $a):
            [$lbl,$cls] = $typeLabel[$a['type']];
            $indent = $a['parent_code'] ? 'padding-left:28px;' : 'font-weight:700;'; ?>
          <tr>
            <td class="mono text-gold"><?= e($a['code']) ?></td>
            <td style="<?= $indent ?>"><?php if ($a['parent_code']): ?><span class="text-muted">└ </span><?php endif; ?><?= e($a['name']) ?></td>
            <td><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
