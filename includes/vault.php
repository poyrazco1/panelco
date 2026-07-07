<?php
declare(strict_types=1);

/**
 * includes/vault.php — Güvenli Şifre Kasası iş mantığı.
 *
 * GÜVENLİK İLKELERİ:
 *  - Şifreler DÜZ METİN saklanmaz; AES-256-GCM ile şifrelenir (VAULT_KEY, config.php).
 *  - Şifreyi görüntülemek için kullanıcı KENDİ panel şifresini yeniden girer.
 *  - Her görüntüleme (reveal) loglanır: kim, ne zaman, hangi kayıt, IP.
 *  - Eski şifreler password_vault_history'de saklanır.
 *  - Anahtar placeholder ise kasa çalışmaz (şifreleme/çözme reddedilir).
 *  - VAULT_KEY / düz şifre asla log'a, ekrana, hata mesajına yazılmaz.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Kasa şifreleme için yapılandırılmış mı? */
function vault_is_configured(): bool
{
    if (!defined('VAULT_KEY')) { return false; }
    $key = (string) VAULT_KEY;
    $ph = defined('VAULT_KEY_PLACEHOLDER') ? (string) VAULT_KEY_PLACEHOLDER : '';
    if ($key === '' || $key === $ph) { return false; }
    if (!function_exists('openssl_encrypt')) { return false; }
    return true;
}

/** 32 baytlık türetilmiş anahtar (config değeri ne uzunlukta olursa olsun). */
function vault_key(): string
{
    return hash('sha256', (string) VAULT_KEY, true);
}

/** Düz metni şifreler → base64(iv|tag|cipher). Boş/yapılandırılmamışsa null. */
function vault_encrypt(string $plain): ?string
{
    if (!vault_is_configured() || $plain === '') { return null; }
    try {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', vault_key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) { return null; }
        return base64_encode($iv . $tag . $cipher);
    } catch (Throwable $e) {
        log_error('vault_encrypt hata (detay gizli)');
        return null;
    }
}

/** Şifreli değeri çözer → düz metin. Hata/yapılandırma yoksa null. */
function vault_decrypt(?string $enc): ?string
{
    if (!vault_is_configured() || $enc === null || $enc === '') { return null; }
    try {
        $raw = base64_decode($enc, true);
        if ($raw === false || strlen($raw) < 28) { return null; }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', vault_key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    } catch (Throwable $e) {
        log_error('vault_decrypt hata (detay gizli)');
        return null;
    }
}

/** Kategoriler. */
function vault_categories(): array
{
    return [
        'computers'   => 'Bilgisayarlar',
        'servers'     => 'Sunucular',
        'plesk'       => 'Plesk',
        'email'       => 'E-posta Hesapları',
        'marketplace' => 'Pazaryerleri',
        'tsoft'       => 'T-Soft',
        'logo_erp'    => 'Logo ERP',
        'pbx'         => 'Santral',
        'banking'     => 'Banka / Ödeme',
        'social'      => 'Sosyal Medya',
        'api'         => 'API Bilgileri',
        'wifi'        => 'Wi-Fi',
        'printers'    => 'Yazıcılar',
        'other'       => 'Diğer',
    ];
}
function vault_category_label(string $k): string { return vault_categories()[$k] ?? $k; }

/** Süper admin (tüm kayıtları görür). */
function vault_is_admin(): bool
{
    $perms = function_exists('current_permissions') ? current_permissions() : [];
    return in_array('all', $perms, true);
}

/** Kayıt bu kullanıcı tarafından görülebilir mi? (allowed_user_ids kısıtı). */
function vault_can_see(array $record): bool
{
    if (vault_is_admin()) { return true; }
    $uid = (int) (current_user_id() ?? 0);
    if ((int) ($record['created_by'] ?? 0) === $uid && $uid > 0) { return true; }
    $allowed = json_decode((string) ($record['allowed_user_ids'] ?? ''), true);
    if (!is_array($allowed) || !$allowed) { return true; } // kısıt yoksa tüm yetkililer görür
    return in_array($uid, array_map('intval', $allowed), true);
}

/** Kasa kayıtları (listede şifre DÖNMEZ). */
function get_vault_items(array $f = []): array
{
    $where = ['is_deleted = 0'];
    $params = [];
    if (!empty($f['category']) && isset(vault_categories()[$f['category']])) { $where[] = 'category = :cat'; $params[':cat'] = $f['category']; }
    if (!empty($f['search'])) { $where[] = '(title LIKE :q OR username LIKE :q OR url LIKE :q OR responsible_person LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $st = db()->prepare('SELECT * FROM password_vault WHERE ' . implode(' AND ', $where) . ' ORDER BY title ASC LIMIT 1000');
        $st->execute($params);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { log_error('get_vault_items: ' . $e->getMessage()); return []; }
    // Görünürlük süzmesi
    return array_values(array_filter($rows, 'vault_can_see'));
}

/** Tek kayıt (görünürlük kontrollü). Şifre çözülmez; secret_enc döner. */
function get_vault_item(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM password_vault WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) { return null; }
        return vault_can_see($row) ? $row : null;
    } catch (Throwable $e) { log_error('get_vault_item: ' . $e->getMessage()); return null; }
}

function vault_counts(): array
{
    $out = ['all' => 0];
    try {
        foreach (db()->query('SELECT category, COUNT(*) c FROM password_vault WHERE is_deleted = 0 GROUP BY category')->fetchAll() as $r) {
            $out['all'] += (int) $r['c'];
            $out[(string) $r['category']] = (int) $r['c'];
        }
    } catch (Throwable $e) { log_error('vault_counts: ' . $e->getMessage()); }
    return $out;
}

function vault_fields_from_input(array $in): array
{
    $cat = (string) ($in['category'] ?? 'other');
    if (!isset(vault_categories()[$cat])) { $cat = 'other'; }
    $allowed = [];
    foreach ((array) ($in['allowed_user_ids'] ?? []) as $uid) {
        $uid = (int) $uid;
        if ($uid > 0) { $allowed[] = $uid; }
    }
    return [
        'title'              => trim((string) ($in['title'] ?? '')),
        'category'           => $cat,
        'username'           => trim((string) ($in['username'] ?? '')),
        'secret'             => (string) ($in['secret'] ?? ''), // düz metin (kaydederken şifrelenir)
        'url'                => trim((string) ($in['url'] ?? '')),
        'description'        => trim((string) ($in['description'] ?? '')),
        'responsible_person' => trim((string) ($in['responsible_person'] ?? '')),
        'change_period_days' => (int) ($in['change_period_days'] ?? 0) ?: null,
        'allowed_user_ids'   => $allowed ? json_encode(array_values(array_unique($allowed))) : null,
    ];
}

function vault_validate(array $d): array
{
    $errors = [];
    if (($d['title'] ?? '') === '') { $errors[] = 'Başlık zorunludur.'; }
    if (!vault_is_configured()) { $errors[] = 'Şifre kasası yapılandırılmamış (VAULT_KEY). Yönetici config.php içinde anahtar tanımlamalı.'; }
    return $errors;
}

function create_vault_item(array $d, ?int $userId): int
{
    try {
        $enc = ($d['secret'] ?? '') !== '' ? vault_encrypt((string) $d['secret']) : null;
        $st = db()->prepare(
            'INSERT INTO password_vault
                (title, category, username, secret_enc, url, description, responsible_person,
                 last_changed_at, change_period_days, allowed_user_ids, created_by, updated_by)
             VALUES
                (:title,:cat,:user,:secret,:url,:desc,:resp,NOW(),:period,:allowed,:cby,:uby)'
        );
        $st->execute([
            ':title' => $d['title'], ':cat' => $d['category'], ':user' => $d['username'] ?: null,
            ':secret' => $enc, ':url' => $d['url'] ?: null, ':desc' => $d['description'] ?: null,
            ':resp' => $d['responsible_person'] ?: null, ':period' => $d['change_period_days'],
            ':allowed' => $d['allowed_user_ids'], ':cby' => $userId, ':uby' => $userId,
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('create_vault_item: ' . $e->getMessage()); return 0; }
}

function update_vault_item(int $id, array $d, ?int $userId): bool
{
    try {
        $current = get_vault_item($id);
        if (!$current) { return false; }

        // Şifre değiştiyse eski değeri geçmişe taşı ve yeniden şifrele.
        $secretChanged = ($d['secret'] ?? '') !== '';
        $enc = $current['secret_enc'];
        $lastChanged = '';
        if ($secretChanged) {
            // Eski değeri geçmişe yaz
            if (!empty($current['secret_enc'])) {
                db()->prepare('INSERT INTO password_vault_history (vault_id, username, secret_enc, changed_by) VALUES (:vid,:user,:enc,:by)')
                    ->execute([':vid' => $id, ':user' => $current['username'], ':enc' => $current['secret_enc'], ':by' => $userId]);
            }
            $enc = vault_encrypt((string) $d['secret']);
            $lastChanged = ', last_changed_at = NOW()';
        }

        $st = db()->prepare(
            'UPDATE password_vault SET
                title=:title, category=:cat, username=:user, secret_enc=:secret, url=:url, description=:desc,
                responsible_person=:resp, change_period_days=:period, allowed_user_ids=:allowed, updated_by=:uby' . $lastChanged . '
             WHERE id=:id AND is_deleted = 0'
        );
        return $st->execute([
            ':title' => $d['title'], ':cat' => $d['category'], ':user' => $d['username'] ?: null,
            ':secret' => $enc, ':url' => $d['url'] ?: null, ':desc' => $d['description'] ?: null,
            ':resp' => $d['responsible_person'] ?: null, ':period' => $d['change_period_days'],
            ':allowed' => $d['allowed_user_ids'], ':uby' => $userId, ':id' => $id,
        ]);
    } catch (Throwable $e) { log_error('update_vault_item: ' . $e->getMessage()); return false; }
}

function delete_vault_item(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE password_vault SET is_deleted = 1, updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('delete_vault_item: ' . $e->getMessage()); return false; }
}

/** Kullanıcının panel şifresini doğrular (reveal için). */
function vault_verify_panel_password(int $userId, string $password): bool
{
    if ($userId <= 0 || $password === '') { return false; }
    try {
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $st->execute([':id' => $userId]);
        $hash = (string) ($st->fetchColumn() ?: '');
        return $hash !== '' && password_verify($password, $hash);
    } catch (Throwable $e) { log_error('vault_verify_panel_password: ' . $e->getMessage()); return false; }
}

/** Görüntüleme (reveal) erişimini loglar. */
function vault_log_access(int $vaultId, string $action = 'reveal'): void
{
    try {
        db()->prepare('INSERT INTO password_vault_access (vault_id, user_id, action, ip) VALUES (:vid,:uid,:act,:ip)')
            ->execute([
                ':vid' => $vaultId, ':uid' => current_user_id(), ':act' => $action,
                ':ip' => function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null),
            ]);
    } catch (Throwable $e) { log_error('vault_log_access: ' . $e->getMessage()); }
    // Ayrıca genel denetim loguna (şifre YAZILMAZ)
    if (function_exists('log_activity')) {
        log_activity('vault_' . $action, 'vault', $vaultId, null, 'success', 'Kasa kaydı görüntülendi');
    }
}

/** Bir kaydın erişim geçmişi (son N). */
function vault_access_log(int $vaultId, int $limit = 20): array
{
    try {
        $st = db()->prepare('SELECT a.*, u.full_name FROM password_vault_access a LEFT JOIN users u ON u.id = a.user_id WHERE a.vault_id = :vid ORDER BY a.id DESC LIMIT ' . max(1, min(100, $limit)));
        $st->execute([':vid' => $vaultId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('vault_access_log: ' . $e->getMessage()); return []; }
}

/** Şifre değişim geçmişi sayısı. */
function vault_history_count(int $vaultId): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM password_vault_history WHERE vault_id = :vid');
        $st->execute([':vid' => $vaultId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
