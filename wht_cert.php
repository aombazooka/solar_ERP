<?php
/** wht_cert.php — หนังสือรับรองการหักภาษี ณ ที่จ่าย (50 ทวิ) แบบพิมพ์ */
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireCan('finance.view');

$id = (int) input('id');
$p = Database::one(
    'SELECT vp.*, v.name AS vendor_name, v.tax_id AS vendor_tax, v.address AS vendor_addr, b.doc_no AS bill_no
     FROM vendor_payments vp
     JOIN vendors v ON v.id = vp.vendor_id
     JOIN vendor_bills b ON b.id = vp.bill_id
     WHERE vp.id = :id', ['id' => $id]
);
if (!$p) { http_response_code(404); exit('ไม่พบรายการจ่าย'); }
if ((float) $p['wht_amount'] <= 0) { http_response_code(400); exit('รายการนี้ไม่มีการหักภาษี ณ ที่จ่าย'); }

$co = company();
$wht = (float) $p['wht_amount'];
$rate = (float) $p['wht_rate'];
// ฐานเงินได้ที่ใช้คำนวณ WHT (ถอดกลับจากภาษี) เพื่อความแม่นยำทุกโหมด VAT
$income = $rate > 0 ? round($wht * 100 / $rate, 2) : 0;
$payDate = thai_date_short($p['paid_at']);
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>50 ทวิ · <?= e($p['doc_no']) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/print.css')) ?>">
<style>
.wht-box { border:1px solid #1f2937; border-radius:6px; padding:10px 14px; margin-bottom:12px; }
.wht-box .lbl { font-size:11px; color:#6b7280; font-weight:600; }
.wht-title { text-align:center; font-size:18px; font-weight:700; margin-bottom:2px; }
.wht-sub { text-align:center; font-size:12px; color:#6b7280; margin-bottom:16px; }
.tick { display:inline-block; width:13px; height:13px; border:1.5px solid #1f2937; border-radius:3px; text-align:center; line-height:12px; font-size:11px; margin-right:5px; }
.tick.on { background:#1f2937; color:#fff; }
</style>
</head><body>
<div class="toolbar">
  <a class="btn-back" href="<?= e(url('vendor_payments.php')) ?>">← กลับ</a>
  <button class="btn-print" onclick="window.print()">🖨 พิมพ์ / บันทึก PDF</button>
</div>
<div class="doc">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
    <div style="font-size:11px;">ฉบับที่ <strong><?= e($p['doc_no']) ?></strong></div>
    <div style="font-size:11px;text-align:right;">(สำหรับผู้ถูกหักภาษี ณ ที่จ่าย)</div>
  </div>
  <div class="wht-title">หนังสือรับรองการหักภาษี ณ ที่จ่าย</div>
  <div class="wht-sub">ตามมาตรา 50 ทวิ แห่งประมวลรัษฎากร</div>

  <!-- ผู้มีหน้าที่หักภาษี (ผู้จ่าย = บริษัท) -->
  <div class="wht-box">
    <div class="lbl">ผู้มีหน้าที่หักภาษี ณ ที่จ่าย (ผู้จ่ายเงิน)</div>
    <div style="font-size:14px;font-weight:700;"><?= e($co['legal_name'] ?: $co['name']) ?></div>
    <div style="font-size:12px;"><?= e($co['address'] ?: '-') ?></div>
    <div style="font-size:12px;">เลขประจำตัวผู้เสียภาษี: <strong><?= e($co['tax_id'] ?: '-') ?></strong></div>
  </div>

  <!-- ผู้ถูกหักภาษี (ผู้รับเงิน = ซัพพลายเออร์) -->
  <div class="wht-box">
    <div class="lbl">ผู้ถูกหักภาษี ณ ที่จ่าย (ผู้รับเงิน)</div>
    <div style="font-size:14px;font-weight:700;"><?= e($p['vendor_name']) ?></div>
    <div style="font-size:12px;"><?= e($p['vendor_addr'] ?: '-') ?></div>
    <div style="font-size:12px;">เลขประจำตัวผู้เสียภาษี: <strong><?= e($p['vendor_tax'] ?: '-') ?></strong></div>
  </div>

  <!-- ตารางเงินได้ -->
  <table>
    <thead><tr><th>ประเภทเงินได้ที่จ่าย</th><th style="width:120px">วันเดือนปีที่จ่าย</th><th class="num" style="width:120px">จำนวนเงินที่จ่าย</th><th class="num" style="width:120px">ภาษีที่หัก</th></tr></thead>
    <tbody>
      <tr>
        <td>ค่าจ้างทำของ / ค่าบริการ / ค่ารับเหมา (หัก <?= rtrim(rtrim($p['wht_rate'],'0'),'.') ?>%)<br><span style="font-size:11px;color:#6b7280;">อ้างอิงใบเจ้าหนี้ <?= e($p['bill_no']) ?></span></td>
        <td><?= e($payDate) ?></td>
        <td class="num"><?= number_format($income, 2) ?></td>
        <td class="num"><?= number_format($wht, 2) ?></td>
      </tr>
      <tr><td colspan="2" style="text-align:right;font-weight:700;">รวม</td><td class="num" style="font-weight:700;"><?= number_format($income, 2) ?></td><td class="num" style="font-weight:700;"><?= number_format($wht, 2) ?></td></tr>
    </tbody>
  </table>

  <div style="border:1px solid #1f2937;border-radius:6px;padding:8px 14px;font-size:13px;margin-bottom:16px;">
    รวมเงินภาษีที่หักนำส่ง (ตัวอักษร): <strong><?= e(bahttext($wht)) ?></strong>
  </div>

  <div style="font-size:12px;margin-bottom:14px;">
    <span class="tick on">✓</span> หัก ณ ที่จ่าย &nbsp;&nbsp;
    <span class="tick"></span> ออกให้ตลอดไป &nbsp;&nbsp;
    <span class="tick"></span> ออกให้ครั้งเดียว
  </div>

  <div class="doc-foot">
    <div class="sign"><div class="sign-line">ผู้จ่ายเงิน / ผู้มีอำนาจลงนาม</div><div style="font-size:11px;margin-top:4px;">วันที่ <?= e($payDate) ?></div></div>
    <div class="sign" style="flex:0.6;"><div style="font-size:11px;color:#6b7280;text-align:center;">ประทับตรา (ถ้ามี)</div></div>
  </div>
</div>
</body></html>
