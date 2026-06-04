<?php
/** notifications.php — การแจ้งเตือนของพนักงานที่ผูกกับ user นี้ */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::require();

$emp = Hr::currentEmployee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($emp && input('do') === 'read_all') {
        Database::run('UPDATE notifications SET is_read=1 WHERE employee_id=:e', ['e' => $emp['id']]);
        flash('success', 'ทำเครื่องหมายอ่านทั้งหมดแล้ว');
    }
    redirect('notifications.php');
}

$rows = $emp
    ? Database::all('SELECT * FROM notifications WHERE employee_id=:e ORDER BY id DESC LIMIT 50', ['e' => $emp['id']])
    : [];

$pageTitle = 'การแจ้งเตือน';
$activeNav = '';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>การแจ้งเตือน</h1><p><?= $emp ? 'ข้อความถึง '.e($emp['name']) : 'บัญชีนี้ไม่ได้ผูกกับพนักงาน' ?></p></div>
  <?php if ($rows): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="read_all">
      <button class="btn btn-ghost"><i class="fa-solid fa-check-double"></i> อ่านทั้งหมด</button></form>
  <?php endif; ?>
</div>

<div class="card">
  <?php if (!$emp): ?>
    <div class="text-muted" style="text-align:center;padding:40px;">บัญชีของคุณยังไม่ได้ผูกกับข้อมูลพนักงาน</div>
  <?php elseif (!$rows): ?>
    <div class="text-muted" style="text-align:center;padding:40px;"><i class="fa-solid fa-bell-slash" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4;"></i>ไม่มีการแจ้งเตือน</div>
  <?php else: foreach ($rows as $n): ?>
    <div style="display:flex;gap:14px;padding:14px 4px;border-bottom:1px solid var(--border);<?= $n['is_read']?'opacity:.55;':'' ?>">
      <div style="width:36px;height:36px;border-radius:50%;background:rgba(245,158,11,.15);color:var(--solar-gold);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa-solid fa-<?= $n['is_read']?'envelope-open':'bell' ?>"></i>
      </div>
      <div style="flex:1;">
        <div style="font-size:13px;<?= $n['is_read']?'':'font-weight:600;' ?>"><?= e($n['message']) ?></div>
        <div class="text-muted" style="font-size:11px;margin-top:3px;"><?= e(date('d/m/Y H:i', strtotime($n['created_at']))) ?></div>
      </div>
      <?php if (!$n['is_read']): ?><span class="badge badge-gold" style="align-self:center;">ใหม่</span><?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
