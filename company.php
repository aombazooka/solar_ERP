<?php
/** company.php — ตั้งค่าข้อมูลบริษัท (ขึ้นบนเอกสาร) — admin */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('settings.company');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        Database::run(
            'UPDATE company_profile SET name=:n, legal_name=:ln, address=:a, phone=:p, email=:e, tax_id=:t, logo_emoji=:lo WHERE id=1',
            ['n'=>input('name') ?: 'SolarSell','ln'=>input('legal_name') ?: null,'a'=>input('address') ?: null,
             'p'=>input('phone') ?: null,'e'=>input('email') ?: null,'t'=>input('tax_id') ?: null,
             'lo'=>mb_substr(input('logo_emoji') ?: '☀️', 0, 8, 'UTF-8')]
        );
        flash('success', 'บันทึกข้อมูลบริษัทเรียบร้อย');
    } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    redirect('company.php');
}

// อ่านสดจาก DB (ไม่ใช้ cache ของ helper เพื่อให้เห็นค่าล่าสุดหลังบันทึก)
$co = Database::one('SELECT * FROM company_profile WHERE id=1') ?: [];

$pageTitle = 'ตั้งค่าบริษัท';
$activeNav = 'company';
require __DIR__ . '/app/layout_header.php';
?>
<div class="page-header">
  <div><h1>ตั้งค่าข้อมูลบริษัท</h1><p>ข้อมูลนี้จะแสดงบนใบเสนอราคา ใบกำกับภาษี และสลิปเงินเดือน</p></div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="card-head"><div class="card-title">ข้อมูลบริษัท</div></div>
    <form method="post"><?= csrf_field() ?>
      <div class="grid g2">
        <div class="form-group"><label class="form-label">ชื่อย่อ/แบรนด์ (ขึ้นหัวเอกสาร) *</label><input class="form-input" name="name" value="<?= e($co['name'] ?? 'SolarSell') ?>" required></div>
        <div class="form-group"><label class="form-label">ไอคอน/อิโมจิ</label><input class="form-input" name="logo_emoji" value="<?= e($co['logo_emoji'] ?? '☀️') ?>" maxlength="8"></div>
        <div class="form-group" style="grid-column:span 2;"><label class="form-label">ชื่อนิติบุคคล (เต็ม)</label><input class="form-input" name="legal_name" value="<?= e($co['legal_name'] ?? '') ?>" placeholder="บริษัท ... จำกัด"></div>
        <div class="form-group" style="grid-column:span 2;"><label class="form-label">ที่อยู่</label><input class="form-input" name="address" value="<?= e($co['address'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">โทรศัพท์</label><input class="form-input" name="phone" value="<?= e($co['phone'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">อีเมล</label><input class="form-input" name="email" value="<?= e($co['email'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">เลขประจำตัวผู้เสียภาษี</label><input class="form-input" name="tax_id" value="<?= e($co['tax_id'] ?? '') ?>"></div>
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-check"></i> บันทึก</button>
    </form>
  </div>

  <div class="card" style="align-self:start;">
    <div class="card-head"><div class="card-title">ตัวอย่างหัวเอกสาร</div></div>
    <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;background:#fff;color:#1f2937;">
      <div style="display:flex;align-items:center;gap:10px;border-bottom:3px solid #f59e0b;padding-bottom:10px;">
        <div style="width:40px;height:40px;border-radius:9px;background:linear-gradient(135deg,#f59e0b,#ea580c);display:flex;align-items:center;justify-content:center;font-size:20px;"><?= e($co['logo_emoji'] ?? '☀️') ?></div>
        <div><div style="font-size:18px;font-weight:700;"><?= e($co['name'] ?? 'SolarSell') ?></div><div style="font-size:11px;color:#6b7280;"><?= e($co['legal_name'] ?? '') ?></div></div>
      </div>
      <div style="font-size:11px;color:#4b5563;margin-top:8px;line-height:1.7;">
        <?= e($co['address'] ?? '') ?><br>
        โทร. <?= e($co['phone'] ?? '-') ?> · เลขภาษี <?= e($co['tax_id'] ?? '-') ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/app/layout_footer.php'; ?>
