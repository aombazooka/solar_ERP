<?php
/** export.php — ส่งออกข้อมูลเป็น CSV (UTF-8 BOM รองรับ Excel ภาษาไทย) */
require_once __DIR__ . '/app/bootstrap.php';
Auth::require();

$type = input('type');

// แต่ละชนิด: [permission, ['หัวคอลัมน์'...], SQL]
$exports = [
    'customers' => ['customer.view', ['รหัส','ชื่อ','ประเภท','จังหวัด','เบอร์โทร','อีเมล'],
        "SELECT code, name, type, province, phone, email FROM customers ORDER BY id"],
    'products' => ['inventory.view', ['SKU','ชื่อ','หมวด','ยี่ห้อ','หน่วย','ต้นทุน','ราคาขาย','คงเหลือ'],
        "SELECT sku, name, category, brand, unit, cost_price, sell_price, stock_qty FROM products ORDER BY category, id"],
    'vendors' => ['inventory.view', ['รหัส','ชื่อ','ผู้ติดต่อ','เบอร์โทร','อีเมล'],
        "SELECT code, name, contact, phone, email FROM vendors ORDER BY id"],
    'invoices' => ['finance.view', ['เลขที่','ลูกค้า','ยอดรวม','ชำระแล้ว','คงค้าง','สถานะ','วันที่ออก','ครบกำหนด'],
        "SELECT i.doc_no, c.name, i.total, i.paid_amount, (i.total-i.paid_amount) AS outstanding, i.status, i.issued_at, i.due_date
         FROM invoices i JOIN customers c ON c.id=i.customer_id ORDER BY i.id"],
    'payments' => ['finance.view', ['เลขที่','ใบแจ้งหนี้','ลูกค้า','วิธี','วันที่','จำนวน'],
        "SELECT p.doc_no, i.doc_no AS inv, c.name, p.method, p.paid_at, p.amount
         FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN customers c ON c.id=p.customer_id ORDER BY p.id"],
    'leads' => ['sales.view', ['ชื่อ','เบอร์โทร','ที่มา','สนใจ','มูลค่าคาด','สถานะ'],
        "SELECT name, phone, source, interest_system, est_value, status FROM leads ORDER BY id DESC"],
];

if (!isset($exports[$type])) { http_response_code(404); exit('ไม่รองรับการส่งออกชนิดนี้'); }
[$perm, $headers, $sql] = $exports[$type];
Auth::requireCan($perm);

$rows = Database::all($sql);

// ส่ง CSV
$filename = "solarsell_{$type}_" . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");          // BOM → Excel อ่านไทยถูก
fputcsv($out, $headers);
foreach ($rows as $r) {
    fputcsv($out, array_values($r));
}
fclose($out);
exit;
