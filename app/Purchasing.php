<?php
/**
 * Purchasing.php — บริการจัดซื้อ: รับเข้าสินค้า (เพิ่มสต็อก) + สร้างเจ้าหนี้ (AP) + จ่ายเจ้าหนี้
 * คู่ขนานกับฝั่งขาย (Sales/Finance) — ทุก method ทำงานใน transaction เดียว
 */

declare(strict_types=1);

final class Purchasing
{
    const VAT_RATE = 0.07;

    public static function billStatus(string $s): array
    {
        return [
            'unpaid'  => ['ค้างจ่าย',     'badge-red'],
            'partial' => ['จ่ายบางส่วน',  'badge-gold'],
            'paid'    => ['จ่ายครบแล้ว',  'badge-green'],
            'void'    => ['ยกเลิก',       'badge-muted'],
        ][$s] ?? [$s, 'badge-muted'];
    }

    public static function poStatus(string $s): array
    {
        return [
            'open'      => ['รอรับของ',    'badge-gold'],
            'partial'   => ['รับบางส่วน',  'badge-blue'],
            'received'  => ['รับครบแล้ว',  'badge-green'],
            'cancelled' => ['ยกเลิก',       'badge-muted'],
        ][$s] ?? [$s, 'badge-muted'];
    }

    /**
     * สร้างใบสั่งซื้อ (PO) — ยังไม่กระทบสต็อก/เจ้าหนี้ จนกว่าจะรับเข้า
     * @param array $items [[product_id, description, qty, unit_cost], ...]
     */
    public static function createPurchaseOrder(int $vendorId, array $items, string $note = '', string $expected = '', string $vatMode = 'exclude'): array
    {
        if ($vendorId <= 0) throw new RuntimeException('กรุณาเลือกซัพพลายเออร์');
        $clean = [];
        foreach ($items as $it) {
            $qty = (int) round((float) ($it['qty'] ?? 0));
            $pid = ($it['product_id'] ?? '') !== '' ? (int) $it['product_id'] : null;
            if (!$pid || $qty <= 0) continue;
            $clean[] = ['product_id' => $pid, 'qty' => $qty, 'unit_cost' => (float) ($it['unit_cost'] ?? 0),
                        'description' => trim((string) ($it['description'] ?? ''))];
        }
        if (!$clean) throw new RuntimeException('ต้องมีรายการสินค้าอย่างน้อย 1 รายการ');

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            foreach ($clean as $it) $subtotal += $it['qty'] * $it['unit_cost'];
            $mode = in_array($vatMode, ['exclude','include','none'], true) ? $vatMode : 'exclude';
            $calc = vat_calc($subtotal, 0, $mode);

            $poNo = next_doc_no('PO', 'purchase_orders');
            $pdo->prepare(
                'INSERT INTO purchase_orders (doc_no, vendor_id, status, subtotal, vat, total, vat_mode, expected_date, note, created_by)
                 VALUES (:no,:vid,:st,:sub,:vat,:tot,:vm,:exp,:note,:cb)'
            )->execute(['no' => $poNo, 'vid' => $vendorId, 'st' => 'open', 'sub' => $subtotal,
                'vat' => $calc['vat'], 'tot' => $calc['total'], 'vm' => $mode, 'exp' => $expected ?: null, 'note' => $note ?: null, 'cb' => Auth::id()]);
            $poId = (int) $pdo->lastInsertId();

            $ins = $pdo->prepare('INSERT INTO purchase_order_items (po_id, product_id, description, qty, unit_cost, line_total) VALUES (:po,:pid,:d,:q,:c,:lt)');
            foreach ($clean as $it) {
                $ins->execute(['po' => $poId, 'pid' => $it['product_id'], 'd' => $it['description'] ?: '-',
                    'q' => $it['qty'], 'c' => $it['unit_cost'], 'lt' => $it['qty'] * $it['unit_cost']]);
            }
            self::audit('create', 'purchase_orders', $poId);
            $pdo->commit();
            return ['po_id' => $poId, 'po_no' => $poNo];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** รับเข้าสินค้าตาม PO (รับส่วนที่ยังไม่ครบทั้งหมด) → สร้าง GR + AP + ตัดยอดรับ */
    public static function receivePurchaseOrder(int $poId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $po = Database::one('SELECT * FROM purchase_orders WHERE id=:id FOR UPDATE', ['id' => $poId]);
            if (!$po) throw new RuntimeException('ไม่พบใบสั่งซื้อ');
            if ($po['status'] === 'received') throw new RuntimeException('ใบสั่งซื้อนี้รับของครบแล้ว');
            if ($po['status'] === 'cancelled') throw new RuntimeException('ใบสั่งซื้อถูกยกเลิก');

            $items = Database::all('SELECT * FROM purchase_order_items WHERE po_id=:id', ['id' => $poId]);
            $toReceive = [];
            foreach ($items as $it) {
                $remain = (int) $it['qty'] - (int) $it['qty_received'];
                if ($remain > 0 && $it['product_id']) {
                    $toReceive[] = ['product_id' => $it['product_id'], 'qty' => $remain,
                                    'unit_cost' => $it['unit_cost'], 'description' => $it['description']];
                }
            }
            if (!$toReceive) throw new RuntimeException('ไม่มีรายการที่ค้างรับ');

            // ใช้ createGoodsReceipt เดิม (เพิ่มสต็อก + AP) พร้อมผูก po_id
            $res = self::createGoodsReceipt((int) $po['vendor_id'], $toReceive, 'รับตามใบสั่งซื้อ ' . $po['doc_no'], $poId, $po['vat_mode'] ?? 'exclude');

            // ตัดยอดรับ + ปิด PO
            $pdo->prepare('UPDATE purchase_order_items SET qty_received = qty WHERE po_id=:id')->execute(['id' => $poId]);
            $pdo->prepare("UPDATE purchase_orders SET status='received' WHERE id=:id")->execute(['id' => $poId]);
            self::audit('receive', 'purchase_orders', $poId);
            $pdo->commit();
            return $res + ['po_no' => $po['doc_no']];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * รับเข้าสินค้า: เพิ่มสต็อก (Stock::move) ทุกบรรทัด + สร้างใบเจ้าหนี้ (AP)
     * @param array $items [[product_id, description, qty, unit_cost], ...]
     * @return array [gr_id, gr_no, bill_id, bill_no]
     */
    public static function createGoodsReceipt(int $vendorId, array $items, string $note = '', ?int $poId = null, string $vatMode = 'exclude'): array
    {
        if ($vendorId <= 0) throw new RuntimeException('กรุณาเลือกซัพพลายเออร์');
        $clean = [];
        foreach ($items as $it) {
            $qty = (float) ($it['qty'] ?? 0);
            $pid = ($it['product_id'] ?? '') !== '' ? (int) $it['product_id'] : null;
            if (!$pid || $qty <= 0) continue;
            $clean[] = ['product_id' => $pid, 'qty' => $qty, 'unit_cost' => (float) ($it['unit_cost'] ?? 0),
                        'description' => trim((string) ($it['description'] ?? ''))];
        }
        if (!$clean) throw new RuntimeException('ต้องมีรายการสินค้าอย่างน้อย 1 รายการ (เลือกสินค้า + จำนวน)');

        $pdo = Database::pdo();
        $ownTx = !$pdo->inTransaction();   // ถ้าถูกเรียกจาก receivePurchaseOrder จะมี tx อยู่แล้ว
        if ($ownTx) $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            foreach ($clean as $it) $subtotal += $it['qty'] * $it['unit_cost'];
            $mode = in_array($vatMode, ['exclude','include','none'], true) ? $vatMode : 'exclude';
            $calc = vat_calc($subtotal, 0, $mode);
            $total = $calc['total'];

            $grNo = next_doc_no('GR', 'goods_receipts');
            $pdo->prepare(
                'INSERT INTO goods_receipts (doc_no, vendor_id, po_id, status, subtotal, vat, total, vat_mode, note, received_at, created_by)
                 VALUES (:no,:vid,:po,:st,:sub,:vat,:tot,:vm,:note,:rcv,:cb)'
            )->execute(['no' => $grNo, 'vid' => $vendorId, 'po' => $poId, 'st' => 'received', 'sub' => $subtotal,
                'vat' => $calc['vat'], 'tot' => $total, 'vm' => $mode, 'note' => $note ?: null, 'rcv' => date('Y-m-d'), 'cb' => Auth::id()]);
            $grId = (int) $pdo->lastInsertId();

            $insItem = $pdo->prepare(
                'INSERT INTO goods_receipt_items (gr_id, product_id, description, qty, unit_cost, line_total)
                 VALUES (:gr,:pid,:d,:q,:c,:lt)'
            );
            foreach ($clean as $it) {
                $lt = $it['qty'] * $it['unit_cost'];
                $insItem->execute(['gr' => $grId, 'pid' => $it['product_id'], 'd' => $it['description'] ?: '-',
                    'q' => $it['qty'], 'c' => $it['unit_cost'], 'lt' => $lt]);
                // เพิ่มสต็อก + อัปเดตต้นทุนล่าสุดของสินค้า
                Stock::move($it['product_id'], 'receipt', (int) round($it['qty']), [
                    'unit_cost' => $it['unit_cost'], 'ref_type' => 'goods_receipt', 'ref_id' => $grNo,
                    'note' => 'รับเข้าจาก ' . $grNo,
                ]);
                $pdo->prepare('UPDATE products SET cost_price=:c WHERE id=:id')
                    ->execute(['c' => $it['unit_cost'], 'id' => $it['product_id']]);
            }

            // สร้างใบเจ้าหนี้ (AP)
            $apNo = next_doc_no('AP', 'vendor_bills');
            $pdo->prepare(
                'INSERT INTO vendor_bills (doc_no, vendor_id, gr_id, status, total, issued_at, due_date, created_by)
                 VALUES (:no,:vid,:gr,:st,:tot,:issued,:due,:cb)'
            )->execute(['no' => $apNo, 'vid' => $vendorId, 'gr' => $grId, 'st' => 'unpaid',
                'tot' => $total, 'issued' => date('Y-m-d'), 'due' => date('Y-m-d', strtotime('+30 days')), 'cb' => Auth::id()]);
            $billId = (int) $pdo->lastInsertId();

            self::audit('create', 'goods_receipts', $grId);
            if ($ownTx) $pdo->commit();
            return ['gr_id' => $grId, 'gr_no' => $grNo, 'bill_id' => $billId, 'bill_no' => $apNo];
        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** จ่ายเจ้าหนี้ + อัปเดตสถานะใบ (รองรับภาษีหัก ณ ที่จ่าย WHT)
     *  $amount = ยอดที่ตัดหนี้ (gross), หัก WHT แล้วเงินสดจ่ายจริง = amount − wht */
    public static function payBill(int $billId, float $amount, string $method, string $paidAt, string $note = '', float $whtRate = 0): array
    {
        if ($amount <= 0) throw new RuntimeException('จำนวนเงินต้องมากกว่า 0');
        if ($whtRate < 0 || $whtRate > 100) throw new RuntimeException('อัตรา WHT ไม่ถูกต้อง');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $bill = Database::one('SELECT * FROM vendor_bills WHERE id=:id FOR UPDATE', ['id' => $billId]);
            if (!$bill) throw new RuntimeException('ไม่พบใบเจ้าหนี้');
            if ($bill['status'] === 'void') throw new RuntimeException('ใบนี้ถูกยกเลิก');
            $outstanding = (float) $bill['total'] - (float) $bill['paid_amount'];
            if ($amount > $outstanding + 0.001) throw new RuntimeException('เกินยอดค้างจ่าย (ค้าง ' . number_format($outstanding, 2) . ')');

            // WHT คิดจากฐานก่อน VAT (ประมาณ amount/1.07) ตามหลักภาษีไทย
            $whtBase = $whtRate > 0 ? round($amount / (1 + self::VAT_RATE), 2) : 0;
            $whtAmount = round($whtBase * $whtRate / 100, 2);
            $netCash = $amount - $whtAmount;

            $vpNo = next_doc_no('VP', 'vendor_payments');
            $pdo->prepare(
                'INSERT INTO vendor_payments (doc_no, bill_id, vendor_id, amount, wht_rate, wht_amount, method, paid_at, note, created_by)
                 VALUES (:no,:bid,:vid,:amt,:wr,:wa,:m,:pd,:note,:cb)'
            )->execute(['no' => $vpNo, 'bid' => $billId, 'vid' => $bill['vendor_id'], 'amt' => $amount,
                'wr' => $whtRate, 'wa' => $whtAmount,
                'm' => $method, 'pd' => $paidAt ?: date('Y-m-d'), 'note' => $note ?: null, 'cb' => Auth::id()]);

            $newPaid = (float) $bill['paid_amount'] + $amount;
            $status = $newPaid >= (float) $bill['total'] - 0.001 ? 'paid' : 'partial';
            $pdo->prepare('UPDATE vendor_bills SET paid_amount=:p, status=:s WHERE id=:id')
                ->execute(['p' => $newPaid, 's' => $status, 'id' => $billId]);

            self::audit('payment', 'vendor_bills', $billId);
            $pdo->commit();
            return ['doc_no' => $vpNo, 'status' => $status, 'wht' => $whtAmount, 'net_cash' => $netCash];
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
            ['u' => Auth::id(), 'c' => Auth::id(), 'a' => $action, 'e' => $entity, 'eid' => $id, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }
}
