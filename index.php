<?php
/** index.php — แดชบอร์ดส่วนบุคคล (ของตัวเองเท่านั้น, ทุกสิทธิ์)
 *  ตอกบัตรเข้า-ออกงาน: บังคับถ่ายรูปสด + GPS หาไซต์ใกล้สุด */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('dashboard.view');
$me  = Auth::user();
$emp = Hr::currentEmployee();

// ─── ตอกบัตร (เฉพาะของตัวเอง) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = input('do');
    try {
        if (!$emp) throw new RuntimeException('บัญชีนี้ยังไม่ได้ผูกกับข้อมูลพนักงาน — ติดต่อ HR');
        if (in_array($do, ['checkin', 'checkout'], true)) {
            $lat = input('lat'); $lon = input('lon');
            if ($lat === '' || $lon === '') throw new RuntimeException('ต้องอนุญาตการเข้าถึง GPS ก่อน');
            $photo = save_uploaded_image('photo');
            if (!$photo) throw new RuntimeException('ต้องถ่ายรูปสดก่อนตอกบัตร');
            $dir = $do === 'checkin' ? 'in' : 'out';
            $res = Hr::quickClock($emp['id'], $dir, (float)$lat, (float)$lon, input('accuracy') !== '' ? (float)input('accuracy') : null, $photo);
            $place = $res['site_name'] ? " · {$res['site_name']} (" . number_format($res['distance']) . "ม.)" : '';
            $warn  = ($res['status'] && $res['status'] !== 'approved') ? ' ⚠ อยู่นอกพื้นที่ที่กำหนด' : '';
            flash($dir === 'in' ? 'success' : 'info',
                ($dir === 'in' ? 'เช็คอินเข้างาน' : 'เช็คเอาท์ออกงาน') . " เวลา {$res['time']}{$place}{$warn}");
        }
        // ─── ยื่นใบลา (ของตัวเอง) ───
        elseif ($do === 'leave') {
            $from = input('date_from'); $to = input('date_to');
            $days = ($from && $to) ? (strtotime($to) - strtotime($from)) / 86400 + 1 : 1;
            Hr::requestLeave($emp['id'], input('leave_type'), $from, $to, max(0.5, $days), input('reason'));
            flash('success', 'ยื่นใบลาเรียบร้อย — รอการอนุมัติ');
        }
        // ─── ขอ OT (ของตัวเอง) ───
        elseif ($do === 'ot') {
            Hr::requestOt($emp['id'], input('ot_date'), (float) input('hours'), input('reason'));
            flash('success', 'ยื่นคำขอ OT เรียบร้อย — รอการอนุมัติ');
        }
        // ─── ขอเบิกเงินล่วงหน้า (ของตัวเอง) ───
        elseif ($do === 'advance') {
            Hr::requestAdvance($emp['id'], (float) input('amount'), input('reason'));
            flash('success', 'ยื่นคำขอเบิกเงินล่วงหน้าเรียบร้อย — รอการอนุมัติ');
        }
        // ─── แก้ไขใบลา (ของตัวเอง, รออนุมัติ) ───
        elseif ($do === 'update_leave') {
            $from = input('date_from'); $to = input('date_to');
            $days = ($from && $to) ? (strtotime($to) - strtotime($from)) / 86400 + 1 : 1;
            Hr::updateLeave((int) input('id'), input('leave_type'), $from, $to, max(0.5, $days), input('reason'));
            flash('success', 'แก้ไขใบลาเรียบร้อย');
        }
        // ─── แก้ไข OT (ของตัวเอง, รออนุมัติ) ───
        elseif ($do === 'update_ot') {
            Hr::updateOt((int) input('id'), input('ot_date'), (float) input('hours'), input('reason'));
            flash('success', 'แก้ไขคำขอ OT เรียบร้อย');
        }
        // ─── ยกเลิกคำขอ (ของตัวเอง, รออนุมัติ) ───
        elseif ($do === 'cancel_leave') { Hr::cancelLeave((int) input('id')); flash('success', 'ยกเลิกใบลาเรียบร้อย'); }
        elseif ($do === 'cancel_ot')    { Hr::cancelOt((int) input('id'));    flash('success', 'ยกเลิกคำขอ OT เรียบร้อย'); }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('index.php' . (input('m') ? '?m=' . urlencode(input('m')) : ''));
}

$clock = $emp ? Hr::todayClock($emp['id']) : ['state' => 'none', 'in' => null, 'out' => null];

// ─── เดือนปฏิทิน (?m=YYYY-MM ค.ศ.) ───
$m = input('m');
$base = ($m && preg_match('/^\d{4}-\d{2}$/', $m)) ? strtotime($m . '-01') : strtotime(date('Y-m-01'));
$year = (int) date('Y', $base); $month = (int) date('n', $base);
$monthStart = date('Y-m-01', $base); $monthEnd = date('Y-m-t', $base);
$daysInMonth = (int) date('t', $base); $firstDow = (int) date('w', $base);
$prevM = date('Y-m', strtotime('-1 month', $base));
$nextM = date('Y-m', strtotime('+1 month', $base));
$thaiMonths = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$dows = ['อา','จ','อ','พ','พฤ','ศ','ส'];
$today = date('Y-m-d');

$attMap = []; $leaveDay = [];
if ($emp) {
    foreach (Database::all('SELECT work_date, check_in FROM attendance WHERE employee_id=:e AND work_date BETWEEN :a AND :b',
        ['e'=>$emp['id'],'a'=>$monthStart,'b'=>$monthEnd]) as $r) $attMap[$r['work_date']] = $r['check_in'];
    $lvLabel = ['sick'=>'ลาป่วย','personal'=>'ลากิจ','vacation'=>'พักร้อน','other'=>'ลาอื่นๆ'];
    foreach (Database::all("SELECT leave_type, date_from, date_to, status FROM leave_requests
        WHERE employee_id=:e AND status IN ('approved','pending') AND date_from<=:b AND date_to>=:a",
        ['e'=>$emp['id'],'a'=>$monthStart,'b'=>$monthEnd]) as $lv) {
        $d = max(strtotime($lv['date_from']), strtotime($monthStart));
        $end = min(strtotime($lv['date_to']), strtotime($monthEnd));
        for (; $d <= $end; $d += 86400) $leaveDay[date('Y-m-d', $d)] = ['label'=>$lvLabel[$lv['leave_type']] ?? 'ลา','status'=>$lv['status']];
    }
}
$presentDays = count($attMap); $leaveDaysThisMonth = count($leaveDay);
$balance = $emp ? Hr::leaveBalance($emp['id'], (int)date('Y')) : [];
$initial = mb_substr($me['name'] ?? '?', 0, 1, 'UTF-8');

// ─── ข้อมูลสำหรับ self-service (ลา/OT) ───
$leaveTypes = Hr::leaveTypes();
$ltLabel = array_column($leaveTypes, 'name', 'code');
$reqStatus = ['pending'=>['รออนุมัติ','badge-gold'],'approved'=>['อนุมัติ','badge-green'],'rejected'=>['ปฏิเสธ','badge-red'],'cancelled'=>['ยกเลิก','badge-muted'],'deducted'=>['หักแล้ว','badge-green']];
$advLimit = $emp ? Hr::advanceLimit($emp['id']) : ['available'=>0,'worked'=>0,'daily'=>0,'outstanding'=>0];
$myReq = [];
if ($emp) {
    foreach (Database::all("SELECT id, leave_type, date_from, date_to, days, reason, status, created_at FROM leave_requests WHERE employee_id=:e ORDER BY id DESC LIMIT 8", ['e'=>$emp['id']]) as $r)
        $myReq[] = ['kind'=>'leave','id'=>(int)$r['id'],'ic'=>'fa-plane-departure','label'=>($ltLabel[$r['leave_type']] ?? 'ลา'),
            'detail'=>thai_date_short($r['date_from']).' · '.rtrim(rtrim($r['days'],'0'),'.').' วัน','status'=>$r['status'],'ts'=>$r['created_at'],
            'edit'=>['leave_type'=>$r['leave_type'],'date_from'=>$r['date_from'],'date_to'=>$r['date_to'],'reason'=>$r['reason'] ?? '']];
    try {
        foreach (Database::all("SELECT id, ot_date, hours, reason, status, created_at FROM ot_requests WHERE employee_id=:e ORDER BY id DESC LIMIT 8", ['e'=>$emp['id']]) as $r)
            $myReq[] = ['kind'=>'ot','id'=>(int)$r['id'],'ic'=>'fa-business-time','label'=>'OT',
                'detail'=>thai_date_short($r['ot_date']).' · '.rtrim(rtrim($r['hours'],'0'),'.').' ชม.','status'=>$r['status'],'ts'=>$r['created_at'],
                'edit'=>['ot_date'=>$r['ot_date'],'hours'=>rtrim(rtrim($r['hours'],'0'),'.'),'reason'=>$r['reason'] ?? '']];
        foreach (Database::all("SELECT id, amount, reason, status, created_at FROM salary_advances WHERE employee_id=:e ORDER BY id DESC LIMIT 8", ['e'=>$emp['id']]) as $r)
            $myReq[] = ['kind'=>'advance','id'=>(int)$r['id'],'ic'=>'fa-hand-holding-dollar','label'=>'เบิกล่วงหน้า',
                'detail'=>baht($r['amount']).($r['reason']?' · '.$r['reason']:''),'status'=>$r['status'],'ts'=>$r['created_at'],'edit'=>null];
    } catch (\Throwable $e) {}
    usort($myReq, fn($a,$b)=>strcmp($b['ts'],$a['ts']));
    $myReq = array_slice($myReq, 0, 6);
}

$pageTitle = 'หน้าหลัก';
$activeNav = 'index';
require __DIR__ . '/app/layout_header.php';
?>

<!-- ═══ HERO: greeting + live clock + ตอกบัตร ═══ -->
<div class="hero">
  <div class="hero-greet">
    <div class="hero-avatar"><?= e($initial) ?></div>
    <div>
      <div class="hero-hello">สวัสดี, <?= e($me['name']) ?> 👋</div>
      <div class="hero-sub"><?= e(thai_date()) ?>
        <?php if ($emp): ?><span class="chip"><?= e($emp['code']) ?></span><?php if ($emp['position']): ?><span class="chip"><?= e($emp['position']) ?></span><?php endif; ?>
        <?php else: ?><span class="chip">บัญชียังไม่ผูกพนักงาน</span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="hero-clock">
    <div class="clock-time" id="liveClock">--:--<span class="sec">:--</span></div>
    <?php if ($emp): ?>
      <div class="clock-state">
        <?php if ($clock['state']==='none'): ?>
          <span class="state-pill none"><i class="fa-solid fa-circle"></i> ยังไม่เช็คอินวันนี้</span>
          <button class="btn btn-primary clock-action-btn" data-do="checkin"><i class="fa-solid fa-right-to-bracket"></i> เช็คอินเข้างาน</button>
        <?php elseif ($clock['state']==='in'): ?>
          <span class="state-pill in"><i class="fa-solid fa-circle-check"></i> เข้างาน <?= e(substr($clock['in'],0,5)) ?> น.</span>
          <button class="btn clock-action-btn checkout" data-do="checkout"><i class="fa-solid fa-right-from-bracket"></i> เช็คเอาท์ออกงาน</button>
        <?php else: ?>
          <span class="state-pill out"><i class="fa-solid fa-flag-checkered"></i> เข้างาน <?= e(substr($clock['in'],0,5)) ?> · ออกงาน <?= e(substr($clock['out'],0,5)) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($clock['state'] !== 'out'): ?><div class="gps-line" id="gpsLine"><i class="fa-solid fa-location-crosshairs"></i> กำลังเตรียม GPS...</div><?php endif; ?>
    <?php else: ?>
      <span class="state-pill none">— ไม่มีข้อมูลพนักงาน —</span>
    <?php endif; ?>
  </div>
</div>

<div class="grid g21">
  <!-- ปฏิทิน -->
  <div class="card">
    <div class="cal-head">
      <div>
        <div class="cal-title"><?= e($thaiMonths[$month]) ?> <?= $year + 543 ?></div>
        <div class="card-sub"><?php if ($emp): ?>เข้างาน <?= $presentDays ?> วัน · ลา <?= $leaveDaysThisMonth ?> วัน<?php else: ?>ปฏิทินส่วนบุคคล<?php endif; ?></div>
      </div>
      <div class="cal-nav">
        <a class="icon-btn" href="<?= e(url('index.php?m='.$prevM)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
        <a class="btn btn-ghost" style="padding:7px 12px;font-size:12px;" href="<?= e(url('index.php')) ?>">วันนี้</a>
        <a class="icon-btn" href="<?= e(url('index.php?m='.$nextM)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
      </div>
    </div>
    <div class="cal-grid">
      <?php foreach ($dows as $i => $dw): ?><div class="cal-dow" style="<?= ($i===0||$i===6)?'color:var(--solar-orange)':'' ?>"><?= e($dw) ?></div><?php endforeach; ?>
      <?php for ($i=0; $i<$firstDow; $i++): ?><div class="cal-cell empty"></div><?php endfor; ?>
      <?php for ($day=1; $day<=$daysInMonth; $day++):
          $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
          $dow = (int) date('w', strtotime($date));
          $cls = 'cal-cell'.(($dow===0||$dow===6)?' weekend':'').($date===$today?' today':''); ?>
        <div class="<?= $cls ?>">
          <div class="cal-daynum"><?= $day ?></div>
          <?php if (isset($attMap[$date])): ?><span class="cal-tag present"><i class="fa-solid fa-check"></i> <?= $attMap[$date]?e(substr($attMap[$date],0,5)):'มา' ?></span><?php endif; ?>
          <?php if (isset($leaveDay[$date])): ?><span class="cal-tag <?= $leaveDay[$date]['status']==='approved'?'leave':'leave-pending' ?>"><?= e($leaveDay[$date]['label']) ?><?= $leaveDay[$date]['status']==='pending'?'?':'' ?></span><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
    <div class="cal-legend">
      <span><i style="background:var(--green)"></i> เข้างาน</span>
      <span><i style="background:var(--solar-gold)"></i> ลา (อนุมัติ)</span>
      <span><i style="background:var(--purple)"></i> ลา (รออนุมัติ)</span>
      <span><i style="background:transparent;box-shadow:0 0 0 1px var(--solar-gold)"></i> วันนี้</span>
    </div>
  </div>

  <!-- side -->
  <div style="display:flex;flex-direction:column;gap:20px;">
    <!-- สิทธิ์ลา (วงแหวน) -->
    <div class="card">
      <div class="card-head"><div class="card-title">สิทธิ์วันลาคงเหลือ</div><div class="card-sub">ปี <?= (int)date('Y')+543 ?></div></div>
      <?php if (!$emp): ?>
        <div class="text-muted" style="font-size:13px;text-align:center;padding:14px;">— ไม่มีข้อมูล —</div>
      <?php else:
        $ringColors = ['sick'=>'#3b82f6','personal'=>'#8b5cf6','vacation'=>'#10b981'];
        $palette = ['#f59e0b','#3b82f6','#8b5cf6','#10b981','#ec4899'];
        // แสดงวงแหวนเฉพาะชนิดที่มีโควต้า (quota > 0)
        $rings = array_filter($balance, fn($b) => (int)$b['quota'] > 0 && $b['remaining'] !== null);
        $C = 2 * M_PI * 26; $pi = 0; ?>
        <?php if (!$rings): ?>
          <div class="text-muted" style="font-size:13px;text-align:center;padding:10px;">ไม่มีชนิดลาที่กำหนดโควต้า</div>
        <?php else: ?>
        <div class="ring-row">
          <?php foreach ($rings as $type => $b):
            $frac = max(0, min(1, $b['remaining'] / $b['quota']));
            $color = $ringColors[$type] ?? $palette[$pi++ % count($palette)]; ?>
            <div class="ring">
              <svg width="72" height="72" viewBox="0 0 72 72">
                <circle cx="36" cy="36" r="26" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="7"/>
                <circle cx="36" cy="36" r="26" fill="none" stroke="<?= $color ?>" stroke-width="7" stroke-linecap="round"
                  stroke-dasharray="<?= round($C*$frac,1) ?> <?= round($C*(1-$frac),1) ?>"/>
              </svg>
              <div class="ring-center"><div class="n" style="color:<?= $color ?>"><?= rtrim(rtrim(number_format((float)$b['remaining'],1),'0'),'.') ?></div><div class="u">/<?= (int)$b['quota'] ?></div></div>
              <div class="ring-label"><?= e($b['label']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:16px;">
          <button type="button" class="btn btn-ghost" style="font-size:12px;justify-content:center;" onclick="openNew('leave')"><i class="fa-solid fa-plane-departure"></i> ยื่นใบลา</button>
          <button type="button" class="btn btn-ghost" style="font-size:12px;justify-content:center;" onclick="openNew('ot')"><i class="fa-solid fa-business-time"></i> ขอ OT</button>
          <button type="button" class="btn btn-ghost" style="font-size:12px;justify-content:center;" onclick="openSheet('advanceModal')"><i class="fa-solid fa-hand-holding-dollar"></i> เบิกล่วงหน้า</button>
          <a class="btn btn-ghost" style="font-size:12px;justify-content:center;" href="<?= e(url('payslip.php')) ?>"><i class="fa-solid fa-receipt"></i> สลิปเงินเดือน</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- คำขอล่าสุดของฉัน -->
    <?php if ($emp): ?>
    <div class="card">
      <div class="card-head"><div class="card-title">คำขอล่าสุดของฉัน</div></div>
      <?php if (!$myReq): ?>
        <div class="text-muted" style="font-size:13px;text-align:center;padding:14px;">ยังไม่มีคำขอลา/OT</div>
      <?php else: foreach ($myReq as $r): [$sl,$sc]=$reqStatus[$r['status']]; ?>
        <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);">
          <i class="fa-solid <?= e($r['ic']) ?>" style="color:var(--solar-gold);width:16px;text-align:center;"></i>
          <div style="flex:1;min-width:0;"><div style="font-size:13px;font-weight:600;"><?= e($r['label']) ?></div><div class="text-muted" style="font-size:11px;"><?= e($r['detail']) ?></div></div>
          <span class="badge <?= e($sc) ?>"><?= e($sl) ?></span>
          <?php if ($r['status']==='pending' && $r['edit'] !== null): ?>
            <div style="display:flex;gap:3px;">
              <button type="button" class="icon-btn" style="width:28px;height:28px;font-size:11px;" title="แก้ไข"
                onclick='editReq(<?= htmlspecialchars(json_encode(["kind"=>$r["kind"],"id"=>$r["id"],"edit"=>$r["edit"]], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)'><i class="fa-solid fa-pen"></i></button>
              <form method="post" style="display:inline;" onsubmit="return confirm('ยกเลิกคำขอนี้?')">
                <?= csrf_field() ?><input type="hidden" name="do" value="cancel_<?= e($r['kind']) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="m" value="<?= e($m ?? '') ?>">
                <button class="icon-btn" style="width:28px;height:28px;font-size:11px;color:var(--red);" title="ยกเลิก"><i class="fa-solid fa-xmark"></i></button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>

    <!-- แจ้งเตือน -->
    <div class="card">
      <div class="card-head"><div class="card-title">แจ้งเตือนล่าสุด</div><a href="<?= e(url('notifications.php')) ?>" class="text-muted" style="font-size:12px;">ดูทั้งหมด</a></div>
      <?php $notifs = $emp ? Database::all('SELECT message, is_read, created_at FROM notifications WHERE employee_id=:e ORDER BY id DESC LIMIT 5', ['e'=>$emp['id']]) : [];
      if (!$notifs): ?>
        <div class="text-muted" style="font-size:13px;text-align:center;padding:14px;"><i class="fa-solid fa-bell-slash" style="opacity:.4;"></i> ไม่มีการแจ้งเตือน</div>
      <?php else: foreach ($notifs as $n): ?>
        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);<?= $n['is_read']?'opacity:.55;':'' ?>">
          <i class="fa-solid fa-<?= $n['is_read']?'envelope-open':'bell' ?>" style="color:var(--solar-gold);font-size:13px;margin-top:2px;"></i>
          <div style="flex:1;"><div style="font-size:12px;<?= $n['is_read']?'':'font-weight:600;' ?>"><?= e($n['message']) ?></div>
            <div class="text-muted" style="font-size:10px;margin-top:2px;"><?= e(date('d/m H:i', strtotime($n['created_at']))) ?></div></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php if ($emp && $clock['state'] !== 'out'): ?>
<!-- ═══ CAMERA MODAL (บังคับถ่ายรูปสด) ═══ -->
<div class="modal-overlay" id="camModal">
  <div class="modal-cam">
    <h3 id="camTitle">ถ่ายรูปยืนยันการตอกบัตร</h3>
    <div class="ms">ถ่ายภาพสด ณ จุดที่อยู่ปัจจุบัน เพื่อเป็นหลักฐาน (PDPA)</div>
    <div class="cam-stage">
      <video id="camVideo" autoplay playsinline muted></video>
      <img id="camPreview" style="display:none;">
      <div class="cam-fallback" id="camFallback" style="display:none;">
        <i class="fa-solid fa-camera" style="font-size:34px;display:block;margin-bottom:12px;opacity:.5;"></i>
        เปิดกล้องสดไม่ได้ — แตะเพื่อถ่ายด้วยกล้องอุปกรณ์
        <div style="margin-top:14px;"><input type="file" id="camFileInput" accept="image/*" capture="environment" class="form-input"></div>
      </div>
      <div class="cam-scan"></div>
      <div class="cam-gps" id="camGps"><i class="fa-solid fa-location-crosshairs"></i> GPS...</div>
    </div>
    <div class="cam-actions">
      <button class="btn btn-ghost" id="camCancel">ยกเลิก</button>
      <button class="btn btn-ghost" id="camRetake" style="display:none;">ถ่ายใหม่</button>
      <button class="btn btn-primary" id="camShoot"><i class="fa-solid fa-camera"></i> ถ่ายรูป</button>
      <button class="btn btn-primary" id="camConfirm" style="display:none;"><i class="fa-solid fa-check"></i> ยืนยันตอกบัตร</button>
    </div>
  </div>
</div>

<form method="post" id="clockForm" enctype="multipart/form-data" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="do" id="clockDo">
  <input type="hidden" name="lat" id="clockLat"><input type="hidden" name="lon" id="clockLon"><input type="hidden" name="accuracy" id="clockAcc">
  <input type="hidden" name="m" value="<?= e($m ?? '') ?>">
</form>

<script>
(function(){
  // นาฬิกาเดินจริง
  const clk = document.getElementById('liveClock');
  function tick(){ const d=new Date(); const p=n=>String(n).padStart(2,'0');
    clk.innerHTML = p(d.getHours())+':'+p(d.getMinutes())+'<span class="sec">:'+p(d.getSeconds())+'</span>'; }
  tick(); setInterval(tick, 1000);

  // GPS
  const gps = {lat:null, lon:null, acc:null, ok:false};
  const gpsLine = document.getElementById('gpsLine');
  const camGps = document.getElementById('camGps');
  function reqGPS(){
    if (!navigator.geolocation){ if(gpsLine) gpsLine.innerHTML='เบราว์เซอร์ไม่รองรับ GPS'; return; }
    navigator.geolocation.getCurrentPosition(pos=>{
      gps.lat=pos.coords.latitude; gps.lon=pos.coords.longitude; gps.acc=pos.coords.accuracy; gps.ok=true;
      const msg='<i class="fa-solid fa-circle-check" style="color:var(--green)"></i> พร้อม (±'+Math.round(gps.acc)+' ม.)';
      if(gpsLine) gpsLine.innerHTML=msg; if(camGps) camGps.innerHTML='<i class="fa-solid fa-location-dot"></i> ±'+Math.round(gps.acc)+' ม.';
    }, e=>{ const m='<i class="fa-solid fa-triangle-exclamation" style="color:var(--red)"></i> GPS: '+e.message; if(gpsLine) gpsLine.innerHTML=m; if(camGps) camGps.textContent='GPS ไม่พร้อม'; },
    {enableHighAccuracy:true, timeout:10000, maximumAge:0});
  }
  reqGPS();

  // กล้อง + modal
  const modal=document.getElementById('camModal'), video=document.getElementById('camVideo'),
        preview=document.getElementById('camPreview'), fallback=document.getElementById('camFallback'),
        fileInput=document.getElementById('camFileInput'),
        shoot=document.getElementById('camShoot'), confirm=document.getElementById('camConfirm'),
        retake=document.getElementById('camRetake'), cancel=document.getElementById('camCancel'),
        title=document.getElementById('camTitle');
  let stream=null, photoBlob=null, pendingDir=null;

  async function openModal(dir){
    pendingDir=dir; photoBlob=null;
    title.textContent = (dir==='checkin'?'ถ่ายรูปยืนยัน เช็คอินเข้างาน':'ถ่ายรูปยืนยัน เช็คเอาท์ออกงาน');
    modal.classList.add('show');
    preview.style.display='none'; confirm.style.display='none'; retake.style.display='none';
    shoot.style.display=''; video.style.display=''; fallback.style.display='none';
    try {
      stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}, audio:false});
      video.srcObject = stream;
    } catch(err){
      video.style.display='none'; shoot.style.display='none'; fallback.style.display='block';
    }
  }
  function stopCam(){ if(stream){ stream.getTracks().forEach(t=>t.stop()); stream=null; } }
  function closeModal(){ stopCam(); modal.classList.remove('show'); }

  shoot.addEventListener('click', ()=>{
    const cv=document.createElement('canvas'); cv.width=video.videoWidth||720; cv.height=video.videoHeight||960;
    cv.getContext('2d').drawImage(video,0,0,cv.width,cv.height);
    cv.toBlob(b=>{ photoBlob=b; preview.src=URL.createObjectURL(b); preview.style.display='block'; video.style.display='none';
      shoot.style.display='none'; confirm.style.display=''; retake.style.display=''; stopCam(); }, 'image/jpeg', 0.85);
  });
  if (fileInput) fileInput.addEventListener('change', e=>{
    const f=e.target.files[0]; if(!f) return; photoBlob=f; preview.src=URL.createObjectURL(f);
    preview.style.display='block'; fallback.style.display='none'; shoot.style.display='none'; confirm.style.display=''; retake.style.display='';
  });
  retake.addEventListener('click', ()=>openModal(pendingDir));
  cancel.addEventListener('click', closeModal);

  confirm.addEventListener('click', async ()=>{
    if (!gps.ok){ alert('ยังไม่ได้ตำแหน่ง GPS — กรุณารอสักครู่แล้วลองใหม่'); return; }
    if (!photoBlob){ alert('กรุณาถ่ายรูปก่อน'); return; }
    confirm.disabled=true; confirm.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';
    const fd=new FormData(document.getElementById('clockForm'));
    fd.set('do', pendingDir); fd.set('lat', gps.lat); fd.set('lon', gps.lon); fd.set('accuracy', gps.acc);
    fd.append('photo', photoBlob, 'clock.jpg');
    try {
      await fetch('<?= e(url('index.php')) ?>', {method:'POST', body:fd, credentials:'same-origin'});
      window.location.href = '<?= e(url('index.php')) ?>';
    } catch(err){ confirm.disabled=false; confirm.innerHTML='<i class="fa-solid fa-check"></i> ยืนยันตอกบัตร'; alert('บันทึกไม่สำเร็จ ลองใหม่'); }
  });

  document.querySelectorAll('.clock-action-btn').forEach(b=>b.addEventListener('click', ()=>openModal(b.dataset.do)));
})();
</script>
<?php endif; ?>

<?php if ($emp): ?>
<!-- ═══ MODAL: ยื่นใบลา (ของตัวเอง) ═══ -->
<div class="modal-overlay" id="leaveModal">
  <div class="modal-cam" style="width:min(460px,94vw);">
    <h3 id="leaveTitle">ยื่นใบลา</h3>
    <div class="ms">คำขอลาของคุณ — รออนุมัติจากหัวหน้า/HR</div>
    <form method="post" id="leaveForm">
      <?= csrf_field() ?><input type="hidden" name="do" value="leave"><input type="hidden" name="m" value="<?= e($m ?? '') ?>">
      <div class="form-group"><label class="form-label">ประเภทการลา</label>
        <select class="form-select" name="leave_type">
          <?php foreach ($leaveTypes as $lt): ?><option value="<?= e($lt['code']) ?>"><?= e($lt['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="grid g2">
        <div class="form-group"><label class="form-label">ตั้งแต่วันที่</label><input class="form-input" type="date" name="date_from" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label class="form-label">ถึงวันที่</label><input class="form-input" type="date" name="date_to" value="<?= date('Y-m-d') ?>" required></div>
      </div>
      <div class="form-group"><label class="form-label">เหตุผล</label><input class="form-input" name="reason" placeholder="เช่น ไปธุระส่วนตัว"></div>
      <div class="cam-actions">
        <button type="button" class="btn btn-ghost" onclick="closeSheet('leaveModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> ยื่นใบลา</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: ขอ OT (ของตัวเอง) ═══ -->
<div class="modal-overlay" id="otModal">
  <div class="modal-cam" style="width:min(460px,94vw);">
    <h3 id="otTitle">ขอทำงานล่วงเวลา (OT)</h3>
    <div class="ms">คำขอ OT ของคุณ — รออนุมัติ · จ่าย 1.5× ของค่าแรงต่อชั่วโมง</div>
    <form method="post" id="otForm">
      <?= csrf_field() ?><input type="hidden" name="do" value="ot"><input type="hidden" name="m" value="<?= e($m ?? '') ?>">
      <div class="grid g2">
        <div class="form-group"><label class="form-label">วันที่</label><input class="form-input" type="date" name="ot_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label class="form-label">จำนวนชั่วโมง</label><input class="form-input" type="number" step="0.5" min="0.5" max="24" name="hours" placeholder="เช่น 2" required></div>
      </div>
      <div class="form-group"><label class="form-label">เหตุผล</label><input class="form-input" name="reason" placeholder="เช่น ติดตั้งงานเร่งด่วน"></div>
      <div class="cam-actions">
        <button type="button" class="btn btn-ghost" onclick="closeSheet('otModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-business-time"></i> ยื่นขอ OT</button>
      </div>
    </form>
  </div>
</div>
<!-- ═══ MODAL: เบิกเงินล่วงหน้า (ของตัวเอง) ═══ -->
<div class="modal-overlay" id="advanceModal">
  <div class="modal-cam" style="width:min(460px,94vw);">
    <h3>ขอเบิกเงินล่วงหน้า</h3>
    <div class="ms">หักจากเงินเดือนงวดถัดไป · รออนุมัติจาก HR</div>
    <div class="alert alert-info" style="font-size:12px;">
      <i class="fa-solid fa-circle-info"></i> เบิกได้สูงสุด <strong><?= number_format($advLimit['available'],2) ?></strong> บาท
      <div style="margin-top:3px;opacity:.85;">ทำงานมาแล้ว <?= (int)$advLimit['worked'] ?> วัน × <?= number_format($advLimit['daily'],2) ?> (ฐาน/30)<?= $advLimit['outstanding']>0 ? ' − เบิกค้าง '.number_format($advLimit['outstanding'],2) : '' ?></div>
    </div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="do" value="advance"><input type="hidden" name="m" value="<?= e($m ?? '') ?>">
      <div class="form-group"><label class="form-label">จำนวนเงิน (สูงสุด <?= number_format($advLimit['available'],2) ?>)</label>
        <input class="form-input" type="number" step="0.01" min="1" max="<?= number_format($advLimit['available'],2,'.','') ?>" name="amount" required <?= $advLimit['available']<=0?'disabled':'' ?>></div>
      <div class="form-group"><label class="form-label">เหตุผล</label><input class="form-input" name="reason" placeholder="เช่น ค่ารักษาพยาบาล"></div>
      <div class="cam-actions">
        <button type="button" class="btn btn-ghost" onclick="closeSheet('advanceModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary" <?= $advLimit['available']<=0?'disabled':'' ?>><i class="fa-solid fa-hand-holding-dollar"></i> ยื่นขอเบิก</button>
      </div>
      <?php if ($advLimit['available']<=0): ?><div class="text-muted" style="font-size:11px;margin-top:8px;text-align:center;">ยังไม่มีวงเงิน — ต้องมีวันทำงาน/มีฐานเงินเดือนก่อน</div><?php endif; ?>
    </form>
  </div>
</div>

<script>
function openSheet(id){ document.getElementById(id).classList.add('show'); }
function closeSheet(id){ document.getElementById(id).classList.remove('show'); }
function setId(form, id){ let el=form.querySelector('input[name="id"]'); if(!el){el=document.createElement('input');el.type='hidden';el.name='id';form.appendChild(el);} el.value=id; }
function rmId(form){ const el=form.querySelector('input[name="id"]'); if(el) el.remove(); }

// เปิดฟอร์มใหม่ (สร้าง)
function openNew(kind){
  if(kind==='leave'){ const f=document.getElementById('leaveForm'); f.reset(); f.elements['do'].value='leave'; rmId(f);
    document.getElementById('leaveTitle').textContent='ยื่นใบลา'; openSheet('leaveModal'); }
  else { const f=document.getElementById('otForm'); f.reset(); f.elements['do'].value='ot'; rmId(f);
    document.getElementById('otTitle').textContent='ขอทำงานล่วงเวลา (OT)'; openSheet('otModal'); }
}
// เปิดฟอร์มแก้ไข (เฉพาะคำขอที่รออนุมัติ)
function editReq(data){
  if(data.kind==='leave'){ const f=document.getElementById('leaveForm'); f.elements['do'].value='update_leave'; setId(f,data.id);
    f.elements['leave_type'].value=data.edit.leave_type; f.elements['date_from'].value=data.edit.date_from;
    f.elements['date_to'].value=data.edit.date_to; f.elements['reason'].value=data.edit.reason;
    document.getElementById('leaveTitle').textContent='แก้ไขใบลา (รออนุมัติ)'; openSheet('leaveModal'); }
  else { const f=document.getElementById('otForm'); f.elements['do'].value='update_ot'; setId(f,data.id);
    f.elements['ot_date'].value=data.edit.ot_date; f.elements['hours'].value=data.edit.hours; f.elements['reason'].value=data.edit.reason;
    document.getElementById('otTitle').textContent='แก้ไขคำขอ OT (รออนุมัติ)'; openSheet('otModal'); }
}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click', e=>{ if(e.target===o) o.classList.remove('show'); }));
</script>
<?php endif; ?>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
