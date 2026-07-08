<?php
declare(strict_types=1);

/**
 * includes/international-customers.php
 * ------------------------------------------------------------------
 * EK PART 27 — Yurtdışı Müşteriler / İthalat-İhracat CRM iş mantığı.
 *
 * İLKELER:
 *  - Fiziksel silme YOK → is_deleted / deleted_at.
 *  - Kayıt no benzersiz + değişmez → INT-2026-000001 (oluşturmada üretilir).
 *  - Toplu SPAM YOK; mesaj gönderimi kullanıcı onaylı + loglanır (message-templates.php).
 *  - Tüm sorgular hataya dayanıklı: fatal yerine boş/0 döner, log yazar.
 *  - Açıklama/notlar tam-metin (LIKE) aranabilir; kişiler/markalar/kategoriler dahil.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/leave.php'; // app_setting_get / app_setting_set

/* =========================================================================
 |  Sabit / tanım listeleri
 * ====================================================================== */

/** Firma rolü (buyer/seller/both). */
function ic_company_roles(): array
{
    return ['buyer' => 'Alıcı', 'seller' => 'Satıcı', 'both' => 'Hem Alıcı Hem Satıcı'];
}
function ic_company_role_label(string $k): string { return ic_company_roles()[$k] ?? $k; }

/** Marka ilişki tipleri. */
function ic_brand_relation_types(): array
{
    return [
        'distributor' => 'Distribütörü',
        'seller'      => 'Satıyor',
        'buyer'       => 'Alıyor',
        'interested'  => 'İlgileniyor',
        'service'     => 'Servis Veriyor',
    ];
}
function ic_brand_relation_label(string $k): string { return ic_brand_relation_types()[$k] ?? $k; }

/** Aktif müşteri durumları. */
function ic_statuses(): array
{
    try {
        return db()->query('SELECT * FROM international_customer_statuses WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
    } catch (Throwable $e) { log_error('ic_statuses: ' . $e->getMessage()); return []; }
}
function ic_status_map(): array
{
    $out = [];
    foreach (ic_statuses() as $s) { $out[(int) $s['id']] = $s; }
    return $out;
}
function ic_status_badge_class(string $color): string
{
    $ok = ['success', 'danger', 'info', 'muted', 'leave'];
    return 'badge-' . (in_array($color, $ok, true) ? $color : 'muted');
}

/** Aktif şirket tipleri. */
function ic_company_types(): array
{
    try {
        return db()->query('SELECT * FROM company_types WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
    } catch (Throwable $e) { log_error('ic_company_types: ' . $e->getMessage()); return []; }
}

/** Kategoriler (ağaç düz liste). */
function ic_categories(): array
{
    try {
        return db()->query('SELECT * FROM international_customer_categories WHERE is_active = 1 ORDER BY COALESCE(parent_id,0) ASC, sort_order ASC, id ASC')->fetchAll();
    } catch (Throwable $e) { log_error('ic_categories: ' . $e->getMessage()); return []; }
}
/** Kategori adı (ağaç yolunu göster: Ana › Alt). */
function ic_category_label(int $id, ?array $all = null): string
{
    $all = $all ?? ic_categories();
    $byId = [];
    foreach ($all as $c) { $byId[(int) $c['id']] = $c; }
    if (!isset($byId[$id])) { return ''; }
    $c = $byId[$id];
    $name = (string) $c['name'];
    if (!empty($c['parent_id']) && isset($byId[(int) $c['parent_id']])) {
        $name = (string) $byId[(int) $c['parent_id']]['name'] . ' › ' . $name;
    }
    return $name;
}

/* =========================================================================
 |  Kayıt numarası — INT-2026-000001 (benzersiz, değişmez)
 * ====================================================================== */
function ic_next_record_no(): string
{
    $year = (int) date('Y');
    $prefix = 'INT-' . $year . '-';
    try {
        $st = db()->prepare("SELECT record_no FROM international_customers WHERE record_no LIKE :p ORDER BY id DESC LIMIT 1");
        $st->execute([':p' => $prefix . '%']);
        $last = (string) ($st->fetchColumn() ?: '');
        $seq = 0;
        if ($last !== '' && preg_match('/(\d+)$/', $last, $m)) { $seq = (int) $m[1]; }
        // Boşluk / eşzamanlılık güvenliği: çakışırsa artır (UNIQUE index nihai koruma).
        for ($i = 0; $i < 50; $i++) {
            $seq++;
            $candidate = $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            $c = db()->prepare('SELECT COUNT(*) FROM international_customers WHERE record_no = :r');
            $c->execute([':r' => $candidate]);
            if ((int) $c->fetchColumn() === 0) { return $candidate; }
        }
        return $prefix . str_pad((string) (time() % 1000000), 6, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        log_error('ic_next_record_no: ' . $e->getMessage());
        return $prefix . str_pad((string) (time() % 1000000), 6, '0', STR_PAD_LEFT);
    }
}

/* =========================================================================
 |  Listeleme + detaylı filtre + tam-metin arama
 * ====================================================================== */
/**
 * @param array $f Filtre: search, country[], city, company_role[], status_id[],
 *   company_type_id[], category_id[], brand, data_source, event_name,
 *   contact_permission, has_email, has_phone, include_blacklist, include_uninterested,
 *   created_from, created_to, prepared_by, ids[]
 */
function ic_build_where(array $f, array &$params): array
{
    $where = ['c.is_deleted = 0'];

    if (!empty($f['ids']) && is_array($f['ids'])) {
        $in = [];
        foreach (array_values($f['ids']) as $i => $id) { $k = ':id' . $i; $in[] = $k; $params[$k] = (int) $id; }
        if ($in) { $where[] = 'c.id IN (' . implode(',', $in) . ')'; }
    }

    if (!empty($f['search'])) {
        $q = '%' . $f['search'] . '%';
        $params[':q'] = $q;
        $where[] = '(c.company_name LIKE :q OR c.website LIKE :q OR c.notes LIKE :q
            OR c.email LIKE :q OR c.phone LIKE :q OR c.whatsapp LIKE :q OR c.country LIKE :q
            OR c.city LIKE :q OR c.event_name LIKE :q OR c.data_source LIKE :q OR c.record_no LIKE :q
            OR EXISTS (SELECT 1 FROM international_customer_contacts ct WHERE ct.customer_id = c.id AND ct.is_deleted = 0
                        AND (ct.full_name LIKE :q OR ct.title LIKE :q OR ct.email LIKE :q OR ct.phone LIKE :q OR ct.department LIKE :q))
            OR EXISTS (SELECT 1 FROM international_customer_brands b WHERE b.customer_id = c.id
                        AND (b.brand_name LIKE :q OR b.notes LIKE :q))
            OR EXISTS (SELECT 1 FROM international_customer_category_relations rc
                        JOIN international_customer_categories cat ON cat.id = rc.category_id
                        WHERE rc.customer_id = c.id AND cat.name LIKE :q))';
    }

    $multi = static function (string $col, string $key, array $f, array &$params, array &$where): void {
        if (empty($f[$key]) || !is_array($f[$key])) { return; }
        $in = [];
        foreach (array_values($f[$key]) as $i => $v) {
            $p = ':' . str_replace('.', '_', $key) . $i; $in[] = $p; $params[$p] = $v;
        }
        if ($in) { $where[] = $col . ' IN (' . implode(',', $in) . ')'; }
    };
    $multi('c.country', 'country', $f, $params, $where);
    $multi('c.company_role', 'company_role', $f, $params, $where);
    $multi('c.status_id', 'status_id', $f, $params, $where);

    if (!empty($f['city']))        { $where[] = 'c.city LIKE :city'; $params[':city'] = '%' . $f['city'] . '%'; }
    if (!empty($f['data_source'])) { $where[] = 'c.data_source LIKE :ds'; $params[':ds'] = '%' . $f['data_source'] . '%'; }
    if (!empty($f['event_name']))  { $where[] = 'c.event_name LIKE :ev'; $params[':ev'] = '%' . $f['event_name'] . '%'; }
    if (!empty($f['prepared_by'])) { $where[] = 'c.prepared_by_user_id = :pb'; $params[':pb'] = (int) $f['prepared_by']; }

    if (isset($f['contact_permission']) && $f['contact_permission'] !== '') {
        $where[] = 'c.contact_permission = :cp'; $params[':cp'] = (int) $f['contact_permission'];
    }
    if (!empty($f['has_email']))  { $where[] = "(c.email IS NOT NULL AND c.email <> '')"; }
    if (!empty($f['has_phone']))  { $where[] = "((c.phone IS NOT NULL AND c.phone <> '') OR (c.whatsapp IS NOT NULL AND c.whatsapp <> ''))"; }
    if (!empty($f['created_from'])) { $where[] = 'c.created_at >= :cf'; $params[':cf'] = $f['created_from'] . ' 00:00:00'; }
    if (!empty($f['created_to']))   { $where[] = 'c.created_at <= :ct'; $params[':ct'] = $f['created_to'] . ' 23:59:59'; }

    // Şirket tipi ilişkisi
    if (!empty($f['company_type_id']) && is_array($f['company_type_id'])) {
        $in = [];
        foreach (array_values($f['company_type_id']) as $i => $v) { $p = ':ct' . $i; $in[] = $p; $params[$p] = (int) $v; }
        if ($in) { $where[] = 'EXISTS (SELECT 1 FROM international_customer_company_types ict WHERE ict.customer_id = c.id AND ict.company_type_id IN (' . implode(',', $in) . '))'; }
    }
    // Kategori ilişkisi
    if (!empty($f['category_id']) && is_array($f['category_id'])) {
        $in = [];
        foreach (array_values($f['category_id']) as $i => $v) { $p = ':cat' . $i; $in[] = $p; $params[$p] = (int) $v; }
        if ($in) { $where[] = 'EXISTS (SELECT 1 FROM international_customer_category_relations icr WHERE icr.customer_id = c.id AND icr.category_id IN (' . implode(',', $in) . '))'; }
    }
    // Marka (ad veya id)
    if (!empty($f['brand'])) {
        $where[] = 'EXISTS (SELECT 1 FROM international_customer_brands b WHERE b.customer_id = c.id AND b.brand_name LIKE :brnd)';
        $params[':brnd'] = '%' . $f['brand'] . '%';
    }

    // Varsayılan gizleme: Kara liste + "İlgilenmiyor" (danger) durumu (aksi belirtilmedikçe).
    if (empty($f['include_blacklist'])) { $where[] = 'c.is_blacklisted = 0'; }
    if (empty($f['include_uninterested'])) {
        $where[] = "(c.status_id IS NULL OR c.status_id NOT IN (SELECT id FROM international_customer_statuses WHERE color = 'danger'))";
    }

    return $where;
}

function ic_list(array $f = [], int $limit = 500, int $offset = 0): array
{
    $params = [];
    $where = ic_build_where($f, $params);
    try {
        $sql = 'SELECT c.*, s.name AS status_name, s.color AS status_color
                FROM international_customers c
                LEFT JOIN international_customer_statuses s ON s.id = c.status_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('ic_list: ' . $e->getMessage()); return []; }
}

function ic_count(array $f = []): int
{
    $params = [];
    $where = ic_build_where($f, $params);
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM international_customers c WHERE ' . implode(' AND ', $where));
        $st->execute($params);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { log_error('ic_count: ' . $e->getMessage()); return 0; }
}

/** Özet sayımlar (kartlar): tümü, alıcı, satıcı, kara liste, izin yok. */
function ic_summary_counts(): array
{
    $out = ['all' => 0, 'buyer' => 0, 'seller' => 0, 'both' => 0, 'blacklist' => 0, 'no_permission' => 0];
    try {
        foreach (db()->query('SELECT company_role, COUNT(*) c FROM international_customers WHERE is_deleted = 0 AND is_blacklisted = 0 GROUP BY company_role')->fetchAll() as $r) {
            $role = (string) $r['company_role'];
            $out[$role] = (int) $r['c'];
            $out['all'] += (int) $r['c'];
        }
        $out['blacklist'] = (int) db()->query('SELECT COUNT(*) FROM international_customers WHERE is_deleted = 0 AND is_blacklisted = 1')->fetchColumn();
        $out['no_permission'] = (int) db()->query('SELECT COUNT(*) FROM international_customers WHERE is_deleted = 0 AND contact_permission = 0')->fetchColumn();
    } catch (Throwable $e) { log_error('ic_summary_counts: ' . $e->getMessage()); }
    return $out;
}

/** Distinct ülke listesi (filtre seçenekleri). */
function ic_distinct_countries(): array
{
    try {
        return db()->query("SELECT DISTINCT country FROM international_customers WHERE is_deleted = 0 AND country IS NOT NULL AND country <> '' ORDER BY country ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { log_error('ic_distinct_countries: ' . $e->getMessage()); return []; }
}

/* =========================================================================
 |  Tekil kayıt + ilişkileri
 * ====================================================================== */
function ic_get(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT c.*, s.name AS status_name, s.color AS status_color
            FROM international_customers c LEFT JOIN international_customer_statuses s ON s.id = c.status_id
            WHERE c.id = :id AND c.is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('ic_get: ' . $e->getMessage()); return null; }
}

function ic_contacts(int $customerId): array
{
    try {
        $st = db()->prepare('SELECT * FROM international_customer_contacts WHERE customer_id = :c AND is_deleted = 0 ORDER BY is_primary DESC, id ASC');
        $st->execute([':c' => $customerId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('ic_contacts: ' . $e->getMessage()); return []; }
}
function ic_primary_contact(int $customerId): ?array
{
    foreach (ic_contacts($customerId) as $ct) { if ((int) $ct['is_primary'] === 1) { return $ct; } }
    $all = ic_contacts($customerId);
    return $all[0] ?? null;
}

function ic_brands(int $customerId): array
{
    try {
        $st = db()->prepare('SELECT * FROM international_customer_brands WHERE customer_id = :c ORDER BY id ASC');
        $st->execute([':c' => $customerId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('ic_brands: ' . $e->getMessage()); return []; }
}
function ic_customer_type_ids(int $customerId): array
{
    try {
        $st = db()->prepare('SELECT company_type_id FROM international_customer_company_types WHERE customer_id = :c');
        $st->execute([':c' => $customerId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { log_error('ic_customer_type_ids: ' . $e->getMessage()); return []; }
}
function ic_customer_category_ids(int $customerId): array
{
    try {
        $st = db()->prepare('SELECT category_id FROM international_customer_category_relations WHERE customer_id = :c');
        $st->execute([':c' => $customerId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { log_error('ic_customer_category_ids: ' . $e->getMessage()); return []; }
}
function ic_notes(int $customerId): array
{
    try {
        $st = db()->prepare('SELECT * FROM international_customer_notes WHERE customer_id = :c AND is_deleted = 0 ORDER BY id DESC');
        $st->execute([':c' => $customerId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('ic_notes: ' . $e->getMessage()); return []; }
}
function ic_status_logs(int $customerId): array
{
    try {
        $st = db()->prepare('SELECT * FROM international_customer_status_logs WHERE customer_id = :c ORDER BY id DESC LIMIT 100');
        $st->execute([':c' => $customerId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('ic_status_logs: ' . $e->getMessage()); return []; }
}
function ic_messages(int $customerId): array
{
    try {
        $st = db()->prepare('SELECT * FROM international_customer_messages WHERE customer_id = :c ORDER BY id DESC LIMIT 200');
        $st->execute([':c' => $customerId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('ic_messages: ' . $e->getMessage()); return []; }
}

/* =========================================================================
 |  Alan normalize + doğrulama
 * ====================================================================== */
function ic_fields_from_input(array $in): array
{
    $role = (string) ($in['company_role'] ?? 'buyer');
    if (!isset(ic_company_roles()[$role])) { $role = 'buyer'; }
    return [
        'company_name'         => trim((string) ($in['company_name'] ?? '')),
        'company_role'         => $role,
        'status_id'            => (int) ($in['status_id'] ?? 0) ?: null,
        'category_id'          => (int) ($in['category_id'] ?? 0) ?: null,
        'country'              => trim((string) ($in['country'] ?? '')),
        'city'                 => trim((string) ($in['city'] ?? '')),
        'address'              => trim((string) ($in['address'] ?? '')),
        'website'              => trim((string) ($in['website'] ?? '')),
        'email'                => trim((string) ($in['email'] ?? '')),
        'phone'                => trim((string) ($in['phone'] ?? '')),
        'whatsapp'             => trim((string) ($in['whatsapp'] ?? '')),
        'tax_no'               => trim((string) ($in['tax_no'] ?? '')),
        'currency'             => trim((string) ($in['currency'] ?? '')),
        'language'             => trim((string) ($in['language'] ?? '')),
        'data_source'          => trim((string) ($in['data_source'] ?? '')),
        'event_name'           => trim((string) ($in['event_name'] ?? '')),
        'relationship_status'  => trim((string) ($in['relationship_status'] ?? '')),
        'communication_status' => trim((string) ($in['communication_status'] ?? '')),
        'prepared_by_user_id'  => (int) ($in['prepared_by_user_id'] ?? 0) ?: null,
        'contact_permission'   => isset($in['contact_permission']) ? 1 : 0,
        'notes'                => trim((string) ($in['notes'] ?? '')),
    ];
}

function ic_validate(array $d): array
{
    $errors = [];
    if (($d['company_name'] ?? '') === '') { $errors[] = 'Firma adı zorunludur.'; }
    if (($d['email'] ?? '') !== '' && !is_valid_email($d['email'])) { $errors[] = 'Geçerli bir e-posta girin.'; }
    return $errors;
}

function ic_bind(array $d, ?int $userId): array
{
    return [
        ':company_name' => $d['company_name'], ':company_role' => $d['company_role'],
        ':status_id' => $d['status_id'], ':category_id' => $d['category_id'],
        ':country' => $d['country'] ?: null, ':city' => $d['city'] ?: null,
        ':address' => $d['address'] ?: null, ':website' => $d['website'] ?: null,
        ':email' => $d['email'] ?: null, ':phone' => $d['phone'] ?: null, ':whatsapp' => $d['whatsapp'] ?: null,
        ':tax_no' => $d['tax_no'] ?: null, ':currency' => $d['currency'] ?: null, ':language' => $d['language'] ?: null,
        ':data_source' => $d['data_source'] ?: null, ':event_name' => $d['event_name'] ?: null,
        ':relationship_status' => $d['relationship_status'] ?: null, ':communication_status' => $d['communication_status'] ?: null,
        ':prepared_by' => $d['prepared_by_user_id'], ':contact_permission' => (int) $d['contact_permission'],
        ':notes' => $d['notes'] ?: null, ':uby' => $userId,
    ];
}

/* =========================================================================
 |  CRUD
 * ====================================================================== */
function ic_create(array $d, ?int $userId): int
{
    try {
        $recordNo = ic_next_record_no();
        $p = ic_bind($d, $userId);
        $p[':record_no'] = $recordNo;
        $p[':cby'] = $userId;
        $st = db()->prepare(
            'INSERT INTO international_customers
                (record_no, company_name, company_role, status_id, category_id, country, city, address, website,
                 email, phone, whatsapp, tax_no, currency, language, data_source, event_name,
                 relationship_status, communication_status, prepared_by_user_id, contact_permission, notes,
                 created_by, updated_by)
             VALUES
                (:record_no,:company_name,:company_role,:status_id,:category_id,:country,:city,:address,:website,
                 :email,:phone,:whatsapp,:tax_no,:currency,:language,:data_source,:event_name,
                 :relationship_status,:communication_status,:prepared_by,:contact_permission,:notes,
                 :cby,:uby)'
        );
        $st->execute($p);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('ic_create: ' . $e->getMessage()); return 0; }
}

function ic_update(int $id, array $d, ?int $userId): bool
{
    // Kayıt no DEĞİŞMEZ — güncellemede dokunulmaz.
    try {
        $p = ic_bind($d, $userId);
        $p[':id'] = $id;
        $st = db()->prepare(
            'UPDATE international_customers SET
                company_name=:company_name, company_role=:company_role, status_id=:status_id, category_id=:category_id,
                country=:country, city=:city, address=:address, website=:website, email=:email, phone=:phone,
                whatsapp=:whatsapp, tax_no=:tax_no, currency=:currency, language=:language, data_source=:data_source,
                event_name=:event_name, relationship_status=:relationship_status, communication_status=:communication_status,
                prepared_by_user_id=:prepared_by, contact_permission=:contact_permission, notes=:notes, updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        return $st->execute($p);
    } catch (Throwable $e) { log_error('ic_update: ' . $e->getMessage()); return false; }
}

function ic_delete(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE international_customers SET is_deleted = 1, deleted_at = NOW(), updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('ic_delete: ' . $e->getMessage()); return false; }
}

/** Durum değiştir + log. */
function ic_set_status(int $id, ?int $newStatusId, ?int $userId, string $note = ''): bool
{
    try {
        $cur = ic_get($id);
        if (!$cur) { return false; }
        $map = ic_status_map();
        $oldId = $cur['status_id'] !== null ? (int) $cur['status_id'] : null;
        $oldLabel = $oldId && isset($map[$oldId]) ? (string) $map[$oldId]['name'] : null;
        $newLabel = $newStatusId && isset($map[$newStatusId]) ? (string) $map[$newStatusId]['name'] : null;
        db()->prepare('UPDATE international_customers SET status_id = :s, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':s' => $newStatusId ?: null, ':uby' => $userId, ':id' => $id]);
        db()->prepare('INSERT INTO international_customer_status_logs (customer_id, old_status_id, new_status_id, old_label, new_label, note, created_by)
            VALUES (:c,:os,:ns,:ol,:nl,:note,:by)')
            ->execute([':c' => $id, ':os' => $oldId, ':ns' => $newStatusId ?: null, ':ol' => $oldLabel, ':nl' => $newLabel, ':note' => $note ?: null, ':by' => $userId]);
        return true;
    } catch (Throwable $e) { log_error('ic_set_status: ' . $e->getMessage()); return false; }
}

/** Kara liste durumunu değiştir (yalnızca yetkili çağırmalı). */
function ic_set_blacklist(int $id, bool $on, ?int $userId, string $reason = ''): bool
{
    try {
        return db()->prepare('UPDATE international_customers SET is_blacklisted = :b, blacklist_reason = :r, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':b' => $on ? 1 : 0, ':r' => $on ? ($reason ?: null) : null, ':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('ic_set_blacklist: ' . $e->getMessage()); return false; }
}

function ic_set_contact_permission(int $id, bool $on, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE international_customers SET contact_permission = :p, updated_by = :uby WHERE id = :id AND is_deleted = 0')
            ->execute([':p' => $on ? 1 : 0, ':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('ic_set_contact_permission: ' . $e->getMessage()); return false; }
}

/* ---- Kişiler ---- */
function ic_contact_save(int $customerId, array $in, ?int $id, ?int $userId): bool
{
    $name = trim((string) ($in['full_name'] ?? ''));
    if ($name === '') { return false; }
    $primary = isset($in['is_primary']) ? 1 : 0;
    $p = [
        ':c' => $customerId, ':n' => $name,
        ':t' => trim((string) ($in['title'] ?? '')) ?: null,
        ':d' => trim((string) ($in['department'] ?? '')) ?: null,
        ':e' => trim((string) ($in['email'] ?? '')) ?: null,
        ':ph' => trim((string) ($in['phone'] ?? '')) ?: null,
        ':wa' => trim((string) ($in['whatsapp'] ?? '')) ?: null,
        ':pr' => $primary, ':note' => trim((string) ($in['notes'] ?? '')) ?: null, ':uby' => $userId,
    ];
    try {
        if ($primary === 1) {
            db()->prepare('UPDATE international_customer_contacts SET is_primary = 0 WHERE customer_id = :c')->execute([':c' => $customerId]);
        }
        if ($id) {
            $p[':id'] = $id;
            return db()->prepare('UPDATE international_customer_contacts SET full_name=:n, title=:t, department=:d, email=:e, phone=:ph, whatsapp=:wa, is_primary=:pr, notes=:note, updated_by=:uby WHERE id=:id AND customer_id=:c')->execute($p);
        }
        $p[':cby'] = $userId;
        return db()->prepare('INSERT INTO international_customer_contacts (customer_id, full_name, title, department, email, phone, whatsapp, is_primary, notes, created_by, updated_by) VALUES (:c,:n,:t,:d,:e,:ph,:wa,:pr,:note,:cby,:uby)')->execute($p);
    } catch (Throwable $e) { log_error('ic_contact_save: ' . $e->getMessage()); return false; }
}
function ic_contact_delete(int $id, int $customerId, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE international_customer_contacts SET is_deleted = 1, updated_by = :uby WHERE id = :id AND customer_id = :c')
            ->execute([':uby' => $userId, ':id' => $id, ':c' => $customerId]);
    } catch (Throwable $e) { log_error('ic_contact_delete: ' . $e->getMessage()); return false; }
}

/* ---- Markalar ---- */
function ic_brand_add(int $customerId, string $brandName, string $relationType, ?int $brandId, ?int $userId, string $notes = ''): bool
{
    $brandName = trim($brandName);
    if ($brandName === '') { return false; }
    if (!isset(ic_brand_relation_types()[$relationType])) { $relationType = 'interested'; }
    try {
        return db()->prepare('INSERT INTO international_customer_brands (customer_id, brand_id, brand_name, relation_type, notes, created_by) VALUES (:c,:bid,:bn,:rt,:note,:by)')
            ->execute([':c' => $customerId, ':bid' => $brandId ?: null, ':bn' => $brandName, ':rt' => $relationType, ':note' => $notes ?: null, ':by' => $userId]);
    } catch (Throwable $e) { log_error('ic_brand_add: ' . $e->getMessage()); return false; }
}
function ic_brand_delete(int $id, int $customerId): bool
{
    try {
        return db()->prepare('DELETE FROM international_customer_brands WHERE id = :id AND customer_id = :c')->execute([':id' => $id, ':c' => $customerId]);
    } catch (Throwable $e) { log_error('ic_brand_delete: ' . $e->getMessage()); return false; }
}
/** "HP:Distribütörü; Canon:Satıyor" formatını parçalar → [[name, relation_type], ...] */
function ic_parse_brand_string(string $s): array
{
    $out = [];
    $relLabels = array_flip(array_map('mb_strtolower', ic_brand_relation_types())); // "distribütörü" => key
    foreach (preg_split('/\s*;\s*/', trim($s)) as $part) {
        $part = trim($part);
        if ($part === '') { continue; }
        $name = $part; $rel = 'interested';
        if (strpos($part, ':') !== false) {
            [$n, $r] = array_map('trim', explode(':', $part, 2));
            $name = $n;
            $rl = mb_strtolower($r);
            if (isset($relLabels[$rl])) { $rel = $relLabels[$rl]; }
            elseif (isset(ic_brand_relation_types()[$rl])) { $rel = $rl; }
        }
        if ($name !== '') { $out[] = [$name, $rel]; }
    }
    return $out;
}

/* ---- Tip / kategori ilişkileri (set) ---- */
function ic_set_company_types(int $customerId, array $typeIds): void
{
    try {
        db()->prepare('DELETE FROM international_customer_company_types WHERE customer_id = :c')->execute([':c' => $customerId]);
        $st = db()->prepare('INSERT IGNORE INTO international_customer_company_types (customer_id, company_type_id) VALUES (:c,:t)');
        foreach (array_unique(array_map('intval', $typeIds)) as $t) { if ($t > 0) { $st->execute([':c' => $customerId, ':t' => $t]); } }
    } catch (Throwable $e) { log_error('ic_set_company_types: ' . $e->getMessage()); }
}
function ic_set_categories(int $customerId, array $catIds): void
{
    try {
        db()->prepare('DELETE FROM international_customer_category_relations WHERE customer_id = :c')->execute([':c' => $customerId]);
        $st = db()->prepare('INSERT IGNORE INTO international_customer_category_relations (customer_id, category_id) VALUES (:c,:k)');
        foreach (array_unique(array_map('intval', $catIds)) as $k) { if ($k > 0) { $st->execute([':c' => $customerId, ':k' => $k]); } }
    } catch (Throwable $e) { log_error('ic_set_categories: ' . $e->getMessage()); }
}

/* ---- Notlar ---- */
function ic_note_add(int $customerId, string $body, ?int $userId): bool
{
    $body = trim($body);
    if ($body === '') { return false; }
    try {
        return db()->prepare('INSERT INTO international_customer_notes (customer_id, body, created_by) VALUES (:c,:b,:by)')
            ->execute([':c' => $customerId, ':b' => $body, ':by' => $userId]);
    } catch (Throwable $e) { log_error('ic_note_add: ' . $e->getMessage()); return false; }
}
function ic_note_delete(int $id, int $customerId, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE international_customer_notes SET is_deleted = 1 WHERE id = :id AND customer_id = :c')->execute([':id' => $id, ':c' => $customerId]);
    } catch (Throwable $e) { log_error('ic_note_delete: ' . $e->getMessage()); return false; }
}

/* ---- Kayıtlı filtreler ---- */
function ic_saved_filters(?int $userId): array
{
    try {
        $st = db()->prepare('SELECT * FROM international_customer_filters WHERE is_shared = 1 OR created_by = :u ORDER BY name ASC');
        $st->execute([':u' => $userId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('ic_saved_filters: ' . $e->getMessage()); return []; }
}
function ic_saved_filter_get(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM international_customer_filters WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('ic_saved_filter_get: ' . $e->getMessage()); return null; }
}
function ic_saved_filter_save(string $name, array $params, bool $shared, ?int $userId): int
{
    $name = trim($name);
    if ($name === '') { return 0; }
    try {
        db()->prepare('INSERT INTO international_customer_filters (name, params, is_shared, created_by) VALUES (:n,:p,:s,:by)')
            ->execute([':n' => $name, ':p' => json_encode($params, JSON_UNESCAPED_UNICODE), ':s' => $shared ? 1 : 0, ':by' => $userId]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('ic_saved_filter_save: ' . $e->getMessage()); return 0; }
}
function ic_saved_filter_delete(int $id, ?int $userId): bool
{
    try {
        // Sahibi veya paylaşılan → yönetici silsin diye created_by kontrolü yumuşak.
        return db()->prepare('DELETE FROM international_customer_filters WHERE id = :id AND (created_by = :u OR :u IS NULL OR is_shared = 1)')
            ->execute([':id' => $id, ':u' => $userId]);
    } catch (Throwable $e) { log_error('ic_saved_filter_delete: ' . $e->getMessage()); return false; }
}

/** Kaydı gönderilebilir mi? (kara liste değil, e-posta veya telefon var). */
function ic_is_blacklisted(array $c): bool { return (int) ($c['is_blacklisted'] ?? 0) === 1; }

/** Panel kullanıcıları (kaydı hazırlayan seçimi için). */
function ic_users_for_select(): array
{
    try {
        return db()->query("SELECT id, CASE WHEN full_name <> '' THEN full_name ELSE username END AS name FROM users WHERE is_active = 1 ORDER BY name ASC LIMIT 500")->fetchAll();
    } catch (Throwable $e) { log_error('ic_users_for_select: ' . $e->getMessage()); return []; }
}
