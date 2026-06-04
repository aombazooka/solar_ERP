<?php
/** users.php — จัดการผู้ใช้งานระบบ (admin เท่านั้น) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');
    try {
        if ($do === 'create') {
            $name = input('name'); $username = input('username'); $roleId = (int) input('role_id'); $pass = $_POST['password'] ?? '';
            // ปรับชื่อผู้ใช้ให้เป็นรูปแบบมาตรฐาน (ตัวพิมพ์เล็ก/จุด)
            $username = strtolower(trim($username));
            $username = trim(preg_replace('/[^a-z0-9._]+/', '.', $username), '.');
            if ($name === '' || $username === '' || $roleId <= 0) throw new RuntimeException('กรอกข้อมูลไม่ครบ');
            if (Database::scalar('SELECT id FROM users WHERE username=:u', ['u' => $username])) throw new RuntimeException('ชื่อผู้ใช้นี้ถูกใช้แล้ว');
            // ถ้าไม่กรอกรหัสผ่าน → ใช้ค่าเริ่มต้น = ชื่อผู้ใช้
            if ($pass === '') $pass = $username;
            Database::run(
                'INSERT INTO users (name, username, password_hash, role_id, is_active) VALUES (:n,:u,:p,:r,1)',
                ['n' => $name, 'u' => $username, 'p' => password_hash($pass, PASSWORD_DEFAULT), 'r' => $roleId]
            );
            flash('success', "เพิ่มผู้ใช้ {$username} เรียบร้อย (รหัสผ่าน = {$pass})");
        } elseif ($do === 'toggle') {
            $uid = (int) input('id');
            if ($uid === Auth::id()) throw new RuntimeException('ปิดบัญชีตัวเองไม่ได้');
            Database::run('UPDATE users SET is_active = 1 - is_active WHERE id=:id', ['id' => $uid]);
            flash('success', 'เปลี่ยนสถานะบัญชีเรียบร้อย');
        } elseif ($do === 'reset') {
            $uid = (int) input('id'); $pass = $_POST['password'] ?? '';
            if (strlen($pass) < 6) throw new RuntimeException('รหัสผ่านอย่างน้อย 6 ตัวอักษร');
            Database::run('UPDATE users SET password_hash=:p WHERE id=:id',
                ['p' => password_hash($pass, PASSWORD_DEFAULT), 'id' => $uid]);
            flash('success', 'รีเซ็ตรหัสผ่านเรียบร้อย');
        } elseif ($do === 'changerole') {
            $uid = (int) input('id'); $roleId = (int) input('role_id');
            if ($uid === Auth::id()) throw new RuntimeException('เปลี่ยน role ตัวเองไม่ได้');
            Database::run('UPDATE users SET role_id=:r WHERE id=:id', ['r' => $roleId, 'id' => $uid]);
            flash('success', 'เปลี่ยนบทบาทเรียบร้อย');
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('users.php');
}

$users = Database::all('SELECT u.*, r.name AS role_name, e.code AS emp_code FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN employees e ON e.user_id = u.id ORDER BY u.id');
$roles = Database::all('SELECT id, name FROM roles ORDER BY id');

$pageTitle = 'ผู้ใช้งาน';
$activeNav = 'users';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>จัดการผู้ใช้งาน</h1><p>ผู้ใช้ระบบทั้งหมด <?= count($users) ?> บัญชี</p></div>
  <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')"><i class="fa-solid fa-user-plus"></i> เพิ่มผู้ใช้</button>
</div>

<div class="card hidden" id="addForm" style="margin-bottom:20px;">
  <div class="card-head"><div class="card-title">เพิ่มผู้ใช้ใหม่</div></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="create">
    <div class="grid g4">
      <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อ-สกุล *</label><input class="form-input" name="name" required></div>
      <div class="form-group"><label class="form-label">ชื่อผู้ใช้ (Username) *</label><input class="form-input" name="username" placeholder="เช่น somchai.jaidee" required></div>
      <div class="form-group"><label class="form-label">บทบาท *</label><select class="form-select" name="role_id" required>
        <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="form-group"><label class="form-label">รหัสผ่าน <span class="text-muted" style="font-weight:400;">(เว้นว่าง = เท่ากับชื่อผู้ใช้)</span></label><input class="form-input" type="text" name="password" placeholder="ค่าเริ่มต้น = ชื่อผู้ใช้"></div>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>ชื่อ</th><th>ชื่อผู้ใช้</th><th>บทบาท</th><th>สถานะ</th><th>เข้าระบบล่าสุด</th><th>จัดการ</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td style="font-weight:600;"><?= e($u['name']) ?><?php if ($u['id']==Auth::id()): ?> <span class="badge badge-blue" style="font-size:9px;">คุณ</span><?php endif; ?></td>
            <td><span class="mono badge badge-blue"><?= e($u['username'] ?? '-') ?></span><?php if (!empty($u['emp_code'])): ?> <span class="text-muted" style="font-size:11px;"><?= e($u['emp_code']) ?></span><?php endif; ?></td>
            <td>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="changerole"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <select class="form-select" name="role_id" style="padding:4px 8px;font-size:12px;width:auto;" onchange="this.form.submit()" <?= $u['id']==Auth::id()?'disabled':'' ?>>
                  <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $r['id']==$u['role_id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
                </select>
              </form>
            </td>
            <td><span class="badge <?= $u['is_active']?'badge-green':'badge-muted' ?>"><?= $u['is_active']?'ใช้งาน':'ปิด' ?></span></td>
            <td class="text-muted" style="font-size:12px;"><?= $u['last_login_at'] ? e(date('d/m/y H:i', strtotime($u['last_login_at']))) : 'ยังไม่เคย' ?></td>
            <td style="text-align:right;white-space:nowrap;">
              <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" onclick="resetPw(<?= (int)$u['id'] ?>,'<?= e($u['name']) ?>')"><i class="fa-solid fa-key"></i></button>
              <?php if ($u['id']!=Auth::id()): ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-ghost" style="padding:4px 9px;font-size:11px;" title="เปิด/ปิดบัญชี"><i class="fa-solid fa-power-off"></i></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- reset password (hidden form) -->
<form method="post" id="resetForm" style="display:none;"><?= csrf_field() ?><input type="hidden" name="do" value="reset"><input type="hidden" name="id" id="resetId"></form>
<script>
function resetPw(id, name){
  const pw = prompt('ตั้งรหัสผ่านใหม่สำหรับ "'+name+'" (อย่างน้อย 6 ตัว):');
  if(!pw) return;
  const f = document.getElementById('resetForm');
  document.getElementById('resetId').value = id;
  let inp = f.querySelector('[name=password]'); if(!inp){inp=document.createElement('input');inp.type='hidden';inp.name='password';f.appendChild(inp);}
  inp.value = pw; f.submit();
}
</script>
<style>.hidden{display:none;}</style>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
