<?php
declare(strict_types=1);

/**
 * includes/services/LogoErpService.php — Logo ERP API iskeleti.
 * Cari/ürün/sipariş/stok/fatura aktarımı için altyapı. Gerçek entegrasyon
 * Logo API dokümanı gelene kadar placeholder; uydurma aktarım yapılmaz.
 */
require_once __DIR__ . '/../integrations.php';

final class LogoErpService
{
    public static function isConfigured(): bool
    {
        $r = get_integration('logo_erp');
        return !empty($r['is_active']) && trim((string) ($r['api_url'] ?? '')) !== '';
    }

    public static function pushCustomer(array $customer): array { return self::todo(); }
    public static function pullProducts(): array { return self::todo(); }
    public static function pushOrder(array $order): array { return self::todo(); }
    public static function stockStatus(string $code): array { return self::todo(); }

    private static function todo(): array
    {
        return ['ok' => false, 'message' => 'Logo ERP API dokümanı bağlandığında etkinleşecek (placeholder).'];
    }
}
