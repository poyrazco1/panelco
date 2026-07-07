<?php
declare(strict_types=1);

/**
 * includes/services/FingerprintService.php — Parmak izi (PDKS) API iskeleti.
 * Personel giriş/çıkış senkronizasyonu için altyapı. Gerçek cihaz API'si
 * dokümanı gelene kadar yalnızca placeholder.
 */
require_once __DIR__ . '/../integrations.php';

final class FingerprintService
{
    public static function isConfigured(): bool
    {
        $r = get_integration('fingerprint');
        return !empty($r['is_active']) && trim((string) ($r['api_url'] ?? '')) !== '';
    }

    /** Giriş/çıkış kayıtlarını senkronlar (altyapı). */
    public static function syncAttendance(?string $date = null): array
    {
        return ['ok' => false, 'imported' => 0, 'message' => 'Parmak izi cihaz API dokümanı bağlandığında etkinleşecek (placeholder).'];
    }
}
