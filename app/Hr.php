<?php
/**
 * Hr.php — บริการ HR: ลงเวลา/ลา (รองรับ act-on-behalf §5.1) + Payroll/คอมมิชชั่น
 * ───────────────────────────────────────────────────────────
 * กฎ §5.1:
 *   - แยก employee_id (เจ้าของ) ออกจาก created_by (ผู้บันทึก)
 *   - ทำแทน (on_behalf) ต้องระบุเหตุผลเสมอ
 *   - แก้ย้อนหลังเกิน BACKDATE_LIMIT_DAYS ไม่ได้ (ยกเว้น admin)
 *   - งวด payroll ที่ล็อกแล้ว = ห้ามแก้
 *   - ทำแทน → แจ้งเตือนพนักงาน (โปร่งใส)
 */

declare(strict_types=1);

final class Hr
{
    const BACKDATE_LIMIT_DAYS = 30;

    /**
     * ดึงประเภทวันลาจาก DB (fallback ค่าเริ่มต้นถ้าตารางยังไม่มี)
     * @return array [['code','name','quota_days','deduct_pay','is_active'], ...]
     */
    public static function leaveTypes(): array
    {
        try {
            $rows = Database::all('SELECT * FROM leave_types WHERE is_active=1 ORDER BY sort_order, name');
            if ($rows) return $rows;
        } catch (\Throwable $e) {
            // leave_types ยังไม่ถูกสร้าง — ใช้ค่าเริ่มต้น
        }
        return [
            ['code' => 'sick',     'name' => 'ลาป่วย',   'quota_days' => 30, 'deduct_pay' => 0, 'is_active' => 1],
            ['code' => 'personal', 'name' => 'ลากิจ',     'quota_days' => 3,  'deduct_pay' => 1, 'is_active' => 1],
            ['code' => 'vacation', 'name' => 'ลาพักร้อน', 'quota_days' => 6,  'deduct_pay' => 0, 'is_active' => 1],
            ['code' => 'other',    'name' => 'อื่นๆ',     'quota_days' => 0,  'deduct_pay' => 1, 'is_active' => 1],
        ];
    }

    /**
     * สิทธิ์วันลาคงเหลือของพนักงานในปีหนึ่ง
     * @return array [code => ['label','quota','used','remaining']]
     */
    public static function leaveBalance(int $employeeId, ?int $year = null): array
    {
        $year ??= (int) date('Y');
        $out  = [];
        foreach (self::leaveTypes() as $lt) {
            $quota = (int) $lt['quota_days'];
            $used  = (float) Database::scalar(
                "SELECT COALESCE(SUM(days),0) FROM leave_requests
                 WHERE employee_id=:e AND leave_type=:t AND status='approved' AND YEAR(date_from)=:y",
                ['e' => $employeeId, 't' => $lt['code'], 'y' => $year]
            );
            $out[$lt['code']] = [
                'label'     => $lt['name'],
                'quota'     => $quota,
                'used'      => $used,
                'remaining' => $quota > 0 ? max(0, $quota - $used) : null,
            ];
        }
        return $out;
    }

    /** employee ที่ผูกกับ user ปัจจุบัน (ถ้ามี) */
    public static function currentEmployee(): ?array
    {
        if (!Auth::check()) return null;
        return Database::one('SELECT * FROM employees WHERE user_id=:uid', ['uid' => Auth::id()]);
    }

    /** ระบุวิธีบันทึก: self ถ้าเป็นเจ้าของ, ไม่งั้น on_behalf (ต้องมีสิทธิ์ + เหตุผล) */
    private static function resolveEntry(int $employeeId, string $reason): array
    {
        $emp = Database::one('SELECT user_id FROM employees WHERE id=:id', ['id' => $employeeId]);
        if (!$emp) throw new RuntimeException('ไม่พบพนักงาน');

        $isSelf = $emp['user_id'] !== null && (int) $emp['user_id'] === Auth::id();
        if ($isSelf) return ['self', null];

        // ทำแทน — ต้องมีสิทธิ์ hr.manage และระบุเหตุผล (§5.1)
        if (!Auth::can('hr.manage')) {
            throw new RuntimeException('คุณไม่มีสิทธิ์บันทึกแทนพนักงานอื่น');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('การทำแทนต้องระบุเหตุผลเสมอ (§5.1)');
        }
        return ['on_behalf', trim($reason)];
    }

    private static function assertBackdate(string $date): void
    {
        if (Auth::user()['role_slug'] === 'admin') return;
        $limit = strtotime('-' . self::BACKDATE_LIMIT_DAYS . ' days');
        if (strtotime($date) < $limit) {
            throw new RuntimeException('แก้ย้อนหลังได้ไม่เกิน ' . self::BACKDATE_LIMIT_DAYS . ' วัน');
        }
    }

    private static function notify(int $employeeId, string $msg): void
    {
        Database::run('INSERT INTO notifications (employee_id, message) VALUES (:e,:m)',
            ['e' => $employeeId, 'm' => $msg]);
    }

    /** แจ้งเตือนผู้มีสิทธิ์อนุมัติ (hr.approve หรือ admin) — เว้นตัวผู้ยื่นเอง */
    private static function notifyApprovers(string $msg): void
    {
        $approvers = Database::all(
            "SELECT DISTINCT e.id FROM employees e
             JOIN users u ON u.id = e.user_id
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN role_permissions rp ON rp.role_id = r.id
             LEFT JOIN permissions p ON p.id = rp.permission_id
             WHERE e.is_active = 1 AND u.is_active = 1
               AND (r.slug = 'admin' OR p.slug = 'hr.approve')"
        );
        foreach ($approvers as $a) {
            if ((int) $a['id'] === 0) continue;
            self::notify((int) $a['id'], $msg);
        }
    }

    /** บันทึก/อัปเดตการลงเวลา (upsert ต่อ employee+วัน) */
    public static function saveAttendance(int $employeeId, string $workDate, ?string $in, ?string $out, string $note, string $reason = ''): void
    {
        self::assertBackdate($workDate);
        [$method, $actedReason] = self::resolveEntry($employeeId, $reason);

        $existing = Database::one('SELECT id, is_locked FROM attendance WHERE employee_id=:e AND work_date=:d',
            ['e' => $employeeId, 'd' => $workDate]);
        if ($existing && $existing['is_locked']) {
            throw new RuntimeException('รายการนี้ถูกล็อก (ปิดงวดเงินเดือนแล้ว) แก้ไขไม่ได้');
        }

        if ($existing) {
            Database::run(
                'UPDATE attendance SET check_in=:in, check_out=:out, note=:note,
                   created_by=:cb, entry_method=:em, acted_for_reason=:reason WHERE id=:id',
                ['in' => $in ?: null, 'out' => $out ?: null, 'note' => $note ?: null,
                 'cb' => Auth::id(), 'em' => $method, 'reason' => $actedReason, 'id' => $existing['id']]
            );
        } else {
            Database::run(
                'INSERT INTO attendance (employee_id, work_date, check_in, check_out, note, created_by, entry_method, acted_for_reason)
                 VALUES (:e,:d,:in,:out,:note,:cb,:em,:reason)',
                ['e' => $employeeId, 'd' => $workDate, 'in' => $in ?: null, 'out' => $out ?: null,
                 'note' => $note ?: null, 'cb' => Auth::id(), 'em' => $method, 'reason' => $actedReason]
            );
        }

        if ($method === 'on_behalf') {
            self::notify($employeeId, "HR บันทึกเวลาทำงานวันที่ {$workDate} แทนคุณ (เหตุผล: {$actedReason})");
        }
    }

    /** ยื่นคำขอลา */
    public static function requestLeave(int $employeeId, string $type, string $from, string $to, float $days, string $reason, string $actReason = ''): void
    {
        self::assertBackdate($from);
        [$method, $actedReason] = self::resolveEntry($employeeId, $actReason);
        if (strtotime($to) < strtotime($from)) throw new RuntimeException('วันสิ้นสุดต้องไม่ก่อนวันเริ่ม');

        Database::run(
            'INSERT INTO leave_requests (employee_id, leave_type, date_from, date_to, days, reason, created_by, entry_method, acted_for_reason)
             VALUES (:e,:t,:f,:to,:d,:r,:cb,:em,:ar)',
            ['e' => $employeeId, 't' => $type, 'f' => $from, 'to' => $to, 'd' => $days,
             'r' => $reason ?: null, 'cb' => Auth::id(), 'em' => $method, 'ar' => $actedReason]
        );
        if ($method === 'on_behalf') {
            self::notify($employeeId, "HR ยื่นใบลา ({$from} ถึง {$to}) แทนคุณ (เหตุผล: {$actedReason})");
        }
        $emp = Database::one('SELECT name FROM employees WHERE id=:id', ['id' => $employeeId]);
        self::notifyApprovers("มีใบลาใหม่รออนุมัติ: {$emp['name']} ({$from} ถึง {$to})");
    }

    /** ยื่นคำขอ OT (ทำงานล่วงเวลา) */
    public static function requestOt(int $employeeId, string $date, float $hours, string $reason, string $actReason = ''): void
    {
        self::assertBackdate($date);
        [$method, $actedReason] = self::resolveEntry($employeeId, $actReason);
        if ($hours <= 0 || $hours > 24) throw new RuntimeException('จำนวนชั่วโมง OT ไม่ถูกต้อง');

        Database::run(
            'INSERT INTO ot_requests (employee_id, ot_date, hours, reason, created_by, entry_method, acted_for_reason)
             VALUES (:e,:d,:h,:r,:cb,:em,:ar)',
            ['e' => $employeeId, 'd' => $date, 'h' => $hours, 'r' => $reason ?: null,
             'cb' => Auth::id(), 'em' => $method, 'ar' => $actedReason]
        );
        if ($method === 'on_behalf') {
            self::notify($employeeId, "HR ยื่นขอ OT วันที่ {$date} ({$hours} ชม.) แทนคุณ (เหตุผล: {$actedReason})");
        }
        $emp = Database::one('SELECT name FROM employees WHERE id=:id', ['id' => $employeeId]);
        self::notifyApprovers("มีคำขอ OT ใหม่รออนุมัติ: {$emp['name']} วันที่ {$date} ({$hours} ชม.)");
    }

    /**
     * วงเงินเบิกล่วงหน้าที่ทำได้ = (ฐานเงินเดือน ÷ 30) × วันที่ทำงานมาแล้วในเดือนนี้ − ยอดเบิกค้าง
     * @return array [base, daily, worked, earned, outstanding, available]
     */
    public static function advanceLimit(int $employeeId): array
    {
        $base = (float) Database::scalar('SELECT base_salary FROM employees WHERE id=:id', ['id' => $employeeId]);
        $daily = $base / 30;
        $worked = (int) Database::scalar(
            "SELECT COUNT(*) FROM attendance WHERE employee_id=:e AND check_in IS NOT NULL
             AND work_date BETWEEN :a AND :b",
            ['e' => $employeeId, 'a' => date('Y-m-01'), 'b' => date('Y-m-t')]
        );
        $earned = round($daily * $worked, 2);
        $outstanding = (float) Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM salary_advances
             WHERE employee_id=:e AND status IN ('pending','approved')",
            ['e' => $employeeId]
        );
        return [
            'base' => $base, 'daily' => round($daily, 2), 'worked' => $worked,
            'earned' => $earned, 'outstanding' => $outstanding,
            'available' => max(0, round($earned - $outstanding, 2)),
        ];
    }

    /** ยื่นขอเบิกเงินล่วงหน้า (self หรือผู้มีสิทธิ์ hr.advance) — จำกัดตามวงเงินที่ทำงานมาแล้ว */
    public static function requestAdvance(int $employeeId, float $amount, string $reason): array
    {
        if ($amount <= 0) throw new RuntimeException('จำนวนเงินต้องมากกว่า 0');
        $emp = Database::one('SELECT user_id FROM employees WHERE id=:id', ['id' => $employeeId]);
        if (!$emp) throw new RuntimeException('ไม่พบพนักงาน');
        $isSelf = $emp['user_id'] !== null && (int) $emp['user_id'] === Auth::id();
        if (!$isSelf && !Auth::can('hr.advance')) throw new RuntimeException('คุณไม่มีสิทธิ์ยื่นแทนคนอื่น');

        $lim = self::advanceLimit($employeeId);
        if ($amount > $lim['available'] + 0.001) {
            throw new RuntimeException(
                'เกินวงเงินที่เบิกได้ — สูงสุด ' . number_format($lim['available'], 2) . ' บาท'
                . ' (ทำงานมาแล้ว ' . $lim['worked'] . ' วัน × ฐาน/30'
                . ($lim['outstanding'] > 0 ? ' − เบิกค้าง ' . number_format($lim['outstanding'], 2) : '') . ')'
            );
        }

        $no = next_doc_no('ADV', 'salary_advances');
        Database::run(
            'INSERT INTO salary_advances (doc_no, employee_id, amount, reason, status, request_date, created_by)
             VALUES (:no,:e,:amt,:r,:st,:rd,:cb)',
            ['no' => $no, 'e' => $employeeId, 'amt' => $amount, 'r' => $reason ?: null,
             'st' => 'pending', 'rd' => date('Y-m-d'), 'cb' => Auth::id()]
        );
        $empName = Database::scalar('SELECT name FROM employees WHERE id=:id', ['id' => $employeeId]);
        self::notifyApprovers("มีคำขอเบิกเงินล่วงหน้ารออนุมัติ: {$empName} จำนวน " . number_format($amount, 2) . " บาท");
        return ['doc_no' => $no];
    }

    /** อนุมัติ/ปฏิเสธการเบิกเงินล่วงหน้า (ต้องมี hr.advance) */
    public static function decideAdvance(int $advId, string $decision): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        $adv = Database::one('SELECT * FROM salary_advances WHERE id=:id', ['id' => $advId]);
        if (!$adv) throw new RuntimeException('ไม่พบคำขอเบิก');
        if ($adv['status'] !== 'pending') throw new RuntimeException('พิจารณาได้เฉพาะคำขอที่รออนุมัติ');
        Database::run('UPDATE salary_advances SET status=:s, approved_by=:by WHERE id=:id',
            ['s' => $decision, 'by' => Auth::id(), 'id' => $advId]);
        self::notify((int) $adv['employee_id'],
            'คำขอเบิกเงินล่วงหน้า ' . number_format((float)$adv['amount'],2) . ' บาท ได้รับการ' . ($decision==='approved'?'อนุมัติ (จะหักจากเงินเดือนงวดถัดไป)':'ปฏิเสธ'));
    }

    /** ตรวจสิทธิ์แก้/ยกเลิก: ต้องเป็นเจ้าของ (หรือ hr.manage) + สถานะ pending + ไม่ถูกล็อก */
    private static function assertOwnEditable(array $row): void
    {
        $emp = Database::one('SELECT user_id FROM employees WHERE id=:id', ['id' => $row['employee_id']]);
        $isSelf = $emp && $emp['user_id'] !== null && (int) $emp['user_id'] === Auth::id();
        if (!$isSelf && !Auth::can('hr.manage')) {
            throw new RuntimeException('คุณไม่มีสิทธิ์จัดการคำขอนี้');
        }
        if ($row['status'] !== 'pending') {
            throw new RuntimeException('แก้ไข/ยกเลิกได้เฉพาะคำขอที่ยัง "รออนุมัติ" เท่านั้น');
        }
        if (!empty($row['is_locked'])) {
            throw new RuntimeException('คำขอนี้ถูกล็อก (ปิดงวดแล้ว)');
        }
    }

    /** ยกเลิกใบลา (ของตัวเอง, เฉพาะที่รออนุมัติ) */
    public static function cancelLeave(int $leaveId): void
    {
        $lv = Database::one('SELECT * FROM leave_requests WHERE id=:id', ['id' => $leaveId]);
        if (!$lv) throw new RuntimeException('ไม่พบใบลา');
        self::assertOwnEditable($lv);
        Database::run("UPDATE leave_requests SET status='cancelled' WHERE id=:id", ['id' => $leaveId]);
    }

    /** แก้ไขใบลา (ของตัวเอง, เฉพาะที่รออนุมัติ) */
    public static function updateLeave(int $leaveId, string $type, string $from, string $to, float $days, string $reason): void
    {
        $lv = Database::one('SELECT * FROM leave_requests WHERE id=:id', ['id' => $leaveId]);
        if (!$lv) throw new RuntimeException('ไม่พบใบลา');
        self::assertOwnEditable($lv);
        if (strtotime($to) < strtotime($from)) throw new RuntimeException('วันสิ้นสุดต้องไม่ก่อนวันเริ่ม');
        Database::run(
            'UPDATE leave_requests SET leave_type=:t, date_from=:f, date_to=:to, days=:d, reason=:r WHERE id=:id',
            ['t' => $type, 'f' => $from, 'to' => $to, 'd' => $days, 'r' => $reason ?: null, 'id' => $leaveId]
        );
    }

    /** ยกเลิกคำขอ OT (ของตัวเอง, เฉพาะที่รออนุมัติ) */
    public static function cancelOt(int $otId): void
    {
        $ot = Database::one('SELECT * FROM ot_requests WHERE id=:id', ['id' => $otId]);
        if (!$ot) throw new RuntimeException('ไม่พบคำขอ OT');
        self::assertOwnEditable($ot);
        Database::run("UPDATE ot_requests SET status='cancelled' WHERE id=:id", ['id' => $otId]);
    }

    /** แก้ไขคำขอ OT (ของตัวเอง, เฉพาะที่รออนุมัติ) */
    public static function updateOt(int $otId, string $date, float $hours, string $reason): void
    {
        $ot = Database::one('SELECT * FROM ot_requests WHERE id=:id', ['id' => $otId]);
        if (!$ot) throw new RuntimeException('ไม่พบคำขอ OT');
        self::assertOwnEditable($ot);
        if ($hours <= 0 || $hours > 24) throw new RuntimeException('จำนวนชั่วโมง OT ไม่ถูกต้อง');
        Database::run('UPDATE ot_requests SET ot_date=:d, hours=:h, reason=:r WHERE id=:id',
            ['d' => $date, 'h' => $hours, 'r' => $reason ?: null, 'id' => $otId]);
    }

    /** อนุมัติ/ปฏิเสธ OT */
    public static function decideOt(int $otId, string $decision): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        $ot = Database::one('SELECT * FROM ot_requests WHERE id=:id', ['id' => $otId]);
        if (!$ot) throw new RuntimeException('ไม่พบคำขอ OT');
        if (!self::canApproveFor((int) $ot['employee_id'])) throw new RuntimeException('คุณอนุมัติได้เฉพาะคำขอของทีมตัวเอง');
        if ($ot['is_locked']) throw new RuntimeException('คำขอ OT นี้ถูกล็อกแล้ว');
        Database::run('UPDATE ot_requests SET status=:s, approved_by=:by WHERE id=:id',
            ['s' => $decision, 'by' => Auth::id(), 'id' => $otId]);
        self::notify((int) $ot['employee_id'],
            'คำขอ OT ของคุณได้รับการ' . ($decision === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ') . 'แล้ว');
    }

    /** ทีมที่ผู้ใช้ปัจจุบันดูแล (= ทีมของพนักงานที่ผูกกับ user) */
    public static function approverTeam(): ?string
    {
        $emp = self::currentEmployee();
        return $emp['team'] ?? null;
    }

    /** ผู้ใช้ปัจจุบันมีสิทธิ์พิจารณาคำขอของพนักงานคนนี้ไหม (ทั้งองค์กร หรือเฉพาะทีม §5.1) */
    public static function canApproveFor(int $employeeId): bool
    {
        if (Auth::can('hr.approve')) return true;            // HR/admin = ทั้งองค์กร
        if (Auth::can('hr.approve_team')) {                  // หัวหน้างาน = เฉพาะทีมตัวเอง
            $myTeam = self::approverTeam();
            if (!$myTeam) return false;
            $empTeam = Database::scalar('SELECT team FROM employees WHERE id=:id', ['id' => $employeeId]);
            return $empTeam !== null && $empTeam === $myTeam;
        }
        return false;
    }

    /** จำนวนคำขอ (ลา+OT) ที่ผู้ใช้ปัจจุบันมีสิทธิ์อนุมัติ */
    public static function pendingApprovalCount(): int
    {
        if (Auth::can('hr.approve')) {
            return (int) Database::scalar("SELECT (SELECT COUNT(*) FROM leave_requests WHERE status='pending')
                + (SELECT COUNT(*) FROM ot_requests WHERE status='pending')");
        }
        if (Auth::can('hr.approve_team')) {
            $team = self::approverTeam();
            if (!$team) return 0;
            return (int) Database::scalar(
                "SELECT (SELECT COUNT(*) FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE l.status='pending' AND e.team=:t)
                      + (SELECT COUNT(*) FROM ot_requests o JOIN employees e ON e.id=o.employee_id WHERE o.status='pending' AND e.team=:t2)",
                ['t' => $team, 't2' => $team]
            );
        }
        return 0;
    }

    /** อนุมัติ/ปฏิเสธการลา (approval §5.1 — รองรับ scope ทีม) */
    public static function decideLeave(int $leaveId, string $decision): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        $lv = Database::one('SELECT * FROM leave_requests WHERE id=:id', ['id' => $leaveId]);
        if (!$lv) throw new RuntimeException('ไม่พบใบลา');
        if (!self::canApproveFor((int) $lv['employee_id'])) throw new RuntimeException('คุณอนุมัติได้เฉพาะคำขอของทีมตัวเอง');
        if ($lv['is_locked']) throw new RuntimeException('ใบลานี้ถูกล็อกแล้ว');
        Database::run('UPDATE leave_requests SET status=:s, approved_by=:by WHERE id=:id',
            ['s' => $decision, 'by' => Auth::id(), 'id' => $leaveId]);
        self::notify((int) $lv['employee_id'],
            'ใบลาของคุณได้รับการ' . ($decision === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ') . 'แล้ว');
    }

    /** สถานะการตอกบัตรวันนี้ของพนักงาน → ['none'|'in'|'out', check_in, check_out] */
    public static function todayClock(int $employeeId): array
    {
        $row = Database::one('SELECT check_in, check_out FROM attendance WHERE employee_id=:e AND work_date=:d',
            ['e' => $employeeId, 'd' => date('Y-m-d')]);
        if (!$row || !$row['check_in']) return ['state' => 'none', 'in' => null, 'out' => null];
        if ($row['check_out']) return ['state' => 'out', 'in' => $row['check_in'], 'out' => $row['check_out']];
        return ['state' => 'in', 'in' => $row['check_in'], 'out' => null];
    }

    /** หาไซต์งานที่ใกล้ที่สุดจากพิกัด (คำนวณที่ server) */
    private static function nearestSite(float $lat, float $lon): ?array
    {
        $best = null; $bestDist = INF;
        foreach (Database::all('SELECT * FROM work_sites WHERE is_active=1') as $s) {
            $d = Geo::distanceMeters($lat, $lon, (float)$s['latitude'], (float)$s['longitude']);
            if ($d < $bestDist) { $bestDist = $d; $best = $s; }
        }
        if (!$best) return null;
        [$dist, $status] = Geo::evaluate($lat, $lon, (float)$best['latitude'], (float)$best['longitude'], (int)$best['allowed_radius_m']);
        return ['site' => $best, 'distance' => $dist, 'status' => $status];
    }

    /**
     * ตอกบัตรลัด เข้า/ออกงาน (เก็บเวลา + หลักฐาน GPS หน้างาน)
     * @param string $dir  'in' | 'out'
     * @return array ['time','site_name','status','distance']
     */
    public static function quickClock(int $employeeId, string $dir, float $lat, float $lon, ?float $accuracy = null, ?string $photoPath = null): array
    {
        if (!in_array($dir, ['in', 'out'], true)) throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        $today = date('Y-m-d');
        $now   = date('H:i:s');

        $existing = Database::one('SELECT id, check_in, check_out, is_locked FROM attendance WHERE employee_id=:e AND work_date=:d',
            ['e' => $employeeId, 'd' => $today]);
        if ($existing && $existing['is_locked']) throw new RuntimeException('ข้อมูลวันนี้ถูกล็อก (ปิดงวดแล้ว)');

        if ($dir === 'in' && $existing && $existing['check_in']) throw new RuntimeException('เช็คอินเข้างานไปแล้ววันนี้ (' . substr($existing['check_in'],0,5) . ')');
        if ($dir === 'out') {
            if (!$existing || !$existing['check_in']) throw new RuntimeException('ยังไม่ได้เช็คอินเข้างานวันนี้');
            if ($existing['check_out']) throw new RuntimeException('เช็คเอาท์ออกงานไปแล้ว (' . substr($existing['check_out'],0,5) . ')');
        }

        $near = self::nearestSite($lat, $lon);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // 1) เวลาเข้า/ออก ในตาราง attendance (upsert)
            if ($dir === 'in') {
                if ($existing) {
                    $pdo->prepare("UPDATE attendance SET check_in=:t, created_by=:cb, entry_method='self' WHERE id=:id")
                        ->execute(['t' => $now, 'cb' => Auth::id(), 'id' => $existing['id']]);
                } else {
                    $pdo->prepare("INSERT INTO attendance (employee_id, work_date, check_in, created_by, entry_method) VALUES (:e,:d,:t,:cb,'self')")
                        ->execute(['e' => $employeeId, 'd' => $today, 't' => $now, 'cb' => Auth::id()]);
                }
            } else {
                $pdo->prepare('UPDATE attendance SET check_out=:t WHERE id=:id')->execute(['t' => $now, 'id' => $existing['id']]);
            }

            // 2) หลักฐาน GPS หน้างาน (ถ้ามีไซต์)
            if ($near) {
                $pdo->prepare(
                    'INSERT INTO site_checkins (employee_id, site_id, kind, checkin_lat, checkin_long, distance_from_site_m, gps_accuracy_m, photo_path, status, device_info)
                     VALUES (:e,:s,:k,:lat,:lon,:dist,:acc,:photo,:st,:dev)'
                )->execute([
                    'e' => $employeeId, 's' => $near['site']['id'], 'k' => $dir,
                    'lat' => $lat, 'lon' => $lon, 'dist' => $near['distance'],
                    'acc' => $accuracy, 'photo' => $photoPath, 'st' => $near['status'],
                    'dev' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return [
            'time' => substr($now, 0, 5),
            'site_name' => $near['site']['site_name'] ?? null,
            'status' => $near['status'] ?? null,
            'distance' => $near['distance'] ?? null,
        ];
    }

    public static function entryBadge(string $method): array
    {
        return [
            'self'      => ['ตนเอง',  'badge-muted'],
            'on_behalf' => ['HR ทำแทน','badge-gold'],
            'import'    => ['นำเข้า',  'badge-blue'],
        ][$method] ?? [$method, 'badge-muted'];
    }

    // ─────────────────────────────────────────────────────
    //  Payroll + commission
    // ─────────────────────────────────────────────────────

    /** สร้าง/คำนวณงวดเงินเดือน (commission ดึงจากยอดขายที่พนักงานทำในเดือนนั้น) */
    public static function generatePayroll(string $period): array
    {
        // period = "2569-06" (พ.ศ.) → แปลงเป็น ค.ศ. เพื่อ query วันที่
        [$beYear, $month] = explode('-', $period);
        $ceYear = (int) $beYear - 543;
        $monthStart = sprintf('%04d-%02d-01', $ceYear, (int) $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $p = Database::one('SELECT * FROM payroll_periods WHERE period=:p', ['p' => $period]);
            if ($p && $p['status'] === 'locked') throw new RuntimeException('งวดนี้ถูกล็อกแล้ว');
            if (!$p) {
                $pdo->prepare('INSERT INTO payroll_periods (period) VALUES (:p)')->execute(['p' => $period]);
                $periodId = (int) $pdo->lastInsertId();
            } else {
                $periodId = (int) $p['id'];
                $pdo->prepare('DELETE FROM payroll_items WHERE period_id=:id')->execute(['id' => $periodId]);
            }

            // คืนสถานะการเบิกที่เคยหักในงวดนี้ (ให้คำนวณซ้ำได้แบบ idempotent)
            $pdo->prepare("UPDATE salary_advances SET status='approved', period_deducted=NULL WHERE period_deducted=:p")
                ->execute(['p' => $period]);

            $employees = Database::all('SELECT * FROM employees WHERE is_active=1');
            $ins = $pdo->prepare(
                'INSERT INTO payroll_items (period_id, employee_id, base_salary, commission, ot_pay, leave_days, deduction, advance_deduct, net_pay)
                 VALUES (:pid,:eid,:base,:comm,:ot,:lv,:ded,:adv,:net)'
            );
            $count = 0;
            foreach ($employees as $emp) {
                // commission = ยอดขายที่ตัวเองเป็นผู้สร้าง (delivered/invoiced) ในเดือน × rate%
                $sales = 0.0;
                if ($emp['user_id']) {
                    $sales = (float) Database::scalar(
                        "SELECT COALESCE(SUM(total),0) FROM sales_orders
                         WHERE created_by=:uid AND status IN ('delivered','invoiced')
                         AND created_at BETWEEN :s AND :e",
                        ['uid' => $emp['user_id'], 's' => $monthStart . ' 00:00:00', 'e' => $monthEnd . ' 23:59:59']
                    );
                }
                $commission = round($sales * (float) $emp['commission_rate'] / 100, 2);

                // วันลา (approved) ประเภทที่หักเงิน → หักเงิน (ฐาน/30 ต่อวัน)
                try {
                    $leaveDays = (float) Database::scalar(
                        "SELECT COALESCE(SUM(days),0) FROM leave_requests
                         WHERE employee_id=:e AND status='approved'
                         AND leave_type IN (SELECT code FROM leave_types WHERE deduct_pay=1)
                         AND date_from BETWEEN :s AND :e2",
                        ['e' => $emp['id'], 's' => $monthStart, 'e2' => $monthEnd]
                    );
                } catch (\Throwable $e) {
                    $leaveDays = (float) Database::scalar(
                        "SELECT COALESCE(SUM(days),0) FROM leave_requests
                         WHERE employee_id=:e AND status='approved' AND leave_type IN ('personal','other')
                         AND date_from BETWEEN :s AND :e2",
                        ['e' => $emp['id'], 's' => $monthStart, 'e2' => $monthEnd]
                    );
                }
                // OT (approved) ในเดือน → จ่ายเพิ่ม (ฐาน/30/8 ต่อชม. × 1.5)
                $otHours = (float) Database::scalar(
                    "SELECT COALESCE(SUM(hours),0) FROM ot_requests
                     WHERE employee_id=:e AND status='approved' AND ot_date BETWEEN :s AND :e2",
                    ['e' => $emp['id'], 's' => $monthStart, 'e2' => $monthEnd]
                );
                $base = (float) $emp['base_salary'];
                $otPay = round($base / 30 / 8 * 1.5 * $otHours, 2);
                $deduction = round($base / 30 * $leaveDays, 2);

                // หักเบิกเงินล่วงหน้าที่อนุมัติแล้วและยังไม่ถูกหัก
                $advance = (float) Database::scalar(
                    "SELECT COALESCE(SUM(amount),0) FROM salary_advances
                     WHERE employee_id=:e AND status='approved' AND period_deducted IS NULL",
                    ['e' => $emp['id']]
                );
                $net = $base + $commission + $otPay - $deduction - $advance;

                $ins->execute([
                    'pid' => $periodId, 'eid' => $emp['id'], 'base' => $base, 'comm' => $commission, 'ot' => $otPay,
                    'lv' => $leaveDays, 'ded' => $deduction, 'adv' => $advance, 'net' => $net,
                ]);
                // ทำเครื่องหมายว่าหักแล้วในงวดนี้
                if ($advance > 0) {
                    $pdo->prepare("UPDATE salary_advances SET status='deducted', period_deducted=:p
                                   WHERE employee_id=:e AND status='approved' AND period_deducted IS NULL")
                        ->execute(['p' => $period, 'e' => $emp['id']]);
                }
                $count++;
            }
            $pdo->commit();
            return ['period_id' => $periodId, 'count' => $count];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** ปิดงวด: ล็อกงวด + ล็อก attendance/leave ของเดือนนั้น (§5.1 ปิดงวด = ห้ามแก้) */
    public static function lockPayroll(int $periodId): void
    {
        $p = Database::one('SELECT * FROM payroll_periods WHERE id=:id', ['id' => $periodId]);
        if (!$p) throw new RuntimeException('ไม่พบงวด');
        if ($p['status'] === 'locked') throw new RuntimeException('งวดนี้ถูกล็อกอยู่แล้ว');

        [$beYear, $month] = explode('-', $p['period']);
        $ceYear = (int) $beYear - 543;
        $monthStart = sprintf('%04d-%02d-01', $ceYear, (int) $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE payroll_periods SET status='locked', locked_at=NOW(), locked_by=:by WHERE id=:id")
                ->execute(['by' => Auth::id(), 'id' => $periodId]);
            $pdo->prepare('UPDATE attendance SET is_locked=1 WHERE work_date BETWEEN :s AND :e')
                ->execute(['s' => $monthStart, 'e' => $monthEnd]);
            $pdo->prepare('UPDATE leave_requests SET is_locked=1 WHERE date_from BETWEEN :s AND :e')
                ->execute(['s' => $monthStart, 'e' => $monthEnd]);
            $pdo->prepare('UPDATE ot_requests SET is_locked=1 WHERE ot_date BETWEEN :s AND :e')
                ->execute(['s' => $monthStart, 'e' => $monthEnd]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
