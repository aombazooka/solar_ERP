<?php
/** login.php — หน้าเข้าสู่ระบบ */
require_once __DIR__ . '/app/bootstrap.php';

// login อยู่แล้ว → ไปแดชบอร์ด
if (Auth::check()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = input('username');
    $pass  = $_POST['password'] ?? '';

    if ($username === '' || $pass === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } elseif (Auth::attempt($username, $pass)) {
        // บันทึก audit log
        Database::run(
            'INSERT INTO audit_log (user_id, created_by, action, entity, ip_address)
             VALUES (:uid, :cb, :act, :ent, :ip)',
            ['uid' => Auth::id(), 'cb' => Auth::id(), 'act' => 'login',
             'ent' => 'users', 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]
        );
        flash('success', 'ยินดีต้อนรับเข้าสู่ระบบ SolarSell');
        redirect('index.php');
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark light">
  <script>
    (function () {
      try {
        var t = localStorage.getItem('solarsell-theme');
        if (!t) t = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
      } catch (e) {}
    })();
  </script>
  <title>เข้าสู่ระบบ · SolarSell</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
  <button class="icon-btn theme-toggle login-theme-toggle" type="button" onclick="toggleTheme(this)" title="สลับโหมดสว่าง/มืด" aria-label="สลับธีม">
    <i class="fa-solid fa-moon i-moon"></i>
    <i class="fa-solid fa-sun i-sun"></i>
  </button>
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-brand">
        <div class="brand-icon">☀️</div>
        <h1>SolarSell</h1>
        <p>ระบบจัดการร้านขายและติดตั้งโซลาร์เซลล์</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('login.php')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="username">ชื่อผู้ใช้ (Username)</label>
          <input class="form-input" type="text" id="username" name="username" autocomplete="username"
                 value="<?= e(input('username')) ?>" placeholder="เช่น admin" autofocus required>
        </div>
        <div class="form-group">
          <label class="form-label" for="password">รหัสผ่าน</label>
          <input class="form-input" type="password" id="password" name="password" autocomplete="current-password"
                 placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
          <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ
        </button>
      </form>

      <div class="login-hint">
        <strong>บัญชีทดสอบ</strong> (ชื่อผู้ใช้ = รหัสผ่าน):<br>
        ผู้ดูแล — <strong>admin</strong> / admin<br>
        ฝ่ายขาย — sales · ฝ่ายบุคคล — hr · พนักงาน — staff
      </div>
    </div>
  </div>
  <script>
    requestAnimationFrame(function () { document.body.classList.add('theme-ready'); });
    function toggleTheme(btn) {
      var isLight = document.documentElement.getAttribute('data-theme') === 'light';
      if (isLight) { document.documentElement.removeAttribute('data-theme'); }
      else { document.documentElement.setAttribute('data-theme', 'light'); }
      try { localStorage.setItem('solarsell-theme', isLight ? 'dark' : 'light'); } catch (e) {}
      if (btn) { btn.classList.remove('spin'); void btn.offsetWidth; btn.classList.add('spin'); }
    }
  </script>
</body>
</html>
