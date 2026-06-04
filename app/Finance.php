<?php
/**
 * Finance.php — บริการการเงิน (AR): ออกใบแจ้งหนี้จากใบสั่งขาย, รับชำระ
 * ทุก method ทำงานใน transaction เดียว
 */

declare(strict_types=1);

final class Finance
{
    public static function invoiceStatus(string $s): array
    {
        return [
            'unpaid'  => ['ค้างชำระ',     'badge-red'],
            'partial' => ['ชำระบางส่วน',  'badge-gold'],
            'paid'    => ['ชำระครบแล้ว',  'badge-green'],
            'void'    => ['ยกเลิก',       'badge-muted'],
        ][$s] ?? [$s, 'badge-muted'];
    }

    public static function methodLabel(string $m): string
    {
        return ['cash' => 'เงินสด', 'transfer' => 'โอนเงิน', 'cheque' => 'เช็ค', 'card' => 'บัตร'][$m] ?? $m;
    }

    /** ออกใบแจ้งหนี้จากใบสั่งขาย (1 order → 1 invoice) */
    public static function createInvoiceFromOrder(int $orderId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $o = Database::one('SELECT * FROM sales_orders WHERE id=:id FOR UPDATE', ['id' => $orderId]);
            if (!$o) throw new RuntimeException('ไม่พบใบสั่งขาย');
            if ($o['status'] === 'pending') throw new RuntimeException('ต้องส่งของก่อนจึงจะออกบิลได้');
            if ($o['status'] === 'cancelled') throw new RuntimeException('ใบสั่งขายถูกยกเลิก');

            $exists = Database::scalar('SELECT id FROM invoices WHERE order_id=:id', ['id' => $orderId]);
            if ($exists) throw new RuntimeException('ใบสั่งขายนี้ออกบิลไปแล้ว');

            $docNo = next_doc_no('INV', 'invoices');
            $due = date('Y-m-d', strtotime('+30 days'));
            $pdo->prepare(
                'INSERT INTO invoices (doc_no, order_id, customer_id, status, total, vat_mode, issued_at, due_date, created_by)
                 VALUES (:no,:oid,:cid,:st,:tot,:vm,:issued,:due,:cb)'
            )->execute([
                'no' => $docNo, 'oid' => $orderId, 'cid' => $o['customer_id'],
                'st' => 'unpaid', 'tot' => $o['total'], 'vm' => $o['vat_mode'] ?? 'exclude',
                'issued' => date('Y-m-d'), 'due' => $due, 'cb' => Auth::id(),
            ]);
            $invId = (int) $pdo->lastInsertId();

            $items = Database::all('SELECT description, qty, unit_price, line_total FROM sales_order_items WHERE order_id=:id', ['id' => $orderId]);
            $ins = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, line_total) VALUES (:iid,:d,:q,:p,:lt)');
            foreach ($items as $it) {
                $ins->execute(['iid' => $invId, 'd' => $it['description'], 'q' => $it['qty'], 'p' => $it['unit_price'], 'lt' => $it['line_total']]);
            }

            $pdo->prepare("UPDATE sales_orders SET status='invoiced' WHERE id=:id")->execute(['id' => $orderId]);
            self::audit('create', 'invoices', $invId);
            $pdo->commit();
            return ['id' => $invId, 'doc_no' => $docNo];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** บันทึกการรับชำระ + ปรับสถานะใบแจ้งหนี้ */
    public static function recordPayment(int $invoiceId, float $amount, string $method, string $paidAt, string $note = ''): array
    {
        if ($amount <= 0) throw new RuntimeException('จำนวนเงินต้องมากกว่า 0');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $inv = Database::one('SELECT * FROM invoices WHERE id=:id FOR UPDATE', ['id' => $invoiceId]);
            if (!$inv) throw new RuntimeException('ไม่พบใบแจ้งหนี้');
            if ($inv['status'] === 'void') throw new RuntimeException('ใบแจ้งหนี้ถูกยกเลิก');

            $outstanding = (float) $inv['total'] - (float) $inv['paid_amount'];
            if ($amount > $outstanding + 0.001) {
                throw new RuntimeException('จำนวนเกินยอดค้างชำระ (ค้าง ' . number_format($outstanding, 2) . ')');
            }

            $docNo = next_doc_no('PAY', 'payments');
            $pdo->prepare(
                'INSERT INTO payments (doc_no, invoice_id, customer_id, amount, method, paid_at, note, created_by)
                 VALUES (:no,:iid,:cid,:amt,:m,:pd,:note,:cb)'
            )->execute([
                'no' => $docNo, 'iid' => $invoiceId, 'cid' => $inv['customer_id'],
                'amt' => $amount, 'm' => $method, 'pd' => $paidAt ?: date('Y-m-d'),
                'note' => $note ?: null, 'cb' => Auth::id(),
            ]);

            $newPaid = (float) $inv['paid_amount'] + $amount;
            $status = $newPaid >= (float) $inv['total'] - 0.001 ? 'paid' : 'partial';
            $pdo->prepare('UPDATE invoices SET paid_amount=:p, status=:s WHERE id=:id')
                ->execute(['p' => $newPaid, 's' => $status, 'id' => $invoiceId]);

            self::audit('payment', 'invoices', $invoiceId);
            $pdo->commit();
            return ['doc_no' => $docNo, 'status' => $status];
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
