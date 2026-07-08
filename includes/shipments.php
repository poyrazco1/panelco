<?php
declare(strict_types=1);

/**
 * includes/shipments.php — Sevkiyat Takibi iş mantığı.
 *
 * İLKELER:
 *  - Sevkiyatçı yalnızca KENDİSİNE atanan sevkiyatları görür; fiyat/cari/yönetici
 *    notu gibi alanları göremez (view'da gizlenir).
 *  - Otomatik WhatsApp gönderimi YOK; yöneticiye TIKLANABİLİR mesaj butonu üretilir.
 *  - Durum değişiklikleri shipment_status_logs'a yazılır. Her işlem loglanır.
 *  - Fotoğraf yükleme yalnızca görsel; PHP/script engellenir; boyut sınırı vardır.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/personnel.php';
require_once __DIR__ . '/leave.php'; // app_setting_get / app_setting_set

/* ---- Sabit listeler ---- */
function shipment_statuses(): array
{
    return [
        'planned'      => 'Planlandı',
        'assigned'     => 'Sevkiyatçıya Atandı',
        'on_way'       => 'Yola Çıktı',
        'arrived'      => 'Adrese Ulaştı',
        'delivered'    => 'Teslim Edildi',
        'collected'    => 'Toplama Yapıldı',
        'partial'      => 'Kısmi Tamamlandı',
        'failed'       => 'Başarısız',
        'cancelled'    => 'İptal Edildi',
    ];
}
function shipment_status_label(string $k): string { return shipment_statuses()[$k] ?? $k; }
function shipment_status_class(string $k): string
{
    return match ($k) {
        'delivered', 'collected' => 'badge-success',
        'failed', 'cancelled'    => 'badge-danger',
        'partial'                => 'badge-leave',
        'on_way', 'arrived', 'assigned' => 'badge-info',
        default                  => 'badge-muted',
    };
}
function shipment_types(): array { return ['delivery' => 'Teslimat', 'collection' => 'Toplama', 'both' => 'Teslimat + Toplama']; }
function shipment_type_label(string $k): string { return shipment_types()[$k] ?? $k; }
function shipment_priorities(): array { return ['normal' => 'Normal', 'urgent' => 'Acil', 'critical' => 'Kritik']; }
function shipment_related_types(): array { return ['manual' => 'Manuel', 'order' => 'Sipariş', 'service' => 'Servis', 'rma' => 'İade/Değişim']; }
function shipment_fail_reasons(): array
{
    return [
        'no_one'        => 'Adreste kimse yok',
        'phone_off'     => 'Telefon kapalı',
        'wrong_address' => 'Yanlış adres',
        'refused'       => 'Müşteri teslim almadı',
        'not_ready'     => 'Ürün hazır değil',
        'closed'        => 'Firma kapalı',
        'other'         => 'Diğer',
    ];
}
function shipment_address_types(): array { return ['delivery' => 'Teslimat', 'collection' => 'Toplama', 'both' => 'Teslimat + Toplama']; }
function collection_statuses(): array
{
    return [
        'planned'   => 'Planlandı',
        'assigned'  => 'Sevkiyatçıya Atandı',
        'arrived'   => 'Adrese Gidildi',
        'collected' => 'Ürünler Alındı',
        'partial'   => 'Eksik Ürün Alındı',
        'failed'    => 'Alınamadı',
        'cancelled' => 'İptal Edildi',
    ];
}
function collection_status_label(string $k): string { return collection_statuses()[$k] ?? $k; }

/* ---- Görünürlük ---- */
function shipment_can_see_all(): bool
{
    $perms = function_exists('current_permissions') ? current_permissions() : [];
    return in_array('all', $perms, true) || can('shipments.assign') || can('shipments.reports') || can('shipments.route_plan');
}
function shipment_courier_personnel_id(): int
{
    $uid = (int) (current_user_id() ?? 0);
    if ($uid <= 0) { return 0; }
    $p = get_personnel_by_user_id($uid);
    return $p ? (int) $p['id'] : 0;
}

/* =========================================================================
 |  SEVKİYAT ADRESLERİ
 * ====================================================================== */
function get_shipment_addresses(array $f = []): array
{
    $where = ['is_deleted = 0'];
    $params = [];
    if (isset($f['active']) && $f['active'] !== '') { $where[] = 'is_active = :a'; $params[':a'] = (int) $f['active']; }
    if (!empty($f['type']) && isset(shipment_address_types()[$f['type']])) { $where[] = "(address_type = :t OR address_type = 'both')"; $params[':t'] = $f['type']; }
    if (!empty($f['city'])) { $where[] = 'city LIKE :city'; $params[':city'] = '%' . $f['city'] . '%'; }
    if (!empty($f['district'])) { $where[] = 'district LIKE :dist'; $params[':dist'] = '%' . $f['district'] . '%'; }
    if (!empty($f['search'])) { $where[] = '(company_name LIKE :q OR contact_name LIKE :q OR phone LIKE :q OR whatsapp LIKE :q OR district LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $st = db()->prepare('SELECT * FROM shipment_addresses WHERE ' . implode(' AND ', $where) . ' ORDER BY company_name ASC LIMIT 1000');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_shipment_addresses: ' . $e->getMessage()); return []; }
}
function get_shipment_address(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM shipment_addresses WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('get_shipment_address: ' . $e->getMessage()); return null; }
}
function shipment_address_fields(array $in): array
{
    $type = (string) ($in['address_type'] ?? 'delivery');
    if (!isset(shipment_address_types()[$type])) { $type = 'delivery'; }
    return [
        'company_name' => trim((string) ($in['company_name'] ?? '')),
        'contact_name' => trim((string) ($in['contact_name'] ?? '')),
        'phone' => trim((string) ($in['phone'] ?? '')), 'whatsapp' => trim((string) ($in['whatsapp'] ?? '')),
        'email' => trim((string) ($in['email'] ?? '')), 'country' => trim((string) ($in['country'] ?? '')),
        'city' => trim((string) ($in['city'] ?? '')), 'district' => trim((string) ($in['district'] ?? '')),
        'neighborhood' => trim((string) ($in['neighborhood'] ?? '')), 'address' => trim((string) ($in['address'] ?? '')),
        'location_url' => trim((string) ($in['location_url'] ?? '')), 'lat' => trim((string) ($in['lat'] ?? '')),
        'lng' => trim((string) ($in['lng'] ?? '')), 'address_type' => $type,
        'default_note' => trim((string) ($in['default_note'] ?? '')), 'working_hours' => trim((string) ($in['working_hours'] ?? '')),
        'vehicle_access' => trim((string) ($in['vehicle_access'] ?? '')), 'floor_building' => trim((string) ($in['floor_building'] ?? '')),
        'has_elevator' => isset($in['has_elevator']) ? 1 : 0, 'notes' => trim((string) ($in['notes'] ?? '')),
        'is_active' => isset($in['is_active']) ? 1 : 0,
    ];
}
function shipment_address_validate(array $d): array
{
    $errors = [];
    if (($d['company_name'] ?? '') === '') { $errors[] = 'Firma / müşteri adı zorunludur.'; }
    return $errors;
}
function shipment_address_bind(array $d, ?int $userId, bool $isNew): array
{
    $p = [
        ':company' => $d['company_name'], ':contact' => $d['contact_name'] ?: null, ':phone' => $d['phone'] ?: null,
        ':whatsapp' => $d['whatsapp'] ?: null, ':email' => $d['email'] ?: null, ':country' => $d['country'] ?: null,
        ':city' => $d['city'] ?: null, ':district' => $d['district'] ?: null, ':neigh' => $d['neighborhood'] ?: null,
        ':address' => $d['address'] ?: null, ':loc' => $d['location_url'] ?: null, ':lat' => $d['lat'] ?: null, ':lng' => $d['lng'] ?: null,
        ':type' => $d['address_type'], ':dnote' => $d['default_note'] ?: null, ':hours' => $d['working_hours'] ?: null,
        ':vaccess' => $d['vehicle_access'] ?: null, ':floor' => $d['floor_building'] ?: null, ':elev' => (int) $d['has_elevator'],
        ':notes' => $d['notes'] ?: null, ':active' => (int) $d['is_active'], ':uby' => $userId,
    ];
    if ($isNew) { $p[':cby'] = $userId; }
    return $p;
}
function create_shipment_address(array $d, ?int $userId): int
{
    try {
        $st = db()->prepare(
            'INSERT INTO shipment_addresses (company_name,contact_name,phone,whatsapp,email,country,city,district,neighborhood,address,location_url,lat,lng,address_type,default_note,working_hours,vehicle_access,floor_building,has_elevator,notes,is_active,created_by,updated_by)
             VALUES (:company,:contact,:phone,:whatsapp,:email,:country,:city,:district,:neigh,:address,:loc,:lat,:lng,:type,:dnote,:hours,:vaccess,:floor,:elev,:notes,:active,:cby,:uby)'
        );
        $st->execute(shipment_address_bind($d, $userId, true));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('create_shipment_address: ' . $e->getMessage()); return 0; }
}
function update_shipment_address(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE shipment_addresses SET company_name=:company,contact_name=:contact,phone=:phone,whatsapp=:whatsapp,email=:email,country=:country,city=:city,district=:district,neighborhood=:neigh,address=:address,location_url=:loc,lat=:lat,lng=:lng,address_type=:type,default_note=:dnote,working_hours=:hours,vehicle_access=:vaccess,floor_building=:floor,has_elevator=:elev,notes=:notes,is_active=:active,updated_by=:uby WHERE id=:id AND is_deleted=0'
        );
        $params = shipment_address_bind($d, $userId, false);
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) { log_error('update_shipment_address: ' . $e->getMessage()); return false; }
}
function delete_shipment_address(int $id, ?int $userId): bool
{
    try { return db()->prepare('UPDATE shipment_addresses SET is_deleted=1, updated_by=:uby WHERE id=:id')->execute([':uby' => $userId, ':id' => $id]); }
    catch (Throwable $e) { log_error('delete_shipment_address: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  SEVKİYATLAR
 * ====================================================================== */
function shipment_generate_no(): string
{
    $year = date('Y');
    for ($i = 0; $i < 50; $i++) {
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM shipments WHERE shipment_no LIKE :p');
            $st->execute([':p' => 'SVK-' . $year . '-%']);
            $seq = (int) $st->fetchColumn() + 1 + $i;
        } catch (Throwable $e) { $seq = (int) substr((string) time(), -5) + $i; }
        $no = 'SVK-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        try {
            $c = db()->prepare('SELECT COUNT(*) FROM shipments WHERE shipment_no = :n'); $c->execute([':n' => $no]);
            if ((int) $c->fetchColumn() === 0) { return $no; }
        } catch (Throwable $e) { return $no; }
    }
    return 'SVK-' . $year . '-' . substr((string) time(), -5);
}

function get_shipments(array $f = []): array
{
    $where = ['s.is_deleted = 0'];
    $params = [];
    if (!shipment_can_see_all()) {
        $pid = shipment_courier_personnel_id();
        $where[] = 's.courier_id = :own';
        $params[':own'] = $pid > 0 ? $pid : -1;
    } elseif (!empty($f['courier_id'])) { $where[] = 's.courier_id = :cid'; $params[':cid'] = (int) $f['courier_id']; }
    if (!empty($f['status']) && isset(shipment_statuses()[$f['status']])) { $where[] = 's.status = :st'; $params[':st'] = $f['status']; }
    if (!empty($f['type']) && isset(shipment_types()[$f['type']])) { $where[] = 's.shipment_type = :ty'; $params[':ty'] = $f['type']; }
    if (!empty($f['date_from'])) { $where[] = 's.shipment_date >= :df'; $params[':df'] = $f['date_from']; }
    if (!empty($f['date_to']))   { $where[] = 's.shipment_date <= :dt'; $params[':dt'] = $f['date_to']; }
    if (!empty($f['route_date'])) { $where[] = 's.route_date = :rd'; $params[':rd'] = $f['route_date']; }
    if (!empty($f['city'])) { $where[] = 'a.city LIKE :city'; $params[':city'] = '%' . $f['city'] . '%'; }
    if (!empty($f['search'])) { $where[] = '(s.shipment_no LIKE :q OR s.customer_name LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $sql = 'SELECT s.*, a.city AS addr_city, a.district AS addr_district, a.location_url, a.address AS addr_text,
                       p.full_name AS courier_name
                FROM shipments s
                LEFT JOIN shipment_addresses a ON a.id = s.address_id
                LEFT JOIN personnel p ON p.id = s.courier_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . (!empty($f['route_date']) ? 's.route_seq ASC, ' : '') . 's.id DESC LIMIT 1000';
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_shipments: ' . $e->getMessage()); return []; }
}

function get_shipment(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT s.*, a.company_name AS addr_company, a.city AS addr_city, a.district AS addr_district, a.address AS addr_text, a.location_url, a.phone AS addr_phone, a.contact_name AS addr_contact, p.full_name AS courier_name
                             FROM shipments s LEFT JOIN shipment_addresses a ON a.id = s.address_id LEFT JOIN personnel p ON p.id = s.courier_id
                             WHERE s.id = :id AND s.is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        $s = $st->fetch();
        if (!$s) { return null; }
        // Sevkiyatçı yalnızca kendi kaydını görebilir.
        if (!shipment_can_see_all() && (int) $s['courier_id'] !== shipment_courier_personnel_id()) { return null; }
        $its = db()->prepare('SELECT * FROM shipment_items WHERE shipment_id = :id ORDER BY sort, id'); $its->execute([':id' => $id]);
        $s['items'] = $its->fetchAll();
        $ph = db()->prepare('SELECT * FROM shipment_photos WHERE shipment_id = :id ORDER BY id DESC'); $ph->execute([':id' => $id]);
        $s['photos'] = $ph->fetchAll();
        return $s;
    } catch (Throwable $e) { log_error('get_shipment: ' . $e->getMessage()); return null; }
}

function shipment_summary(): array
{
    $out = ['total' => 0, 'completed' => 0, 'failed' => 0, 'pending' => 0, 'collections' => 0];
    try {
        foreach (db()->query('SELECT status, COUNT(*) c FROM shipments WHERE is_deleted = 0 GROUP BY status')->fetchAll() as $r) {
            $out['total'] += (int) $r['c'];
            if (in_array((string) $r['status'], ['delivered', 'collected'], true)) { $out['completed'] += (int) $r['c']; }
            elseif ((string) $r['status'] === 'failed') { $out['failed'] += (int) $r['c']; }
            elseif (!in_array((string) $r['status'], ['cancelled'], true)) { $out['pending'] += (int) $r['c']; }
        }
        $out['collections'] = (int) db()->query('SELECT COUNT(*) FROM shipment_collections WHERE is_deleted = 0')->fetchColumn();
    } catch (Throwable $e) { log_error('shipment_summary: ' . $e->getMessage()); }
    return $out;
}

function shipment_fields_from_input(array $in): array
{
    $type = (string) ($in['shipment_type'] ?? 'delivery'); if (!isset(shipment_types()[$type])) { $type = 'delivery'; }
    $rel = (string) ($in['related_type'] ?? 'manual'); if (!isset(shipment_related_types()[$rel])) { $rel = 'manual'; }
    $prio = (string) ($in['priority'] ?? 'normal'); if (!isset(shipment_priorities()[$prio])) { $prio = 'normal'; }
    $st = (string) ($in['status'] ?? 'planned'); if (!isset(shipment_statuses()[$st])) { $st = 'planned'; }
    return [
        'shipment_date' => trim((string) ($in['shipment_date'] ?? '')) ?: null,
        'planned_date'  => trim((string) ($in['planned_date'] ?? '')) ?: null,
        'customer_name' => trim((string) ($in['customer_name'] ?? '')),
        'address_id'    => (int) ($in['address_id'] ?? 0) ?: null,
        'shipment_type' => $type, 'related_type' => $rel, 'related_ref' => trim((string) ($in['related_ref'] ?? '')),
        'courier_id'    => (int) ($in['courier_id'] ?? 0) ?: null,
        'vehicle_info'  => trim((string) ($in['vehicle_info'] ?? '')),
        'priority'      => $prio, 'status' => $st,
        'description'   => trim((string) ($in['description'] ?? '')),
        'manager_note'  => trim((string) ($in['manager_note'] ?? '')),
    ];
}
function shipment_validate(array $d): array
{
    $errors = [];
    if (($d['customer_name'] ?? '') === '' && empty($d['address_id'])) { $errors[] = 'Müşteri adı veya sevkiyat adresi gereklidir.'; }
    return $errors;
}
function create_shipment(array $d, ?int $userId): int
{
    try {
        $no = shipment_generate_no();
        // Sevkiyatçı atandıysa durum otomatik "assigned" olabilir.
        if (!empty($d['courier_id']) && $d['status'] === 'planned') { $d['status'] = 'assigned'; }
        $st = db()->prepare(
            'INSERT INTO shipments (shipment_no,shipment_date,planned_date,customer_name,address_id,shipment_type,related_type,related_ref,courier_id,vehicle_info,priority,status,description,manager_note,created_by,updated_by)
             VALUES (:no,:sdate,:pdate,:cname,:addr,:type,:rtype,:rref,:courier,:vehicle,:prio,:status,:desc,:mnote,:cby,:uby)'
        );
        $st->execute([
            ':no' => $no, ':sdate' => $d['shipment_date'], ':pdate' => $d['planned_date'], ':cname' => $d['customer_name'] ?: null,
            ':addr' => $d['address_id'], ':type' => $d['shipment_type'], ':rtype' => $d['related_type'], ':rref' => $d['related_ref'] ?: null,
            ':courier' => $d['courier_id'], ':vehicle' => $d['vehicle_info'] ?: null, ':prio' => $d['priority'], ':status' => $d['status'],
            ':desc' => $d['description'] ?: null, ':mnote' => $d['manager_note'] ?: null, ':cby' => $userId, ':uby' => $userId,
        ]);
        $id = (int) db()->lastInsertId();
        shipment_log_status($id, '', $d['status'], 'Sevkiyat oluşturuldu', $userId);
        return $id;
    } catch (Throwable $e) { log_error('create_shipment: ' . $e->getMessage()); return 0; }
}
function update_shipment(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE shipments SET shipment_date=:sdate,planned_date=:pdate,customer_name=:cname,address_id=:addr,shipment_type=:type,related_type=:rtype,related_ref=:rref,courier_id=:courier,vehicle_info=:vehicle,priority=:prio,description=:desc,manager_note=:mnote,updated_by=:uby WHERE id=:id AND is_deleted=0'
        );
        return $st->execute([
            ':sdate' => $d['shipment_date'], ':pdate' => $d['planned_date'], ':cname' => $d['customer_name'] ?: null,
            ':addr' => $d['address_id'], ':type' => $d['shipment_type'], ':rtype' => $d['related_type'], ':rref' => $d['related_ref'] ?: null,
            ':courier' => $d['courier_id'], ':vehicle' => $d['vehicle_info'] ?: null, ':prio' => $d['priority'],
            ':desc' => $d['description'] ?: null, ':mnote' => $d['manager_note'] ?: null, ':uby' => $userId, ':id' => $id,
        ]);
    } catch (Throwable $e) { log_error('update_shipment: ' . $e->getMessage()); return false; }
}
function delete_shipment(int $id, ?int $userId): bool
{
    try { return db()->prepare('UPDATE shipments SET is_deleted=1, updated_by=:uby WHERE id=:id')->execute([':uby' => $userId, ':id' => $id]); }
    catch (Throwable $e) { log_error('delete_shipment: ' . $e->getMessage()); return false; }
}
function set_shipment_status(int $id, string $status, ?string $note, ?int $userId, ?string $failReason = null): bool
{
    if (!isset(shipment_statuses()[$status])) { return false; }
    try {
        $cur = get_shipment($id);
        if (!$cur) { return false; }
        db()->prepare('UPDATE shipments SET status=:s, fail_reason=:fr, courier_note=CASE WHEN :note2 <> \'\' THEN :note3 ELSE courier_note END, updated_by=:uby WHERE id=:id AND is_deleted=0')
            ->execute([':s' => $status, ':fr' => $status === 'failed' ? ($failReason ?: null) : null, ':note2' => (string) $note, ':note3' => (string) $note, ':uby' => $userId, ':id' => $id]);
        shipment_log_status($id, (string) $cur['status'], $status, $note, $userId);
        return true;
    } catch (Throwable $e) { log_error('set_shipment_status: ' . $e->getMessage()); return false; }
}
function assign_shipment(int $id, int $courierId, ?int $userId): bool
{
    try {
        $cur = get_shipment($id);
        if (!$cur) { return false; }
        $newStatus = in_array((string) $cur['status'], ['planned'], true) ? 'assigned' : (string) $cur['status'];
        db()->prepare('UPDATE shipments SET courier_id=:cid, status=:st, updated_by=:uby WHERE id=:id AND is_deleted=0')
            ->execute([':cid' => $courierId ?: null, ':st' => $newStatus, ':uby' => $userId, ':id' => $id]);
        if ($newStatus !== (string) $cur['status']) { shipment_log_status($id, (string) $cur['status'], $newStatus, 'Sevkiyatçı atandı', $userId); }
        return true;
    } catch (Throwable $e) { log_error('assign_shipment: ' . $e->getMessage()); return false; }
}
function shipment_log_status(int $id, string $old, string $new, ?string $note, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO shipment_status_logs (shipment_id, old_status, new_status, note, changed_by) VALUES (:id,:o,:n,:note,:by)')
            ->execute([':id' => $id, ':o' => $old ?: null, ':n' => $new, ':note' => $note ?: null, ':by' => $userId]);
    } catch (Throwable $e) { log_error('shipment_log_status: ' . $e->getMessage()); }
}
function shipment_status_history(int $id): array
{
    try {
        $st = db()->prepare('SELECT l.*, u.full_name FROM shipment_status_logs l LEFT JOIN users u ON u.id = l.changed_by WHERE l.shipment_id = :id ORDER BY l.id DESC LIMIT 50');
        $st->execute([':id' => $id]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* ---- Fotoğraflar ---- */
function shipment_handle_photo(string $field): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return ['path' => null, 'error' => 'Fotoğraf seçilmedi.']; }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) { return ['path' => null, 'error' => 'Yükleme hatası.']; }
    if ($f['size'] <= 0 || $f['size'] > 8 * 1024 * 1024) { return ['path' => null, 'error' => 'Fotoğraf en fazla 8 MB olabilir.']; }
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic'];
    $ext = strtolower((string) pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) { return ['path' => null, 'error' => 'Yalnızca fotoğraf (jpg/png/webp) yüklenebilir.']; }
    if (function_exists('finfo_open') && $ext !== 'heic') {
        $fi = finfo_open(FILEINFO_MIME_TYPE); $mime = (string) finfo_file($fi, (string) $f['tmp_name']); finfo_close($fi);
        if ($mime !== '' && strpos($mime, 'image/') !== 0) { return ['path' => null, 'error' => 'Dosya bir görsel değil.']; }
    }
    $dir = APP_ROOT . '/uploads/shipments';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'svk-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $rel = 'uploads/shipments/' . $name;
    if (!move_uploaded_file((string) $f['tmp_name'], APP_ROOT . '/' . $rel)) { return ['path' => null, 'error' => 'Fotoğraf kaydedilemedi.']; }
    return ['path' => $rel, 'error' => null];
}
function shipment_add_photo(int $shipmentId, string $path, string $type, ?int $userId, ?int $collectionId = null): bool
{
    try {
        return db()->prepare('INSERT INTO shipment_photos (shipment_id, collection_id, file_path, photo_type, uploaded_by) VALUES (:sid,:cid,:path,:type,:by)')
            ->execute([':sid' => $shipmentId, ':cid' => $collectionId, ':path' => $path, ':type' => $type, ':by' => $userId]);
    } catch (Throwable $e) { log_error('shipment_add_photo: ' . $e->getMessage()); return false; }
}

/* ---- Google Maps rota linki ---- */
function shipment_maps_link(array $addr): ?string
{
    if (!empty($addr['location_url'])) { return (string) $addr['location_url']; }
    if (!empty($addr['lat']) && !empty($addr['lng'])) { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr['lat'] . ',' . $addr['lng']); }
    $parts = array_filter([$addr['address'] ?? '', $addr['neighborhood'] ?? '', $addr['district'] ?? '', $addr['city'] ?? '']);
    if ($parts) { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(' ', $parts)); }
    return null;
}
/** Sıralı adreslerden Google Maps çok-duraklı rota linki. */
function shipment_route_maps_link(array $stops): ?string
{
    $pts = [];
    foreach ($stops as $addr) {
        if (!empty($addr['lat']) && !empty($addr['lng'])) { $pts[] = $addr['lat'] . ',' . $addr['lng']; }
        else { $p = array_filter([$addr['address'] ?? '', $addr['district'] ?? '', $addr['city'] ?? '']); if ($p) { $pts[] = implode(' ', $p); } }
    }
    if (!$pts) { return null; }
    $dest = array_pop($pts);
    $url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($dest);
    if ($pts) { $url .= '&waypoints=' . rawurlencode(implode('|', $pts)); }
    return $url;
}

/* =========================================================================
 |  AYARLAR + YÖNETİCİ WHATSAPP MESAJI
 * ====================================================================== */
function shipment_settings(): array
{
    return [
        'manager_name'     => (string) app_setting_get('shipment_manager_name', ''),
        'manager_whatsapp' => (string) app_setting_get('shipment_manager_whatsapp', ''),
        'wa_template'      => (string) app_setting_get('shipment_wa_template', ''),
    ];
}
function shipment_save_settings(array $in): void
{
    app_setting_set('shipment_manager_name', trim((string) ($in['manager_name'] ?? '')));
    app_setting_set('shipment_manager_whatsapp', trim((string) ($in['manager_whatsapp'] ?? '')));
    app_setting_set('shipment_wa_template', trim((string) ($in['wa_template'] ?? '')));
}
/** Yöneticiye durum bilgilendirme WhatsApp linki (TIKLANABİLİR; otomatik değil). */
function shipment_wa_manager_link(array $s): ?string
{
    $set = shipment_settings();
    $phone = trim((string) $set['manager_whatsapp']);
    if ($phone === '' || trim((string) $set['wa_template']) === '') { return null; }
    if (!function_exists('build_whatsapp_message_link')) { require_once __DIR__ . '/notifications.php'; }
    $addr = trim(((string) ($s['addr_text'] ?? '')) . ' ' . ((string) ($s['addr_district'] ?? '')) . ' ' . ((string) ($s['addr_city'] ?? '')));
    $vars = [
        '{sevkiyat_no}'   => (string) ($s['shipment_no'] ?? ''),
        '{sevkiyat_tipi}' => shipment_type_label((string) ($s['shipment_type'] ?? '')),
        '{firma_adi}'     => (string) ($s['customer_name'] ?? ($s['addr_company'] ?? '')),
        '{adres}'         => $addr,
        '{sevkiyatci_adi}' => (string) ($s['courier_name'] ?? ''),
        '{durum}'         => shipment_status_label((string) ($s['status'] ?? '')),
        '{tarih}'         => (string) ($s['shipment_date'] ?? ''),
        '{not}'           => (string) ($s['courier_note'] ?? ''),
        '{takip_linki}'   => '',
        '{panel_linki}'   => rtrim(base_url(), '/') . '/' . 'modules/shipments/view.php?id=' . (int) ($s['id'] ?? 0),
    ];
    $msg = strtr($set['wa_template'], $vars);
    return build_whatsapp_message_link($phone, $msg);
}

/* =========================================================================
 |  TOPLAMA (collections)
 * ====================================================================== */
function get_collection(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT c.*, s.shipment_no, s.courier_id FROM shipment_collections c LEFT JOIN shipments s ON s.id = c.shipment_id WHERE c.id = :id AND c.is_deleted = 0 LIMIT 1');
        $st->execute([':id' => $id]);
        $c = $st->fetch();
        if (!$c) { return null; }
        $its = db()->prepare('SELECT * FROM shipment_collection_items WHERE collection_id = :id ORDER BY sort, id'); $its->execute([':id' => $id]);
        $c['items'] = $its->fetchAll();
        return $c;
    } catch (Throwable $e) { log_error('get_collection: ' . $e->getMessage()); return null; }
}
function get_collections_for_shipment(int $shipmentId): array
{
    try {
        $st = db()->prepare('SELECT * FROM shipment_collections WHERE shipment_id = :sid AND is_deleted = 0 ORDER BY id DESC');
        $st->execute([':sid' => $shipmentId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}
function create_collection(int $shipmentId, array $d, array $items, ?int $userId): int
{
    try {
        $pdo = db(); $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO shipment_collections (shipment_id, company_name, address_id, collection_date, package_count, photo_required, note, status, created_by, updated_by) VALUES (:sid,:company,:addr,:cdate,:pkg,:preq,:note,:status,:cby,:uby)');
        $st->execute([
            ':sid' => $shipmentId ?: null, ':company' => trim((string) ($d['company_name'] ?? '')) ?: null,
            ':addr' => (int) ($d['address_id'] ?? 0) ?: null, ':cdate' => trim((string) ($d['collection_date'] ?? '')) ?: null,
            ':pkg' => (int) ($d['package_count'] ?? 0) ?: null, ':preq' => isset($d['photo_required']) ? 1 : 0,
            ':note' => trim((string) ($d['note'] ?? '')) ?: null, ':status' => 'planned', ':cby' => $userId, ':uby' => $userId,
        ]);
        $cid = (int) $pdo->lastInsertId();
        collection_replace_items($pdo, $cid, $items);
        $pdo->commit();
        return $cid;
    } catch (Throwable $e) { if (db()->inTransaction()) { db()->rollBack(); } log_error('create_collection: ' . $e->getMessage()); return 0; }
}
function collection_replace_items(PDO $pdo, int $cid, array $items): void
{
    $pdo->prepare('DELETE FROM shipment_collection_items WHERE collection_id = :cid')->execute([':cid' => $cid]);
    $st = $pdo->prepare('INSERT INTO shipment_collection_items (collection_id,name,product_code,barcode,qty,taken_qty,condition_note,is_taken,note,sort) VALUES (:cid,:name,:code,:barcode,:qty,:taken,:cond,:taken2,:note,:sort)');
    $sort = 0;
    foreach ($items as $it) {
        $name = trim((string) ($it['name'] ?? ''));
        if ($name === '') { continue; }
        $st->execute([
            ':cid' => $cid, ':name' => $name, ':code' => trim((string) ($it['product_code'] ?? '')) ?: null,
            ':barcode' => trim((string) ($it['barcode'] ?? '')) ?: null, ':qty' => (float) str_replace(',', '.', (string) ($it['qty'] ?? 1)),
            ':taken' => (float) str_replace(',', '.', (string) ($it['taken_qty'] ?? 0)), ':cond' => trim((string) ($it['condition_note'] ?? '')) ?: null,
            ':taken2' => !empty($it['is_taken']) ? 1 : 0, ':note' => trim((string) ($it['note'] ?? '')) ?: null, ':sort' => $sort++,
        ]);
    }
}
function set_collection_status(int $id, string $status, ?int $userId): bool
{
    if (!isset(collection_statuses()[$status])) { return false; }
    try { return db()->prepare('UPDATE shipment_collections SET status=:s, updated_by=:uby WHERE id=:id AND is_deleted=0')->execute([':s' => $status, ':uby' => $userId, ':id' => $id]); }
    catch (Throwable $e) { log_error('set_collection_status: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  ROTA TABANLI MODEL (Adres Defteri + Günlük Rota + Duraklar + Kanıt)
 |  Ana kayıt mantığı "tek tek sevkiyat" değil "rota ve rota durakları"dır.
 |  Sürücü (sevkiyatçı) = panel kullanıcısı; rota driver_user_id ile atanır.
 |  Sürücü yalnızca kendi rotalarını görür. Tüm işlemler hataya dayanıklıdır.
 * ====================================================================== */

/* ---- Rota durumları ---- */
function ship_route_statuses(): array
{
    return [
        'draft'     => 'Taslak',
        'planned'   => 'Planlandı',
        'on_way'    => 'Yolda',
        'partial'   => 'Kısmen Tamamlandı',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal',
    ];
}
function ship_route_status_label(string $k): string { return ship_route_statuses()[$k] ?? $k; }
function ship_route_status_class(string $k): string
{
    return match ($k) {
        'completed'         => 'badge-success',
        'on_way'            => 'badge-leave',
        'planned'           => 'badge-info',
        'partial'           => 'badge-leave',
        'cancelled'         => 'badge-muted',
        default             => 'badge-muted',
    };
}

/* ---- Durak durumları ---- */
function ship_stop_statuses(): array
{
    return [
        'pending'             => 'Bekliyor',
        'en_route'            => 'Gidiliyor',
        'delivered'           => 'Teslim Edildi',
        'collected'           => 'Toplandı',
        'delivered_collected' => 'Teslim Edildi + Toplandı',
        'not_found'           => 'Adreste Bulunamadı',
        'cancelled'           => 'İptal',
    ];
}
function ship_stop_status_label(string $k): string { return ship_stop_statuses()[$k] ?? $k; }
function ship_stop_status_class(string $k): string
{
    return match ($k) {
        'delivered', 'collected', 'delivered_collected' => 'badge-success',
        'en_route'   => 'badge-leave',
        'not_found'  => 'badge-danger',
        'cancelled'  => 'badge-muted',
        default      => 'badge-muted', // pending
    };
}
/** Durak "tamamlandı" sayılan durumlar. */
function ship_stop_done_statuses(): array { return ['delivered', 'collected', 'delivered_collected']; }
/** Durak "sorunlu" sayılan durumlar. */
function ship_stop_problem_statuses(): array { return ['not_found']; }

/* ---- İşlem tipleri (durak) ---- */
function ship_stop_operations(): array { return ['delivery' => 'Teslimat', 'collection' => 'Toplama', 'both' => 'Teslimat + Toplama']; }
function ship_stop_operation_label(string $k): string { return ship_stop_operations()[$k] ?? $k; }

/** İşlem tipine göre önerilen "tamamlandı" durumu. */
function ship_operation_done_status(string $op): string
{
    return match ($op) {
        'collection' => 'collected',
        'both'       => 'delivered_collected',
        default      => 'delivered',
    };
}

/* ---- Görünürlük / roller ---- */
/** Yönetici/sevkiyat yöneticisi: tüm rotaları görür ve yönetir. */
function ship_can_manage(): bool
{
    $perms = function_exists('current_permissions') ? current_permissions() : [];
    return in_array('all', $perms, true) || can('shipments.assign') || can('shipments.reports')
        || can('shipments.route_plan') || can('shipments.create') || can('shipments.edit');
}
/** Aktif sürücü kullanıcı id'si (rotalar bu kullanıcıya atanır). */
function ship_current_driver_user_id(): int { return (int) (current_user_id() ?? 0); }

/** Sürücü seçenekleri: panel kullanıcısına bağlı aktif personel [user_id => Ad]. */
function ship_driver_options(): array
{
    $out = [];
    try {
        $st = db()->query('SELECT p.user_id, p.full_name FROM personnel p
                           WHERE p.user_id IS NOT NULL AND p.is_active = 1
                           ORDER BY p.full_name ASC');
        foreach ($st->fetchAll() as $r) { $out[(int) $r['user_id']] = (string) $r['full_name']; }
    } catch (Throwable $e) { log_error('ship_driver_options: ' . $e->getMessage()); }
    return $out;
}
/** user_id → sürücü adı (yoksa users tablosundan). */
function ship_driver_name(?int $userId): string
{
    if (!$userId) { return ''; }
    static $cache = [];
    if (isset($cache[$userId])) { return $cache[$userId]; }
    try {
        $st = db()->prepare('SELECT COALESCE(p.full_name, u.full_name, u.username) AS n
                             FROM users u LEFT JOIN personnel p ON p.user_id = u.id WHERE u.id = :id LIMIT 1');
        $st->execute([':id' => $userId]);
        return $cache[$userId] = (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) { return ''; }
}

/* =========================================================================
 |  ROTALAR
 * ====================================================================== */
/**
 * Rota listesi. Sürücü yalnızca kendi rotalarını görür.
 * Filtreler: date, date_from, date_to, driver_user_id, status, today(bool)
 */
function get_routes(array $f = []): array
{
    $where = ['r.deleted_at IS NULL'];
    $params = [];
    if (!ship_can_manage()) {
        $where[] = 'r.driver_user_id = :own';
        $params[':own'] = ship_current_driver_user_id() ?: -1;
    } elseif (!empty($f['driver_user_id'])) {
        $where[] = 'r.driver_user_id = :drv'; $params[':drv'] = (int) $f['driver_user_id'];
    }
    if (!empty($f['today'])) { $where[] = 'r.route_date = CURDATE()'; }
    if (!empty($f['date'])) { $where[] = 'r.route_date = :d'; $params[':d'] = $f['date']; }
    if (!empty($f['date_from'])) { $where[] = 'r.route_date >= :df'; $params[':df'] = $f['date_from']; }
    if (!empty($f['date_to'])) { $where[] = 'r.route_date <= :dt'; $params[':dt'] = $f['date_to']; }
    if (!empty($f['status']) && isset(ship_route_statuses()[$f['status']])) { $where[] = 'r.status = :st'; $params[':st'] = $f['status']; }
    if (isset($f['completed']) && $f['completed'] === true) { $where[] = "r.status = 'completed'"; }
    try {
        $sql = 'SELECT r.*,
                       (SELECT COUNT(*) FROM shipment_route_stops s WHERE s.route_id = r.id AND s.deleted_at IS NULL) AS stop_total,
                       (SELECT COUNT(*) FROM shipment_route_stops s WHERE s.route_id = r.id AND s.deleted_at IS NULL AND s.status IN ("delivered","collected","delivered_collected")) AS stop_done,
                       (SELECT COUNT(*) FROM shipment_route_stops s WHERE s.route_id = r.id AND s.deleted_at IS NULL AND s.status = "not_found") AS stop_problem
                FROM shipment_routes r
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.route_date DESC, r.id DESC LIMIT 500';
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) { $r['driver_name'] = ship_driver_name($r['driver_user_id'] ? (int) $r['driver_user_id'] : null); }
        return $rows;
    } catch (Throwable $e) { log_error('get_routes: ' . $e->getMessage()); return []; }
}

/** Tek rota (görünürlük kontrollü). */
function get_route(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM shipment_routes WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        if (!$r) { return null; }
        if (!ship_can_manage() && (int) $r['driver_user_id'] !== ship_current_driver_user_id()) { return null; }
        $r['driver_name'] = ship_driver_name($r['driver_user_id'] ? (int) $r['driver_user_id'] : null);
        return $r;
    } catch (Throwable $e) { log_error('get_route: ' . $e->getMessage()); return null; }
}

/** Rota durakları (adres bilgisiyle birleşik, sıraya göre). */
function get_route_stops(int $routeId): array
{
    if ($routeId <= 0) { return []; }
    try {
        $st = db()->prepare('SELECT s.*, a.company_name, a.contact_name, a.phone, a.whatsapp, a.city, a.district,
                                    a.neighborhood, a.address, a.location_url, a.lat, a.lng
                             FROM shipment_route_stops s
                             LEFT JOIN shipment_addresses a ON a.id = s.address_id
                             WHERE s.route_id = :rid AND s.deleted_at IS NULL
                             ORDER BY s.stop_order ASC, s.id ASC');
        $st->execute([':rid' => $routeId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_route_stops: ' . $e->getMessage()); return []; }
}

/** Tek durak (rota + adres bilgisiyle, görünürlük kontrollü). */
function get_route_stop(int $stopId): ?array
{
    if ($stopId <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT s.*, r.route_name, r.driver_user_id, r.route_date, r.status AS route_status,
                                    a.company_name, a.contact_name, a.phone, a.whatsapp, a.city, a.district,
                                    a.neighborhood, a.address, a.location_url, a.lat, a.lng
                             FROM shipment_route_stops s
                             INNER JOIN shipment_routes r ON r.id = s.route_id
                             LEFT JOIN shipment_addresses a ON a.id = s.address_id
                             WHERE s.id = :id AND s.deleted_at IS NULL AND r.deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $stopId]);
        $s = $st->fetch();
        if (!$s) { return null; }
        if (!ship_can_manage() && (int) $s['driver_user_id'] !== ship_current_driver_user_id()) { return null; }
        return $s;
    } catch (Throwable $e) { log_error('get_route_stop: ' . $e->getMessage()); return null; }
}

/** Rota başlık alanlarını girişten üretir. */
function ship_route_header_fields(array $in): array
{
    $status = (string) ($in['status'] ?? 'draft');
    if (!isset(ship_route_statuses()[$status])) { $status = 'draft'; }
    return [
        'route_name'     => trim((string) ($in['route_name'] ?? '')),
        'route_date'     => trim((string) ($in['route_date'] ?? '')) ?: null,
        'driver_user_id' => (int) ($in['driver_user_id'] ?? 0) ?: null,
        'start_point'    => trim((string) ($in['start_point'] ?? '')) ?: null,
        'end_point'      => trim((string) ($in['end_point'] ?? '')) ?: null,
        'note'           => trim((string) ($in['note'] ?? '')) ?: null,
        'status'         => $status,
    ];
}

/**
 * Rota + durakları tek işlemde oluşturur (transactional).
 * $stops: her biri ['address_id','operation_type','reference_no','stop_note'] (sırayla).
 * Sürücü atanmışsa rota 'planned', değilse 'draft'.
 */
function create_route(array $h, array $stops, ?int $userId): int
{
    if (($h['route_name'] ?? '') === '') { $h['route_name'] = 'Rota ' . date('d.m.Y H:i'); }
    $status = !empty($h['driver_user_id']) ? 'planned' : 'draft';
    try {
        $pdo = db(); $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO shipment_routes (route_name,route_date,driver_user_id,start_point,end_point,status,note,created_by,updated_by)
                             VALUES (:name,:date,:drv,:start,:end,:status,:note,:cby,:uby)');
        $st->execute([
            ':name' => $h['route_name'], ':date' => $h['route_date'], ':drv' => $h['driver_user_id'],
            ':start' => $h['start_point'], ':end' => $h['end_point'], ':status' => $status,
            ':note' => $h['note'], ':cby' => $userId, ':uby' => $userId,
        ]);
        $routeId = (int) $pdo->lastInsertId();
        ship_route_insert_stops($pdo, $routeId, $stops, $userId);
        $pdo->commit();
        route_log($routeId, null, '', $status, 'Rota oluşturuldu', $userId);
        return $routeId;
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('create_route: ' . $e->getMessage());
        return 0;
    }
}

/** Durakları verilen sırayla ekler (PDO transaction içinde çağrılır). */
function ship_route_insert_stops(PDO $pdo, int $routeId, array $stops, ?int $userId): void
{
    $st = $pdo->prepare('INSERT INTO shipment_route_stops (route_id,address_id,stop_order,operation_type,status,reference_no,stop_note,created_by,updated_by)
                         VALUES (:rid,:addr,:ord,:op,:status,:ref,:note,:cby,:uby)');
    $order = 1;
    foreach ($stops as $s) {
        $addr = (int) ($s['address_id'] ?? 0);
        if ($addr <= 0) { continue; }
        $op = (string) ($s['operation_type'] ?? 'delivery');
        if (!isset(ship_stop_operations()[$op])) { $op = 'delivery'; }
        $st->execute([
            ':rid' => $routeId, ':addr' => $addr, ':ord' => $order++, ':op' => $op, ':status' => 'pending',
            ':ref' => trim((string) ($s['reference_no'] ?? '')) ?: null,
            ':note' => trim((string) ($s['stop_note'] ?? '')) ?: null,
            ':cby' => $userId, ':uby' => $userId,
        ]);
    }
}

/** Rota başlığını günceller. */
function update_route(int $id, array $h, ?int $userId): bool
{
    try {
        $st = db()->prepare('UPDATE shipment_routes SET route_name=:name,route_date=:date,driver_user_id=:drv,start_point=:start,end_point=:end,note=:note,updated_by=:uby WHERE id=:id AND deleted_at IS NULL');
        return $st->execute([
            ':name' => $h['route_name'] ?: ('Rota ' . date('d.m.Y')), ':date' => $h['route_date'], ':drv' => $h['driver_user_id'],
            ':start' => $h['start_point'], ':end' => $h['end_point'], ':note' => $h['note'], ':uby' => $userId, ':id' => $id,
        ]);
    } catch (Throwable $e) { log_error('update_route: ' . $e->getMessage()); return false; }
}

/** Rotanın duraklarını yeni listeyle değiştirir (düzenleme). */
function route_replace_stops(int $routeId, array $stops, ?int $userId): bool
{
    try {
        $pdo = db(); $pdo->beginTransaction();
        $pdo->prepare('UPDATE shipment_route_stops SET deleted_at = NOW(), updated_by = :uby WHERE route_id = :rid AND deleted_at IS NULL')
            ->execute([':uby' => $userId, ':rid' => $routeId]);
        ship_route_insert_stops($pdo, $routeId, $stops, $userId);
        $pdo->commit();
        return true;
    } catch (Throwable $e) { if (db()->inTransaction()) { db()->rollBack(); } log_error('route_replace_stops: ' . $e->getMessage()); return false; }
}

/** Durak sırasını günceller: [stopId => order]. */
function route_reorder_stops(int $routeId, array $orderMap, ?int $userId): bool
{
    try {
        $pdo = db(); $pdo->beginTransaction();
        $st = $pdo->prepare('UPDATE shipment_route_stops SET stop_order = :ord, updated_by = :uby WHERE id = :id AND route_id = :rid AND deleted_at IS NULL');
        foreach ($orderMap as $stopId => $ord) {
            $st->execute([':ord' => (int) $ord, ':uby' => $userId, ':id' => (int) $stopId, ':rid' => $routeId]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) { if (db()->inTransaction()) { db()->rollBack(); } log_error('route_reorder_stops: ' . $e->getMessage()); return false; }
}

/** Rotaya sürücü atar (durum planned olur). */
function assign_route_driver(int $id, int $driverUserId, ?int $userId): bool
{
    try {
        $cur = get_route($id);
        if (!$cur) { return false; }
        $newStatus = (string) $cur['status'] === 'draft' ? 'planned' : (string) $cur['status'];
        db()->prepare('UPDATE shipment_routes SET driver_user_id=:drv, status=:st, updated_by=:uby WHERE id=:id AND deleted_at IS NULL')
            ->execute([':drv' => $driverUserId ?: null, ':st' => $newStatus, ':uby' => $userId, ':id' => $id]);
        if ($newStatus !== (string) $cur['status']) { route_log($id, null, (string) $cur['status'], $newStatus, 'Sürücü atandı', $userId); }
        return true;
    } catch (Throwable $e) { log_error('assign_route_driver: ' . $e->getMessage()); return false; }
}

/** Rota durumunu doğrudan ayarlar (iptal vb.). */
function set_route_status(int $id, string $status, ?string $note, ?int $userId): bool
{
    if (!isset(ship_route_statuses()[$status])) { return false; }
    try {
        $cur = get_route($id);
        if (!$cur) { return false; }
        $extra = '';
        if ($status === 'completed') { $extra = ', completed_at = NOW()'; }
        if ($status === 'on_way' && empty($cur['started_at'])) { $extra .= ', started_at = NOW()'; }
        db()->prepare("UPDATE shipment_routes SET status=:s{$extra}, updated_by=:uby WHERE id=:id AND deleted_at IS NULL")
            ->execute([':s' => $status, ':uby' => $userId, ':id' => $id]);
        route_log($id, null, (string) $cur['status'], $status, $note, $userId);
        return true;
    } catch (Throwable $e) { log_error('set_route_status: ' . $e->getMessage()); return false; }
}

/** Rotayı sil (soft). */
function delete_route(int $id, ?int $userId): bool
{
    try { return db()->prepare('UPDATE shipment_routes SET deleted_at = NOW(), updated_by = :uby WHERE id = :id')->execute([':uby' => $userId, ':id' => $id]); }
    catch (Throwable $e) { log_error('delete_route: ' . $e->getMessage()); return false; }
}

/**
 * Durak durumunu günceller: durak + log + rota genel durumu otomatik hesap.
 * Sürücü yalnızca kendi rotasının durağını güncelleyebilir (get_route_stop kontrolü).
 */
function set_stop_status(int $stopId, string $status, ?string $driverNote, ?int $userId): bool
{
    if (!isset(ship_stop_statuses()[$status])) { return false; }
    $stop = get_route_stop($stopId);
    if (!$stop) { return false; }
    try {
        $done = in_array($status, ship_stop_done_statuses(), true) || $status === 'cancelled';
        $completedSql = $done ? 'NOW()' : 'NULL';
        db()->prepare("UPDATE shipment_route_stops
                       SET status = :s,
                           driver_note = CASE WHEN :note_set = 1 THEN :note ELSE driver_note END,
                           completed_at = " . ($status === 'pending' ? 'NULL' : ($done ? 'NOW()' : 'completed_at')) . ",
                           updated_by = :uby
                       WHERE id = :id AND deleted_at IS NULL")
            ->execute([
                ':s' => $status,
                ':note_set' => ($driverNote !== null && $driverNote !== '') ? 1 : 0,
                ':note' => (string) $driverNote,
                ':uby' => $userId, ':id' => $stopId,
            ]);
        route_log((int) $stop['route_id'], $stopId, (string) $stop['status'], $status, $driverNote, $userId);
        recompute_route_status((int) $stop['route_id'], $userId);
        return true;
    } catch (Throwable $e) { log_error('set_stop_status: ' . $e->getMessage()); return false; }
}

/**
 * Rota genel durumunu duraklara göre yeniden hesaplar.
 *  - Tüm aktif duraklar tamamlandı → completed
 *  - Bir kısmı tamamlandı veya sorunlu → partial
 *  - Hiçbiri tamamlanmadı ama yolda durak var → on_way
 *  - Aksi halde → planned
 * İptal edilmiş rota otomatik değişmez.
 */
function recompute_route_status(int $routeId, ?int $userId = null): void
{
    try {
        $route = db()->prepare('SELECT status, started_at FROM shipment_routes WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $route->execute([':id' => $routeId]);
        $cur = $route->fetch();
        if (!$cur || (string) $cur['status'] === 'cancelled') { return; }

        $st = db()->prepare('SELECT status FROM shipment_route_stops WHERE route_id = :rid AND deleted_at IS NULL AND status <> "cancelled"');
        $st->execute([':rid' => $routeId]);
        $statuses = $st->fetchAll(PDO::FETCH_COLUMN);
        $total = count($statuses);
        if ($total === 0) { return; }

        $done = 0; $problem = 0; $enRoute = 0;
        foreach ($statuses as $s) {
            if (in_array($s, ['delivered', 'collected', 'delivered_collected'], true)) { $done++; }
            elseif ($s === 'not_found') { $problem++; }
            elseif ($s === 'en_route') { $enRoute++; }
        }

        if ($done === $total) { $new = 'completed'; }
        elseif ($done > 0 || $problem > 0) { $new = 'partial'; }
        elseif ($enRoute > 0) { $new = 'on_way'; }
        else { $new = 'planned'; }

        if ($new === (string) $cur['status']) { return; }

        $extra = '';
        if ($new === 'completed') { $extra = ', completed_at = NOW()'; }
        if (in_array($new, ['on_way', 'partial'], true) && empty($cur['started_at'])) { $extra .= ', started_at = NOW()'; }
        db()->prepare("UPDATE shipment_routes SET status = :s{$extra}, updated_by = :uby WHERE id = :id AND deleted_at IS NULL")
            ->execute([':s' => $new, ':uby' => $userId, ':id' => $routeId]);
        route_log($routeId, null, (string) $cur['status'], $new, 'Otomatik durum güncellemesi', $userId);
    } catch (Throwable $e) { log_error('recompute_route_status: ' . $e->getMessage()); }
}

/** Rota/durak durum logu. */
function route_log(int $routeId, ?int $stopId, string $old, string $new, ?string $note, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO shipment_status_logs (route_id, stop_id, old_status, new_status, note, changed_by) VALUES (:r,:s,:o,:n,:note,:by)')
            ->execute([':r' => $routeId ?: null, ':s' => $stopId, ':o' => $old ?: null, ':n' => $new, ':note' => $note ?: null, ':by' => $userId]);
    } catch (Throwable $e) { log_error('route_log: ' . $e->getMessage()); }
}
/** Rota durum geçmişi (rota + duraklar). */
function route_status_history(int $routeId): array
{
    try {
        $st = db()->prepare('SELECT l.*, u.full_name FROM shipment_status_logs l LEFT JOIN users u ON u.id = l.changed_by WHERE l.route_id = :rid ORDER BY l.id DESC LIMIT 100');
        $st->execute([':rid' => $routeId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* =========================================================================
 |  GOOGLE MAPS ROTA LİNKİ (API'siz — directions linki)
 * ====================================================================== */
/** Bir adres/durak için tekli konum linki. */
function ship_stop_maps_link(array $a): ?string
{
    if (!empty($a['location_url'])) { return (string) $a['location_url']; }
    if (!empty($a['lat']) && !empty($a['lng'])) { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($a['lat'] . ',' . $a['lng']); }
    $parts = array_filter([$a['address'] ?? '', $a['neighborhood'] ?? '', $a['district'] ?? '', $a['city'] ?? '']);
    if ($parts) { return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(' ', $parts)); }
    return null;
}
/** Bir durak için harita "noktası" (enlem/boylam varsa onu, yoksa adres metni). */
function ship_stop_point(array $a): string
{
    if (!empty($a['lat']) && !empty($a['lng'])) { return $a['lat'] . ',' . $a['lng']; }
    $parts = array_filter([$a['address'] ?? '', $a['neighborhood'] ?? '', $a['district'] ?? '', $a['city'] ?? '']);
    return $parts ? implode(' ', $parts) : '';
}
/**
 * Rota için Google Maps directions linki üretir.
 * Başlangıç + sıralı duraklar + bitiş. Enlem/boylam varsa öncelikli.
 * @return array{url:?string, warn:bool}  warn: durak sayısı Google limitini aşıyorsa
 */
function ship_route_maps_link(array $route, array $stops): array
{
    $pts = [];
    foreach ($stops as $s) {
        $p = ship_stop_point($s);
        if ($p !== '') { $pts[] = $p; }
    }
    $start = trim((string) ($route['start_point'] ?? ''));
    $end   = trim((string) ($route['end_point'] ?? ''));

    // Başlangıç/bitiş noktalarını (varsa) ekle.
    $origin = $start !== '' ? $start : ($pts ? array_shift($pts) : '');
    $dest   = $end !== '' ? $end : ($pts ? array_pop($pts) : $origin);
    if ($origin === '' && $dest === '' && !$pts) { return ['url' => null, 'warn' => false]; }

    $url = 'https://www.google.com/maps/dir/?api=1';
    if ($origin !== '') { $url .= '&origin=' . rawurlencode($origin); }
    $url .= '&destination=' . rawurlencode($dest !== '' ? $dest : $origin);
    // Google Maps waypoints ~23 ile sınırlıdır.
    $warn = count($pts) > 23;
    if ($pts) { $url .= '&waypoints=' . rawurlencode(implode('|', array_slice($pts, 0, 23))); }
    $url .= '&travelmode=driving';
    return ['url' => $url, 'warn' => $warn];
}

/* =========================================================================
 |  TESLİMAT / TOPLAMA KANITI (fotoğraf)
 * ====================================================================== */
/** Fotoğraf yükleme ayarları (Genel Ayarlar > Sevkiyat Ayarları'ndan). */
function ship_proof_config(): array
{
    $maxMb = (int) app_setting_get('shipment_photo_max_mb', '8');
    if ($maxMb <= 0 || $maxMb > 32) { $maxMb = 8; }
    $typesRaw = (string) app_setting_get('shipment_photo_types', 'jpg,jpeg,png,webp');
    $types = array_values(array_filter(array_map(static fn($t) => strtolower(trim($t)), explode(',', $typesRaw))));
    if (!$types) { $types = ['jpg', 'jpeg', 'png', 'webp']; }
    return ['max_bytes' => $maxMb * 1024 * 1024, 'max_mb' => $maxMb, 'types' => $types];
}
/**
 * Yüklenen kanıt fotoğrafını doğrular ve uploads/shipment-proof/ altına kaydeder.
 * @return array{ok:bool, error:?string, path:?string, name:?string, mime:?string, size:?int}
 */
function ship_handle_proof(string $field): array
{
    $cfg = ship_proof_config();
    $fail = static fn(string $m) => ['ok' => false, 'error' => $m, 'path' => null, 'name' => null, 'mime' => null, 'size' => null];
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return $fail('Fotoğraf seçilmedi.'); }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) { return $fail('Yükleme sırasında hata oluştu.'); }
    if ($f['size'] <= 0 || $f['size'] > $cfg['max_bytes']) { return $fail('Fotoğraf en fazla ' . $cfg['max_mb'] . ' MB olabilir.'); }
    $ext = strtolower((string) pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $cfg['types'], true)) { return $fail('İzin verilen dosya tipleri: ' . implode(', ', $cfg['types']) . '.'); }
    // Gerçek içerik türü görsel mi?
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE); $mime = (string) finfo_file($fi, (string) $f['tmp_name']); finfo_close($fi);
        if ($mime !== '' && strpos($mime, 'image/') !== 0) { return $fail('Dosya bir görsel değil.'); }
    } else { $mime = (string) ($f['type'] ?? ''); }
    $dir = APP_ROOT . '/uploads/shipment-proof';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    // Güvenlik: klasörde PHP/script çalışmasını engelle (fresh deploy'da .htaccess yoksa yaz).
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "php_flag engine off\n<FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|php8|pht|phar|cgi|pl|py|sh)$\">\n    Require all denied\n</FilesMatch>\nRemoveHandler .php .phtml .phar\nAddType text/plain .php .phtml .phar\n");
    }
    $safe = 'proof-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $rel = 'uploads/shipment-proof/' . $safe;
    if (!move_uploaded_file((string) $f['tmp_name'], APP_ROOT . '/' . $rel)) { return $fail('Fotoğraf kaydedilemedi.'); }
    return ['ok' => true, 'error' => null, 'path' => $rel, 'name' => (string) $f['name'], 'mime' => $mime ?? null, 'size' => (int) $f['size']];
}
/** Kanıt kaydını DB'ye yazar. */
function ship_add_proof(?int $routeId, ?int $stopId, array $file, ?string $note, ?int $userId): bool
{
    try {
        return db()->prepare('INSERT INTO shipment_proofs (route_id, stop_id, uploaded_by, file_path, original_name, mime_type, file_size, note) VALUES (:r,:s,:by,:path,:name,:mime,:size,:note)')
            ->execute([
                ':r' => $routeId ?: null, ':s' => $stopId ?: null, ':by' => $userId, ':path' => $file['path'],
                ':name' => $file['name'] ?? null, ':mime' => $file['mime'] ?? null, ':size' => $file['size'] ?? null,
                ':note' => ($note !== null && $note !== '') ? $note : null,
            ]);
    } catch (Throwable $e) { log_error('ship_add_proof: ' . $e->getMessage()); return false; }
}
function get_stop_proofs(int $stopId): array
{
    try { $st = db()->prepare('SELECT * FROM shipment_proofs WHERE stop_id = :id ORDER BY id DESC'); $st->execute([':id' => $stopId]); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function get_route_proofs(int $routeId): array
{
    try { $st = db()->prepare('SELECT * FROM shipment_proofs WHERE route_id = :id ORDER BY id DESC'); $st->execute([':id' => $routeId]); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function stop_proof_count(int $stopId): int
{
    try { $st = db()->prepare('SELECT COUNT(*) FROM shipment_proofs WHERE stop_id = :id'); $st->execute([':id' => $stopId]); return (int) $st->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

/* =========================================================================
 |  ROTA MERKEZİ ÖZETİ + RAPORLAR
 * ====================================================================== */
/** Sevkiyat Takibi ana ekranı özet kartları (görünürlüğe göre). */
function ship_route_center_summary(): array
{
    $out = ['today_routes' => 0, 'pending_stops' => 0, 'done_stops' => 0, 'problem_stops' => 0];
    $scope = ''; $params = [];
    if (!ship_can_manage()) { $scope = ' AND r.driver_user_id = :own'; $params[':own'] = ship_current_driver_user_id() ?: -1; }
    try {
        $q = db()->prepare("SELECT COUNT(*) FROM shipment_routes r WHERE r.deleted_at IS NULL AND r.route_date = CURDATE()$scope");
        $q->execute($params); $out['today_routes'] = (int) $q->fetchColumn();

        $base = "SELECT COUNT(*) FROM shipment_route_stops s INNER JOIN shipment_routes r ON r.id = s.route_id
                 WHERE s.deleted_at IS NULL AND r.deleted_at IS NULL AND r.route_date = CURDATE()$scope";
        $p = db()->prepare($base . " AND s.status IN ('pending','en_route')"); $p->execute($params); $out['pending_stops'] = (int) $p->fetchColumn();
        $d = db()->prepare($base . " AND s.status IN ('delivered','collected','delivered_collected')"); $d->execute($params); $out['done_stops'] = (int) $d->fetchColumn();
        $pr = db()->prepare($base . " AND s.status = 'not_found'"); $pr->execute($params); $out['problem_stops'] = (int) $pr->fetchColumn();
    } catch (Throwable $e) { log_error('ship_route_center_summary: ' . $e->getMessage()); }
    return $out;
}

/**
 * Durak bazlı rapor (rota + adres + sürücü birleşik). Filtreler:
 * date_from, date_to, driver_user_id, status, city, district, operation_type, has_photo(''|'1'|'0'), collection_only(bool)
 */
function ship_stop_report(array $f = []): array
{
    $where = ['s.deleted_at IS NULL', 'r.deleted_at IS NULL'];
    $params = [];
    if (!ship_can_manage()) { $where[] = 'r.driver_user_id = :own'; $params[':own'] = ship_current_driver_user_id() ?: -1; }
    elseif (!empty($f['driver_user_id'])) { $where[] = 'r.driver_user_id = :drv'; $params[':drv'] = (int) $f['driver_user_id']; }
    if (!empty($f['date_from'])) { $where[] = 'r.route_date >= :df'; $params[':df'] = $f['date_from']; }
    if (!empty($f['date_to']))   { $where[] = 'r.route_date <= :dt'; $params[':dt'] = $f['date_to']; }
    if (!empty($f['status']) && isset(ship_stop_statuses()[$f['status']])) { $where[] = 's.status = :st'; $params[':st'] = $f['status']; }
    if (!empty($f['operation_type']) && isset(ship_stop_operations()[$f['operation_type']])) { $where[] = 's.operation_type = :op'; $params[':op'] = $f['operation_type']; }
    if (!empty($f['collection_only'])) { $where[] = "s.operation_type IN ('collection','both')"; }
    if (!empty($f['city'])) { $where[] = 'a.city LIKE :city'; $params[':city'] = '%' . $f['city'] . '%'; }
    if (!empty($f['district'])) { $where[] = 'a.district LIKE :dist'; $params[':dist'] = '%' . $f['district'] . '%'; }
    if (isset($f['has_photo']) && $f['has_photo'] !== '') {
        $where[] = $f['has_photo'] === '1'
            ? 'EXISTS (SELECT 1 FROM shipment_proofs pp WHERE pp.stop_id = s.id)'
            : 'NOT EXISTS (SELECT 1 FROM shipment_proofs pp WHERE pp.stop_id = s.id)';
    }
    try {
        $sql = 'SELECT s.id, s.route_id, s.stop_order, s.operation_type, s.status, s.reference_no, s.stop_note, s.driver_note, s.completed_at,
                       r.route_name, r.route_date, r.driver_user_id,
                       a.company_name, a.contact_name, a.phone, a.city, a.district, a.address,
                       (SELECT COUNT(*) FROM shipment_proofs pp WHERE pp.stop_id = s.id) AS photo_count
                FROM shipment_route_stops s
                INNER JOIN shipment_routes r ON r.id = s.route_id
                LEFT JOIN shipment_addresses a ON a.id = s.address_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.route_date DESC, r.id DESC, s.stop_order ASC LIMIT 5000';
        $st = db()->prepare($sql); $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) { $r['driver_name'] = ship_driver_name($r['driver_user_id'] ? (int) $r['driver_user_id'] : null); }
        return $rows;
    } catch (Throwable $e) { log_error('ship_stop_report: ' . $e->getMessage()); return []; }
}

/* ---- Genişletilmiş sevkiyat ayarları (rota varsayılanları) ---- */
function ship_route_settings(): array
{
    return [
        'manager_name'     => (string) app_setting_get('shipment_manager_name', ''),
        'manager_whatsapp' => (string) app_setting_get('shipment_manager_whatsapp', ''),
        'wa_template'      => (string) app_setting_get('shipment_wa_template', ''),
        'default_start'    => (string) app_setting_get('shipment_default_start', ''),
        'default_end'      => (string) app_setting_get('shipment_default_end', ''),
        'photo_max_mb'     => (string) app_setting_get('shipment_photo_max_mb', '8'),
        'photo_types'      => (string) app_setting_get('shipment_photo_types', 'jpg,jpeg,png,webp'),
    ];
}

/** Yöneticiye durak/rota durum bilgilendirme WhatsApp linki (tıklanabilir; otomatik değil). */
function ship_wa_manager_link_for_stop(array $stop, array $route): ?string
{
    $set = ship_route_settings();
    $phone = trim((string) $set['manager_whatsapp']);
    if ($phone === '') { return null; }
    if (!function_exists('build_whatsapp_message_link')) { require_once __DIR__ . '/notifications.php'; }
    $addr = trim(((string) ($stop['address'] ?? '')) . ' ' . ((string) ($stop['district'] ?? '')) . ' ' . ((string) ($stop['city'] ?? '')));
    $msg = "Sevkiyat durum bildirimi\n"
         . 'Rota: ' . (string) ($route['route_name'] ?? '') . "\n"
         . 'Firma: ' . (string) ($stop['company_name'] ?? '') . "\n"
         . 'İşlem: ' . ship_stop_operation_label((string) ($stop['operation_type'] ?? '')) . "\n"
         . 'Durum: ' . ship_stop_status_label((string) ($stop['status'] ?? '')) . "\n"
         . 'Adres: ' . $addr;
    return build_whatsapp_message_link($phone, $msg);
}

/** Telefon araması için tel: linki (rakamlar). */
function ship_tel_link(?string $phone): ?string
{
    $d = preg_replace('/[^\d+]/', '', (string) $phone);
    return ($d === '' || $d === null) ? null : 'tel:' . $d;
}
/** Müşteriyi WhatsApp'tan açma linki (numara). */
function ship_wa_contact_link(?string $phone): ?string
{
    if (!function_exists('build_whatsapp_message_link')) { require_once __DIR__ . '/notifications.php'; }
    return build_whatsapp_message_link($phone, '');
}
