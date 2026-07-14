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

/**
 * Lead durum satırları — yönetilebilir `lead_statuses` tablosundan (§6).
 * Tablo yok/boşsa güvenli varsayılanlara döner. code => satır dizisi.
 * @param bool $activeOnly Yalnızca aktif durumlar
 * @return array<string,array>
 */
function lead_status_rows(bool $activeOnly = false): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT * FROM lead_statuses ORDER BY sort_order ASC, id ASC')->fetchAll() as $r) {
                $cache[(string) $r['code']] = $r;
            }
        } catch (Throwable $e) {
            $cache = [];
        }
        if (!$cache) { $cache = lead_status_defaults(); }
    }
    if ($activeOnly) {
        return array_filter($cache, static fn($r) => !empty($r['is_active']));
    }
    return $cache;
}

/** Statik durum önbelleğini sıfırlar (yönetim ekranında kayıttan sonra). */
function lead_status_cache_reset(): void
{
    // lead_status_rows statik önbelleğini bozmanın taşınabilir yolu yok; ayrı
    // istekte tazedir. Aynı istekte tekrar okumak gerekirse doğrudan sorgulanır.
}

/** Tablo yoksa kullanılan güvenli varsayılan durumlar (install.sql ile aynı küme). */
function lead_status_defaults(): array
{
    $mk = static fn(string $code, string $name, string $color, int $so, int $c = 0, int $ok = 0, int $f = 0, int $rf = 0): array =>
        ['code' => $code, 'name' => $name, 'color' => $color, 'sort_order' => $so,
         'is_active' => 1, 'is_completed' => $c, 'is_success' => $ok, 'is_failure' => $f, 'requires_followup' => $rf];
    $rows = [
        $mk('new', 'Yeni Lead', 'badge-info', 10),
        $mk('to_review', 'İncelenecek', 'badge-muted', 20),
        $mk('to_call', 'Arama Bekliyor', 'badge-warning', 30),
        $mk('call_later', 'Daha Sonra Aranacak', 'badge-warning', 35, 0, 0, 0, 1),
        $mk('called', 'Arandı', 'badge-info', 40),
        $mk('unreachable', 'Ulaşılamadı', 'badge-warning', 50),
        $mk('call_again', 'Tekrar Aranacak', 'badge-warning', 60, 0, 0, 0, 1),
        $mk('wa_to_send', 'WhatsApp Gönderilecek', 'badge-warning', 70, 0, 0, 0, 1),
        $mk('wa_sent', 'WhatsApp Gönderildi', 'badge-info', 80),
        $mk('email_sent', 'E-posta Gönderildi', 'badge-info', 90),
        $mk('met', 'Görüşme Yapıldı', 'badge-info', 100),
        $mk('meet_again', 'Tekrar Görüşülecek', 'badge-warning', 105, 0, 0, 0, 1),
        $mk('wants_quote', 'Teklif İstiyor', 'badge-info', 110),
        $mk('quote_followup', 'Teklif İçin Dönüş Yapılacak', 'badge-warning', 115, 0, 0, 0, 1),
        $mk('quote_sent', 'Teklif Gönderildi', 'badge-info', 120),
        $mk('positive', 'Olumlu', 'badge-success', 130),
        $mk('converted', 'Müşteriye Dönüştü', 'badge-success', 140, 1, 1, 0),
        $mk('not_interested', 'İlgilenmiyor', 'badge-muted', 150, 1, 0, 1),
        $mk('wrong_number', 'Yanlış Numara', 'badge-muted', 160, 1, 0, 1),
        $mk('closed', 'Firma Kapalı', 'badge-muted', 170, 1, 0, 1),
        $mk('blacklist', 'Kara Liste', 'badge-danger', 180, 1, 0, 1),
    ];
    $out = [];
    foreach ($rows as $r) { $out[$r['code']] = $r; }
    return $out;
}

/** Bu durum bir takip (tarih/saat) kaydı gerektiriyor mu? (§21) */
function lead_status_requires_followup(string $code): bool
{
    $meta = lead_status_meta($code);
    return $meta !== null && (int) ($meta['requires_followup'] ?? 0) === 1;
}

/** Lead durumları — code => etiket (geriye dönük uyum). */
function lead_statuses(bool $activeOnly = false): array
{
    $out = [];
    foreach (lead_status_rows($activeOnly) as $code => $r) { $out[$code] = (string) $r['name']; }
    return $out;
}
function lead_status_label(string $k): string { return (string) (lead_status_rows()[$k]['name'] ?? $k); }
function lead_status_class(string $k): string { return (string) (lead_status_rows()[$k]['color'] ?? 'badge-muted'); }
/** Durum satırı (is_completed/is_success/is_failure için). */
function lead_status_meta(string $k): ?array { return lead_status_rows()[$k] ?? null; }

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

/** Yumuşak silme (§10): is_deleted + deleted_at/by/reason; denetim kaydı korunur. */
function delete_lead(int $id, ?int $userId, string $reason = ''): bool
{
    try {
        $ok = db()->prepare('UPDATE leads SET is_deleted = 1, deleted_at = NOW(), deleted_by = :dby, delete_reason = :r, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':dby' => $userId, ':r' => mb_substr($reason, 0, 255) ?: null, ':uby' => $userId, ':id' => $id]);
        if ($ok && function_exists('lead_audit_log')) {
            lead_audit_log($id, 'lead_soft_delete', $reason !== '' ? 'Sebep: ' . $reason : '', $userId);
        }
        // Silinen lead'in açık takipleri iptal edilir (§21)
        if ($ok && function_exists('lead_reminders_cancel_for_lead')) {
            lead_reminders_cancel_for_lead($id, $userId);
        }
        return (bool) $ok;
    } catch (Throwable $e) { log_error('delete_lead: ' . $e->getMessage()); return false; }
}

/** Çöp kutusundaki (yumuşak silinmiş) lead'ler. */
function get_trashed_leads(string $search = ''): array
{
    $where = ['is_deleted = 1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(company_name LIKE :q OR phone LIKE :q OR city LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }
    try {
        $st = db()->prepare('SELECT l.*, u.full_name AS deleted_by_name FROM leads l LEFT JOIN users u ON u.id = l.deleted_by WHERE ' . implode(' AND ', $where) . ' ORDER BY l.deleted_at DESC LIMIT 500');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_trashed_leads: ' . $e->getMessage()); return []; }
}

function trashed_lead_count(): int
{
    try { return (int) db()->query('SELECT COUNT(*) FROM leads WHERE is_deleted = 1')->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

/** Tek çöp kaydı (silinmiş dahil). */
function get_lead_including_deleted(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM leads WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

/** Çöpten geri yükler. */
function lead_restore(int $id, ?int $userId): bool
{
    try {
        $ok = db()->prepare('UPDATE leads SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL, delete_reason = NULL, updated_by = :uby WHERE id = :id AND is_deleted = 1')
            ->execute([':uby' => $userId, ':id' => $id]);
        if ($ok && function_exists('lead_audit_log')) { lead_audit_log($id, 'lead_restore', '', $userId); }
        return (bool) $ok;
    } catch (Throwable $e) { log_error('lead_restore: ' . $e->getMessage()); return false; }
}

/**
 * KALICI silme (§10): yalnızca çöpteki kayıt. Denetim logu (lead_audit_logs)
 * silinmeden ÖNCE ayrı olarak korunur (lead_id null'a düşer ama kayıt kalır).
 * Çağıran taraf Süper Admin + 2. onay denetimini yapar.
 */
function lead_purge(int $id, ?int $userId): bool
{
    try {
        $lead = get_lead_including_deleted($id);
        if (!$lead || (int) ($lead['is_deleted'] ?? 0) !== 1) { return false; }
        // Denetim kaydını önce yaz (kalıcı silme öncesi anlık görüntü)
        if (function_exists('lead_audit_log')) {
            lead_audit_log(null, 'lead_purge', 'Kalıcı silinen lead #' . $id . ': ' . (string) ($lead['company_name'] ?? '')
                . ' · tel ' . (string) ($lead['phone'] ?? '') . ' · place ' . (string) ($lead['place_id'] ?? ''), $userId);
        }
        // İlişkili kayıtlar FK ON DELETE CASCADE ile temizlenir.
        return db()->prepare('DELETE FROM leads WHERE id = :id AND is_deleted = 1')->execute([':id' => $id]);
    } catch (Throwable $e) { log_error('lead_purge: ' . $e->getMessage()); return false; }
}

/** Süper Admin mi? (kalıcı silme yetkisi) */
function lead_is_super_admin(): bool
{
    $perms = function_exists('current_permissions') ? current_permissions() : [];
    return in_array('all', $perms, true);
}

function set_lead_status(int $id, string $status, ?int $userId, string $note = ''): bool
{
    if (!isset(lead_status_rows()[$status])) { return false; }
    try {
        $lead = get_lead($id);
        if (!$lead) { return false; }
        $old = (string) ($lead['status'] ?? '');
        if ($old === $status && $note === '') { return true; } // değişiklik yok

        $ok = db()->prepare('UPDATE leads SET status = :s, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':s' => $status, ':uby' => $userId, ':id' => $id]);
        if ($ok && $old !== $status) {
            lead_status_history_add($id, $old, $status, $note, $userId);
            lead_activity_add($id, 'status', lead_status_label($old) . ' → ' . lead_status_label($status)
                . ($note !== '' ? ' · ' . $note : ''), null, $userId);
        }
        return (bool) $ok;
    } catch (Throwable $e) { log_error('set_lead_status: ' . $e->getMessage()); return false; }
}

/** Durum geçişini geçmişe yazar (lead_status_history). */
function lead_status_history_add(int $leadId, string $old, string $new, string $note, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO lead_status_history (lead_id, old_status, new_status, note, created_by) VALUES (:l,:o,:n,:note,:by)')
            ->execute([':l' => $leadId, ':o' => $old, ':n' => $new, ':note' => mb_substr($note, 0, 500), ':by' => $userId]);
    } catch (Throwable $e) { log_error('lead_status_history_add: ' . $e->getMessage()); }
}

/** Lead durum geçmişi (son N). */
function lead_status_history(int $leadId, int $limit = 100): array
{
    try {
        $st = db()->prepare('SELECT h.*, u.full_name FROM lead_status_history h LEFT JOIN users u ON u.id = h.created_by WHERE h.lead_id = :l ORDER BY h.id DESC LIMIT ' . max(1, min(500, $limit)));
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Genel aktivite kaydı (lead_activities): status/call/whatsapp/note/mail/assign vb. */
function lead_activity_add(int $leadId, string $type, string $summary, ?array $meta, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO lead_activities (lead_id, type, summary, meta_json, created_by) VALUES (:l,:t,:s,:m,:by)')
            ->execute([
                ':l' => $leadId, ':t' => mb_substr($type, 0, 40), ':s' => mb_substr($summary, 0, 500),
                ':m' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, ':by' => $userId,
            ]);
    } catch (Throwable $e) { log_error('lead_activity_add: ' . $e->getMessage()); }
}

/** Lead aktivite akışı (son N). */
function lead_activities(int $leadId, int $limit = 200): array
{
    try {
        $st = db()->prepare('SELECT a.*, u.full_name FROM lead_activities a LEFT JOIN users u ON u.id = a.created_by WHERE a.lead_id = :l ORDER BY a.id DESC LIMIT ' . max(1, min(500, $limit)));
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* ---- Durum yönetimi (ayar ekranı) ---- */

/** Tüm durum satırları (yönetim ekranı; aktif+pasif, sıralı). */
function lead_status_manage_list(): array
{
    try {
        return db()->query('SELECT * FROM lead_statuses ORDER BY sort_order ASC, id ASC')->fetchAll();
    } catch (Throwable $e) { log_error('lead_status_manage_list: ' . $e->getMessage()); return []; }
}

/** Durum ekler/günceller. code benzersizdir; boş kod/isim reddedilir. */
function lead_status_save(array $in, ?int $id): array
{
    $name = trim((string) ($in['name'] ?? ''));
    $code = trim((string) ($in['code'] ?? ''));
    if ($id === null) {
        // Yeni: koddan slug üret
        if ($code === '') { $code = $name; }
        $code = strtolower(preg_replace('/[^a-z0-9_]+/', '_', strtr(mb_strtolower($code, 'UTF-8'),
            ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u'])) ?? '');
        $code = trim($code, '_');
    }
    if ($name === '' || $code === '') { return ['ok' => false, 'error' => 'Kod ve isim zorunludur.']; }

    $color = trim((string) ($in['color'] ?? 'badge-muted')) ?: 'badge-muted';
    $sort  = (int) ($in['sort_order'] ?? 0);
    $active = !empty($in['is_active']) ? 1 : 0;
    $comp   = !empty($in['is_completed']) ? 1 : 0;
    $succ   = !empty($in['is_success']) ? 1 : 0;
    $fail   = !empty($in['is_failure']) ? 1 : 0;

    try {
        if ($id === null) {
            db()->prepare('INSERT INTO lead_statuses (code,name,color,sort_order,is_active,is_completed,is_success,is_failure) VALUES (:c,:n,:col,:so,:a,:comp,:s,:f)')
                ->execute([':c'=>$code, ':n'=>$name, ':col'=>$color, ':so'=>$sort, ':a'=>$active, ':comp'=>$comp, ':s'=>$succ, ':f'=>$fail]);
        } else {
            // Kod değiştirilemez (var olan lead'lerle tutarlılık); yalnızca diğerleri.
            db()->prepare('UPDATE lead_statuses SET name=:n,color=:col,sort_order=:so,is_active=:a,is_completed=:comp,is_success=:s,is_failure=:f WHERE id=:id')
                ->execute([':n'=>$name, ':col'=>$color, ':so'=>$sort, ':a'=>$active, ':comp'=>$comp, ':s'=>$succ, ':f'=>$fail, ':id'=>$id]);
        }
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        log_error('lead_status_save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Durum kaydedilemedi (kod zaten kullanılıyor olabilir).'];
    }
}

/** Durumu siler. Kullanımdaki (lead'lere atanmış) durum silinemez → pasife alınır. */
function lead_status_delete(int $id): array
{
    try {
        $st = db()->prepare('SELECT code FROM lead_statuses WHERE id = :id');
        $st->execute([':id' => $id]);
        $code = (string) ($st->fetchColumn() ?: '');
        if ($code === '') { return ['ok' => false, 'error' => 'Durum bulunamadı.']; }

        $cnt = db()->prepare('SELECT COUNT(*) FROM leads WHERE status = :c AND is_deleted = 0');
        $cnt->execute([':c' => $code]);
        if ((int) $cnt->fetchColumn() > 0) {
            db()->prepare('UPDATE lead_statuses SET is_active = 0 WHERE id = :id')->execute([':id' => $id]);
            return ['ok' => false, 'error' => 'Bu durum lead\'lere atanmış; silinemez, pasife alındı.'];
        }
        db()->prepare('DELETE FROM lead_statuses WHERE id = :id')->execute([':id' => $id]);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) { log_error('lead_status_delete: ' . $e->getMessage()); return ['ok' => false, 'error' => 'Durum silinemedi.']; }
}

/** Rozet renk seçenekleri (durum yönetimi). */
function lead_status_color_options(): array
{
    return ['badge-info' => 'Mavi', 'badge-success' => 'Yeşil', 'badge-warning' => 'Turuncu',
            'badge-danger' => 'Kırmızı', 'badge-muted' => 'Gri', 'badge-leave' => 'Mor'];
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
