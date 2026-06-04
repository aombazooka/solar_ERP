<?php
/**
 * Geo.php — คำนวณระยะทางภูมิศาสตร์ (Haversine)
 * ───────────────────────────────────────────────────────────
 * ⚠️ กฎเหล็ก (context.md §5.2): คำนวณระยะที่ SERVER เท่านั้น
 *    ห้ามเชื่อ "distance" ที่ client ส่งมา — รับแค่พิกัดดิบ (lat/long)
 *    แล้วคำนวณเองที่นี่ เพื่อกันการปลอมระยะ
 */

declare(strict_types=1);

final class Geo
{
    /** รัศมีโลกเฉลี่ย (เมตร) */
    private const EARTH_RADIUS_M = 6371000.0;

    /** ระยะทางระหว่าง 2 พิกัด (เมตร) ด้วยสูตร Haversine */
    public static function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $rLat1 = deg2rad($lat1);
        $rLat2 = deg2rad($lat2);
        $dLat  = deg2rad($lat2 - $lat1);
        $dLon  = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos($rLat1) * cos($rLat2) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * ประเมินผลเช็คอินจากพิกัดดิบ
     * @return array [distance_m, status]
     *   status: approved (ในรัศมี) | out_of_range (นอกรัศมี) | pending_review (GPS แม่นยำต่ำ)
     */
    public static function evaluate(
        float $checkinLat, float $checkinLon,
        float $siteLat, float $siteLon,
        int $allowedRadiusM, ?float $accuracyM = null
    ): array {
        $distance = round(self::distanceMeters($checkinLat, $checkinLon, $siteLat, $siteLon), 2);

        // ความแม่นยำ GPS แย่มาก (> 100 ม.) → ส่งตรวจสอบ ไม่ตัดสินอัตโนมัติ
        if ($accuracyM !== null && $accuracyM > 100) {
            return [$distance, 'pending_review'];
        }
        $status = $distance <= $allowedRadiusM ? 'approved' : 'out_of_range';
        return [$distance, $status];
    }

    public static function statusLabel(string $s): array
    {
        return [
            'approved'       => ['อยู่ในพื้นที่',  'badge-green', 'fa-circle-check'],
            'out_of_range'   => ['นอกพื้นที่',    'badge-red',   'fa-circle-xmark'],
            'pending_review' => ['รอตรวจสอบ',    'badge-gold',  'fa-clock'],
        ][$s] ?? [$s, 'badge-muted', 'fa-circle'];
    }
}
