<?php
/**
 * Stock.php — บริการจัดการสต็อก (Service Layer)
 * ───────────────────────────────────────────────────────────
 * ทุกการเปลี่ยนแปลงสต็อกต้องผ่านที่นี่ เพื่อให้ products.stock_qty
 * กับ ledger (stock_movements) ตรงกันเสมอ ภายใน transaction เดียว
 *
 * โมดูล Sales / Goods Receipt จะเรียกใช้ Stock::move() ร่วมกัน
 */

declare(strict_types=1);

final class Stock
{
    /**
     * บันทึกการเคลื่อนไหวสต็อก 1 รายการ (atomic)
     * @param int    $productId
     * @param string $type   receipt|issue|adjust|sale|return
     * @param int    $qty    signed (+ เข้า / − ออก)
     * @param array  $opts   unit_cost, ref_type, ref_id, note
     * @return int  ยอดคงเหลือใหม่
     * @throws RuntimeException ถ้าตัดสต็อกแล้วติดลบ
     */
    public static function move(int $productId, string $type, int $qty, array $opts = []): int
    {
        $pdo = Database::pdo();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) $pdo->beginTransaction();

        try {
            // ล็อกแถวสินค้า กัน race condition
            $stmt = $pdo->prepare('SELECT sku, name, stock_qty, reorder_level, category FROM products WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new RuntimeException("ไม่พบสินค้า id={$productId}");
            }
            if ($product['category'] === 'service') {
                throw new RuntimeException('สินค้าประเภทบริการไม่มีสต็อก');
            }

            $newBalance = (int) $product['stock_qty'] + $qty;
            if ($newBalance < 0) {
                throw new RuntimeException(
                    "สต็อกไม่พอ (คงเหลือ {$product['stock_qty']}, ต้องการตัด " . abs($qty) . ')'
                );
            }

            $pdo->prepare('UPDATE products SET stock_qty = :q WHERE id = :id')
                ->execute(['q' => $newBalance, 'id' => $productId]);

            $pdo->prepare(
                'INSERT INTO stock_movements
                    (product_id, type, qty, balance_after, unit_cost, ref_type, ref_id, note, created_by)
                 VALUES (:pid, :type, :qty, :bal, :cost, :rt, :rid, :note, :cb)'
            )->execute([
                'pid' => $productId, 'type' => $type, 'qty' => $qty, 'bal' => $newBalance,
                'cost' => $opts['unit_cost'] ?? null,
                'rt'   => $opts['ref_type'] ?? null,
                'rid'  => $opts['ref_id'] ?? null,
                'note' => $opts['note'] ?? null,
                'cb'   => $opts['created_by'] ?? (Auth::check() ? Auth::id() : null),
            ]);

            // แจ้งเตือนเมื่อสต็อก "เพิ่งตก" ถึง/ต่ำกว่าจุดสั่งซื้อ (เฉพาะรายการตัดออก)
            $reorder = (int) $product['reorder_level'];
            if ($qty < 0 && $reorder > 0 && (int) $product['stock_qty'] > $reorder && $newBalance <= $reorder) {
                self::notifyLowStock($pdo, $product['sku'], $product['name'], $newBalance, $reorder);
            }

            if ($ownTx) $pdo->commit();
            return $newBalance;

        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** แจ้งเตือนผู้จัดการคลัง (inventory.manage หรือ admin) เมื่อสต็อกใกล้หมด */
    private static function notifyLowStock(\PDO $pdo, string $sku, string $name, int $balance, int $reorder): void
    {
        try {
            $msg = "⚠ สต็อกใกล้หมด: {$sku} {$name} เหลือ {$balance} (จุดสั่งซื้อ {$reorder})";
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (employee_id, message)
                 SELECT DISTINCT e.id, :msg FROM employees e
                 JOIN users u ON u.id = e.user_id
                 JOIN roles r ON r.id = u.role_id
                 LEFT JOIN role_permissions rp ON rp.role_id = r.id
                 LEFT JOIN permissions p ON p.id = rp.permission_id
                 WHERE e.is_active = 1 AND u.is_active = 1
                   AND (r.slug = 'admin' OR p.slug = 'inventory.manage')"
            );
            $stmt->execute(['msg' => $msg]);
        } catch (\Throwable $e) { /* แจ้งเตือนล้มเหลวไม่ควรล้มการตัดสต็อก */ }
    }

    /** ประวัติการเคลื่อนไหวล่าสุด (ทั้งระบบหรือเฉพาะสินค้า) */
    public static function history(?int $productId = null, int $limit = 50): array
    {
        if ($productId !== null) {
            return Database::all(
                'SELECT sm.*, p.sku, p.name AS product_name, u.name AS by_name
                 FROM stock_movements sm
                 JOIN products p ON p.id = sm.product_id
                 LEFT JOIN users u ON u.id = sm.created_by
                 WHERE sm.product_id = :pid ORDER BY sm.id DESC LIMIT ' . (int)$limit,
                ['pid' => $productId]
            );
        }
        return Database::all(
            'SELECT sm.*, p.sku, p.name AS product_name, u.name AS by_name
             FROM stock_movements sm
             JOIN products p ON p.id = sm.product_id
             LEFT JOIN users u ON u.id = sm.created_by
             ORDER BY sm.id DESC LIMIT ' . (int)$limit
        );
    }

    /** ป้ายกำกับชนิดการเคลื่อนไหว */
    public static function typeLabel(string $type): array
    {
        return [
            'receipt' => ['รับเข้า',  'badge-green', 'fa-arrow-down'],
            'issue'   => ['เบิกออก',  'badge-red',   'fa-arrow-up'],
            'sale'    => ['ขายออก',   'badge-gold',  'fa-cart-shopping'],
            'return'  => ['รับคืน',   'badge-blue',  'fa-rotate-left'],
            'adjust'  => ['ปรับยอด',  'badge-purple','fa-sliders'],
        ][$type] ?? [$type, 'badge-muted', 'fa-circle'];
    }
}
