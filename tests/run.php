<?php
/**
 * tests/run.php — ชุดทดสอบอัตโนมัติ (รันผ่าน CLI)
 * ───────────────────────────────────────────────────────────
 *   C:\xampp\php\php.exe tests\run.php
 *
 * ครอบคลุม: unit (Geo/helpers), service guard (Stock), DB invariants
 * ทั้งหมดไม่เขียนข้อมูลถาวร (write-test ใช้ transaction rollback)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/../app/bootstrap.php';

$passed = 0; $failed = 0; $failures = [];

function check(string $name, bool $cond, string $detail = ''): void {
    global $passed, $failed, $failures;
    if ($cond) { $passed++; echo "  \033[32m✓\033[0m {$name}\n"; }
    else { $failed++; $failures[] = $name . ($detail ? " — {$detail}" : ''); echo "  \033[31m✗\033[0m {$name}" . ($detail ? " — {$detail}" : '') . "\n"; }
}
function approx(float $a, float $b, float $eps = 0.01): bool { return abs($a - $b) < $eps; }
function section(string $t): void { echo "\n\033[1m{$t}\033[0m\n"; }

echo "═══ SolarSell Test Suite ═══\n";

// ─────────────────────────────────────────────
section('1) Geo — Haversine (server-side)');
check('ระยะจุดเดียวกัน = 0', approx(Geo::distanceMeters(13.75, 100.5, 13.75, 100.5), 0.0, 0.5));
$d = Geo::distanceMeters(13.7563, 100.5018, 13.7563, 100.5118); // ~1.08 km ตามแนวลองจิจูด
check('1 องศาลองจิจูด ≈ ระยะสมเหตุผล (1000–1200ม.)', $d > 1000 && $d < 1200, round($d) . ' ม.');
[$dist, $st] = Geo::evaluate(13.75, 100.50, 13.75, 100.50, 150, 10);
check('อยู่ในรัศมี → approved', $st === 'approved', "dist={$dist}");
[$dist2, $st2] = Geo::evaluate(13.80, 100.60, 13.75, 100.50, 150, 10);
check('นอกรัศมี → out_of_range', $st2 === 'out_of_range', "dist=" . round($dist2));
[$dist3, $st3] = Geo::evaluate(13.7501, 100.5001, 13.75, 100.50, 150, 250);
check('GPS แม่นยำต่ำ (>100ม.) → pending_review', $st3 === 'pending_review');

// ─────────────────────────────────────────────
section('2) Helpers');
check('baht() จัดรูปแบบเงิน', baht(1234.5) === '฿1,234.50', baht(1234.5));
check('thai_date_short() เป็น พ.ศ.', str_contains(thai_date_short('2024-01-15'), '2567'), thai_date_short('2024-01-15'));
check('e() กัน XSS', e('<script>') === '&lt;script&gt;');
check('next_doc_no() รูปแบบถูกต้อง', (bool) preg_match('/^QT-\d{4}-\d{4}$/', next_doc_no('QT', 'quotations')));

// ─────────────────────────────────────────────
section('3) Stock — กันสต็อกติดลบ (tx rollback)');
$pdo = Database::pdo();
$prod = Database::one("SELECT id, stock_qty FROM products WHERE category<>'service' AND stock_qty < 100000 LIMIT 1");
if ($prod) {
    $pdo->beginTransaction();
    $threw = false;
    try { Stock::move((int)$prod['id'], 'issue', -999999, ['note' => 'TEST']); }
    catch (\Throwable $e) { $threw = true; }
    $pdo->rollBack();
    check('ตัดสต็อกเกินคงเหลือ → โยน exception', $threw);
} else { check('ตัดสต็อกเกินคงเหลือ → โยน exception', false, 'ไม่มีสินค้าให้ทดสอบ'); }

// ─────────────────────────────────────────────
section('4) DB Invariants (read-only)');

// 4.1 stock_qty ตรงกับ ledger ล่าสุด
$bad = Database::all(
    "SELECT p.id, p.sku, p.stock_qty, lm.balance_after
     FROM products p
     JOIN ( SELECT sm.product_id, sm.balance_after
            FROM stock_movements sm
            JOIN ( SELECT product_id, MAX(id) AS mx FROM stock_movements GROUP BY product_id ) t
              ON t.product_id=sm.product_id AND t.mx=sm.id ) lm ON lm.product_id=p.id
     WHERE p.stock_qty <> lm.balance_after"
);
check('stock_qty = balance_after ล่าสุดในทุกสินค้า', count($bad) === 0, count($bad) . ' สินค้าไม่ตรง');

// 4.2 invoices: paid <= total และ sum(payments) = paid_amount
$badInv = Database::scalar("SELECT COUNT(*) FROM invoices WHERE paid_amount > total + 0.001");
check('ไม่มีใบแจ้งหนี้ที่จ่ายเกินยอด', (int)$badInv === 0);
$payMismatch = Database::all(
    "SELECT i.id, i.paid_amount, COALESCE(SUM(p.amount),0) AS sump
     FROM invoices i LEFT JOIN payments p ON p.invoice_id=i.id
     GROUP BY i.id HAVING ABS(i.paid_amount - sump) > 0.01"
);
check('paid_amount = ผลรวมการรับชำระจริง', count($payMismatch) === 0, count($payMismatch) . ' ใบไม่ตรง');

// 4.3 vendor_bills: paid <= total
$badAp = Database::scalar("SELECT COUNT(*) FROM vendor_bills WHERE paid_amount > total + 0.001");
check('ไม่มีใบเจ้าหนี้ที่จ่ายเกินยอด', (int)$badAp === 0);

// 4.4 invoice status สอดคล้องกับยอด
$badStatus = Database::all(
    "SELECT id, status, total, paid_amount FROM invoices
     WHERE (status='paid' AND paid_amount < total - 0.01)
        OR (status='unpaid' AND paid_amount > 0.01)"
);
check('สถานะใบแจ้งหนี้สอดคล้องกับยอดชำระ', count($badStatus) === 0, count($badStatus) . ' ใบไม่สอดคล้อง');

// 4.5 journal entries สมดุล (debit = credit)
$unbal = Database::all(
    "SELECT je.id, SUM(jl.debit) AS d, SUM(jl.credit) AS c
     FROM journal_entries je JOIN journal_lines jl ON jl.entry_id=je.id
     GROUP BY je.id HAVING ABS(SUM(jl.debit)-SUM(jl.credit)) > 0.01"
);
check('ทุกรายการสมุดรายวันสมดุล (เดบิต=เครดิต)', count($unbal) === 0, count($unbal) . ' รายการไม่สมดุล');

// 4.6 quotation total = (subtotal - discount) * 1.07
$badQt = Database::all(
    "SELECT id, subtotal, discount, vat, total FROM quotations
     WHERE ABS(total - ((subtotal-discount) + ROUND((subtotal-discount)*0.07,2))) > 0.02"
);
check('ยอดสุทธิใบเสนอราคา = (ยอด−ส่วนลด)×1.07', count($badQt) === 0, count($badQt) . ' ใบคำนวณผิด');

// ─────────────────────────────────────────────
echo "\n═══════════════════════════\n";
echo "ผลรวม: \033[32m{$passed} ผ่าน\033[0m";
if ($failed > 0) echo ", \033[31m{$failed} ล้มเหลว\033[0m";
echo "\n";
if ($failures) { echo "\nรายการที่ล้มเหลว:\n"; foreach ($failures as $f) echo "  - {$f}\n"; }
exit($failed > 0 ? 1 : 0);
