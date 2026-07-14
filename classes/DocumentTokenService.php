<?php
declare(strict_types=1);

/**
 * classes/DocumentTokenService.php
 * Güvenli belge erişim/onay bağlantısı token'ları (mutabakat müşteri onayı vb.).
 * Ham token yalnızca üretim anında bilinir; DB'de SADECE sha256 hash saklanır.
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

final class DocumentTokenService
{
    /** Belge türüne göre public onay sayfası. */
    private static function publicPage(string $type): string
    {
        return match ($type) {
            'reconciliation' => 'mutabakat-onay.php',
            default          => '',
        };
    }

    /** Ham token için mutlak public URL (e-postaya konur). */
    public static function absoluteUrl(string $type, string $rawToken): string
    {
        $page = self::publicPage($type);
        if ($page === '') { return ''; }
        return url($page) . '?token=' . $rawToken; // url() base_url (şema+host) içerir
    }

    /**
     * Yeni token üretir ve saklar.
     * @return array{ok:bool, id:int, token:string, url:string}
     */
    public static function issue(string $type, int $docId, ?int $days = 30, ?int $userId = null): array
    {
        try {
            $raw  = bin2hex(random_bytes(32));
            $hash = hash('sha256', $raw);
            $expires = ($days !== null && $days > 0) ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
            db()->prepare(
                'INSERT INTO document_access_tokens (document_type, document_id, token_hash, expires_at, created_by)
                 VALUES (?,?,?,?,?)'
            )->execute([$type, $docId, $hash, $expires, $userId]);
            $id = (int) db()->lastInsertId();
            return ['ok' => true, 'id' => $id, 'token' => $raw, 'url' => self::absoluteUrl($type, $raw)];
        } catch (Throwable $e) {
            log_error('DocumentTokenService::issue: ' . $e->getMessage());
            return ['ok' => false, 'id' => 0, 'token' => '', 'url' => ''];
        }
    }

    /**
     * Ham token'ı doğrular. Geçerliyse token satırını döndürür (kullanım sayacını artırır).
     * İptal/süresi dolmuş/geçersiz ise null.
     */
    public static function verify(string $rawToken, string $type): ?array
    {
        if ($rawToken === '' || !ctype_xdigit($rawToken) || strlen($rawToken) < 32) { return null; }
        try {
            $hash = hash('sha256', $rawToken);
            $st = db()->prepare('SELECT * FROM document_access_tokens WHERE token_hash = ? AND document_type = ? LIMIT 1');
            $st->execute([$hash, $type]);
            $t = $st->fetch();
            if (!$t) { return null; }
            if (!empty($t['revoked_at'])) { return null; }
            if (!empty($t['expires_at']) && strtotime((string) $t['expires_at']) < time()) { return null; }
            db()->prepare('UPDATE document_access_tokens SET used_count = used_count + 1, last_used_at = NOW() WHERE id = ?')->execute([(int) $t['id']]);
            return $t;
        } catch (Throwable $e) {
            log_error('DocumentTokenService::verify: ' . $e->getMessage());
            return null;
        }
    }

    /** Bir belgenin aktif (iptal edilmemiş/süresi geçmemiş) token var mı? (bilgi amaçlı) */
    public static function hasActive(string $type, int $docId): bool
    {
        try {
            $st = db()->prepare(
                'SELECT COUNT(*) FROM document_access_tokens
                 WHERE document_type = ? AND document_id = ? AND revoked_at IS NULL
                   AND (expires_at IS NULL OR expires_at > NOW())'
            );
            $st->execute([$type, $docId]);
            return (int) $st->fetchColumn() > 0;
        } catch (Throwable $e) { log_error('DocumentTokenService::hasActive: ' . $e->getMessage()); return false; }
    }

    /** Bir belgenin tüm token'larını iptal eder. */
    public static function revokeAll(string $type, int $docId): bool
    {
        try {
            db()->prepare('UPDATE document_access_tokens SET revoked_at = NOW() WHERE document_type = ? AND document_id = ? AND revoked_at IS NULL')
                ->execute([$type, $docId]);
            return true;
        } catch (Throwable $e) { log_error('DocumentTokenService::revokeAll: ' . $e->getMessage()); return false; }
    }
}
