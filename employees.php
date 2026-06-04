<?php
/** employees.php — ทะเบียนพนักงาน (เพิ่ม/แก้ไข + ข้อมูลละเอียด) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.view');
$canPayroll = Auth::can('hr.payroll');   // สิทธิ์ดู/แก้ เงินเดือน+บัญชีธนาคาร (ข้อมูลละเอียดอ่อน)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('hr.manage');
    csrf_verify();
    $do = input('do');
    try {
        $name = input('name');
        if ($do === 'delete') {
            Database::run('DELETE FROM employees WHERE id=:id', ['id' => (int) input('id')]);
            flash('success', 'ลบพนักงานเรียบร้อย');
            redirect('employees.php');
        }
        if ($name === '') throw new RuntimeException('กรุณากรอกชื่อพนักงาน');

        $nameEn = input('name_en');
        $roleId = (int) input('role_id');

        // ฟิลด์ทั่วไป (hr.manage แก้ได้)
        $data = [
            'n'=>$name, 'en'=>$nameEn?:null, 'nid'=>input('national_id')?:null, 'bd'=>input('birth_date')?:null,
            'hd'=>input('hire_date')?:null, 'em'=>input('email')?:null, 'ph'=>input('phone')?:null,
            'pos'=>input('position')?:null, 'dep'=>input('department')?:null, 'team'=>input('team')?:null,
            'edu'=>input('education')?:null, 'addr'=>input('address')?:null,
        ];

        if ($do === 'update') {
            $id = (int) input('id');
            Database::run(
                'UPDATE employees SET name=:n, name_en=:en, national_id=:nid, birth_date=:bd, hire_date=:hd, email=:em,
                   phone=:ph, position=:pos, department=:dep, team=:team, education=:edu, address=:addr WHERE id=:id',
                $data + ['id'=>$id]
            );
            // เงินเดือน/บัญชีธนาคาร — เฉพาะผู้มีสิทธิ์ hr.payroll
            if ($canPayroll) {
                Database::run(
                    'UPDATE employees SET base_salary=:bs, commission_rate=:cr, bank_name=:bn, bank_account=:ba, bank_branch=:bb WHERE id=:id',
                    ['bs'=>(float)input('base_salary'),'cr'=>(float)input('commission_rate'),
                     'bn'=>input('bank_name')?:null,'ba'=>input('bank_account')?:null,'bb'=>input('bank_branch')?:null,'id'=>$id]
                );
            }
            $empId = $id;
            flash('success', 'แก้ไขข้อมูลพนักงานเรียบร้อย');
        } else {
            $maxId = (int) Database::scalar('SELECT COALESCE(MAX(id),0) FROM employees');
            $code  = 'EMP-' . str_pad((string)($maxId+1), 4, '0', STR_PAD_LEFT);
            Database::run(
                'INSERT INTO employees (code, name, name_en, national_id, birth_date, hire_date, email, phone, position, department, team, education, address)
                 VALUES (:c,:n,:en,:nid,:bd,:hd,:em,:ph,:pos,:dep,:team,:edu,:addr)',
                $data + ['c'=>$code]
            );
            $empId = (int) Database::lastId();
            if ($canPayroll) {
                Database::run('UPDATE employees SET base_salary=:bs, commission_rate=:cr, bank_name=:bn, bank_account=:ba, bank_branch=:bb WHERE id=:id',
                    ['bs'=>(float)input('base_salary'),'cr'=>(float)input('commission_rate'),
                     'bn'=>input('bank_name')?:null,'ba'=>input('bank_account')?:null,'bb'=>input('bank_branch')?:null,'id'=>$empId]);
            }
            flash('success', "เพิ่มพนักงาน {$code} เรียบร้อย");
        }

        // สร้างบัญชีเข้าระบบอัตโนมัติ — ถ้ามีชื่ออังกฤษ และพนักงานยังไม่มี user
        if ($nameEn !== '') {
            $hasUser = (int) Database::scalar('SELECT COALESCE(user_id,0) FROM employees WHERE id=:id', ['id'=>$empId]);
            if (!$hasUser) {
                if ($roleId <= 0) {
                    $roleId = (int) Database::scalar("SELECT id FROM roles WHERE slug='staff' LIMIT 1");
                }
                $username = Auth::generateUsername($nameEn);
                Database::run(
                    'INSERT INTO users (name, username, password_hash, role_id, is_active) VALUES (:n,:u,:p,:r,1)',
                    ['n'=>$name, 'u'=>$username, 'p'=>password_hash($username, PASSWORD_DEFAULT), 'r'=>$roleId]
                );
                $uid = (int) Database::lastId();
                Database::run('UPDATE employees SET user_id=:u WHERE id=:id', ['u'=>$uid, 'id'=>$empId]);
                flash('success', "สร้างบัญชีเข้าระบบ: ชื่อผู้ใช้ = {$username} (รหัสผ่านเริ่มต้น = {$username})");
            }
        }
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('employees.php');
}

$editId = (int) input('edit');
$ed = $editId ? Database::one('SELECT * FROM employees WHERE id=:id', ['id'=>$editId]) : null;
$employees = Database::all(
    'SELECT e.*, u.username AS login_username
     FROM employees e LEFT JOIN users u ON u.id = e.user_id ORDER BY e.id'
);
$canManage = Auth::can('hr.manage');
$roles = Database::all('SELECT id, name, slug FROM roles ORDER BY id');
// บัญชีเข้าระบบของพนักงานที่กำลังแก้ไข (ถ้ามี)
$edUser = ($ed && $ed['user_id'])
    ? Database::one('SELECT username, role_id FROM users WHERE id=:id', ['id'=>$ed['user_id']])
    : null;

try {
    $masterPositions   = Database::all('SELECT name FROM positions WHERE is_active=1 ORDER BY sort_order, name');
    $masterDepartments = Database::all('SELECT name FROM departments WHERE is_active=1 ORDER BY sort_order, name');
    $masterTeams       = Database::all('SELECT code, name FROM teams WHERE is_active=1 ORDER BY sort_order, code');
} catch (\Throwable $e) { $masterPositions = $masterDepartments = $masterTeams = []; }

$pageTitle = 'พนักงาน / ทีมช่าง';
$activeNav = 'employees';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>พนักงาน / ทีมช่าง</h1><p>ทะเบียนพนักงานทั้งหมด <?= count($employees) ?> คน</p></div>
  <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-plus"></i> เพิ่มพนักงาน</button>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="card <?= $ed ? '' : 'hidden' ?>" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title"><?= $ed ? 'แก้ไขพนักงาน '.e($ed['code']) : 'เพิ่มพนักงานใหม่' ?></div></div>
  <form method="post"><?= csrf_field() ?>
    <input type="hidden" name="do" value="<?= $ed ? 'update' : 'create' ?>">
    <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><?php endif; ?>

    <?php if ($edUser): ?>
      <div class="alert alert-info" style="margin-bottom:14px;"><i class="fa-solid fa-circle-user"></i> พนักงานนี้มีบัญชีเข้าระบบแล้ว — ชื่อผู้ใช้: <strong><?= e($edUser['username']) ?></strong> (จัดการสิทธิ์/รีเซ็ตรหัสผ่านได้ที่เมนู “ผู้ใช้งาน”)</div>
    <?php elseif (!$ed): ?>
      <div class="alert alert-info" style="margin-bottom:14px;"><i class="fa-solid fa-circle-info"></i> กรอก “ชื่อจริง (ภาษาอังกฤษ)” แล้วระบบจะสร้างบัญชีเข้าระบบให้อัตโนมัติ โดย <strong>รหัสผ่านเริ่มต้น = ชื่อผู้ใช้</strong></div>
    <?php endif; ?>
    <div style="font-size:13px;font-weight:600;color:var(--text-soft);margin-bottom:10px;"><i class="fa-solid fa-id-card text-gold"></i> ข้อมูลทั่วไป</div>
    <div class="grid g3">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อ-สกุล (ภาษาไทย) *</label><input class="form-input" name="name" value="<?= e($ed['name'] ?? '') ?>" required></div>
      <div class="form-group"><label class="form-label">เลขบัตรประชาชน</label><input class="form-input" name="national_id" value="<?= e($ed['national_id'] ?? '') ?>"></div>
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อจริง (ภาษาอังกฤษ) <span class="text-muted" style="font-weight:400;">— ใช้สร้างชื่อผู้ใช้เข้าระบบ</span></label><input class="form-input" name="name_en" value="<?= e($ed['name_en'] ?? '') ?>" placeholder="เช่น Somchai Jaidee"></div>
      <div class="form-group"><label class="form-label">สิทธิ์ผู้ใช้ (Role)</label>
        <select class="form-select" name="role_id" <?= $edUser ? 'disabled' : '' ?>>
          <?php foreach ($roles as $r): $sel = ($edUser ? (int)$edUser['role_id'] : 0) === (int)$r['id']; ?>
            <option value="<?= (int)$r['id'] ?>" <?= $sel ? 'selected' : ($r['slug']==='staff' && !$edUser ? 'selected' : '') ?>><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label class="form-label">เบอร์โทร</label><input class="form-input" name="phone" value="<?= e($ed['phone'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">อีเมล</label><input class="form-input" type="email" name="email" value="<?= e($ed['email'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">วันเกิด</label><input class="form-input" type="date" name="birth_date" value="<?= e($ed['birth_date'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">ตำแหน่ง</label>
        <input class="form-input" name="position" list="pos-list" value="<?= e($ed['position'] ?? '') ?>">
        <datalist id="pos-list"><?php foreach ($masterPositions as $p): ?><option value="<?= e($p['name']) ?>"><?php endforeach; ?></datalist></div>
      <div class="form-group"><label class="form-label">แผนก</label>
        <input class="form-input" name="department" list="dept-list" value="<?= e($ed['department'] ?? '') ?>">
        <datalist id="dept-list"><?php foreach ($masterDepartments as $d): ?><option value="<?= e($d['name']) ?>"><?php endforeach; ?></datalist></div>
      <div class="form-group"><label class="form-label">ทีม</label>
        <input class="form-input" name="team" list="team-list" value="<?= e($ed['team'] ?? '') ?>">
        <datalist id="team-list"><?php foreach ($masterTeams as $t): ?><option value="<?= e($t['code']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?></datalist></div>
      <div class="form-group"><label class="form-label">วันเริ่มงาน</label><input class="form-input" type="date" name="hire_date" value="<?= e($ed['hire_date'] ?? '') ?>"></div>
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">วุฒิการศึกษา</label><input class="form-input" name="education" value="<?= e($ed['education'] ?? '') ?>" placeholder="เช่น ปริญญาตรี วิศวกรรมไฟฟ้า"></div>
      <div class="form-group" style="grid-column:span 3;"><label class="form-label">ที่อยู่</label><input class="form-input" name="address" value="<?= e($ed['address'] ?? '') ?>"></div>
    </div>

    <?php if ($canPayroll): ?>
    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
    <div style="font-size:13px;font-weight:600;color:var(--text-soft);margin-bottom:10px;"><i class="fa-solid fa-money-check-dollar text-gold"></i> เงินเดือน & บัญชีธนาคาร <span class="text-muted" style="font-weight:400;">(เฉพาะฝ่ายบุคคล)</span></div>
    <div class="grid g3">
      <div class="form-group"><label class="form-label">เงินเดือนฐาน</label><input class="form-input" type="number" step="0.01" name="base_salary" value="<?= e($ed['base_salary'] ?? '0') ?>"></div>
      <div class="form-group"><label class="form-label">อัตราคอมมิชชั่น (%)</label><input class="form-input" type="number" step="0.01" name="commission_rate" value="<?= e($ed['commission_rate'] ?? '0') ?>"></div>
      <div class="form-group"></div>
      <div class="form-group"><label class="form-label">ธนาคาร</label><input class="form-input" name="bank_name" value="<?= e($ed['bank_name'] ?? '') ?>" placeholder="เช่น กสิกรไทย"></div>
      <div class="form-group"><label class="form-label">เลขที่บัญชี</label><input class="form-input" name="bank_account" value="<?= e($ed['bank_account'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">สาขา</label><input class="form-input" name="bank_branch" value="<?= e($ed['bank_branch'] ?? '') ?>"></div>
    </div>
    <?php endif; ?>

    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
    <?php if ($ed): ?><a class="btn btn-ghost" href="<?= e(url('employees.php')) ?>">ยกเลิก</a><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>รหัส</th><th>ชื่อ</th><th>ชื่อผู้ใช้</th><th>ตำแหน่ง</th><th>แผนก</th><th>ทีม</th><th>เบอร์โทร</th><?php if ($canPayroll): ?><th>เงินเดือน</th><?php endif; ?><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td class="mono text-gold"><?= e($emp['code']) ?></td>
            <td style="font-weight:600;"><?= e($emp['name']) ?><?php if (!empty($emp['name_en'])): ?><div style="font-size:11px;color:var(--text-muted)"><?= e($emp['name_en']) ?></div><?php endif; ?></td>
            <td><?= !empty($emp['login_username']) ? '<span class="mono badge badge-blue">'.e($emp['login_username']).'</span>' : '<span class="text-muted">-</span>' ?></td>
            <td><?= e($emp['position'] ?? '-') ?></td>
            <td><?= e($emp['department'] ?? '-') ?></td>
            <td><?= $emp['team'] ? '<span class="badge badge-blue">ทีม '.e($emp['team']).'</span>' : '-' ?></td>
            <td class="text-muted"><?= e($emp['phone'] ?? '-') ?></td>
            <?php if ($canPayroll): ?><td class="mono"><?= number_format((float)($emp['base_salary'] ?? 0)) ?></td><?php endif; ?>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <a class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" href="<?= e(url('employees.php?edit='.$emp['id'])) ?>"><i class="fa-solid fa-pen"></i></a>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบพนักงาน <?= e($emp['code']) ?>?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
