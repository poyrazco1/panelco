<?php
declare(strict_types=1);

/**
 * includes/suppliers.php
 * Tedarikçi iş mantığı: listeleme+filtre, CRUD. Hataya dayanıklı.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Tedarikçi listesi (filtreli). */
function get_suppliers(array $f = []): array
{
    $where = ['is_deleted = 0'];
    $params = [];
    if (isset($f['active']) && $f['active'] !== '') {
        $where[] = 'is_active = :active';
        $params[':active'] = (int) $f['active'];
    }
    if (!empty($f['search'])) {
        $where[] = '(company_name LIKE :q OR contact_name LIKE :q OR phone LIKE :q OR email LIKE :q OR code LIKE :q OR product_groups LIKE :q)';
        $params[':q'] = '%' . $f['search'] . '%';
    }
    try {
        $sql = 'SELECT * FROM suppliers WHERE ' . implode(' AND ', $where) . ' ORDER BY company_name ASC LIMIT 1000';
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_suppliers: ' . $e->getMessage());
        return [];
    }
}

function get_supplier_by_id(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM suppliers WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        log_error('get_supplier_by_id: ' . $e->getMessage());
        return null;
    }
}

function supplier_count(): int
{
    try { return (int) db()->query('SELECT COUNT(*) FROM suppliers WHERE is_deleted = 0')->fetchColumn(); }
    catch (Throwable $e) { log_error('supplier_count: ' . $e->getMessage()); return 0; }
}

function supplier_fields_from_input(array $in): array
{
    return [
        'code'           => trim((string) ($in['code'] ?? '')),
        'company_name'   => trim((string) ($in['company_name'] ?? '')),
        'contact_name'   => trim((string) ($in['contact_name'] ?? '')),
        'phone'          => trim((string) ($in['phone'] ?? '')),
        'whatsapp'       => trim((string) ($in['whatsapp'] ?? '')),
        'email'          => trim((string) ($in['email'] ?? '')),
        'country'        => trim((string) ($in['country'] ?? '')),
        'city'           => trim((string) ($in['city'] ?? '')),
        'address'        => trim((string) ($in['address'] ?? '')),
        'website'        => trim((string) ($in['website'] ?? '')),
        'product_groups' => trim((string) ($in['product_groups'] ?? '')),
        'payment_terms'  => trim((string) ($in['payment_terms'] ?? '')),
        'delivery_time'  => trim((string) ($in['delivery_time'] ?? '')),
        'currency'       => in_array(($in['currency'] ?? 'TRY'), ['TRY', 'USD', 'EUR'], true) ? (string) $in['currency'] : 'TRY',
        'notes'          => trim((string) ($in['notes'] ?? '')),
        'is_active'      => isset($in['is_active']) ? 1 : 0,
    ];
}

function supplier_validate(array $d): array
{
    $errors = [];
    if (($d['company_name'] ?? '') === '') { $errors[] = 'Firma adı zorunludur.'; }
    if (($d['email'] ?? '') !== '' && !is_valid_email($d['email'])) { $errors[] = 'Geçerli bir e-posta girin.'; }
    return $errors;
}

function supplier_bind(array $d, ?int $userId): array
{
    return [
        ':code' => $d['code'] ?: null, ':company_name' => $d['company_name'],
        ':contact_name' => $d['contact_name'] ?: null, ':phone' => $d['phone'] ?: null,
        ':whatsapp' => $d['whatsapp'] ?: null, ':email' => $d['email'] ?: null,
        ':country' => $d['country'] ?: null, ':city' => $d['city'] ?: null,
        ':address' => $d['address'] ?: null, ':website' => $d['website'] ?: null,
        ':product_groups' => $d['product_groups'] ?: null, ':payment_terms' => $d['payment_terms'] ?: null,
        ':delivery_time' => $d['delivery_time'] ?: null, ':currency' => $d['currency'],
        ':notes' => $d['notes'] ?: null, ':is_active' => (int) $d['is_active'],
        ':cby' => $userId, ':uby' => $userId,
    ];
}

function create_supplier(array $d, ?int $userId): int
{
    try {
        $st = db()->prepare(
            'INSERT INTO suppliers
                (code, company_name, contact_name, phone, whatsapp, email, country, city, address,
                 website, product_groups, payment_terms, delivery_time, currency, notes,
                 is_active, created_by, updated_by)
             VALUES
                (:code,:company_name,:contact_name,:phone,:whatsapp,:email,:country,:city,:address,
                 :website,:product_groups,:payment_terms,:delivery_time,:currency,:notes,
                 :is_active,:cby,:uby)'
        );
        $st->execute(supplier_bind($d, $userId));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        log_error('create_supplier: ' . $e->getMessage());
        return 0;
    }
}

function update_supplier(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE suppliers SET
                code=:code, company_name=:company_name, contact_name=:contact_name, phone=:phone,
                whatsapp=:whatsapp, email=:email, country=:country, city=:city, address=:address,
                website=:website, product_groups=:product_groups, payment_terms=:payment_terms,
                delivery_time=:delivery_time, currency=:currency, notes=:notes, is_active=:is_active,
                updated_by=:uby
             WHERE id=:id AND is_deleted = 0'
        );
        $params = supplier_bind($d, $userId);
        unset($params[':cby']);
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) {
        log_error('update_supplier: ' . $e->getMessage());
        return false;
    }
}

function delete_supplier(int $id, ?int $userId): bool
{
    try {
        $st = db()->prepare('UPDATE suppliers SET is_deleted = 1, updated_by = :uby WHERE id = :id');
        return $st->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) {
        log_error('delete_supplier: ' . $e->getMessage());
        return false;
    }
}
