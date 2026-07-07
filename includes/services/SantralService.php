<?php
declare(strict_types=1);

/**
 * includes/services/SantralService.php — Santral (PBX) API iskeleti.
 * API dokümanı gelene kadar yalnızca altyapı; gerçek çağrı entegrasyonu YOK.
 * Hazır kancalar: gelen çağrıyı müşteriyle eşleştirme, arama geçmişi, tıkla-ara.
 */
require_once __DIR__ . '/../integrations.php';

final class SantralService
{
    public static function isConfigured(): bool
    {
        $r = get_integration('pbx');
        return !empty($r['is_active']) && trim((string) ($r['api_url'] ?? '')) !== '';
    }

    /** Gelen numarayı müşteri kartıyla eşleştirir (altyapı). */
    public static function matchIncoming(string $phone): array
    {
        // Sadece yerel eşleştirme (dış API yok): telefon → müşteri.
        try {
            require_once __DIR__ . '/../customers.php';
            $digits = preg_replace('/\D+/', '', $phone);
            if ($digits === '') { return ['matched' => false]; }
            $st = \db()->prepare('SELECT id, company_name FROM customers WHERE is_deleted = 0 AND REPLACE(REPLACE(phone,\' \',\'\'),\'-\',\'\') LIKE :p LIMIT 1');
            $st->execute([':p' => '%' . substr($digits, -7)]);
            $row = $st->fetch();
            return $row ? ['matched' => true, 'customer' => $row] : ['matched' => false];
        } catch (\Throwable $e) { return ['matched' => false]; }
    }

    /** Tıkla-ara (altyapı). Gerçek arama başlatma API dokümanıyla eklenecek. */
    public static function click2call(string $phone): array
    {
        return ['ok' => false, 'message' => 'Santral API dokümanı bağlandığında etkinleşecek (placeholder).'];
    }
}
