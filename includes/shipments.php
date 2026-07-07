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
    if (!empty($f['search'])) { $where[] = '(company_name LIKE :q OR contact_name LIKE :q OR phone LIKE :q OR district LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
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
