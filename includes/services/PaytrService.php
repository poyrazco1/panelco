<?php
declare(strict_types=1);

/**
 * includes/services/PaytrService.php
 * PayTR ödeme altyapısı (iskelet). Merchant bilgileri entegrasyon ayarlarından
 * (şifreli) okunur. Token üretimi PayTR'nin belgelenmiş yöntemine göre yapılır;
 * ANCAK canlı ödeme akışı (iframe sayfası + bildirim/callback doğrulaması)
 * sipariş entegrasyonu tamamlanınca etkinleştirilecektir. Uydurma akış YOKTUR.
 */

require_once __DIR__ . '/../integrations.php';

final class PaytrService
{
    /** Yapılandırma tam mı? */
    public static function isConfigured(): bool
    {
        $s = integration_secrets('paytr');
        return trim((string) ($s['merchant_id'] ?? '')) !== ''
            && trim((string) ($s['merchant_key'] ?? '')) !== ''
            && trim((string) ($s['merchant_salt'] ?? '')) !== '';
    }

    /** Test modunda mı? */
    public static function isTestMode(): bool
    {
        $c = integration_config('paytr');
        return (int) ($c['test_mode'] ?? 1) === 1;
    }

    /**
     * PayTR iframe token'ı üretir (belgelenmiş HMAC-SHA256 yöntemi).
     * Not: Bu yalnızca token/altyapıdır; iframe sayfası ve callback doğrulaması
     * ayrı adımlardır ve sipariş akışı entegre edilince devreye alınacaktır.
     *
     * @return array{ok:bool, token:?string, post:?array, message:string}
     */
    public static function createIframeToken(array $params): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'token' => null, 'post' => null, 'message' => 'PayTR bilgileri yapılandırılmamış.'];
        }
        $s = integration_secrets('paytr');
        $merchantId   = (string) $s['merchant_id'];
        $merchantKey  = (string) $s['merchant_key'];
        $merchantSalt = (string) $s['merchant_salt'];

        $userIp      = (string) ($params['user_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
        $merchantOid = (string) ($params['merchant_oid'] ?? '');
        $email       = (string) ($params['email'] ?? '');
        $paymentAmount = (int) ($params['payment_amount'] ?? 0); // kuruş
        $userBasket  = base64_encode(json_encode($params['basket'] ?? [], JSON_UNESCAPED_UNICODE));
        $noInstallment   = (int) ($params['no_installment'] ?? 0);
        $maxInstallment  = (int) ($params['max_installment'] ?? 0);
        $currency    = (string) ($params['currency'] ?? 'TL');
        $testMode    = self::isTestMode() ? '1' : '0';

        if ($merchantOid === '' || $email === '' || $paymentAmount <= 0) {
            return ['ok' => false, 'token' => null, 'post' => null, 'message' => 'Eksik ödeme parametreleri (sipariş no, e-posta, tutar).'];
        }

        $hashStr = $merchantId . $userIp . $merchantOid . $email . $paymentAmount . $userBasket
            . $noInstallment . $maxInstallment . $currency . $testMode;
        $token = base64_encode(hash_hmac('sha256', $hashStr . $merchantSalt, $merchantKey, true));

        $post = [
            'merchant_id'     => $merchantId,
            'user_ip'         => $userIp,
            'merchant_oid'    => $merchantOid,
            'email'           => $email,
            'payment_amount'  => $paymentAmount,
            'paytr_token'     => $token,
            'user_basket'     => $userBasket,
            'no_installment'  => $noInstallment,
            'max_installment' => $maxInstallment,
            'currency'        => $currency,
            'test_mode'       => $testMode,
        ];

        return ['ok' => true, 'token' => $token, 'post' => $post,
            'message' => 'Token üretildi (altyapı). Canlı ödeme sayfası ve callback doğrulaması sipariş akışıyla etkinleştirilecek.'];
    }

    /**
     * PayTR bildirim (callback) doğrulaması — imza kontrolü.
     * Sipariş akışı bağlanınca çağrılacaktır.
     */
    public static function verifyCallback(array $post): bool
    {
        if (!self::isConfigured()) { return false; }
        $s = integration_secrets('paytr');
        $expected = base64_encode(hash_hmac(
            'sha256',
            (string) ($post['merchant_oid'] ?? '') . (string) $s['merchant_salt'] . (string) ($post['status'] ?? '') . (string) ($post['total_amount'] ?? ''),
            (string) $s['merchant_key'],
            true
        ));
        return isset($post['hash']) && hash_equals($expected, (string) $post['hash']);
    }
}
