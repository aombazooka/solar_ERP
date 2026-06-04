<?php
/** profile.php — โปรไฟล์ + เปลี่ยนรหัสผ่านตัวเอง */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $current = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $row = Database::one('SELECT password_hash FROM users WHERE id=:id', ['id' => Auth::id()]);
        if (!password_verify($current, $row['password_hash'])) throw new RuntimeException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
        if (strlen($new) < 6) throw new RuntimeException('รหัสผ่านใหม่อย่างน้อย 6 ตัวอักษร');
        if ($new !== $confirm) throw new RuntimeException('รหัสผ่านใหม่และยืนยันไม่ตรงกัน');

        Database::run('UPDATE users SET password_hash=:p WHERE id=:id',
            ['p' => password_hash($new, PASSWORD_DEFAULT), 'id' => Auth::id()]);
        Database::run('INSERT INTO audit_log (user_id, created_by, action, entity, ip_address) VALUES (:u,:u,:a,:e,:ip)',
            ['u' => Auth::id(), 'a' => 'change_password', 'e' => 'users', 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อย');
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('profile.php');
}

$me = Auth::user();
$pageTitle = 'โปรไฟล์';
$activeNav = '';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header"><div><h1>โปรไฟล์ของฉัน</h1><p>ข้อมูลบัญชีและความปลอดภัย</p></div></div>

<div class="grid g2">
  <div class="card" style="align-self:start;">
    <div class="card-title" style="margin-bottom:16px;">ข้อมูลบัญชี</div>
    <div style="font-size:13px;line-height:2.2;">
      <div><span class="text-muted" style="display:inline-block;width:80px;">ชื่อ</span> <strong><?= e($me['name']) ?></strong></div>
      <div><span class="text-muted" style="display:inline-block;width:80px;">ชื่อผู้ใช้</span> <span class="mono"><?= e($me['username'] ?? '-') ?></span></div>
      <div><span class="text-muted" style="display:inline-block;width:80px;">บทบาท</span> <span class="badge badge-gold"><?= e($me['role_name']) ?></span></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:16px;"><i class="fa-solid fa-key text-gold"></i> เปลี่ยนรหัสผ่าน</div>
    <form method="post"><?= csrf_field() ?>
      <div class="form-group"><label class="form-label">รหัสผ่านปัจจุบัน</label><input class="form-input" type="password" name="current" required></div>
      <div class="form-group"><label class="form-label">รหัสผ่านใหม่ (≥6 ตัว)</label><input class="form-input" type="password" name="new" required></div>
      <div class="form-group"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input class="form-input" type="password" name="confirm" required></div>
      <button class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> บันทึกรหัสผ่านใหม่</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
