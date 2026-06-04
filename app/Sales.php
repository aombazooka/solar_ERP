<?php
/**
 * Sales.php — บริการงานขาย (Service Layer)
 * จัดการ logic ข้ามตาราง: แปลงใบเสนอราคา → ใบสั่งขาย, ส่งของ (ตัดสต็อก)
 * ทุก method ทำงานใน transaction เดียว เพื่อความถูกต้องของข้อมูล
 */

declare(strict_types=1);

final class Sales
{
    const VAT_RATE = 0.07;

    /** ป้ายสถานะใบเสนอราคา */
    public static function quotationStatus(string $s): array
    {
        return [
            'draft'     => ['ร่าง',        'badge-muted'],
            'sent'      => ['ส่งแล้ว',     'badge-blue'],
            'accepted'  => ['ลูกค้าตอบรับ', 'badge-green'],
            'rejected'  => ['ปฏิเสธ',      'badge-red'],
            'converted' => ['แปลงเป็นใบสั่งขาย', 'badge-gold'],
        ][$s] ?? [$s, 'badge-muted'];
    }

    /** ป้ายสถานะใบสั่งขาย */
    public static function orderStatus(string $s): array
    {
        return [
            'pending'   => ['รอส่งของ',   'badge-gold'],
            'delivered' => ['ส่งของแล้ว', 'badge-blue'],
            'invoiced'  => ['ออกบิลแล้ว', 'badge-green'],
            'cancelled' => ['ยกเลิก',     'badge-red'],
        ][$s] ?? [$s, 'badge-muted'];
    }

    /**
     * สร้างใบเสนอราคาพร้อมรายการ
     * @param array $header  customer_id, system_type, capacity_kwp, discount, valid_until, note
     * @param array $items   [[product_id|null, description, qty, unit_price], ...]
     * @return array  [id, doc_no]
     */
    public static function createQuotation(array $header, array $items): array
    {
        if (empty($items)) {
            throw new RuntimeException('ต้องมีรายการสินค้าอย่างน้อย 1 รายการ');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            foreach ($items as $it) {
                $subtotal += (float) $it['qty'] * (float) $it['unit_price'];
            }
            $discount = (float) ($header['discount'] ?? 0);
            $mode = in_array($header['vat_mode'] ?? '', ['exclude','include','none'], true) ? $header['vat_mode'] : 'exclude';
            $calc = vat_calc($subtotal, $discount, $mode);

            $docNo = next_doc_no('QT', 'quotations');
            $pdo->prepare(
                'INSERT INTO quotations
                    (doc_no, customer_id, status, system_type, capacity_kwp, subtotal, discount, vat_mode, vat, total, valid_until, note, created_by)
                 VALUES (:no,:cid,:st,:sys,:cap,:sub,:disc,:vm,:vat,:tot,:valid,:note,:cb)'
            )->execute([
                'no' => $docNo, 'cid' => $header['customer_id'], 'st' => 'draft',
                'sys' => $header['system_type'] ?: null,
                'cap' => $header['capacity_kwp'] !== '' ? $header['capacity_kwp'] : null,
                'sub' => $subtotal, 'disc' => $discount, 'vm' => $mode, 'vat' => $calc['vat'], 'tot' => $calc['total'],
                'valid' => $header['valid_until'] ?: null, 'note' => $header['note'] ?: null,
                'cb' => Auth::id(),
            ]);
            $qid = (int) $pdo->lastInsertId();

            $insItem = $pdo->prepare(
                'INSERT INTO quotation_items (quotation_id, product_id, description, qty, unit_price, line_total)
                 VALUES (:qid,:pid,:desc,:qty,:price,:lt)'
            );
            foreach ($items as $it) {
                $lt = (float) $it['qty'] * (float) $it['unit_price'];
                $insItem->execute([
                    'qid' => $qid, 'pid' => $it['product_id'] ?: null,
                    'desc' => $it['description'], 'qty' => $it['qty'],
                    'price' => $it['unit_price'], 'lt' => $lt,
                ]);
            }

            self::audit('create', 'quotations', $qid);
            $pdo->commit();
            return ['id' => $qid, 'doc_no' => $docNo];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** แปลงใบเสนอราคา → ใบสั่งขาย (คัดลอกรายการ) */
    public static function convertToOrder(int $quotationId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $q = Database::one('SELECT * FROM quotations WHERE id=:id FOR UPDATE', ['id' => $quotationId]);
            if (!$q) throw new RuntimeException('ไม่พบใบเสนอราคา');
            if ($q['status'] === 'converted') throw new RuntimeException('ใบเสนอราคานี้ถูกแปลงไปแล้ว');

            $docNo = next_doc_no('SO', 'sales_orders');
            $pdo->prepare(
                'INSERT INTO sales_orders (doc_no, quotation_id, customer_id, status, total, vat_mode, created_by)
                 VALUES (:no,:qid,:cid,:st,:tot,:vm,:cb)'
            )->execute([
                'no' => $docNo, 'qid' => $quotationId, 'cid' => $q['customer_id'],
                'st' => 'pending', 'tot' => $q['total'], 'vm' => $q['vat_mode'] ?? 'exclude', 'cb' => Auth::id(),
            ]);
            $oid = (int) $pdo->lastInsertId();

            $items = Database::all('SELECT * FROM quotation_items WHERE quotation_id=:id', ['id' => $quotationId]);
            $ins = $pdo->prepare(
                'INSERT INTO sales_order_items (order_id, product_id, description, qty, unit_price, line_total)
                 VALUES (:oid,:pid,:desc,:qty,:price,:lt)'
            );
            foreach ($items as $it) {
                $ins->execute([
                    'oid' => $oid, 'pid' => $it['product_id'], 'desc' => $it['description'],
                    'qty' => $it['qty'], 'price' => $it['unit_price'], 'lt' => $it['line_total'],
                ]);
            }

            $pdo->prepare("UPDATE quotations SET status='converted' WHERE id=:id")->execute(['id' => $quotationId]);
            self::audit('convert', 'quotations', $quotationId);
            $pdo->commit();
            return ['id' => $oid, 'doc_no' => $docNo];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** ส่งของ: ตัดสต็อกตามรายการสินค้าจริง (ข้ามบริการ) แล้วตั้งสถานะ delivered */
    public static function deliver(int $orderId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $o = Database::one('SELECT * FROM sales_orders WHERE id=:id FOR UPDATE', ['id' => $orderId]);
            if (!$o) throw new RuntimeException('ไม่พบใบสั่งขาย');
            if ($o['status'] !== 'pending') throw new RuntimeException('ใบสั่งขายนี้ส่งของไปแล้วหรือถูกยกเลิก');

            $items = Database::all(
                'SELECT soi.*, p.category FROM sales_order_items soi
                 LEFT JOIN products p ON p.id = soi.product_id
                 WHERE soi.order_id=:id',
                ['id' => $orderId]
            );
            foreach ($items as $it) {
                if (!$it['product_id'] || $it['category'] === 'service') continue;
                Stock::move((int) $it['product_id'], 'sale', -(int) round((float) $it['qty']), [
                    'ref_type' => 'sales_order', 'ref_id' => $o['doc_no'],
                    'note' => 'ตัดสต็อกจากการส่งของ ' . $o['doc_no'],
                ]);
            }

            $pdo->prepare("UPDATE sales_orders SET status='delivered', delivered_at=NOW() WHERE id=:id")
                ->execute(['id' => $orderId]);
            self::audit('deliver', 'sales_orders', $orderId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function audit(string $action, string $entity, int $id): void
    {
        Database::run(
            'INSERT INTO audit_log (user_id, created_by, action, entity, entity_id, ip_address)
             VALUES (:u,:c,:a,:e,:eid,:ip)',
            ['u' => Auth::id(), 'c' => Auth::id(), 'a' => $action, 'e' => $entity,
             'eid' => $id, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }
}
