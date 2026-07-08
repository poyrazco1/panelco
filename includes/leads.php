<?php
declare(strict_types=1);

/**
 * includes/leads.php — Lead (potansiyel müşteri) yönetimi.
 *
 * İLKE: Otomatik TOPLU SPAM mesaj YOKTUR. Her lead için mesaj linki kullanıcı
 * onayıyla üretilir; "gönderildi" işareti manueldir. Kara listedeki lead'e
 * mesaj linki üretilmez. Chrome eklentisi güvenli API (token + rate limit +
 * doğrulama) ile lead kaydedebilir.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/leave.php'; // app_setting_get / app_setting_set

/** Lead durumları. */
function lead_statuses(): array
{
    return [
        'new'          => 'Yeni',
        'to_call'      => 'Aranacak',
        'called'       => 'Arandı',
        'unreachable'  => 'Ulaşılamadı',
        'wants_quote'  => 'Teklif İstiyor',
        'quote_sent'   => 'Teklif Gönderildi',
        'following'    => 'Takipte',
        'converted'    => 'Müşteri Oldu',
        'not_interested' => 'İlgilenmiyor',
        'blacklist'    => 'Kara Liste',
    ];
}
function lead_status_label(string $k): string { return lead_statuses()[$k] ?? $k; }
function lead_status_class(string $k): string
{
    return match ($k) {
        'converted'   => 'badge-success',
        'blacklist', 'not_interested' => 'badge-danger',
        'wants_quote', 'quote_sent' => 'badge-info',
        'following', 'to_call' => 'badge-leave',
        default       => 'badge-muted',
    };
}

/** Lead kara listede mi? (mesaj gönderilemez) */
function lead_is_blacklisted(array $lead): bool
{
    return (string) ($lead['status'] ?? '') === 'blacklist';
}

function get_leads(array $f = []): array
{
    $where = ['is_deleted = 0'];
    $params = [];
    if (!empty($f['status']) && isset(lead_statuses()[$f['status']])) { $where[] = 'status = :st'; $params[':st'] = $f['status']; }
    if (!empty($f['scan_id'])) { $where[] = 'scan_id = :scan'; $params[':scan'] = (int) $f['scan_id']; }
    if (!empty($f['search'])) { $where[] = '(company_name LIKE :q OR contact_name LIKE :q OR phone LIKE :q OR city LIKE :q OR sector LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $st = db()->prepare('SELECT * FROM leads WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 1000');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_leads: ' . $e->getMessage()); return []; }
}

function get_lead(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM leads WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('get_lead: ' . $e->getMessage()); return null; }
}

function lead_counts(): array
{
    $out = ['all' => 0];
    foreach (array_keys(lead_statuses()) as $s) { $out[$s] = 0; }
    try {
        foreach (db()->query('SELECT status, COUNT(*) c FROM leads WHERE is_deleted = 0 GROUP BY status')->fetchAll() as $r) {
            $out['all'] += (int) $r['c'];
            $out[(string) $r['status']] = (int) $r['c'];
        }
    } catch (Throwable $e) { log_error('lead_counts: ' . $e->getMessage()); }
    return $out;
}

function lead_fields_from_input(array $in): array
{
    $st = (string) ($in['status'] ?? 'new');
    if (!isset(lead_statuses()[$st])) { $st = 'new'; }
    return [
        'company_name'          => trim((string) ($in['company_name'] ?? '')),
        'contact_name'          => trim((string) ($in['contact_name'] ?? '')),
        'phone'                 => trim((string) ($in['phone'] ?? '')),
        'whatsapp'              => trim((string) ($in['whatsapp'] ?? '')),
        'email'                 => trim((string) ($in['email'] ?? '')),
        'website'               => trim((string) ($in['website'] ?? '')),
        'instagram'             => trim((string) ($in['instagram'] ?? '')),
        'maps_url'              => trim((string) ($in['maps_url'] ?? '')),
        'sector'                => trim((string) ($in['sector'] ?? '')),
        'city'                  => trim((string) ($in['city'] ?? '')),
        'district'              => trim((string) ($in['district'] ?? '')),
        'source'                => trim((string) ($in['source'] ?? '')),
        'notes'                 => trim((string) ($in['notes'] ?? '')),
        'status'                => $st,
        'assigned_personnel_id' => (int) ($in['assigned_personnel_id'] ?? 0) ?: null,
    ];
}

function lead_validate(array $d): array
{
    $errors = [];
    if (($d['company_name'] ?? '') === '') { $errors[] = 'Firma adı zorunludur.'; }
    if (($d['email'] ?? '') !== '' && !is_valid_email($d['email'])) { $errors[] = 'Geçerli bir e-posta girin.'; }
    return $errors;
}

function lead_bind(array $d, ?int $userId, bool $isNew): array
{
    $p = [
        ':company' => $d['company_name'], ':contact' => $d['contact_name'] ?: null, ':phone' => $d['phone'] ?: null,
        ':whatsapp' => $d['whatsapp'] ?: null, ':email' => $d['email'] ?: null, ':website' => $d['website'] ?: null,
        ':instagram' => $d['instagram'] ?: null, ':maps' => $d['maps_url'] ?: null, ':sector' => $d['sector'] ?: null,
        ':city' => $d['city'] ?: null, ':district' => $d['district'] ?: null, ':source' => $d['source'] ?: null,
        ':notes' => $d['notes'] ?: null, ':status' => $d['status'], ':assigned' => $d['assigned_personnel_id'], ':uby' => $userId,
    ];
    if ($isNew) { $p[':cby'] = $userId; }
    return $p;
}

function create_lead(array $d, ?int $userId): int
{
    try {
        $st = db()->prepare(
            'INSERT INTO leads
                (company_name, contact_name, phone, whatsapp, email, website, instagram, maps_url, sector,
                 city, district, source, notes, status, assigned_personnel_id, created_by, updated_by)
             VALUES
                (:company,:contact,:phone,:whatsapp,:email,:website,:instagram,:maps,:sector,:city,:district,
                 :source,:notes,:status,:assigned,:cby,:uby)'
        );
        $st->execute(lead_bind($d, $userId, true));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('create_lead: ' . $e->getMessage()); return 0; }
}

function update_lead(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE leads SET
                company_name=:company, contact_name=:contact, phone=:phone, whatsapp=:whatsapp, email=:email,
                website=:website, instagram=:instagram, maps_url=:maps, sector=:sector, city=:city, district=:district,
                source=:source, notes=:notes, status=:status, assigned_personnel_id=:assigned, updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        $params = lead_bind($d, $userId, false);
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) { log_error('update_lead: ' . $e->getMessage()); return false; }
}

function delete_lead(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE leads SET is_deleted = 1, updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('delete_lead: ' . $e->getMessage()); return false; }
}

function set_lead_status(int $id, string $status, ?int $userId): bool
{
    if (!isset(lead_statuses()[$status])) { return false; }
    try {
        return db()->prepare('UPDATE leads SET status = :s, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':s' => $status, ':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('set_lead_status: ' . $e->getMessage()); return false; }
}

function lead_mark_messaged(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE leads SET last_message_at = NOW(), updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('lead_mark_messaged: ' . $e->getMessage()); return false; }
}

/** WhatsApp şablonu (ayarlardan). */
function lead_wa_template(): string
{
    return (string) app_setting_get('lead_wa_template', 'Merhaba {yetkili}, {firma} olarak sizinle iletişime geçmek istiyoruz.');
}

/**
 * Bir lead için WhatsApp mesaj linki (kullanıcı onaylı, tekil). Kara liste veya
 * telefon yoksa null. TOPLU gönderim YOK.
 */
function lead_whatsapp_link(array $lead): ?string
{
    if (lead_is_blacklisted($lead)) { return null; }
    $phone = trim((string) ($lead['whatsapp'] ?? '')) ?: trim((string) ($lead['phone'] ?? ''));
    if ($phone === '') { return null; }
    if (!function_exists('build_whatsapp_message_link')) { require_once __DIR__ . '/notifications.php'; }
    $msg = strtr(lead_wa_template(), [
        '{firma}'   => (string) ($lead['company_name'] ?? ''),
        '{yetkili}' => (string) ($lead['contact_name'] ?? '') ?: 'Yetkili',
        '{sehir}'   => (string) ($lead['city'] ?? ''),
    ]);
    return build_whatsapp_message_link($phone, $msg);
}

/* ---- Chrome eklentisi API altyapısı ---- */

/** API token'ı (app_settings). */
function leads_api_token(): string { return (string) app_setting_get('leads_api_token', ''); }

/** Yeni token üretir ve kaydeder. */
function leads_generate_api_token(): string
{
    $t = bin2hex(random_bytes(24));
    app_setting_set('leads_api_token', $t);
    return $t;
}

/** Token doğrulama (sabit zamanlı). */
function leads_verify_api_token(string $token): bool
{
    $stored = leads_api_token();
    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

/**
 * Basit rate limit: son 60 saniyede aynı IP'den en fazla $max istek.
 * @return bool İzin veriliyor mu?
 */
function leads_api_rate_ok(string $ip, int $max = 30): bool
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM lead_api_log WHERE ip = :ip AND created_at >= (NOW() - INTERVAL 60 SECOND)');
        $st->execute([':ip' => $ip]);
        return (int) $st->fetchColumn() < $max;
    } catch (Throwable $e) { log_error('leads_api_rate_ok: ' . $e->getMessage()); return true; }
}

function leads_api_log(string $ip, string $result): void
{
    try { db()->prepare('INSERT INTO lead_api_log (ip, result) VALUES (:ip,:r)')->execute([':ip' => $ip, ':r' => $result]); }
    catch (Throwable $e) { log_error('leads_api_log: ' . $e->getMessage()); }
}
