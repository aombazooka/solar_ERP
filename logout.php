<?php
/** logout.php — ออกจากระบบ */
require_once __DIR__ . '/app/bootstrap.php';

Auth::logout();
flash('info', 'ออกจากระบบเรียบร้อยแล้ว');
redirect('login.php');
