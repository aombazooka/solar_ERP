<?php
/**
 * helpers.php — ฟังก์ชันช่วยที่ใช้ทั่วระบบ
 */

declare(strict_types=1);

/** escape output กัน XSS — ใช้ทุกครั้งที่พ่นข้อมูลผู้ใช้ลง HTML */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** สร้าง URL เต็มจาก path (เติม base_url ให้) */
function url(string $path = ''): string
{
    $base = rtrim($GLOBALS['config']['app']['base_url'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

/** redirect แล้วจบ script */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** redirect ไป URL ตรงๆ (ไม่เติม base) */
function redirectTo(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ─────────────────────────────────────────────────────────
//  CSRF protection
// ─────────────────────────────────────────────────────────

/** คืน token ปัจจุบัน (สร้างใหม่ถ้ายังไม่มี) */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** พิมพ์ hidden input สำหรับใส่ในฟอร์ม */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** ตรวจ token จากฟอร์ม — เรียกต้นทุก POST */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('CSRF token ไม่ถูกต้องหรือหมดอายุ กรุณาโหลดหน้าใหม่');
    }
}

// ─────────────────────────────────────────────────────────
//  flash message (แจ้งเตือนข้ามหน้า)
// ─────────────────────────────────────────────────────────

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** ดึง flash ทั้งหมดแล้วล้าง */
function flash_pull(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/**
 * บันทึกรูปที่อัปโหลด (PDPA: เก็บใน storage นอก public) → คืน path สัมพัทธ์ หรือ null
 */
function save_uploaded_image(string $field, string $subdir = 'checkins'): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime]) || $_FILES[$field]['size'] > 8 * 1024 * 1024) {
        return null;
    }
    $dir = __DIR__ . '/../storage/uploads/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $fname = 'chk_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $fname)) {
        return 'storage/uploads/' . $subdir . '/' . $fname;
    }
    return null;
}

/** อ่านค่า input แบบปลอดภัย (trim + default) */
function input(string $key, string $default = ''): string
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($val) ? trim($val) : $default;
}

/** ข้อมูลบริษัท (cache ต่อ request) — fallback ถ้าตารางยังไม่มี */
function company(): array
{
    static $co = null;
    if ($co !== null) return $co;
    $defaults = ['name'=>'SolarSell','legal_name'=>'','address'=>'','phone'=>'','email'=>'','tax_id'=>'','logo_emoji'=>'☀️'];
    try {
        $row = Database::one('SELECT * FROM company_profile WHERE id=1');
        $co = $row ? array_merge($defaults, $row) : $defaults;
    } catch (\Throwable $e) {
        $co = $defaults;
    }
    return $co;
}

/**
 * คำนวณ VAT ตามโหมด → ['subtotal','vat','total','rate_label']
 * @param float  $lines    ผลรวมราคาตามรายการ (ตามที่กรอก)
 * @param float  $discount ส่วนลด
 * @param string $mode     exclude | include | none
 */
function vat_calc(float $lines, float $discount, string $mode): array
{
    $afterDisc = max(0, $lines - $discount);
    if ($mode === 'include') {            // ราคารวม VAT แล้ว → ถอดออก
        $ex  = round($afterDisc / 1.07, 2);
        return ['subtotal' => $lines, 'vat' => round($afterDisc - $ex, 2), 'total' => $afterDisc, 'mode' => 'include'];
    }
    if ($mode === 'none') {               // ไม่มี VAT
        return ['subtotal' => $lines, 'vat' => 0.0, 'total' => $afterDisc, 'mode' => 'none'];
    }
    $vat = round($afterDisc * 0.07, 2);   // exclude (ค่าเริ่มต้น) → บวกเพิ่ม
    return ['subtotal' => $lines, 'vat' => $vat, 'total' => $afterDisc + $vat, 'mode' => 'exclude'];
}

/** ป้ายกำกับโหมด VAT */
function vat_mode_label(string $mode): string
{
    return ['exclude' => 'ราคาไม่รวม VAT (+7%)', 'include' => 'ราคารวม VAT แล้ว', 'none' => 'ไม่มี VAT'][$mode] ?? $mode;
}

/** จัดรูปแบบเงินบาท */
function baht($amount): string
{
    return '฿' . number_format((float) $amount, 2);
}

/** แปลงจำนวนเต็มเป็นคำอ่านภาษาไทย (ไม่รวมหน่วย) */
function thai_num_words(int $n): string
{
    if ($n === 0) return '';
    if ($n >= 1000000) {
        $m = intdiv($n, 1000000); $rest = $n % 1000000;
        return thai_num_words($m) . 'ล้าน' . ($rest > 0 ? thai_num_words($rest) : '');
    }
    $digits = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    $places = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];
    $s = (string) $n; $len = strlen($s); $out = '';
    for ($i = 0; $i < $len; $i++) {
        $d = (int) $s[$i]; $place = $len - $i - 1;
        if ($d === 0) continue;
        if ($place === 1 && $d === 1)        $out .= 'สิบ';
        elseif ($place === 1 && $d === 2)    $out .= 'ยี่สิบ';
        elseif ($place === 0 && $d === 1 && $len > 1) $out .= 'เอ็ด';
        else $out .= $digits[$d] . $places[$place];
    }
    return $out;
}

/** จำนวนเงินเป็นข้อความภาษาไทย เช่น "หนึ่งพันบาทถ้วน" */
function bahttext($amount): string
{
    $amount = round((float) $amount, 2);
    $baht = (int) floor($amount);
    $satang = (int) round(($amount - $baht) * 100);
    $txt = $baht === 0 ? 'ศูนย์บาท' : thai_num_words($baht) . 'บาท';
    $txt .= $satang === 0 ? 'ถ้วน' : thai_num_words($satang) . 'สตางค์';
    return $txt;
}

/** อ่านเลขหน้าปัจจุบันจาก ?page= (อย่างน้อย 1) */
function current_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

/**
 * สร้าง HTML ตัวแบ่งหน้า
 * @param int    $total    จำนวนแถวทั้งหมด
 * @param int    $perPage
 * @param int    $page     หน้าปัจจุบัน
 * @param string $baseUrl  เช่น url('customers.php') — จะต่อ ?page=N (รักษา query เดิม)
 */
function render_pager(int $total, int $perPage, int $page, string $baseUrl): string
{
    $pages = (int) ceil($total / max(1, $perPage));
    if ($pages <= 1) return '';

    // รักษา query string เดิม (ยกเว้น page)
    $qs = $_GET; unset($qs['page']);
    $sep = $qs ? '?' . http_build_query($qs) . '&' : '?';
    $link = fn($p, $label, $active = false, $disabled = false) =>
        $disabled
            ? '<span class="pager-btn disabled">' . $label . '</span>'
            : '<a class="pager-btn' . ($active ? ' active' : '') . '" href="' . e($baseUrl . $sep . 'page=' . $p) . '">' . $label . '</a>';

    $html = '<div class="pager">';
    $html .= '<span class="pager-info">หน้า ' . $page . ' / ' . $pages . ' (รวม ' . number_format($total) . ')</span>';
    $html .= $link(max(1, $page - 1), '‹', false, $page <= 1);
    $start = max(1, $page - 2); $end = min($pages, $page + 2);
    if ($start > 1) $html .= $link(1, '1') . ($start > 2 ? '<span class="pager-dots">…</span>' : '');
    for ($i = $start; $i <= $end; $i++) $html .= $link($i, (string) $i, $i === $page);
    if ($end < $pages) $html .= ($end < $pages - 1 ? '<span class="pager-dots">…</span>' : '') . $link($pages, (string) $pages);
    $html .= $link(min($pages, $page + 1), '›', false, $page >= $pages);
    $html .= '</div>';
    return $html;
}

/**
 * สร้างเลขเอกสารถัดไป เช่น QT-2569-0001 (ปี พ.ศ. + running 4 หลัก)
 * $table ถูกกำหนดภายในระบบเท่านั้น (ไม่ใช่ input ผู้ใช้) จึง interpolate ได้
 */
function next_doc_no(string $prefix, string $table): string
{
    $year = (int) date('Y') + 543;
    $like = "{$prefix}-{$year}-%";
    $last = Database::scalar(
        "SELECT doc_no FROM {$table} WHERE doc_no LIKE :l ORDER BY doc_no DESC LIMIT 1",
        ['l' => $like]
    );
    $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;
    return sprintf('%s-%d-%04d', $prefix, $year, $seq);
}

/** วันที่ไทยสั้น เช่น 01/06/2569 */
function thai_date_short(?string $datetime): string
{
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    return date('d/m/', $ts) . (date('Y', $ts) + 543);
}

/** วันที่ภาษาไทย เช่น "วันอาทิตย์ที่ 1 มิถุนายน 2568" */
function thai_date(?int $ts = null): string
{
    $ts ??= time();
    $days   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $w = (int) date('w', $ts);
    $d = (int) date('j', $ts);
    $m = (int) date('n', $ts);
    $y = (int) date('Y', $ts) + 543;   // พ.ศ.
    return "วัน{$days[$w]}ที่ {$d} {$months[$m]} {$y}";
}
