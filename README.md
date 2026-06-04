# ☀️ SolarSell — ระบบจัดการร้านขายและติดตั้งโซลาร์เซลล์

ERP เน้นงานขาย + ติดตั้งโซลาร์ เขียนด้วย **PHP ล้วน (no framework) + MariaDB** บน XAMPP

> เปลี่ยนจากแผนเดิม (Laravel + Inertia/Vue) มาเป็น PHP ล้วนเพื่อเริ่มเร็วและใช้ของที่มีใน XAMPP โดยตรง

---

## 🚀 การติดตั้ง (Setup)

### 1. เปิด XAMPP
เปิด **XAMPP Control Panel** → Start **Apache** + **MySQL (MariaDB)**

### 2. สร้างฐานข้อมูล + import
```powershell
# สร้าง DB
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS solarsell CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# import schema + ข้อมูลเริ่มต้น (ตามลำดับ)
$db = "C:\xampp\mysql\bin\mysql.exe"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/schema.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/seed.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phase1_products.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phase2_inventory.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phase3_sales.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phase4_finance.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phase5_hr.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phase5b_attendance.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phase1b_coa.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseB1_purchasing.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseB3_crm.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseB2_journal.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseC_quickclock.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseD_ot.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseE_cancel.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseF_hr_role.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseG_company.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseH_po.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseI_wht.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseJ_employee.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseK_roles.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseL_service.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseM_claim_asset.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseN_warranty_alert.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseO_vatmode.sql"
& $db -u root solarsell -e "source C:/xampp/htdocs/tps_erp/db/phaseJ_employee.sql"  # (reorder_level มีอยู่แล้วใน schema)
```
หรือใช้ phpMyAdmin (`http://localhost/phpmyadmin`) → สร้าง DB `solarsell` แล้ว Import ไฟล์ใน `db/`

### 3. ตั้งค่า config
```powershell
copy config\config.example.php config\config.php
```
แก้ `config/config.php` ให้ตรงกับเครื่อง (ดู ⚠️ ความปลอดภัยด้านล่าง)

### 4. เปิดใช้งาน
เข้า `http://localhost/tps_erp/login.php`

> **เข้าระบบด้วย “ชื่อผู้ใช้” (username)** ไม่ใช่อีเมลแล้ว — ชื่อผู้ใช้สร้างจาก **ชื่อจริงภาษาอังกฤษ** ของพนักงาน
> (เช่น `Somchai Jaidee` → `somchai.jaidee`) และ **รหัสผ่านเริ่มต้น = ชื่อผู้ใช้**

**บัญชีทดสอบ** (ชื่อผู้ใช้ = รหัสผ่าน):
| ชื่อผู้ใช้ / รหัสผ่าน | บทบาท |
|-------|-------|
| `admin` / `admin` | ผู้ดูแลระบบ (เห็นทุกเมนู) |
| `sales` / `sales` | ฝ่ายขาย (ไม่เห็นเมนูการเงิน) |
| `hr` / `hr` | ทรัพยากรบุคคล (เห็นเฉพาะเมนู HR + แดชบอร์ด/รายงาน) |
| `supervisor` / `supervisor` | หัวหน้างาน (อนุมัติลา/OT **เฉพาะทีมตัวเอง** + self-service) |
| `exec` / `exec` | ผู้บริหาร (ดูทุกฝ่าย/รายงาน — **อ่านอย่างเดียว**) |
| `staff` / `staff` | พนักงานทั่วไป (self-service: ตอกบัตร/ลา/OT/สลิป เท่านั้น) |

> เพิ่มพนักงานใหม่ที่เมนู **พนักงาน / ทีมช่าง** → กรอก “ชื่อจริง (ภาษาอังกฤษ)” + เลือกสิทธิ์ → ระบบสร้างบัญชีเข้าระบบให้อัตโนมัติทันที

> ⚠️ เปลี่ยนรหัสผ่านทันทีหลัง login ครั้งแรก

---

## 📁 โครงสร้างโปรเจกต์

```
tps_erp/
├── config/
│   ├── config.example.php   # แม่แบบ (commit ได้)
│   ├── config.php           # ค่าจริง (อยู่ใน .gitignore)
│   └── .htaccess            # block การเข้าถึงผ่านเว็บ
├── app/                     # core (เป็น include เท่านั้น, block ผ่านเว็บ)
│   ├── bootstrap.php        # โหลด config + session + helper ทุกหน้า require ตัวนี้
│   ├── Database.php         # PDO singleton + prepared statement
│   ├── Auth.php             # login / session / RBAC
│   ├── helpers.php          # e(), url(), csrf, flash, thai_date ...
│   ├── layout_header.php    # sidebar + topbar (เมนูกรองตามสิทธิ์)
│   └── layout_footer.php
├── assets/css/app.css       # ธีม solar (gold/amber, dark)
├── db/
│   ├── schema.sql           # โครงสร้างตาราง
│   └── seed.sql             # ข้อมูลเริ่มต้น (roles, permissions, users)
├── storage/
│   ├── uploads/             # ไฟล์ผู้ใช้ (PDPA) — gitignore
│   └── logs/                # error log — gitignore
├── solarsell/index.html     # prototype UI ต้นฉบับ (reference)
├── login.php · logout.php · index.php · customers.php
└── README.md
```

---

## 🔒 ความปลอดภัย (ทำแล้ว)

- **SQL Injection** — ทุก query ใช้ PDO prepared statement (`Database.php`)
- **XSS** — escape ทุก output ด้วย `e()`
- **CSRF** — ทุกฟอร์ม POST มี token + ตรวจด้วย `csrf_verify()`
- **Session** — `httponly`, `samesite=Lax`, regenerate id ตอน login
- **RBAC** — `Auth::can()` / `Auth::requireCan()` + เมนูกรองตามสิทธิ์
- **Password** — `password_hash()` (bcrypt)
- **Audit log** — บันทึก login/create พร้อมแยก `created_by` (รองรับ act-on-behalf)
- **ไฟล์ลับ** — `config/`, `app/`, `db/` มี `.htaccess` block + `config.php` ใน `.gitignore`

### ⚠️ ต้องทำก่อนใช้จริง (Checklist จาก context.md ข้อ 8)
- [ ] ตั้งรหัสให้ MariaDB user `root` (XAMPP default รหัสว่าง)
- [ ] สร้าง user เฉพาะแอป `solarsell_app` (อย่าใช้ root) แล้วแก้ `config.php`
- [ ] เปลี่ยนรหัสผ่านบัญชีทดสอบ
- [ ] ตั้ง `app.debug = false` ตอนขึ้น production

---

## 🗺️ Roadmap (อ้างอิง context.md)

- [x] **Phase 0** — Auth, RBAC, Audit log ✅
- [x] **Phase 1** — ทะเบียนลูกค้า, ซัพพลายเออร์, สินค้า, **ผังบัญชี (COA)** ✅
- [x] **Phase 2** — Inventory: `Stock` service (รับเข้า/เบิก/ปรับยอด + ledger + กันสต็อกติดลบ) ✅
- [x] **Phase 3** — Sales: ใบเสนอราคา → ใบสั่งขาย → ส่งของ (ตัดสต็อกอัตโนมัติ) ✅
- [x] **Phase 4** — Finance: ออกใบแจ้งหนี้จากใบสั่งขาย + รับชำระ (AR, VAT 7%) ✅
- [x] **Phase 5** — HR: พนักงาน, **Geofencing เช็คอิน (Haversine ที่ server)**,
      **ลงเวลา/ลา + HR ทำแทน (act-on-behalf §5.1)**, **Payroll + คอมมิชชั่นจากยอดขาย + ปิดงวดล็อกข้อมูล** ✅
      เหลือ (ต่อยอด): leave balance/โควต้าวันลา, สลิปเงินเดือน PDF, AP (เจ้าหนี้)
- [x] **Phase 6** — รายงาน (`reports.php`) รวมแดชบอร์ดผู้บริหาร แยกหมวด ผู้บริหาร/ฝ่ายขาย/การเงิน/คลัง ✅
- [x] **หน้าหลัก = แดชบอร์ดส่วนบุคคล** (`index.php`) — ปฏิทินเข้างาน/ลารายเดือน (เลือกเดือนได้) + สิทธิ์วันลาคงเหลือ + แจ้งเตือน ✅
- [x] **ปุ่มตอกบัตรเข้า–ออกงานลัด** บนแดชบอร์ด — กดเช็คอิน/เช็คเอาท์ → **บังคับถ่ายรูปสด (กล้อง)** + GPS → หาไซต์ใกล้สุด (Haversine) → บันทึกเวลา + หลักฐาน GPS+รูป, กันตอกซ้ำ (`Hr::quickClock`) ✅
- [x] **Self-service บนแดชบอร์ด (ทุกคน ทุกสิทธิ์ — เฉพาะของตัวเอง):** ยื่นใบลา · ขอ OT · เช็คอิน · ดูสลิปเงินเดือน · **แก้ไข/ยกเลิกคำขอที่ยัง "รออนุมัติ" ได้เอง** (มี guard เจ้าของ + เฉพาะ pending + ไม่ถูกล็อก) ✅
- [x] **ระบบ OT** — ยื่น/อนุมัติ (`ot.php`, `Hr::requestOt`/`decideOt`) + คำนวณเข้าเงินเดือนอัตโนมัติ (ค่าแรง/ชม. × 1.5) ✅
- [x] **ตั้งค่าบริษัท** (`company.php`) — ชื่อ/ที่อยู่/เลขภาษี/โลโก้ ขึ้นบนใบเสนอราคา/ใบกำกับ/สลิป (`company()` helper) ✅
- [x] **กล่องรออนุมัติ** (`approvals.php`) — รวมลา+OT รออนุมัติ + แจ้งเตือนผู้อนุมัติอัตโนมัติ (`Hr::notifyApprovers`) + badge นับบนเมนู ✅
- [x] **หน้าดู Audit Log** (`audit.php`, admin) — ตัวกรอง action/entity + pagination ✅
- [x] **role ทรัพยากรบุคคล (hr)** + user `hr@solarsell.local` — สิทธิ์ HR เต็ม, RBAC ทดสอบแล้ว ✅
- [x] **role: หัวหน้างาน/ผู้บริหาร/พนักงานทั่วไป** — supervisor อนุมัติเฉพาะทีม (§5.1), executive ดูอย่างเดียว, staff self-service ✅
- [x] **งานบริการหลังการขาย (O&M)** (`service.php`) — ใบงานบำรุงรักษา/ซ่อม/เคลม/ตรวจเช็ค + workflow สถานะ + ความเร่งด่วน ✅
- [x] **ทะเบียนอุปกรณ์ & การรับประกัน** (`warranties.php`) — เก็บ Serial + คำนวณวันหมดประกัน + เตือนใกล้หมด/หมดแล้ว ✅
- [x] **เชื่อมเคลมประกัน ↔ อุปกรณ์** — เปิดใบเคลมเลือก Serial → เช็คสถานะประกันทันที (in/out) + กันเลือกอุปกรณ์ข้ามลูกค้า + บันทึก snapshot สถานะ ✅
- [x] **แจ้งเตือนล่วงหน้าก่อนประกันหมด** — สแกนอุปกรณ์ใกล้หมด (90 วัน) → เด้งหาทีมขาย+admin ให้เสนอต่อประกัน/O&M (กันแจ้งซ้ำด้วย `renewal_alerted_at`) ✅
- [x] **ออกใบเสนอราคาจากใบงานบริการ** — ปุ่มในใบงาน → เปิดฟอร์มใบเสนอราคา prefill ลูกค้า+รายการ+อ้างอิงใบงาน ✅
- [x] **โหมด VAT ต่อเอกสาร** (Exclude/Include/No VAT) — ใบเสนอราคา/ใบสั่งซื้อ/รับเข้า + ส่งต่อถึงใบสั่งขาย/ใบกำกับ (`vat_calc` helper) ✅
- [x] **ฟอร์มสินค้า: จุดสั่งซื้อขั้นต่ำ (reorder_level)** แก้ได้ในหน้าเพิ่ม/แก้สินค้า (มีผลกับแจ้งเตือนสต็อก) ✅
- [x] **ช่องรายการใบเสนอราคา** รวมเป็นช่องเดียว (พิมพ์/เลือกสินค้าจาก datalist) ไม่ซ้ำซ้อน ✅

### สถาปัตยกรรม Service Layer (logic ข้ามตาราง อยู่ใน transaction)
| Service | หน้าที่ |
|---------|---------|
| `app/Stock.php`   | `move()` — ทุกการเคลื่อนไหวสต็อก (atomic, กันติดลบ, FOR UPDATE) |
| `app/Sales.php`   | `createQuotation()`, `convertToOrder()`, `deliver()` (เรียก Stock) |
| `app/Finance.php` | `createInvoiceFromOrder()`, `recordPayment()` |
| `app/Geo.php`     | `distanceMeters()` (Haversine), `evaluate()` — **คำนวณที่ server เท่านั้น** |
| `app/Hr.php`      | ลงเวลา/ลา (act-on-behalf §5.1), `generatePayroll()` (commission จากยอดขาย), `lockPayroll()` |
| `app/Purchasing.php` | `createGoodsReceipt()` (เพิ่มสต็อก+สร้าง AP), `payBill()` — วงจรซื้อ |

### Production-ready (กลุ่ม A — เสร็จแล้ว)
- [x] **พิมพ์ / บันทึก PDF** ใบเสนอราคา + ใบกำกับภาษี (A4, `quotation_print.php` / `invoice_print.php` → `window.print()`)
- [x] **จัดการผู้ใช้** (`users.php`, admin): เพิ่ม/เปิด-ปิดบัญชี/เปลี่ยน role/รีเซ็ตรหัส
- [x] **เปลี่ยนรหัสผ่านตัวเอง** (`profile.php`) — ปิดช่องโหว่รหัส default
- [x] **แก้ไข/ลบ master data** (ลูกค้า/สินค้า/ซัพพลายเออร์) + กันลบข้อมูลที่ถูกอ้างอิง (FK guard) + ปิดการใช้งานแทนลบ
- [x] **ระบบแจ้งเตือน** (`notifications.php`) + กระดิ่งนับ unread บน topbar
- [x] **สคริปต์ hardening DB** (`db/security_setup.sql`) — ตั้งรหัส root + สร้าง user เฉพาะแอป

### กลุ่ม B — ฟีเจอร์เต็มระบบ (เสร็จแล้ว)
- [x] **ใบสั่งซื้อ (PO)** → รับเข้า (`purchase_orders.php`, `Purchasing::createPurchaseOrder`/`receivePurchaseOrder`) — ปิดวงจร PO→GR→AP, กันรับซ้ำ ✅
- [x] **รายงานอายุหนี้ (AR/AP Aging)** (`aging.php`) — แบ่งช่วง ยังไม่ถึงกำหนด/1-30/31-60/61-90/90+ พร้อมยอดรวม ✅
- [x] **ภาษีหัก ณ ที่จ่าย (WHT)** ในการจ่ายเจ้าหนี้ (1/2/3/5%) — คิดจากฐานก่อน VAT, แสดงเงินสดจ่ายจริง ✅
- [x] **หน้ารายการจ่ายเจ้าหนี้รวมศูนย์** (`vendor_payments.php`) — กรองตามช่วงเวลา/ซัพพลายเออร์ + **สรุป WHT ตามผู้ขายสำหรับยื่น ภ.ง.ด.3/53** ✅
- [x] **หนังสือรับรองหัก ณ ที่จ่าย (50 ทวิ)** (`wht_cert.php`) — พิมพ์จากแต่ละรายการจ่าย + จำนวนเงินเป็นตัวอักษรไทย (`bahttext` helper) ✅
- [x] **ตรวจนับสต็อก** (`stock_count.php`) — กรอกยอดนับจริง → ปรับยอดตามผลต่าง + ledger ✅
- [x] **กำไรต่อโปรเจกต์** (`job_costing.php`) — รายได้(ex VAT) − ต้นทุนวัสดุ ต่องานติดตั้ง ✅
- [x] **Export CSV** (`export.php`, UTF-8 BOM) — ลูกค้า/สินค้า/บิล/ฯลฯ ✅
- [x] **แจ้งเตือนสต็อกใกล้หมดอัตโนมัติ** — เด้งหาผู้จัดการคลังเมื่อตัดสต็อกต่ำกว่าจุดสั่งซื้อ ✅
- [x] **ข้อมูลพนักงานละเอียด + แก้ไขได้** (`employees.php`) — บัตร ปชช./การศึกษา/วันเกิด-เริ่มงาน/ที่อยู่ + เงินเดือน/บัญชีธนาคาร (gated `hr.payroll`) ✅
- [x] **เบิกเงินล่วงหน้า** (`advances.php` + แดชบอร์ด self-service) — ยื่น/อนุมัติ → หักจากเงินเดือนงวดถัดไป (idempotent) + แสดงในสลิป ✅
      **จำกัดวงเงิน** = (ฐานเงินเดือน ÷ 30) × วันที่ทำงานมาแล้วในเดือน − ยอดเบิกค้าง (`Hr::advanceLimit`, บังคับทั้ง UI + server) ✅
- [x] **B1 รับเข้าสินค้า (Goods Receipt)** → เพิ่มสต็อก + อัปเดตต้นทุน + สร้าง **เจ้าหนี้ (AP)** อัตโนมัติ + จ่ายเจ้าหนี้ (`Purchasing` service)
- [x] **B2 งบการเงิน** — VAT (ภพ.30), งบกำไรขาดทุน (Revenue/COGS/Net), ฐานะการเงิน (AR/AP/สต็อก/เงินสด) + **สมุดรายวันบัญชีคู่** (debit=credit guard)
- [x] **B3 CRM** — Leads (สถานะ + แปลงเป็นลูกค้า) + **งานติดตั้ง** (job tracking, progress %)

วงจรซื้อ: `รับเข้า (GR) → เพิ่มสต็อก + สร้างเจ้าหนี้ (AP) → จ่ายเงิน (VP)` — คู่ขนานกับวงจรขาย

### กลุ่ม C — ขัดเกลา (เสร็จแล้ว)
- [x] **ค้นหารวมทั้งระบบ** (`search.php`) — ลูกค้า/สินค้า/เอกสาร/Leads (เคารพ RBAC) เชื่อมช่องค้นหาบน topbar
- [x] **Pagination** — helper `render_pager()` + ใช้กับ customers/products/quotations/billing/leads
- [x] **แผนที่ Leaflet** (§5.3) — `worksites.php` แสดงหมุด + วงรัศมี + คลิกแผนที่ตั้งพิกัด (OpenStreetMap ฟรี)
- [x] **ชุดทดสอบอัตโนมัติ** (`tests/run.php`) — 17 เทสต์ (Geo/helpers/Stock guard/DB invariants) รันด้วย `php tests/run.php`

### กระแสงานหลัก (ทดสอบผ่านจริงแบบ end-to-end)
```
ใบเสนอราคา (QT) → แปลง → ใบสั่งขาย (SO) → ส่งของ [ตัดสต็อก]
                                          → ออกใบแจ้งหนี้ (INV) → รับชำระ (PAY) [AR ลด]
```
