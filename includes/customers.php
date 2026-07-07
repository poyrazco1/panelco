<?php
declare(strict_types=1);

/**
 * includes/customers.php
 * Müşteri (cari) iş mantığı: kategoriler, listeleme+filtre, CRUD, ilişki özetleri.
 * Tüm sorgular hataya dayanıklıdır (fatal yerine boş/0 döner, log yazar).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Müşteri kategorileri (tip anahtarı => etiket). */
function customer_types(): array
{
    return [
        'tr'        => 'TR Müşteri',
        'abroad'    => 'Yurtdışı Müşteri',
        'site'      => 'Site Müşterisi',
        'dealer'    => 'Bayi',
        'potential' => 'Potansiyel Müşteri',
    ];
}

function customer_type_label(string $key): string
{
    return customer_types()[$key] ?? $key;
}

/** Müşteri listesi (filtreli). */
function get_customers(array $f = []): array
{
    $where = ['is_deleted = 0'];
    $params = [];

    if (!empty($f['type']) && isset(customer_types()[$f['type']])) {
        $where[] = 'customer_type = :type';
        $params[':type'] = $f['type'];
    }
    if (isset($f['active']) && $f['active'] !== '') {
        $where[] = 'is_active = :active';
        $params[':active'] = (int) $f['active'];
    }
    if (!empty($f['search'])) {
        $where[] = '(company_name LIKE :q OR contact_name LIKE :q OR phone LIKE :q OR email LIKE :q OR code LIKE :q OR city LIKE :q)';
        $params[':q'] = '%' . $f['search'] . '%';
    }

    try {
        $sql = 'SELECT * FROM customers WHERE ' . implode(' AND ', $where) . ' ORDER BY company_name ASC LIMIT 1000';
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_customers: ' . $e->getMessage());
        return [];
    }
}

/** Tek müşteri (silinmemiş). */
function get_customer_by_id(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM customers WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('get_customer_by_id: ' . $e->getMessage());
        return null;
    }
}

/** Tip bazında sayımlar (özet kartları). */
function customer_type_counts(): array
{
    $out = ['all' => 0];
    foreach (array_keys(customer_types()) as $t) { $out[$t] = 0; }
    try {
        $rows = db()->query('SELECT customer_type, COUNT(*) c FROM customers WHERE is_deleted = 0 GROUP BY customer_type')->fetchAll();
        foreach ($rows as $r) {
            $out['all'] += (int) $r['c'];
            $out[(string) $r['customer_type']] = (int) $r['c'];
        }
    } catch (Throwable $e) {
        log_error('customer_type_counts: ' . $e->getMessage());
    }
    return $out;
}

/** POST verisinden normalize edilmiş müşteri alanları. */
function customer_fields_from_input(array $in): array
{
    $type = (string) ($in['customer_type'] ?? 'tr');
    if (!isset(customer_types()[$type])) { $type = 'tr'; }
    return [
        'code'          => trim((string) ($in['code'] ?? '')),
        'company_name'  => trim((string) ($in['company_name'] ?? '')),
        'contact_name'  => trim((string) ($in['contact_name'] ?? '')),
        'phone'         => trim((string) ($in['phone'] ?? '')),
        'whatsapp'      => trim((string) ($in['whatsapp'] ?? '')),
        'email'         => trim((string) ($in['email'] ?? '')),
        'tax_office'    => trim((string) ($in['tax_office'] ?? '')),
        'tax_no'        => trim((string) ($in['tax_no'] ?? '')),
        'country'       => trim((string) ($in['country'] ?? '')),
        'city'          => trim((string) ($in['city'] ?? '')),
        'district'      => trim((string) ($in['district'] ?? '')),
        'address'       => trim((string) ($in['address'] ?? '')),
        'website'       => trim((string) ($in['website'] ?? '')),
        'customer_type' => $type,
        'source'        => trim((string) ($in['source'] ?? '')),
        'notes'         => trim((string) ($in['notes'] ?? '')),
        'is_active'     => isset($in['is_active']) ? 1 : 0,
    ];
}

/** Müşteri alanlarını doğrular; hata mesajları dizisi döndürür. */
function customer_validate(array $d): array
{
    $errors = [];
    if (($d['company_name'] ?? '') === '') { $errors[] = 'Firma adı zorunludur.'; }
    if (($d['email'] ?? '') !== '' && !is_valid_email($d['email'])) { $errors[] = 'Geçerli bir e-posta girin.'; }
    return $errors;
}

/** Yeni müşteri oluşturur; id döndürür (0 = hata). */
function create_customer(array $d, ?int $userId): int
{
    try {
        $st = db()->prepare(
            'INSERT INTO customers
                (code, company_name, contact_name, phone, whatsapp, email, tax_office, tax_no,
                 country, city, district, address, website, customer_type, source, notes,
                 is_active, created_by, updated_by)
             VALUES
                (:code,:company_name,:contact_name,:phone,:whatsapp,:email,:tax_office,:tax_no,
                 :country,:city,:district,:address,:website,:customer_type,:source,:notes,
                 :is_active,:cby,:uby)'
        );
        $st->execute(customer_bind($d, $userId));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        log_error('create_customer: ' . $e->getMessage());
        return 0;
    }
}

/** Müşteri günceller. */
function update_customer(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE customers SET
                code=:code, company_name=:company_name, contact_name=:contact_name, phone=:phone,
                whatsapp=:whatsapp, email=:email, tax_office=:tax_office, tax_no=:tax_no,
                country=:country, city=:city, district=:district, address=:address, website=:website,
                customer_type=:customer_type, source=:source, notes=:notes, is_active=:is_active,
                updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        $params = customer_bind($d, $userId);
        unset($params[':cby']);
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) {
        log_error('update_customer: ' . $e->getMessage());
        return false;
    }
}

/** Ortak bind dizisi. */
function customer_bind(array $d, ?int $userId): array
{
    return [
        ':code' => $d['code'] ?: null, ':company_name' => $d['company_name'],
        ':contact_name' => $d['contact_name'] ?: null, ':phone' => $d['phone'] ?: null,
        ':whatsapp' => $d['whatsapp'] ?: null, ':email' => $d['email'] ?: null,
        ':tax_office' => $d['tax_office'] ?: null, ':tax_no' => $d['tax_no'] ?: null,
        ':country' => $d['country'] ?: null, ':city' => $d['city'] ?: null,
        ':district' => $d['district'] ?: null, ':address' => $d['address'] ?: null,
        ':website' => $d['website'] ?: null, ':customer_type' => $d['customer_type'],
        ':source' => $d['source'] ?: null, ':notes' => $d['notes'] ?: null,
        ':is_active' => (int) $d['is_active'], ':cby' => $userId, ':uby' => $userId,
    ];
}

/** Yumuşak silme. */
function delete_customer(int $id, ?int $userId): bool
{
    try {
        $st = db()->prepare('UPDATE customers SET is_deleted = 1, updated_by = :uby WHERE id = :id');
        return $st->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) {
        log_error('delete_customer: ' . $e->getMessage());
        return false;
    }
}

/** Müşteri seçimi için hafif liste (id + ad) — teklif/sipariş formları. */
function customers_for_select(): array
{
    try {
        return db()->query('SELECT id, company_name, contact_name, phone, email FROM customers WHERE is_deleted = 0 AND is_active = 1 ORDER BY company_name ASC LIMIT 2000')->fetchAll();
    } catch (Throwable $e) {
        log_error('customers_for_select: ' . $e->getMessage());
        return [];
    }
}
