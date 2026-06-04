# 📋 Context: โปรเจกต์ระบบ ERP (บริษัทเน้นด้านการขาย)

> **เอกสารนี้คืออะไร:** เอกสารสรุปภาพรวมโปรเจกต์ ใช้เป็นจุดอ้างอิงหลัก (Single Source of Truth) เพื่อให้การพัฒนาต่อเนื่องถูกต้อง ไม่หลงทาง และสามารถส่งต่อให้ Claude Cowork หรือผู้ช่วยอื่นทำงานต่อได้ทันที
>
> **อัปเดตล่าสุด:** 1 มิ.ย. 2569 — เปลี่ยนสแต็กเป็น PHP ล้วน + พัฒนา Phase 0–6 (core)
> **สถานะปัจจุบัน:** Phase 0–6 + กลุ่ม A + B + C เสร็จครบ (ทดสอบ end-to-end ผ่าน + ชุดเทสต์อัตโนมัติ 17 รายการ) — ระบบใช้งานได้เต็มวงจร ซื้อ-ขาย-ติดตั้ง-การเงิน-HR. เหลือต่อยอดเล็กน้อย: โควต้าวันลา, สลิปเงินเดือน PDF

---

## 1. ภาพรวมโปรเจกต์ (Project Overview)

ระบบ ERP สำหรับบริษัททั่วไปที่ **เน้นด้านการขาย** ประกอบด้วยฝ่ายงานหลัก:

| ฝ่าย | บทบาท |
|------|-------|
| บัญชีการเงิน (Finance) | จัดการบัญชี ลูกหนี้ เจ้าหนี้ ภาษี รายงานการเงิน |
| พัสดุและคลัง (Inventory) | สต็อกสินค้า รับเข้า เบิกจ่าย โอนย้าย ตรวจนับ |
| ทรัพยากรบุคคล (HR) | พนักงาน เงินเดือน ลงเวลา การลา |
| ผู้บริหาร (Executive) | Dashboard ภาพรวม KPI ทุกฝ่าย |
| ฝ่ายขาย (Sales) | **หัวใจของระบบ** — ใบเสนอราคา ใบสั่งขาย ส่งของ ออกบิล CRM คอมมิชชั่น |

**ผู้พัฒนา:** ทำคนเดียว (Solo Developer) โดยใช้ Claude Cowork เป็นเครื่องมือช่วยพัฒนา

---

## 2. หลักการสำคัญที่ยึดตลอดโปรเจกต์ (Guiding Principles)

1. **สร้างฐานให้แน่นก่อน ต่อยอดทีละชั้น** — ERP พังเพราะ "ทำพร้อมกันทุกโมดูล" ไม่ใช่เพราะโค้ดไม่ดี
2. **Database First** — ออกแบบ ERD ทั้งหมดก่อนเขียนโค้ดบรรทัดแรก
3. **Develop on what you deploy on** — dev environment ต้องใกล้เคียง production
4. **Security First** — Parameterized Query, Validate Data, ป้องกัน XSS, RBAC ทุกหน้า
5. **PDPA & ข้อมูลละเอียดอ่อน** — คำนึงถึงความปลอดภัยข้อมูลบุคคล/การเงินเสมอ
6. **Modular Design** — แต่ละโมดูลแยกอิสระ คุยกันผ่าน Service ไม่เรียกตารางข้ามโมดูลตรงๆ
7. **ปิด Phase ก่อนหน้าให้เสร็จจริง (test ผ่าน) ก่อนเริ่ม Phase ใหม่**

---

## 3. Tech Stack ที่เลือกใช้ (สรุปสุดท้าย)

> **⚠️ อัปเดต (1 มิ.ย. 2569):** เปลี่ยนจาก Laravel + Inertia/Vue → **PHP ล้วน (no framework) + MariaDB**
> เหตุผล: เริ่มเร็ว ใช้ของที่มีใน XAMPP โดยตรง ไม่ต้องลง Composer/Node เหมาะกับ solo dev
> โครงสร้างจริงที่วางแล้วดูใน `README.md`

```
┌─────────────────────────────────────────────────────────┐
│  Frontend:  HTML + CSS (ธีม solar) + JS เล็กน้อย           │
│  Backend:   PHP ล้วน (PDO, no framework)                  │
│  Database:  MariaDB (InnoDB, utf8mb4)                     │
│  Local Env: XAMPP (Apache + MariaDB + PHP)                │
│  Auth:      เขียนเอง (session + bcrypt + RBAC)            │
│  DB GUI:    phpMyAdmin (มากับ XAMPP)                       │
│  ผู้ช่วย:    Claude Cowork                                  │
│  Version:   Git (config/config.php อยู่ใน .gitignore)      │
└─────────────────────────────────────────────────────────┘
```

> **หมายเหตุ:** ส่วน Setup (ข้อ 6) ด้านล่างเป็นของแผน Laravel เดิม — ดูขั้นตอนติดตั้งจริงของ PHP ล้วนใน `README.md` แทน

### เหตุผลการเลือก (Why)

| เลือก | เหตุผล |
|-------|--------|
| **Laravel** | มี Auth/RBAC/ORM/Migration/Queue พร้อมใช้ + Eloquent ORM กัน SQL Injection อัตโนมัติ |
| **Inertia + Vue 3** | UI ลื่นแบบ SPA แต่ไม่ต้องสร้าง REST API แยก (ลดงานครึ่งหนึ่งสำหรับคนเดียว) |
| **PrimeVue** | มี DataTable/ฟอร์ม/ปฏิทิน พร้อมใช้ เหมาะ ERP |
| **MariaDB ตั้งแต่ต้น** | หลีกเลี่ยงการ migrate ทีหลัง + transaction (InnoDB) จำเป็นกับงานบัญชี |
| **XAMPP** | คุ้นเคย ติดตั้งง่าย มี Apache + MariaDB + PHP + phpMyAdmin ครบในตัว เริ่มเร็ว |

### ⚠️ ข้อควรระวังของ XAMPP (อ่านก่อน deploy)
- **XAMPP ห่างจาก production (Linux server) มากกว่า Docker** → เสี่ยงเจอปัญหา "ในเครื่องรันได้ แต่บน server พัง"
- **ต้องเช็กเวอร์ชัน PHP ของ XAMPP ให้ตรงกับ Laravel 12** (ต้องการ PHP 8.2 ขึ้นไป) และให้ตรงกับ server จริงที่จะ deploy
- XAMPP ให้ Apache/MariaDB/PHP แต่ **ไม่ได้ให้ Composer และ Node.js** ต้องติดตั้งแยกเอง
- **ก่อน deploy จริง:** ทดสอบบนสภาพแวดล้อมที่ใกล้ server (เช่น staging บน Linux) เพื่อจับปัญหาที่ XAMPP ซ่อนไว้

### ข้อควรเข้าใจเรื่อง Claude Cowork
- Cowork = **ผู้ช่วยเขียนโค้ด/ออกแบบ/แก้บั๊ก** ไม่ใช่เซิร์ฟเวอร์รันระบบจริง
- Database จริงอยู่ที่ **เครื่องผู้พัฒนา** (ผ่าน XAMPP/MariaDB)
- Flow: Cowork ช่วยเขียนโค้ด → รัน/ทดสอบที่เครื่อง (XAMPP) → deploy บน server จริง (VPS/องค์กร) เมื่อพร้อม

---

## 4. Roadmap การพัฒนา (ลำดับสำคัญ ห้ามสลับ)

> **หลักการ:** โมดูลที่ถูกพึ่งพาเยอะที่สุด ทำก่อน

### Phase 0: Foundation (รากฐาน)
1. ออกแบบ Database Schema/ERD ทั้งระบบบนกระดาษก่อน
2. วางมาตรฐาน: naming convention, รหัสเอกสาร, โครงสร้างโฟลเดอร์
3. ระบบ Authentication (Breeze จัดการให้แล้ว)
4. ระบบ RBAC — สิทธิ์ตามฝ่าย/ตำแหน่ง (ต้องรองรับ "act_on_behalf" ตั้งแต่ออกแบบ)
5. Audit Log กลาง (ต้องบันทึก `created_by` แยกจาก `employee_id` ได้)

### Phase 1: Master Data (ข้อมูลหลัก)
6. ทะเบียนลูกค้า (Customer)
7. ทะเบียนซัพพลายเออร์ (Vendor)
8. ทะเบียนสินค้า (Product) — เชื่อมกับคลังและขาย
9. ผังบัญชี (Chart of Accounts)

### Phase 2: Inventory (คลังสินค้า)
10. รับเข้าสินค้า (Goods Receipt)
11. ระบบติดตามสต็อก (stock movement)
12. เบิก/จ่าย/โอนย้าย
13. ตรวจนับ + ปรับยอด (Stock Count/Adjustment)

### Phase 3: Sales (ฝ่ายขาย) — หัวใจระบบ
14. ใบเสนอราคา → ใบสั่งขาย
15. เชื่อมการตัดสต็อกจาก Phase 2
16. ส่งของ + ออกบิล (เชื่อมไป Finance)

### Phase 4: Finance (บัญชีการเงิน)
17. ลูกหนี้ (AR) — รับข้อมูลบิลจาก Sales
18. เจ้าหนี้ (AP) — รับข้อมูลจากการรับเข้าสินค้า
19. สมุดรายวัน + ภาษี (VAT, หัก ณ ที่จ่าย)
20. รายงานการเงิน (งบกำไรขาดทุน, งบดุล, กระแสเงินสด)

### Phase 5: HR (ทรัพยากรบุคคล)
21. ทะเบียนพนักงาน
22a. ลงเวลา self-service
22b. การลา self-service
22c. **HR ทำแทน** (on-behalf) + รายงานการทำแทน
22d. ล็อกข้อมูลเมื่อปิดงวด (เชื่อม Payroll)
22e. **เช็คอินหน้างาน (Geofencing)** — GPS + ถ่ายรูป
23. Payroll + คอมมิชชั่น (ดึงข้อมูลที่ล็อกแล้ว + จาก Sales)

### Phase 6: Executive Dashboard (ผู้บริหาร)
24. รวบรวม KPI จากทุกโมดูล
25. กราฟและรายงานเชิงวิเคราะห์ + drill-down

---

## 5. Requirement พิเศษที่ตกลงไว้ (รายละเอียดสำคัญ)

### 5.1 HR ทำแทนพนักงานได้ (Act on Behalf)
ทุกตารางที่เกี่ยวกับเวลา/ลา ต้องแยก "เจ้าของข้อมูล" ออกจาก "ผู้บันทึก":

| ฟิลด์ | ความหมาย |
|-------|----------|
| `employee_id` | พนักงานที่ข้อมูลเป็นของเขา (เจ้าของจริง) |
| `created_by` | ใครเป็นคนกดบันทึก (ตัวเอง หรือ HR) |
| `entry_method` | `self` / `on_behalf` / `import` |
| `acted_for_reason` | เหตุผลที่ทำแทน (บังคับกรอกเมื่อ on_behalf) |
| `created_at` | เวลาที่บันทึกจริง |

**กฎธุรกิจที่ต้องกำหนดกับ HR จริง:**
- HR แก้ย้อนหลังได้กี่วัน (เช่น ไม่เกิน 30 วัน)
- ปิดงวด Payroll แล้ว = ล็อกห้ามแก้
- การทำแทนต้องระบุเหตุผลเสมอ
- แจ้งเตือนพนักงานเมื่อ HR ทำแทน (โปร่งใส)
- ปรับยอดวันลาควรมี approval ชั้นสอง

**สิทธิ์ใน RBAC:** พนักงาน (ตัวเอง) < หัวหน้างาน (ลูกทีม) < HR (ทั้งองค์กร)

### 5.2 เช็คอินหน้างาน (Geofencing Attendance)
สำหรับทีมออกไซต์งาน เช่น ติดตั้ง

**หลักการทำงาน:**
```
กดเช็คอิน → ดึง GPS (navigator.geolocation) + ถ่ายรูป
→ ส่งพิกัดดิบไป server → server คำนวณระยะ (Haversine)
→ อยู่ในรัศมี = ผ่าน / นอกรัศมี = pending_review
```

**กฎเหล็ก:** คำนวณระยะที่ **server เท่านั้น** ห้ามเชื่อผลจาก client (กันปลอม)

**ตาราง Work Sites (จุดหน้างาน):** `site_id`, `site_name`, `latitude`, `longitude`, `allowed_radius_m`, `assigned_team`

**ตาราง Site Check-ins:** `employee_id`, `site_id`, `checkin_lat`, `checkin_long`, `distance_from_site_m`, `gps_accuracy_m`, `photo_path`, `status` (`approved`/`out_of_range`/`pending_review`), `device_info`, `created_at`

**ความปลอดภัย:**
- ปลอม GPS → เก็บ accuracy + ใช้รูปถ่ายเป็นหลักฐานคู่
- รูปเก่า → บังคับกล้องสด (capture) ห้ามเลือกจากอัลบั้ม
- รูป/พิกัด = ข้อมูล PDPA → เก็บนอก public folder + จำกัดสิทธิ์เข้าถึง + แจ้งขอความยินยอม + กำหนดอายุข้อมูล

### 5.3 แผนที่ (Maps)
- **GPS เช็คระยะ:** ใช้ `navigator.geolocation` (ฟรี 100%) + คำนวณ Haversine ที่ server
- **แสดงแผนที่/ปักหมุด:** เลือกได้ 2 ทาง
  - **Leaflet + OpenStreetMap** — ฟรี 100% ไม่ต้องผูกบัตร (แนะนำสำหรับงานนี้)
  - **Google Maps API** — สวยกว่า แต่ต้องผูกบัตรเครดิตเสมอ (มีโควต้าฟรี ~$200 เครดิต + 28,500 map loads/เดือน)
- **หมายเหตุ Google Maps:** ถ้าใช้ ต้องตั้ง Budget Alert + จำกัด API Key (HTTP Referrer + เฉพาะ Maps API) กันค่าใช้จ่ายพุ่ง/Key รั่ว
- **ประเมินการใช้งานจริง:** โปรเจกต์นี้ใช้แผนที่แค่ฝั่ง HR/Admin (ไม่ใช่หน้าสาธารณะ) → โอกาสสูงที่จะใช้ฟรีตลอดแม้ใช้ Google

---

## 6. ขั้นตอน Setup Environment (ทำตามลำดับ)

> เขียนสำหรับ Windows + XAMPP

### Step 0: Prerequisites (ติดตั้งก่อน)
1. **XAMPP** — ให้ Apache + MariaDB + PHP + phpMyAdmin
   - ดาวน์โหลดเวอร์ชันที่มี **PHP 8.2 ขึ้นไป** (Laravel 12 ต้องการ)
2. **Composer** — ตัวจัดการ package ของ PHP (ติดตั้งแยก ไม่มากับ XAMPP)
3. **Node.js** — สำหรับ build frontend (Vue) ติดตั้งแยก
4. **Git** — version control

**เช็กว่าติดตั้งสำเร็จ:**
```bash
php --version        # ต้อง 8.2+ (ชี้ไปที่ php ของ XAMPP)
composer --version
node --version
npm --version
git --version
```

> **Why ต้องเช็กเวอร์ชัน PHP:** XAMPP บางรุ่นมาพร้อม PHP เก่ากว่าที่ Laravel 12 ต้องการ ถ้า PHP ต่ำกว่า 8.2 จะติดตั้ง Laravel ไม่ได้ ต้องโหลด XAMPP รุ่นที่ PHP ใหม่พอ และตั้ง PATH ให้ `php` ใน terminal ชี้ไปที่ PHP ของ XAMPP (เช่น `C:\xampp\php`)

### Step 1: เปิด XAMPP + สร้าง Database
1. เปิด **XAMPP Control Panel** → Start ที่ **Apache** และ **MySQL** (จริงๆ คือ MariaDB)
2. เปิด `http://localhost/phpmyadmin`
3. สร้าง database ใหม่ชื่อ `erp_system`
   - **Collation:** เลือก `utf8mb4_unicode_ci` (รองรับภาษาไทย + emoji)

> **Why ตั้ง collation ตอนสร้าง:** ถ้าสร้าง database โดยไม่ระบุ collation จะได้ค่า default ที่อาจไม่ใช่ utf8mb4 ทำให้ภาษาไทยเพี้ยน การตั้งให้ถูกตั้งแต่สร้างง่ายกว่าแก้ทีหลังมาก

### Step 2: สร้างโปรเจกต์ Laravel
ไปที่โฟลเดอร์ที่ต้องการเก็บโปรเจกต์ (เช่น `C:\xampp\htdocs` หรือโฟลเดอร์แยกก็ได้) แล้วรัน:
```bash
composer create-project laravel/laravel erp-system
cd erp-system
```

> **หมายเหตุ:** ไม่จำเป็นต้องวางใน `htdocs` เพราะเราจะรันด้วย `php artisan serve` (เซิร์ฟเวอร์ในตัวของ Laravel) ไม่ได้ใช้ Apache ของ XAMPP โดยตรง — ใช้ XAMPP แค่ส่วน **MariaDB** เป็นหลัก วิธีนี้ง่ายและตรงกับวิธีพัฒนา Laravel มาตรฐานกว่า

### Step 3: ตั้งค่า .env
เปิดไฟล์ `.env` แก้ส่วน database ให้ตรงกับ XAMPP:
```env
DB_CONNECTION=mariadb     # ★ ต้องเป็น mariadb ไม่ใช่ mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_system
DB_USERNAME=root          # ★ XAMPP default คือ root (ดูหมายเหตุความปลอดภัย)
DB_PASSWORD=              # ★ XAMPP default รหัสว่าง (ต้องตั้งรหัส — ดูข้อ 8)
```
ตรวจ `config/database.php` ส่วน `mariadb`:
```php
'charset'   => 'utf8mb4',           // รองรับภาษาไทย + emoji
'collation' => 'utf8mb4_unicode_ci',
```

> **Why `DB_CONNECTION=mariadb`:** Laravel 11+ มี driver เฉพาะ MariaDB แยกจาก MySQL การตั้งเป็น `mariadb` ทำให้ใช้ฟีเจอร์เฉพาะของ MariaDB ได้เต็มที่
>
> **⚠️ ความปลอดภัย:** XAMPP default ใช้ user `root` รหัสว่าง ซึ่ง **ไม่ปลอดภัย** สำหรับ ERP ที่มีข้อมูลการเงิน ดูวิธีตั้งค่าให้ปลอดภัยในข้อ 8

### Step 4: App Key + Migration
```bash
php artisan key:generate
php artisan migrate
```
ถ้า migrate สำเร็จ = Laravel เชื่อมต่อ MariaDB (XAMPP) ได้แล้ว 🎉

> **Why key:generate:** Laravel ใช้ APP_KEY เข้ารหัส session/cookie/ข้อมูลละเอียดอ่อน ขาดไม่ได้

### Step 5: Frontend (Vue 3 + Inertia)
```bash
composer require laravel/breeze --dev
php artisan breeze:install vue
npm install
npm run dev
```

### Step 6: รันเซิร์ฟเวอร์ + เปิดเบราว์เซอร์
เปิด terminal **2 หน้าต่าง** (ทำงานคู่กัน):
```bash
# หน้าต่างที่ 1: Laravel backend
php artisan serve

# หน้าต่างที่ 2: frontend dev server (build Vue real-time)
npm run dev
```
แล้วเปิด browser ไปที่:
```
http://localhost:8000
```

> **Why ต้องเปิด 2 หน้าต่าง:** `php artisan serve` รัน backend (ปกติพอร์ต 8000) ส่วน `npm run dev` คอย build ไฟล์ Vue แบบ real-time ตอน dev ต้องรันทั้งคู่พร้อมกัน (ต่างจาก Sail ที่จัดการให้ในคำสั่งเดียว)

### Step 7: Git (ป้องกันรหัสหลุด)
```bash
git init
cat .gitignore | grep .env     # ★ ต้องเห็น .env ในนี้
git add .
git commit -m "Initial commit: Laravel 12 + XAMPP/MariaDB + Inertia/Vue"
```

---

## 7. คำสั่งที่ใช้บ่อย (Cheat Sheet)

| คำสั่ง | ความหมาย |
|--------|----------|
| `php artisan serve` | รัน backend (พอร์ต 8000) |
| `npm run dev` | รัน frontend dev server (รันคู่กับ serve) |
| `php artisan migrate` | รัน migration (สร้าง/อัปเดตตาราง) |
| `php artisan make:model ...` | สร้าง model/controller ฯลฯ |
| `composer require ...` | ติดตั้ง package PHP |
| `npm run build` | build frontend สำหรับ production |

**ก่อนเริ่มงานทุกครั้ง:** เปิด XAMPP Control Panel → Start **Apache** + **MySQL (MariaDB)** ก่อนเสมอ ไม่งั้นเชื่อมฐานข้อมูลไม่ได้

**เชื่อม phpMyAdmin:** เปิด `http://localhost/phpmyadmin` (มากับ XAMPP)
**เชื่อม HeidiSQL/DBeaver (ถ้าต้องการ):** Host=`127.0.0.1`, Port=`3306`, DB=`erp_system`, User=`root` (หรือ user เฉพาะแอปตามข้อ 8)

---

## 8. Checklist ความปลอดภัยฐานข้อมูล (ตั้งแต่ setup)

> **สำคัญเป็นพิเศษกับ XAMPP** เพราะ default ตั้งค่าหละหลวมเพื่อความสะดวก ไม่เหมาะกับข้อมูลจริง

- [ ] **ตั้งรหัสให้ user `root` ของ MariaDB** — XAMPP default รหัสว่าง ต้องตั้งรหัสแข็งแรงทันที (ผ่าน phpMyAdmin → User accounts → Edit privileges → Change password)
- [ ] **สร้าง user เฉพาะแอป** — อย่าให้แอปเชื่อมด้วย `root` สร้าง user เช่น `erp_app` ที่มีสิทธิ์เฉพาะ database `erp_system` แล้วอัปเดต `.env` ให้ใช้ user นี้
- [ ] charset = `utf8mb4` (รองรับภาษาไทย) — ตั้งตั้งแต่สร้าง database
- [ ] Storage Engine = InnoDB (รองรับ transaction + foreign key — จำเป็นกับบัญชี) — เป็น default ของ MariaDB อยู่แล้ว แต่อย่าเผลอเปลี่ยน
- [ ] `.env` อยู่ใน `.gitignore` (เช็กก่อน commit แรก)
- [ ] ห้าม hardcode รหัสในโค้ด — ใช้ `.env` เสมอ
- [ ] **อย่าเปิด phpMyAdmin สู่อินเทอร์เน็ต** — ใช้เฉพาะ localhost ตอน dev เท่านั้น

> **Why เรื่อง root รหัสว่างอันตรายมาก:** XAMPP ออกแบบมาเพื่อความสะดวกในการเรียนรู้ จึงตั้ง root ไม่มีรหัส แต่สำหรับ ERP ที่จะมีข้อมูลการเงิน/ข้อมูลบุคคล (PDPA) การปล่อยไว้แบบนี้คือช่องโหว่ร้ายแรง แม้จะรันแค่ในเครื่อง dev ก็ควรตั้งรหัสและสร้าง user เฉพาะแอปตั้งแต่แรก เพื่อสร้างนิสัยที่ถูกต้องและกันพลาดตอนนำขึ้น production

---

## 9. สิ่งที่ต้องทำต่อ (Next Steps)

1. **Setup environment** ตามข้อ 6 ให้เสร็จ + ผ่าน checklist ข้อ 8
2. **เริ่ม Phase 0:**
   - ออกแบบ ERD (users, roles, permissions, audit_log)
   - เลือก package RBAC (แนะนำ: `spatie/laravel-permission`)
   - วางโครงสร้างโฟลเดอร์สำหรับ multi-module (บัญชี/พัสดุ/HR/ขาย)
   - ออกแบบ audit_log ให้รองรับ act_on_behalf
3. **ปิด Phase 0 ให้เสร็จ (test ผ่าน)** ก่อนไป Phase 1

> **หมายเหตุเรื่อง Redis/Mailpit:** ตอนใช้ Sail เราวางแผนมี Redis (cache/queue) และ Mailpit (ทดสอบอีเมล) มาด้วย แต่ XAMPP ไม่มีให้ — ช่วงเริ่มต้นยังไม่จำเป็น เพราะ Laravel ใช้ค่า default ได้: queue ใช้ `database` driver, อีเมลใช้ `log` driver (เขียนลง log แทนส่งจริง) เมื่อถึง Phase 5 (คำนวณเงินเดือน/แจ้งเตือน HR) ที่ต้องใช้ queue จริงจังและทดสอบอีเมล ค่อยพิจารณาติดตั้ง Redis แยก หรือใช้ Mailtrap/บริการอีเมลทดสอบแทน

---

## 10. ข้อเตือนใจสำหรับการพัฒนาคนเดียว

- **อย่าเสียเวลาเลือกเครื่องมือนานเกินไป** — ตัดสินใจแล้วลงมือ
- **Git commit บ่อยๆ** — ไม่มีใครช่วยกู้โค้ด ต้องพึ่ง Git ย้อนเวอร์ชันเอง
- **ทำทีละ Phase** — อย่าโดดข้ามไปทำโมดูลที่ชอบก่อน จะพังภายหลัง
- **ทุกครั้งที่เพิ่ม requirement ใหม่** — กลับมาอัปเดตไฟล์ context.md นี้

---

*เอกสารนี้ควรอัปเดตทุกครั้งที่มีการตัดสินใจสำคัญหรือเปลี่ยน requirement เพื่อให้เป็น Single Source of Truth ที่เชื่อถือได้เสมอ*
