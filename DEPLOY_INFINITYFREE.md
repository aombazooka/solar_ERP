# 🚀 คู่มือ Deploy SolarSell ขึ้น InfinityFree

> โฮสต์ฟรี InfinityFree **ไม่มี SSH / Composer / command line** — ทุกอย่างทำผ่าน
> แผงควบคุม (vPanel), phpMyAdmin และ FTP เท่านั้น คู่มือนี้ปรับให้ตรงกับข้อจำกัดนั้นแล้ว

โดเมนตัวอย่าง: `https://tps.freedev.app` · บัญชี `if0_42095305`

---

## ขั้นที่ 1 — สร้างฐานข้อมูล MySQL

1. เข้า **vPanel → MySQL Databases**
2. สร้างฐานข้อมูลใหม่ เช่นชื่อ `solarsell` → ระบบจะตั้งชื่อจริงเป็น `if0_42095305_solarsell`
3. จดค่าต่อไปนี้ไว้ (ใช้ในขั้นที่ 3):
   - **MySQL Hostname** เช่น `sqlXXX.infinityfree.com` ⚠️ ไม่ใช่ `localhost`
   - **Database name** `if0_42095305_solarsell`
   - **Username** `if0_42095305`
   - **Password** (รหัสที่ตั้งตอนสร้าง)

## ขั้นที่ 2 — Import โครงสร้าง + ข้อมูลตั้งต้น (ครั้งเดียว)

1. ใน vPanel กด **phpMyAdmin** ของฐานข้อมูลที่เพิ่งสร้าง
2. เลือกฐานข้อมูล `if0_42095305_solarsell` ทางซ้าย
3. แท็บ **Import → Choose File →** เลือกไฟล์ **`db/install_all.sql`** (ไฟล์รวมทุก migration ตามลำดับแล้ว) → **Go**
4. รอจนขึ้น "Import has been successfully finished" — ควรได้ **44 ตาราง**

> 💡 ถ้าไฟล์ใหญ่เกินลิมิตอัปโหลดของ phpMyAdmin ให้ import ทีละไฟล์จากโฟลเดอร์ `db/`
> ตามลำดับ: `schema.sql` → `seed.sql` → `phase*` (เรียงตามที่อยู่ใน `install_all.sql`)

## ขั้นที่ 3 — ตั้งค่า config

1. คัดลอก `config/config.production.example.php` เป็น **`config/config.php`**
2. แก้ค่าในบล็อก `db` ให้ตรงกับขั้นที่ 1 (host/name/user/pass)
3. ตั้ง `base_url => ''` (วางที่รากโดเมน) · `env => 'production'` · `debug => false`

## ขั้นที่ 4 — อัปโหลดไฟล์ขึ้นโฮสต์ (FTP)

1. vPanel → **FTP Accounts** เอา host/user/pass มาเชื่อมด้วย **FileZilla**
2. อัปโหลด **เนื้อหาทั้งหมดของโปรเจกต์** เข้าไปใน **`htdocs/`** (ให้ `index.php` อยู่ที่ `htdocs/index.php`)
3. ไฟล์/โฟลเดอร์ที่ **ไม่ต้องอัปโหลด**: `.git/`, `.claude/`, `tests/`, `tools/`, `db/`, `*.docx`, `context.md`
   (โดยเฉพาะ `db/` กับ `tools/` ลบทิ้งหลัง import เพื่อความปลอดภัย)
4. ตรวจว่ามีโฟลเดอร์ `storage/uploads/` และ `storage/logs/` (สร้างถ้ายังไม่มี — ต้องเขียนไฟล์ได้)

## ขั้นที่ 5 — เปิด HTTPS (จำเป็น)

- vPanel → **Free SSL Certificates** ออกใบรับรองให้ `tps.freedev.app` แล้วบังคับ redirect เป็น https
- ⚠️ **กล้องถ่ายรูปเช็คอิน + GPS ทำงานเฉพาะบน HTTPS** เท่านั้น

## ขั้นที่ 6 — เข้าระบบครั้งแรก

เปิด `https://tps.freedev.app/login.php`

| ชื่อผู้ใช้ | รหัสผ่านเริ่มต้น |
|-----------|------------------|
| `admin`   | `password`       |

> รหัสผ่านเริ่มต้นของทุกบัญชีคือ **`password`** (บนเครื่อง dev เราตั้งให้ = ชื่อผู้ใช้ ด้วยสคริปต์ CLI
> แต่บนโฮสต์ไม่มี CLI จึงเป็น `password` ตามค่า seed)

**หลังเข้าได้ครั้งแรก ทำทันที:**
1. โปรไฟล์ (มุมซ้ายล่าง) → **เปลี่ยนรหัสผ่าน** ของ admin
2. เมนู **ผู้ใช้งาน** → รีเซ็ตรหัสผ่าน/ปิดบัญชีทดสอบที่ไม่ใช้ (sales/hr/staff…)
3. เมนู **ตั้งค่าบริษัท** → กรอกชื่อ/ที่อยู่/เลขผู้เสียภาษีจริง (ขึ้นบนเอกสาร)
4. เมนู **พนักงาน** → เพิ่มพนักงานจริง (กรอกชื่ออังกฤษ → ได้ username อัตโนมัติ)

---

## ⚠️ ข้อจำกัด/ข้อควรระวังบน InfinityFree

| เรื่อง | รายละเอียด |
|--------|-----------|
| ไม่มี CLI | รัน `tests/run.php` / `setup_usernames.php` / `gen_manual.php` บนโฮสต์ไม่ได้ — ใช้บนเครื่อง dev เท่านั้น |
| PHP version | ตั้งเป็น **PHP 8.x** ใน vPanel (ต้องมี `pdo_mysql`, `mbstring`, `fileinfo`) |
| host ฐานข้อมูล | ต้องใช้ชื่อ `sqlXXX.infinityfree.com` **ห้ามใช้ `127.0.0.1`/`localhost`** |
| ลิมิตการใช้งาน | แผนฟรีมีลิมิต CPU/hits ต่อวัน + ไม่มี cron — เหมาะ "ทดลอง/เดโม" ไม่เหมาะใช้งานหนัก |
| `.htaccess` | LiteSpeed ของ InfinityFree อ่าน `.htaccess` ได้ — โฟลเดอร์ `app/`,`config/`,`storage/` ถูกบล็อกการเข้าตรงอยู่แล้ว |
| สำรองข้อมูล | ใช้ phpMyAdmin → **Export** เก็บไฟล์ `.sql` เป็นระยะ (แผนฟรีไม่มี backup อัตโนมัติ) |

> สำหรับใช้งานจริงจัง (ลูกค้าหลายราย/ข้อมูลสำคัญ) แนะนำโฮสต์ที่มี SSH + PHP 8.2 +
> ใบรับรอง SSL + สำรองข้อมูลอัตโนมัติ (เช่น VPS เล็ก ๆ) แล้วใช้เช็คลิสต์ใน `DEPLOY_CHECKLIST.md`
