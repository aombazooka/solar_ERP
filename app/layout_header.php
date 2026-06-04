<?php
/**
 * layout_header.php — ส่วนหัวที่ทุกหน้าใน "หลัง login" ใช้ร่วมกัน
 * ก่อน include ต้องตั้ง: $pageTitle (string), $activeNav (string slug)
 */
if (!defined('SOLARSELL')) { http_response_code(403); exit('Direct access denied'); }

Auth::require();
$me   = Auth::user();
$nav  = $activeNav ?? '';
$title = $pageTitle ?? 'SolarSell';

// จำนวนคำขอรออนุมัติ (สำหรับ badge เมนูรออนุมัติ)
$pendingApprovals = 0;
if (Auth::can('hr.approve') || Auth::can('hr.approve_team')) {
    try { $pendingApprovals = Hr::pendingApprovalCount(); } catch (\Throwable $e) {}
}

// เมนู: [slug, label, icon, permission, badge, badge_class]
$navGroups = [
    'ภาพรวม' => [
        ['index', 'แดชบอร์ด', 'fa-gauge-high', 'dashboard.view', null, null],
        ['payslip', 'สลิปเงินเดือนของฉัน', 'fa-receipt', 'dashboard.view', null, null],
    ],
    'งานขาย' => [
        ['leads',      'Leads / ลูกค้าสนใจ', 'fa-user-plus',    'sales.view', null, null],
        ['quotations', 'ใบเสนอราคา',         'fa-file-invoice', 'sales.view', null, null],
        ['orders',     'ใบสั่งขาย',           'fa-cart-shopping','sales.view', null, null],
    ],
    'งานติดตั้ง & บริการ' => [
        ['installations', 'งานติดตั้ง',   'fa-solar-panel', 'install.view', null, null],
        ['service',       'งานบริการ (O&M)','fa-headset',    'service.view', null, null],
        ['warranties',    'การรับประกัน',  'fa-shield-halved','service.view', null, null],
    ],
    'คลัง / สินค้า' => [
        ['products',        'สินค้า / วัสดุ', 'fa-boxes-stacked', 'inventory.view', null, null],
        ['vendors',         'ซัพพลายเออร์',   'fa-truck-field',   'inventory.view', null, null],
        ['purchase_orders', 'ใบสั่งซื้อ (PO)','fa-file-circle-plus','inventory.view', null, null],
        ['goods_receipts',  'รับเข้าสินค้า',  'fa-truck-ramp-box','inventory.view', null, null],
        ['stock',           'คลังสินค้า',     'fa-warehouse',     'inventory.view', null, null],
        ['stock_count',     'ตรวจนับสต็อก',   'fa-clipboard-check','inventory.view', null, null],
    ],
    'การเงิน' => [
        ['billing',  'ใบแจ้งหนี้ / ใบกำกับ', 'fa-file-invoice-dollar', 'finance.view', null, null],
        ['payments', 'รับชำระ',              'fa-money-bill-wave',     'finance.view', null, null],
        ['payables', 'เจ้าหนี้ (AP)',        'fa-hand-holding-dollar', 'finance.view', null, null],
        ['vendor_payments', 'จ่ายเจ้าหนี้',   'fa-money-bill-transfer', 'finance.view', null, null],
        ['aging',    'อายุหนี้ (Aging)',     'fa-hourglass-half',      'finance.view', null, null],
        ['accounts', 'ผังบัญชี',             'fa-sitemap',             'finance.view', null, null],
        ['journal',  'สมุดรายวัน',           'fa-book',                'finance.view', null, null],
        ['finance_reports', 'งบการเงิน',     'fa-file-contract',       'finance.view', null, null],
        ['job_costing',     'กำไรต่อโปรเจกต์','fa-sack-dollar',         'finance.view', null, null],
    ],
    'ทรัพยากรบุคคล' => [
        ['employees',   'พนักงาน / ทีมช่าง', 'fa-people-group',       'hr.view',     null, null],
        ['attendance',  'ลงเวลาทำงาน',       'fa-clock',              'hr.view',     null, null],
        ['leave',       'การลา',             'fa-plane-departure',    'hr.view',     null, null],
        ['ot',          'OT (ล่วงเวลา)',     'fa-business-time',      'hr.view',     null, null],
        ['approvals',   'รออนุมัติ',         'fa-inbox',              'hr.approve_team', $pendingApprovals ?: null, 'green'],
        ['payroll',     'เงินเดือน',         'fa-money-check-dollar', 'hr.payroll',  null, null],
        ['advances',    'เบิกเงินล่วงหน้า',  'fa-hand-holding-dollar','hr.advance',  null, null],
        ['worksites',   'จุดหน้างาน',        'fa-map-location-dot',   'hr.view',     null, null],
        ['checkin',     'เช็คอินหน้างาน',    'fa-location-crosshairs','hr.checkin',  null, null],
        ['master_data', 'ข้อมูลพื้นฐาน HR',  'fa-sliders',            'hr.manage',   null, null],
    ],
    'ระบบ' => [
        ['customers', 'ทะเบียนลูกค้า', 'fa-address-book', 'customer.view',  null, null],
        ['reports',   'รายงาน',        'fa-chart-line',   'dashboard.view', null, null],
        ['users',     'ผู้ใช้งาน',     'fa-users-gear',   'users.manage',   null, null],
        ['company',   'ตั้งค่าบริษัท', 'fa-building',     'settings.company', null, null],
        ['audit',     'บันทึกการใช้งาน','fa-clipboard-list','users.manage',  null, null],
    ],
];
$initial = mb_substr($me['name'] ?? '?', 0, 1, 'UTF-8');

// จำนวนแจ้งเตือนที่ยังไม่อ่าน (ของพนักงานที่ผูกกับ user นี้)
$unreadNotif = (int) Database::scalar(
    'SELECT COUNT(*) FROM notifications n
     JOIN employees e ON e.id = n.employee_id
     WHERE e.user_id = :uid AND n.is_read = 0',
    ['uid' => Auth::id()]
);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark light">
  <script>
    /* set theme before first paint — no flash of wrong theme */
    (function () {
      try {
        var t = localStorage.getItem('solarsell-theme');
        if (!t) t = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
      } catch (e) {}
    })();
  </script>
  <title><?= e($title) ?> · SolarSell</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">☀️</div>
    <div class="brand-text">
      <div class="brand-name">SolarSell</div>
      <div class="brand-tagline">SOLAR MANAGEMENT ERP</div>
    </div>
  </div>

  <div class="sidebar-scroll">
    <?php /* ใช้ชื่อตัวแปร $nav* กันชนกับตัวแปรในหน้าเพจ (เช่น $items/$it) */ ?>
    <?php foreach ($navGroups as $navGroupName => $navGroupItems):
        // กรองเฉพาะเมนูที่ผู้ใช้มีสิทธิ์
        $navVisible = array_filter($navGroupItems, fn($navIt) => Auth::can($navIt[3]));
        if (!$navVisible) continue;
    ?>
      <div class="nav-section"><span class="nav-section-label"><?= e($navGroupName) ?></span></div>
      <?php foreach ($navVisible as $navItem):
          [$navSlug, $navLabel, $navIcon, $navPerm, $navBadge, $navBadgeClass] = $navItem; ?>
        <a class="nav-item <?= $nav === $navSlug ? 'active' : '' ?>" href="<?= e(url($navSlug . '.php')) ?>">
          <i class="fa-solid <?= e($navIcon) ?>"></i> <?= e($navLabel) ?>
          <?php if ($navBadge): ?><span class="nav-badge <?= e($navBadgeClass) ?>"><?= e($navBadge) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <div class="sidebar-footer">
    <a class="user-avatar" href="<?= e(url('profile.php')) ?>" title="โปรไฟล์" style="text-decoration:none;"><?= e($initial) ?></a>
    <a class="user-info" href="<?= e(url('profile.php')) ?>" style="text-decoration:none;">
      <div class="user-name"><?= e($me['name']) ?></div>
      <div class="user-role"><?= e($me['role_name']) ?></div>
    </a>
    <a class="icon-btn" href="<?= e(url('logout.php')) ?>" title="ออกจากระบบ" style="border:none;background:none;">
      <i class="fa-solid fa-right-from-bracket" style="color:var(--text-muted)"></i>
    </a>
  </div>
</aside>

<div class="topbar">
  <div class="topbar-titlewrap">
    <div class="topbar-title"><?= e($title) ?></div>
    <div class="topbar-sub"><?= e(thai_date()) ?></div>
  </div>
  <div class="topbar-actions">
    <form class="topbar-search" method="get" action="<?= e(url('search.php')) ?>">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="ค้นหา ลูกค้า, งาน, สินค้า..." autocomplete="off">
    </form>
    <button class="icon-btn theme-toggle" type="button" onclick="toggleTheme(this)" title="สลับโหมดสว่าง/มืด" aria-label="สลับธีม">
      <i class="fa-solid fa-moon i-moon"></i>
      <i class="fa-solid fa-sun i-sun"></i>
    </button>
    <a class="icon-btn" href="<?= e(url('notifications.php')) ?>" title="การแจ้งเตือน">
      <i class="fa-solid fa-bell"></i>
      <?php if ($unreadNotif > 0): ?><span class="notif-count"><?= $unreadNotif > 9 ? '9+' : $unreadNotif ?></span><?php endif; ?>
    </a>
  </div>
</div>
<script>
  /* enable smooth transitions only after first paint */
  requestAnimationFrame(function () { document.body.classList.add('theme-ready'); });
  function toggleTheme(btn) {
    var isLight = document.documentElement.getAttribute('data-theme') === 'light';
    if (isLight) { document.documentElement.removeAttribute('data-theme'); store('dark'); }
    else { document.documentElement.setAttribute('data-theme', 'light'); store('light'); }
    if (btn) { var ic = btn.querySelector('i:not([style*="display: none"])'); btn.classList.remove('spin'); void btn.offsetWidth; btn.classList.add('spin'); }
    function store(v){ try { localStorage.setItem('solarsell-theme', v); } catch(e){} }
  }
</script>

<main class="main">
  <div class="page">
    <?php foreach (flash_pull() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>">
        <i class="fa-solid fa-circle-info"></i> <?= e($f['message']) ?>
      </div>
    <?php endforeach; ?>
