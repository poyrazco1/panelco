<?php
declare(strict_types=1);

/**
 * includes/inventory.php — Envanter / Demirbaş iş mantığı.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/personnel.php';

function inventory_categories(): array
{
    return [
        'computer'   => 'Bilgisayar',
        'laptop'     => 'Laptop',
        'printer'    => 'Yazıcı',
        'phone'      => 'Telefon',
        'server'     => 'Sunucu',
        'network'    => 'Ağ Cihazı',
        'furniture'  => 'Mobilya',
        'vehicle'    => 'Araç',
        'warehouse'  => 'Depo Ekipmanı',
        'other'      => 'Diğer',
    ];
}
function inventory_category_label(string $k): string { return inventory_categories()[$k] ?? $k; }

function inventory_statuses(): array
{
    return [
        'active'     => 'Aktif',
        'faulty'     => 'Arızalı',
        'in_service' => 'Serviste',
        'scrap'      => 'Hurda',
        'sold'       => 'Satıldı',
        'lost'       => 'Kayıp',
    ];
}
function inventory_status_label(string $k): string { return inventory_statuses()[$k] ?? $k; }
function inventory_status_class(string $k): string
{
    return match ($k) {
        'active' => 'badge-success',
        'faulty', 'lost' => 'badge-danger',
        'in_service' => 'badge-info',
        'scrap', 'sold' => 'badge-muted',
        default => 'badge-muted',
    };
}

function get_inventory_items(array $f = []): array
{
    $where = ['i.is_deleted = 0'];
    $params = [];
    if (!empty($f['category']) && isset(inventory_categories()[$f['category']])) { $where[] = 'i.category = :cat'; $params[':cat'] = $f['category']; }
    if (!empty($f['status']) && isset(inventory_statuses()[$f['status']])) { $where[] = 'i.status = :st'; $params[':st'] = $f['status']; }
    if (!empty($f['search'])) { $where[] = '(i.name LIKE :q OR i.brand LIKE :q OR i.model LIKE :q OR i.serial_no LIKE :q OR i.location LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $sql = 'SELECT i.*, p.full_name AS assigned_name
                FROM inventory_items i LEFT JOIN personnel p ON p.id = i.assigned_personnel_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id DESC LIMIT 1000';
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_inventory_items: ' . $e->getMessage()); return []; }
}

function get_inventory_item(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT i.*, p.full_name AS assigned_name FROM inventory_items i LEFT JOIN personnel p ON p.id = i.assigned_personnel_id WHERE i.id = :id AND i.is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('get_inventory_item: ' . $e->getMessage()); return null; }
}

function inventory_counts(): array
{
    $out = ['all' => 0];
    foreach (array_keys(inventory_statuses()) as $s) { $out[$s] = 0; }
    try {
        foreach (db()->query('SELECT status, COUNT(*) c FROM inventory_items WHERE is_deleted = 0 GROUP BY status')->fetchAll() as $r) {
            $out['all'] += (int) $r['c'];
            $out[(string) $r['status']] = (int) $r['c'];
        }
    } catch (Throwable $e) { log_error('inventory_counts: ' . $e->getMessage()); }
    return $out;
}

function inventory_fields_from_input(array $in): array
{
    $cat = (string) ($in['category'] ?? 'other');
    if (!isset(inventory_categories()[$cat])) { $cat = 'other'; }
    $st = (string) ($in['status'] ?? 'active');
    if (!isset(inventory_statuses()[$st])) { $st = 'active'; }
    return [
        'name'                  => trim((string) ($in['name'] ?? '')),
        'category'              => $cat,
        'brand'                 => trim((string) ($in['brand'] ?? '')),
        'model'                 => trim((string) ($in['model'] ?? '')),
        'serial_no'             => trim((string) ($in['serial_no'] ?? '')),
        'purchase_date'         => trim((string) ($in['purchase_date'] ?? '')) ?: null,
        'invoice_no'            => trim((string) ($in['invoice_no'] ?? '')),
        'warranty_end'          => trim((string) ($in['warranty_end'] ?? '')) ?: null,
        'assigned_personnel_id' => (int) ($in['assigned_personnel_id'] ?? 0) ?: null,
        'location'              => trim((string) ($in['location'] ?? '')),
        'status'                => $st,
        'description'           => trim((string) ($in['description'] ?? '')),
    ];
}

function inventory_validate(array $d): array
{
    $errors = [];
    if (($d['name'] ?? '') === '') { $errors[] = 'Demirbaş adı zorunludur.'; }
    return $errors;
}

function inventory_bind(array $d, ?int $userId, bool $isNew, ?string $filePath): array
{
    $p = [
        ':name' => $d['name'], ':cat' => $d['category'], ':brand' => $d['brand'] ?: null,
        ':model' => $d['model'] ?: null, ':serial' => $d['serial_no'] ?: null,
        ':pdate' => $d['purchase_date'], ':invoice' => $d['invoice_no'] ?: null,
        ':warranty' => $d['warranty_end'], ':assigned' => $d['assigned_personnel_id'],
        ':location' => $d['location'] ?: null, ':status' => $d['status'],
        ':desc' => $d['description'] ?: null, ':uby' => $userId,
    ];
    if ($filePath !== null) { $p[':file'] = $filePath; }
    if ($isNew) { $p[':cby'] = $userId; }
    return $p;
}

function create_inventory_item(array $d, ?int $userId, ?string $filePath): int
{
    try {
        $st = db()->prepare(
            'INSERT INTO inventory_items
                (name, category, brand, model, serial_no, purchase_date, invoice_no, warranty_end,
                 assigned_personnel_id, location, status, description, file_path, created_by, updated_by)
             VALUES
                (:name,:cat,:brand,:model,:serial,:pdate,:invoice,:warranty,:assigned,:location,:status,:desc,:file,:cby,:uby)'
        );
        $st->execute(inventory_bind($d, $userId, true, $filePath ?? ''));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('create_inventory_item: ' . $e->getMessage()); return 0; }
}

function update_inventory_item(int $id, array $d, ?int $userId, ?string $filePath): bool
{
    try {
        $setFile = $filePath !== null ? ', file_path = :file' : '';
        $st = db()->prepare(
            'UPDATE inventory_items SET
                name=:name, category=:cat, brand=:brand, model=:model, serial_no=:serial, purchase_date=:pdate,
                invoice_no=:invoice, warranty_end=:warranty, assigned_personnel_id=:assigned, location=:location,
                status=:status, description=:desc, updated_by=:uby' . $setFile . '
             WHERE id=:id AND is_deleted = 0'
        );
        $params = inventory_bind($d, $userId, false, $filePath);
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) { log_error('update_inventory_item: ' . $e->getMessage()); return false; }
}

function delete_inventory_item(int $id, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE inventory_items SET is_deleted = 1, updated_by = :uby WHERE id = :id')
            ->execute([':uby' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('delete_inventory_item: ' . $e->getMessage()); return false; }
}

/**
 * Envanter belgesi (fatura vb.) güvenli upload. İzinli: pdf, jpg, png, webp.
 * @return array{path:?string, error:?string}
 */
function inventory_handle_upload(string $field): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) { return ['path' => null, 'error' => 'Dosya yüklenemedi.']; }
    if ($f['size'] <= 0 || $f['size'] > 5 * 1024 * 1024) { return ['path' => null, 'error' => 'Dosya en fazla 5 MB olabilir.']; }
    $allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower((string) pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) { return ['path' => null, 'error' => 'İzin verilmeyen dosya türü (pdf/jpg/png/webp).']; }
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, (string) $f['tmp_name']);
        finfo_close($fi);
        if ($mime !== '' && $mime !== $allowed[$ext]) { return ['path' => null, 'error' => 'Dosya içeriği uzantıyla uyuşmuyor.']; }
    }
    $dir = APP_ROOT . '/uploads/inventory';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'inv-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $rel = 'uploads/inventory/' . $name;
    if (!move_uploaded_file((string) $f['tmp_name'], APP_ROOT . '/' . $rel)) {
        return ['path' => null, 'error' => 'Dosya kaydedilemedi.'];
    }
    return ['path' => $rel, 'error' => null];
}
