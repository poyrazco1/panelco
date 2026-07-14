<?php
declare(strict_types=1);

/**
 * includes/permissions.php
 * Rol / yetki kontrolü. Yetkiler modül anahtarları listesi olarak saklanır.
 * Özel anahtar "all" => tüm modüller (Yönetici).
 */

require_once __DIR__ . '/auth.php';

/**
 * İşlem (action) etiketleri — modül bazlı yetki matrisinde kullanılır.
 */
function permission_action_labels(): array
{
    return [
        'view'     => 'Görüntüle',
        'create'   => 'Ekle',
        'edit'     => 'Düzenle',
        'delete'   => 'Sil',
        'export'   => 'Dışa Aktar',
        'import'   => 'İçe Aktar',
        'print'    => 'Yazdır',
        'pdf'      => 'PDF',
        'mail'     => 'Mail',
        'whatsapp' => 'WhatsApp',
        'approve'  => 'Onayla',
        'status'   => 'Durum',
        'settings' => 'Ayarlar',
        'reveal'   => 'Şifre Göster',
        'upload'   => 'Yükle',
        // Sevkiyat modülü işlemleri
        'assign'              => 'Ata',
        'complete'            => 'Tamamla',
        // Form Merkezi işlemleri
        'submit'              => 'Doldur',
        'submissions.view'    => 'Kayıtları Gör',
        'submissions.manage'  => 'Kayıt Yönet',
        'external.manage'     => 'Dış Link Yönet',
        'templates.manage'    => 'Şablon Yönet',
        'categories.manage'   => 'Kategori Yönet',
        'photo_upload'        => 'Fotoğraf Yükle',
        'reports'             => 'Raporlar',
        'route_plan'          => 'Rota Planı',
        'collection_view'     => 'Toplama Gör',
        'collection_create'   => 'Toplama Ekle',
        'collection_edit'     => 'Toplama Düzenle',
        'collection_complete' => 'Toplama Tamamla',
        // Yurtdışı müşteriler / dış ticaret işlemleri
        'filter'              => 'Filtrele',
        'message_mail'        => 'Mail Gönder',
        'message_whatsapp'    => 'WhatsApp Linki',
        'blacklist'           => 'Kara Liste',
        'notes'               => 'Not Ekle',
        'view_contacts'       => 'Kişileri Gör',
        'create_contacts'     => 'Kişi Ekle',
        'edit_contacts'       => 'Kişi Düzenle',
        'delete_contacts'     => 'Kişi Sil',
        'view_brands'         => 'Markaları Gör',
        'edit_brands'         => 'Marka Düzenle',
        'view_notes'          => 'Notları Gör',
        'edit_notes'          => 'Not Düzenle',
        'status_change'       => 'Durum Değiştir',
        'relationship_change' => 'İlişki Değiştir',
    ];
}

/**
 * Modül → [etiket, grup, işlem yetkileri] kaydı (whitelist).
 * Yetki sistemi hem modül bazlı (eski) hem işlem bazlı (yeni) çalışır:
 *   - Eski/kaba anahtar:  "service"        (modülün tamamı)
 *   - Yeni/ince anahtar:  "service.status" (yalnızca ilgili işlem)
 * Bir modülün herhangi bir "modul.*" yetkisi, o modülü görünür kılar (can('modul')).
 */
function module_action_registry(): array
{
    return [
        'dashboard'       => ['Genel Bakış',           'Panel',       ['view']],
        'customers'       => ['Müşteriler',            'Satış',       ['view','create','edit','delete','export','import']],
        'quotes'          => ['Teklifler',             'Satış',       ['view','create','edit','delete','pdf','mail','whatsapp','print']],
        'orders'          => ['Siparişler',            'Satış',       ['view','create','edit','delete','status','print','pdf']],
        'reconciliation'  => ['Mutabakat',             'Satış',       ['view','create','edit','delete','mail','whatsapp','print','pdf','approval_cancel','token_renew']],
        'suppliers'       => ['Tedarikçiler',          'Satın Alma',  ['view','create','edit','delete','export']],
        'tsoft_products'  => ['T-Soft Ürünler',        'Satın Alma',  ['view']],
        'service'         => ['Teknik Servis',         'Operasyon',   ['view','create','edit','delete','status','mail','whatsapp','print','pdf']],
        'rma'             => ['İade-Değişim Yönetimi', 'Operasyon',   ['view','create','edit','delete','import','export','status','print']],
        'shipment_addresses' => ['Sevkiyat Adresleri', 'Sevkiyat',   ['view','create','edit','delete']],
        'shipments'       => ['Sevkiyat Takibi',       'Sevkiyat',   ['view','create','edit','delete','status','assign','complete','photo_upload','reports','route_plan','whatsapp','collection_view','collection_create','collection_edit','collection_complete']],
        'commissions'     => ['Primler',               'İK / Finans', ['view','create','edit','delete','export','print']],
        'finance'         => ['Cari / Finans',         'İK / Finans', ['view','create','edit','delete','mail','pdf','print']],
        'reports'         => ['Raporlar',              'Raporlama',   ['view','export','print']],
        'leads'           => ['Lead Yönetimi',         'Satış',       ['view','create','edit','delete','export','whatsapp','mail']],
        'inventory'       => ['Envanter / Demirbaş',   'Operasyon',   ['view','create','edit','delete','export','print']],
        'file_manager'    => ['Dosya Yöneticisi',      'Araçlar',     ['view','upload','delete']],
        'password_vault'  => ['Şifre Kasası',          'Araçlar',     ['view','reveal','create','edit','delete']],
        'integrations'    => ['Entegrasyonlar',        'Sistem',      ['view','edit']],
        'help'            => ['Yardım Merkezi',        'Sistem',      ['view','create','edit','delete','settings']],
        'currency'        => ['Kur Çevirici',          'Araçlar',     ['view']],
        'brands'          => ['Markalar',              'Operasyon',   ['view','create','edit','delete']],
        'shipping'        => ['Kargo Yöntemleri',      'Operasyon',   ['view','create','edit','delete']],
        'personnel'       => ['Personeller',           'İK / Finans', ['view','create','edit','delete','export']],
        'leave'           => ['İzin / İK',             'İK / Finans', ['view','create','edit','delete','approve','export']],
        'attendance'      => ['Puantaj',               'İK / Finans', ['view','edit','export']],
        'settings'        => ['Ayarlar',               'Sistem',      ['view','edit','settings']],
        'email'           => ['E-Posta / Belge Gönderimi', 'Sistem',  ['manage','send_as_other','logs_view','resend']],
        'forms'           => ['Form Merkezi',          'Form',        ['view','create','edit','delete','submit','submissions.view','submissions.manage','approve','external.manage','templates.manage','categories.manage']],
        'international_customers' => ['Yurtdışı Müşteriler', 'Dış Ticaret', ['view','create','edit','delete','import','export','filter','message_mail','message_whatsapp','blacklist','notes','view_contacts','create_contacts','edit_contacts','delete_contacts','view_brands','edit_brands','view_notes','edit_notes','status_change','relationship_change']],
        'product_lists'   => ['Ürün Listeleri',        'Dış Ticaret', ['view','create','edit','delete','export','pdf','mail','whatsapp','print']],
        'message_templates' => ['Mesaj Şablonları',    'Dış Ticaret', ['view','create','edit','delete']],
    ];
}

/**
 * Sistemdeki modüller ve etiketleri (geriye dönük uyumlu düz liste).
 */
function all_modules(): array
{
    $out = [];
    foreach (module_action_registry() as $key => $meta) {
        $out[$key] = $meta[0];
    }
    return $out;
}

/**
 * Geçerli tüm yetki anahtarlarının kümesi: kaba modül + ince işlem anahtarları.
 * @return array<string,bool>
 */
function valid_permission_keys(): array
{
    $keys = [];
    foreach (module_action_registry() as $mod => $meta) {
        $keys[$mod] = true; // kaba anahtar (geriye uyum)
        foreach ($meta[2] as $action) {
            $keys[$mod . '.' . $action] = true;
        }
    }
    return $keys;
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
 * Kullanıcı belirtilen yetkiye sahip mi? Hem kaba ("service") hem ince
 * ("service.status") anahtarları destekler; tam geriye dönük uyumludur.
 *
 *  - "all" → her şey.
 *  - Tam eşleşme (kaba veya ince anahtar).
 *  - İnce sorgu ("service.status"): kaba modül yetkisi ("service") de geçer.
 *  - Kaba sorgu ("service"): modülün herhangi bir "service.*" yetkisi de geçer.
 */
function can(string $key): bool
{
    if (!is_logged_in()) {
        return false;
    }
    $perms = current_permissions();
    if (in_array('all', $perms, true)) { return true; }
    if (in_array($key, $perms, true))  { return true; }

    $dot = strpos($key, '.');
    if ($dot !== false) {
        // İnce sorgu: kaba modül yetkisi tüm işlemleri kapsar.
        $module = substr($key, 0, $dot);
        return in_array($module, $perms, true);
    }

    // Kaba sorgu: modülün herhangi bir işlem yetkisi modülü görünür kılar.
    $prefix = $key . '.';
    $len = strlen($prefix);
    foreach ($perms as $p) {
        if (is_string($p) && strncmp($p, $prefix, $len) === 0) { return true; }
    }
    return false;
}

/** Kısayol: can("$module.$action"). */
function can_action(string $module, string $action): bool
{
    return can($module . '.' . $action);
}

/**
 * Belirli bir işlem yetkisini zorunlu kılar; yoksa 403.
 */
function require_action(string $module, string $action): void
{
    require_permission($module . '.' . $action);
}

/**
 * Geçerli yetki anahtarlarını temizler. "all" varsa yalnızca ["all"].
 * Hem kaba modül hem ince işlem anahtarlarını kabul eder (whitelist).
 */
function sanitize_permissions(array $keys): string
{
    if (in_array('all', $keys, true)) {
        return json_encode(['all']);
    }
    $valid = valid_permission_keys();
    $out = [];
    foreach ($keys as $k) {
        $k = is_string($k) ? trim($k) : '';
        if ($k !== '' && isset($valid[$k]) && !in_array($k, $out, true)) {
            $out[] = $k;
        }
    }
    return json_encode(array_values($out));
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
