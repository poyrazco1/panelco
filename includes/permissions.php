<?php
declare(strict_types=1);

/**
 * includes/permissions.php
 * Rol / yetki kontrolü. Yetkiler modül anahtarları listesi olarak saklanır.
 * Özel anahtar "all" => tüm modüller (Yönetici).
 */

require_once __DIR__ . '/auth.php';

/**
 * Sistemdeki modüller ve etiketleri.
 * Yeni modül eklerken buraya bir satır eklemek yeterlidir.
 */
function all_modules(): array
{
    return [
        'dashboard' => 'Genel Bakış',
        'currency'  => 'Kur Çevirici',
        'settings'  => 'Ayarlar',
        'shipping'  => 'Kargo Yöntemleri',
        'brands'    => 'Markalar',
        'personnel' => 'Personeller',
        'service'   => 'Teknik Servis',
        'rma'       => 'İade-Değişim Yönetimi',
        'leave'     => 'İzin / İK',
        'attendance'=> 'Puantaj',
    ];
}

/**
 * Oturumdaki kullanıcının yetki listesi.
 */
function current_permissions(): array
{
    $raw = $_SESSION['role_permissions'] ?? '[]';
    $arr = json_decode((string) $raw, true);
    return is_array($arr) ? $arr : [];
}

/**
 * Kullanıcı belirtilen modüle erişebilir mi?
 */
function can(string $moduleKey): bool
{
    if (!is_logged_in()) {
        return false;
    }
    $perms = current_permissions();
    return in_array('all', $perms, true) || in_array($moduleKey, $perms, true);
}

/**
 * Geçerli yetki anahtarlarını temizler. "all" varsa yalnızca ["all"].
 */
function sanitize_permissions(array $keys): string
{
    if (in_array('all', $keys, true)) {
        return json_encode(['all']);
    }
    $valid = array_keys(all_modules());
    return json_encode(array_values(array_intersect($valid, $keys)));
}

/**
 * Yetki zorunluluğu. Girişsizse login'e; yetkisizse sade 403 sayfasına yönlendirir.
 * (Kendi kendine yeten sayfa — layout parçalarına bağımlı değildir.)
 */
function require_permission(string $moduleKey): void
{
    require_login();
    if (can($moduleKey)) {
        return;
    }

    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $modules = all_modules();
    $label   = $modules[$moduleKey] ?? $moduleKey;
    $home    = e(url('dashboard.php'));
    $logout  = e(url('logout.php'));
    $site    = e(SITE_NAME);
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Erişim engellendi · <?= $site ?></title>
        <style>
            body{font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#F5F6F8;color:#1F2937;margin:0;
                 min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
            .box{background:#fff;border:1px solid #E2E5EA;border-radius:8px;max-width:440px;width:100%;
                 padding:32px;box-shadow:0 1px 2px rgba(17,24,39,.05);text-align:center}
            h1{font-size:20px;margin:0 0 8px}
            p{color:#6B7280;margin:0 0 20px}
            .btn{display:inline-block;padding:10px 16px;border-radius:6px;font-size:14px;text-decoration:none;
                 border:1px solid #E2E5EA;color:#1F2937;background:#fff;margin:0 4px}
            .btn-primary{background:#1F2A44;border-color:#1F2A44;color:#fff}
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Erişim engellendi</h1>
            <p>“<?= e($label) ?>” bölümüne erişim yetkiniz bulunmuyor.</p>
            <a class="btn btn-primary" href="<?= $home ?>">Genel Bakış</a>
            <a class="btn" href="<?= $logout ?>">Çıkış</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Yönetici (sistem) rollerinin id listesi.
 */
function admin_role_ids(): array
{
    try {
        $ids = db()->query('SELECT id FROM roles WHERE is_system = 1')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids);
    } catch (Throwable $e) {
        log_error('admin_role_ids hatası: ' . $e->getMessage());
        return [];
    }
}

/**
 * Bir rol id'si yönetici (sistem) rolü mü?
 */
function is_admin_role(?int $roleId): bool
{
    if ($roleId === null) {
        return false;
    }
    return in_array($roleId, admin_role_ids(), true);
}

/**
 * Aktif yönetici kullanıcı sayısı (isteğe bağlı olarak bir kullanıcı hariç).
 * "En az bir yönetici kalmalı" kuralı için kullanılır.
 */
function count_active_admins(?int $excludeUserId = null): int
{
    try {
        $sql = 'SELECT COUNT(*) FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.is_active = 1 AND r.is_system = 1';
        $params = [];
        if ($excludeUserId !== null) {
            $sql .= ' AND u.id <> :ex';
            $params[':ex'] = $excludeUserId;
        }
        $st = db()->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        log_error('count_active_admins hatası: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Verilen taban slug'dan benzersiz bir rol slug'ı üretir.
 */
function role_unique_slug(string $base, ?int $excludeId = null): string
{
    $base = $base !== '' ? $base : 'rol';
    $slug = $base;
    $i = 2;
    try {
        while (true) {
            $sql = 'SELECT COUNT(*) FROM roles WHERE slug = :s' . ($excludeId !== null ? ' AND id <> :id' : '');
            $params = [':s' => $slug];
            if ($excludeId !== null) {
                $params[':id'] = $excludeId;
            }
            $st = db()->prepare($sql);
            $st->execute($params);
            if ((int) $st->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 100) {
                return $base . '-' . substr((string) time(), -5);
            }
        }
    } catch (Throwable $e) {
        log_error('role_unique_slug hatası: ' . $e->getMessage());
        return $base . '-' . substr((string) time(), -5);
    }
}
