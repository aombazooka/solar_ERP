<?php
/**
 * setup_usernames.php — ตั้งรหัสผ่านเริ่มต้น = username (CLI ครั้งเดียว)
 *   C:\xampp\php\php.exe tools\setup_usernames.php
 * ใช้หลังรัน db\phaseP_username.sql
 */
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require __DIR__ . '/../app/bootstrap.php';

$users = Database::all('SELECT id, username, email FROM users');
$n = 0;
foreach ($users as $u) {
    if (!$u['username']) continue;
    $hash = password_hash($u['username'], PASSWORD_DEFAULT);   // รหัสผ่าน = username
    Database::run('UPDATE users SET password_hash=:p WHERE id=:id', ['p' => $hash, 'id' => $u['id']]);
    echo "ตั้งรหัส: {$u['username']} = {$u['username']}" . PHP_EOL;
    $n++;
}
echo "เสร็จ {$n} บัญชี — ทุกบัญชี login ด้วย username เดียวกับรหัสผ่าน (เช่น admin/admin)" . PHP_EOL;
