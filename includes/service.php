<?php
declare(strict_types=1);

/**
 * includes/service.php
 * Servis Kabul / Dış Tamir Takip iş mantığı.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if (!defined('SERVICE_UPLOAD_DIR')) {
    define('SERVICE_UPLOAD_DIR', APP_ROOT . '/uploads/service');
}
if (!defined('SERVICE_MAX_BYTES')) {
    define('SERVICE_MAX_BYTES', 4 * 1024 * 1024);
}

/** Servis durumları (anahtar => etiket), sırası akışa göre. */
function service_statuses(): array
{
    return [
        'new'                 => 'Yeni Kayıt',
        'received'            => 'Cihaz Teslim Alındı',
        'to_repairer'         => 'Dış Tamirciye Gönderilecek',
        'sent_repairer'       => 'Dış Tamirciye Gönderildi',
        'awaiting_cost'       => 'Masraf Bekleniyor',
        'awaiting_approval'   => 'Müşteri Onayı Bekleniyor',
        'approved'            => 'Müşteri Onayladı',
        'rejected'            => 'Müşteri Reddetti',
        'repairing'           => 'Tamirde',
        'repaired'            => 'Tamir Tamamlandı',
        'returned'            => 'Firmaya Geri Geldi',
        'awaiting_payment'    => 'Ödeme Bekliyor',
        'ready'               => 'Teslimata Hazır',
        'delivered'           => 'Müşteriye Teslim Edildi',
        'cancelled'           => 'İptal Edildi',
        'closed'              => 'Süreç Kapandı',
    ];
}

function service_status_label(string $key): string
{
    return service_statuses()[$key] ?? $key;
}

/** Onay durumları. */
function service_approval_labels(): array
{
    return ['pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'];
}

/** Ödeme durumları. */
function service_payment_labels(): array
{
    return ['pending' => 'Ödeme Bekliyor', 'partial' => 'Kısmi Ödendi', 'paid' => 'Ödendi', 'free' => 'Ücretsiz'];
}

/** Ödeme yöntemleri. */
function service_payment_methods(): array
{
    return ['nakit' => 'Nakit', 'kredi_karti' => 'Kredi Kartı', 'havale' => 'Havale / EFT', 'cari' => 'Cari', 'diger' => 'Diğer'];
}

/** Onay yöntemleri. */
function service_approval_methods(): array
{
    return ['telefon' => 'Telefon', 'whatsapp' => 'WhatsApp', 'eposta' => 'E-posta', 'yuz_yuze' => 'Yüz yüze'];
}

/** Cihaz türleri. */
function service_device_types(): array
{
    return ['Yazıcı', 'Fotokopi', 'Tarayıcı', 'Toner / Kartuş', 'Diğer'];
}

/** CSS badge sınıfı (durum). */
function service_status_class(string $key): string
{
    $map = [
        'new' => 'badge-info', 'received' => 'badge-info',
        'to_repairer' => 'badge-leave', 'sent_repairer' => 'badge-leave',
        'awaiting_cost' => 'badge-leave', 'awaiting_approval' => 'badge-leave',
        'approved' => 'badge-success', 'repaired' => 'badge-success',
        'returned' => 'badge-success', 'ready' => 'badge-success',
        'delivered' => 'badge-success', 'closed' => 'badge-muted',
        'rejected' => 'badge-danger', 'cancelled' => 'badge-danger',
        'repairing' => 'badge-info', 'awaiting_payment' => 'badge-leave',
    ];
    return $map[$key] ?? 'badge-muted';
}

/**
 * Benzersiz referans kodu üretir: SRV-YYYY-000001
 */
function service_generate_reference(): string
{
    $year = date('Y');
    try {
        $st = db()->prepare("SELECT reference_code FROM service_records WHERE reference_code LIKE :p ORDER BY id DESC LIMIT 1");
        $st->execute([':p' => 'SRV-' . $year . '-%']);
        $last = (string) ($st->fetchColumn() ?: '');
        $next = 1;
        if ($last !== '' && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        // Çakışmaya karşı garanti
        for ($i = 0; $i < 50; $i++) {
            $code = sprintf('SRV-%s-%06d', $year, $next);
            $c = db()->prepare('SELECT COUNT(*) FROM service_records WHERE reference_code = :c');
            $c->execute([':c' => $code]);
            if ((int) $c->fetchColumn() === 0) {
                return $code;
            }
            $next++;
        }
        return sprintf('SRV-%s-%06d', $year, time() % 1000000);
    } catch (Throwable $e) {
        log_error('service_generate_reference: ' . $e->getMessage());
        return sprintf('SRV-%s-%06d', $year, time() % 1000000);
    }
}

/** Aktif servis koşulu metni. */
function service_active_terms(): ?array
{
    try {
        $row = db()->query('SELECT * FROM service_terms WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
        if ($row) { return $row; }
        $row = db()->query('SELECT * FROM service_terms ORDER BY id DESC LIMIT 1')->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('service_active_terms: ' . $e->getMessage());
        return null;
    }
}

/** Servis koşulunu günceller (tek aktif kayıt). */
function service_update_terms(int $id, string $title, string $content): void
{
    try {
        if ($id > 0) {
            db()->prepare('UPDATE service_terms SET title=:t, content=:c WHERE id=:id')
                ->execute([':t' => $title, ':c' => $content, ':id' => $id]);
        } else {
            db()->prepare('INSERT INTO service_terms (title, content, is_active) VALUES (:t, :c, 1)')
                ->execute([':t' => $title, ':c' => $content]);
        }
    } catch (Throwable $e) {
        log_error('service_update_terms: ' . $e->getMessage());
        throw new RuntimeException('Servis koşulları kaydedilemedi.');
    }
}

/** Firma bilgileri (app_settings). */
function service_company_info(): array
{
    $keys = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_website', 'company_tax'];
    $out = array_fill_keys($keys, '');
    try {
        $in = implode(',', array_fill(0, count($keys), '?'));
        $st = db()->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($in)");
        $st->execute($keys);
        foreach ($st->fetchAll() as $r) {
            $out[$r['setting_key']] = (string) $r['setting_value'];
        }
    } catch (Throwable $e) {
        log_error('service_company_info: ' . $e->getMessage());
    }
    return $out;
}

function service_save_company_info(array $data): void
{
    $keys = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_website', 'company_tax'];
    try {
        $st = db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($keys as $k) {
            $st->execute([':k' => $k, ':v' => trim((string) ($data[$k] ?? ''))]);
        }
    } catch (Throwable $e) {
        log_error('service_save_company_info: ' . $e->getMessage());
        throw new RuntimeException('Firma bilgileri kaydedilemedi.');
    }
}

/* ----------------------- Dış Tamirciler ----------------------- */

function get_repairers(bool $onlyActive = false, string $search = '', string $activeFilter = ''): array
{
    $sql = 'SELECT * FROM external_repairers WHERE 1=1';
    $params = [];
    if ($onlyActive || $activeFilter === 'active') {
        $sql .= ' AND is_active = 1';
    } elseif ($activeFilter === 'passive') {
        $sql .= ' AND is_active = 0';
    }
    if ($search !== '') {
        $sql .= ' AND (name LIKE :q1 OR company_name LIKE :q2 OR specialty LIKE :q3)';
        $params[':q1'] = "%$search%"; $params[':q2'] = "%$search%"; $params[':q3'] = "%$search%";
    }
    $sql .= ' ORDER BY name ASC';
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_repairers: ' . $e->getMessage());
        return [];
    }
}

function get_repairer_by_id(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM external_repairers WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        log_error('get_repairer_by_id: ' . $e->getMessage());
        return null;
    }
}

function get_repairer_options(bool $onlyActive = true): array
{
    $out = [];
    foreach (get_repairers($onlyActive) as $r) {
        $out[(int) $r['id']] = (string) $r['name'] . (!empty($r['company_name']) ? ' — ' . $r['company_name'] : '');
    }
    return $out;
}

function save_repairer(array $d, ?int $id = null): int
{
    $name = trim((string) ($d['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Tamirci adı boş olamaz.');
    }
    $fields = [
        ':n'  => $name,
        ':co' => (trim((string) ($d['company_name'] ?? '')) !== '' ? trim((string) $d['company_name']) : null),
        ':ph' => (trim((string) ($d['phone'] ?? '')) !== '' ? trim((string) $d['phone']) : null),
        ':em' => (trim((string) ($d['email'] ?? '')) !== '' ? trim((string) $d['email']) : null),
        ':ad' => (trim((string) ($d['address'] ?? '')) !== '' ? trim((string) $d['address']) : null),
        ':sp' => (trim((string) ($d['specialty'] ?? '')) !== '' ? trim((string) $d['specialty']) : null),
        ':de' => (trim((string) ($d['description'] ?? '')) !== '' ? trim((string) $d['description']) : null),
        ':ac' => !empty($d['is_active']) ? 1 : 0,
    ];
    try {
        if ($id !== null && $id > 0) {
            $fields[':id'] = $id;
            db()->prepare(
                'UPDATE external_repairers SET name=:n, company_name=:co, phone=:ph, email=:em,
                        address=:ad, specialty=:sp, description=:de, is_active=:ac WHERE id=:id'
            )->execute($fields);
            return $id;
        }
        db()->prepare(
            'INSERT INTO external_repairers (name, company_name, phone, email, address, specialty, description, is_active)
             VALUES (:n, :co, :ph, :em, :ad, :sp, :de, :ac)'
        )->execute($fields);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        log_error('save_repairer: ' . $e->getMessage());
        throw new RuntimeException('Tamirci kaydedilemedi.');
    }
}

function delete_repairer(int $id): void
{
    try {
        db()->prepare('DELETE FROM external_repairers WHERE id = :id')->execute([':id' => $id]);
    } catch (Throwable $e) {
        log_error('delete_repairer: ' . $e->getMessage());
        throw new RuntimeException('Tamirci silinemedi.');
    }
}

/* ----------------------- Servis Kayıtları ----------------------- */

/**
 * Liste (filtreli). $f: ref, customer, phone, brand, status, approval, payment, repairer, date_from, date_to.
 */
function get_service_records(array $f = []): array
{
    $sql = 'SELECT s.*, r.name AS repairer_name
            FROM service_records s
            LEFT JOIN external_repairers r ON r.id = s.external_repairer_id
            WHERE 1=1 AND s.is_deleted = 0';
    $p = [];
    if (!empty($f['ref'])) { $sql .= ' AND s.reference_code LIKE :ref'; $p[':ref'] = '%' . $f['ref'] . '%'; }
    if (!empty($f['customer'])) { $sql .= ' AND s.customer_name LIKE :cn'; $p[':cn'] = '%' . $f['customer'] . '%'; }
    if (!empty($f['phone'])) { $sql .= ' AND s.customer_phone LIKE :ph'; $p[':ph'] = '%' . $f['phone'] . '%'; }
    if (!empty($f['brand'])) { $sql .= ' AND s.brand_name LIKE :br'; $p[':br'] = '%' . $f['brand'] . '%'; }
    if (!empty($f['status']) && array_key_exists($f['status'], service_statuses())) { $sql .= ' AND s.status = :st'; $p[':st'] = $f['status']; }
    if (!empty($f['approval']) && array_key_exists($f['approval'], service_approval_labels())) { $sql .= ' AND s.approval_status = :ap'; $p[':ap'] = $f['approval']; }
    if (!empty($f['payment']) && array_key_exists($f['payment'], service_payment_labels())) { $sql .= ' AND s.payment_status = :pm'; $p[':pm'] = $f['payment']; }
    if (!empty($f['repairer'])) { $sql .= ' AND s.external_repairer_id = :rid'; $p[':rid'] = (int) $f['repairer']; }
    if (!empty($f['date_from'])) { $sql .= ' AND DATE(s.created_at) >= :df'; $p[':df'] = $f['date_from']; }
    if (!empty($f['date_to'])) { $sql .= ' AND DATE(s.created_at) <= :dt'; $p[':dt'] = $f['date_to']; }
    $sql .= ' ORDER BY s.id DESC';
    try {
        $st = db()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_service_records: ' . $e->getMessage());
        return [];
    }
}

function get_service_record(int $id): ?array
{
    try {
        $st = db()->prepare(
            'SELECT s.*, r.name AS repairer_name, r.phone AS repairer_phone
             FROM service_records s
             LEFT JOIN external_repairers r ON r.id = s.external_repairer_id
             WHERE s.id = :id AND s.is_deleted = 0 LIMIT 1'
        );
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        log_error('get_service_record: ' . $e->getMessage());
        return null;
    }
}

function get_service_record_by_ref(string $ref): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM service_records WHERE reference_code = :r LIMIT 1');
        $st->execute([':r' => $ref]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        log_error('get_service_record_by_ref: ' . $e->getMessage());
        return null;
    }
}

/** Durum geçmişi kaydı ekler. */
function service_log_status(int $recordId, ?string $old, string $new, ?string $note = null): void
{
    try {
        db()->prepare(
            'INSERT INTO service_status_history (service_record_id, old_status, new_status, note, changed_by_user_id)
             VALUES (:rid, :o, :n, :note, :uid)'
        )->execute([
            ':rid' => $recordId, ':o' => $old, ':n' => $new, ':note' => $note,
            ':uid' => (function_exists('current_user_id') ? current_user_id() : null),
        ]);
    } catch (Throwable $e) {
        log_error('service_log_status: ' . $e->getMessage());
    }
}

function get_service_history(int $recordId): array
{
    try {
        $st = db()->prepare(
            'SELECT h.*, u.username AS by_username
             FROM service_status_history h
             LEFT JOIN users u ON u.id = h.changed_by_user_id
             WHERE h.service_record_id = :rid ORDER BY h.id DESC'
        );
        $st->execute([':rid' => $recordId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_service_history: ' . $e->getMessage());
        return [];
    }
}

/** Güvenli cihaz fotoğrafı yükleme. */
function service_upload_photo(array $file, ?string &$error = null): ?string
{
    $error = null;
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) { return null; }
    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Dosya yüklenemedi.'; return null; }
    if (($file['size'] ?? 0) > SERVICE_MAX_BYTES) { $error = 'Fotoğraf en fazla 4 MB olabilir.'; return null; }
    if (!is_uploaded_file($file['tmp_name'])) { $error = 'Geçersiz yükleme.'; return null; }
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) { $error = 'Yalnızca PNG, JPG/JPEG veya WEBP.'; return null; }
    if (@getimagesize($file['tmp_name']) === false) { $error = 'Geçerli bir görsel değil.'; return null; }
    if (!is_dir(SERVICE_UPLOAD_DIR)) { @mkdir(SERVICE_UPLOAD_DIR, 0775, true); }
    if (!is_writable(SERVICE_UPLOAD_DIR)) { $error = 'Yükleme klasörü yazılabilir değil (uploads/service).'; return null; }
    $name = 'srv_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], SERVICE_UPLOAD_DIR . '/' . $name)) { $error = 'Dosya kaydedilemedi.'; return null; }
    @chmod(SERVICE_UPLOAD_DIR . '/' . $name, 0644);
    return 'uploads/service/' . $name;
}

function service_delete_photo(?string $rel): void
{
    if (!$rel) { return; }
    $rel = ltrim($rel, '/');
    if (strpos($rel, 'uploads/service/') !== 0) { return; }
    $full = APP_ROOT . '/' . $rel;
    if (is_file($full)) { @unlink($full); }
}

/**
 * $data'dan servis kaydı alanlarını normalize eder (create+update ortak).
 * profit = customer_price - repairer_cost (ikisi de doluysa).
 */
function service_normalize(array $d): array
{
    $repCost = ($d['repairer_cost'] ?? '') !== '' ? (float) str_replace(',', '.', (string) $d['repairer_cost']) : null;
    $custPr  = ($d['customer_price'] ?? '') !== '' ? (float) str_replace(',', '.', (string) $d['customer_price']) : null;
    $profit  = ($repCost !== null && $custPr !== null) ? round($custPr - $repCost, 2) : null;

    $status = (string) ($d['status'] ?? 'new');
    if (!array_key_exists($status, service_statuses())) { $status = 'new'; }

    $brandId = (int) ($d['brand_id'] ?? 0);

    return [
        'customer_name'     => trim((string) ($d['customer_name'] ?? '')),
        'customer_phone'    => (trim((string) ($d['customer_phone'] ?? '')) !== '' ? trim((string) $d['customer_phone']) : null),
        'customer_email'    => (trim((string) ($d['customer_email'] ?? '')) !== '' ? trim((string) $d['customer_email']) : null),
        'customer_address'  => (trim((string) ($d['customer_address'] ?? '')) !== '' ? trim((string) $d['customer_address']) : null),
        'customer_tax_no'   => (trim((string) ($d['customer_tax_no'] ?? '')) !== '' ? trim((string) $d['customer_tax_no']) : null),
        'device_type'       => (trim((string) ($d['device_type'] ?? '')) !== '' ? trim((string) $d['device_type']) : null),
        'brand_id'          => $brandId > 0 ? $brandId : null,
        'brand_name'        => (trim((string) ($d['brand_name'] ?? '')) !== '' ? trim((string) $d['brand_name']) : null),
        'device_model'      => (trim((string) ($d['device_model'] ?? '')) !== '' ? trim((string) $d['device_model']) : null),
        'quantity'          => max(1, (int) ($d['quantity'] ?? 1)),
        'serial_no'         => (trim((string) ($d['serial_no'] ?? '')) !== '' ? trim((string) $d['serial_no']) : null),
        'accessories'       => (trim((string) ($d['accessories'] ?? '')) !== '' ? trim((string) $d['accessories']) : null),
        'problem_description' => (trim((string) ($d['problem_description'] ?? '')) !== '' ? trim((string) $d['problem_description']) : null),
        'physical_condition' => (trim((string) ($d['physical_condition'] ?? '')) !== '' ? trim((string) $d['physical_condition']) : null),
        'received_at'       => (($d['received_at'] ?? '') !== '' && strtotime((string) $d['received_at'])) ? date('Y-m-d', (int) strtotime((string) $d['received_at'])) : date('Y-m-d'),
        'received_by_personnel_id' => (int) ($d['received_by_personnel_id'] ?? 0) ?: null,
        'external_repairer_id' => (int) ($d['external_repairer_id'] ?? 0) ?: null,
        'sent_to_repairer_at' => (($d['sent_to_repairer_at'] ?? '') !== '' && strtotime((string) $d['sent_to_repairer_at'])) ? date('Y-m-d', (int) strtotime((string) $d['sent_to_repairer_at'])) : null,
        'returned_from_repairer_at' => (($d['returned_from_repairer_at'] ?? '') !== '' && strtotime((string) $d['returned_from_repairer_at'])) ? date('Y-m-d', (int) strtotime((string) $d['returned_from_repairer_at'])) : null,
        'repairer_cost'     => $repCost,
        'customer_price'    => $custPr,
        'profit_amount'     => $profit,
        'approval_required' => !empty($d['approval_required']) ? 1 : 0,
        'status'            => $status,
        'is_digital_approved' => !empty($d['is_digital_approved']) ? 1 : 0,
        'final_note'        => (trim((string) ($d['final_note'] ?? '')) !== '' ? trim((string) $d['final_note']) : null),
        'internal_note'     => (trim((string) ($d['internal_note'] ?? '')) !== '' ? trim((string) $d['internal_note']) : null),
        'customer_public_note' => (trim((string) ($d['customer_public_note'] ?? '')) !== '' ? trim((string) $d['customer_public_note']) : null),
        'show_price_public' => !empty($d['show_price_public']) ? 1 : 0,
    ];
}

/** Yeni servis kaydı. Dönen: [id, reference_code]. */
function create_service_record(array $d, ?array $file = null): array
{
    $f = service_normalize($d);
    if ($f['customer_name'] === '') {
        throw new InvalidArgumentException('Teslim eden ad/ünvan boş olamaz.');
    }
    $f['reference_code'] = service_generate_reference();
    $f['public_token'] = service_make_token();
    if (!empty($d['is_digital_approved'])) {
        $f['digital_approved_at'] = date('Y-m-d H:i:s');
    }

    $photo = null;
    if ($file !== null) {
        $err = null;
        $photo = service_upload_photo($file, $err);
        if ($err !== null) { throw new RuntimeException($err); }
    }
    $f['received_photo_path'] = $photo;
    $f['received_by_user_id'] = function_exists('current_user_id') ? current_user_id() : null;

    try {
        $cols = array_keys($f);
        $ph = array_map(fn ($c) => ':' . $c, $cols);
        $bind = [];
        foreach ($f as $k => $v) { $bind[':' . $k] = $v; }
        db()->prepare('INSERT INTO service_records (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')')
            ->execute($bind);
        $id = (int) db()->lastInsertId();
        service_log_status($id, null, $f['status'], 'Kayıt oluşturuldu');
        return ['id' => $id, 'reference_code' => $f['reference_code']];
    } catch (Throwable $e) {
        service_delete_photo($photo);
        log_error('create_service_record: ' . $e->getMessage());
        throw new RuntimeException('Servis kaydı oluşturulamadı.');
    }
}

/** Servis kaydını günceller (durum değişimini de tarihçeye yazar). */
function update_service_record(int $id, array $d, ?array $file = null): void
{
    $current = get_service_record($id);
    if (!$current) { throw new RuntimeException('Kayıt bulunamadı.'); }
    $f = service_normalize($d);
    if ($f['customer_name'] === '') {
        throw new InvalidArgumentException('Teslim eden ad/ünvan boş olamaz.');
    }

    // Müşteri onayı olmadan "Tamirde" durumuna geçilemez
    if ($f['status'] === 'repairing' && $current['approval_status'] !== 'approved' && (int) $f['approval_required'] === 1) {
        throw new RuntimeException('Müşteri onayı alınmadan kayıt “Tamirde” durumuna geçemez.');
    }

    $newPhoto = null;
    if ($file !== null) {
        $err = null;
        $newPhoto = service_upload_photo($file, $err);
        if ($err !== null) { throw new RuntimeException($err); }
    }

    try {
        $photoPath = $current['received_photo_path'];
        if ($newPhoto !== null) {
            service_delete_photo($current['received_photo_path']);
            $photoPath = $newPhoto;
        } elseif (!empty($d['remove_photo'])) {
            service_delete_photo($current['received_photo_path']);
            $photoPath = null;
        }
        $f['received_photo_path'] = $photoPath;

        // dijital onay yeni işaretlendiyse tarih koy
        if ((int) $f['is_digital_approved'] === 1 && (int) $current['is_digital_approved'] === 0) {
            $f['digital_approved_at'] = date('Y-m-d H:i:s');
        }

        $sets = [];
        $bind = [':id' => $id];
        foreach ($f as $k => $v) { $sets[] = "$k = :$k"; $bind[':' . $k] = $v; }
        db()->prepare('UPDATE service_records SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($bind);

        if ($current['status'] !== $f['status']) {
            service_log_status($id, $current['status'], $f['status'], 'Kayıt güncellendi');
            // Düzenleme formundan durum değiştiyse bakım planını da senkronla.
            // Not: bu yol delivered_to_customer_at / closed_at damgalamaz; hook
            // taze kaydı okuyup mevcut teslim tarihine göre karar verir.
            try {
                require_once __DIR__ . '/service_maintenance.php';
                smaint_sync_service_reminder($id, (string) $current['status'], (string) $f['status'], current_user_id());
            } catch (Throwable $e2) {
                log_error('smaint sync (update_record): ' . $e2->getMessage());
            }
        }
    } catch (Throwable $e) {
        service_delete_photo($newPhoto);
        log_error('update_service_record: ' . $e->getMessage());
        throw new RuntimeException($e instanceof RuntimeException ? $e->getMessage() : 'Kayıt güncellenemedi.');
    }
}

/** Yalnızca durum değiştirir + tarihçe. */
function service_change_status(int $id, string $newStatus, ?string $note = null): void
{
    $rec = get_service_record($id);
    if (!$rec) { throw new RuntimeException('Kayıt bulunamadı.'); }
    if (!array_key_exists($newStatus, service_statuses())) { throw new InvalidArgumentException('Geçersiz durum.'); }
    if ($newStatus === 'repairing' && $rec['approval_status'] !== 'approved' && (int) $rec['approval_required'] === 1) {
        throw new RuntimeException('Müşteri onayı alınmadan “Tamirde” durumuna geçilemez.');
    }
    try {
        $extra = '';
        $bind = [':s' => $newStatus, ':id' => $id];
        if ($newStatus === 'delivered') { $extra = ', delivered_to_customer_at = NOW()'; }
        if ($newStatus === 'closed') { $extra = ', closed_at = NOW()'; }
        db()->prepare("UPDATE service_records SET status = :s$extra WHERE id = :id")->execute($bind);
        service_log_status($id, $rec['status'], $newStatus, $note);
        // Bakım hatırlatma senkronizasyonu (teslim/kapatma → plan; geri açılma → pasif).
        // Servis akışını kırmaması için ayrı try/catch.
        try {
            require_once __DIR__ . '/service_maintenance.php';
            smaint_sync_service_reminder($id, (string) $rec['status'], $newStatus, current_user_id());
        } catch (Throwable $e2) {
            log_error('smaint sync (change_status): ' . $e2->getMessage());
        }
    } catch (Throwable $e) {
        log_error('service_change_status: ' . $e->getMessage());
        throw new RuntimeException('Durum güncellenemedi.');
    }
}

/** Müşteri onayı kaydı. */
function service_set_approval(int $id, string $status, ?string $method, ?string $note): void
{
    if (!array_key_exists($status, service_approval_labels())) { throw new InvalidArgumentException('Geçersiz onay durumu.'); }
    $rec = get_service_record($id);
    if (!$rec) { throw new RuntimeException('Kayıt bulunamadı.'); }
    try {
        db()->prepare(
            'UPDATE service_records SET approval_status=:st, approval_method=:m, approval_note=:n,
                    approval_at=NOW(), approval_by_user_id=:uid WHERE id=:id'
        )->execute([
            ':st' => $status, ':m' => $method ?: null, ':n' => $note ?: null,
            ':uid' => function_exists('current_user_id') ? current_user_id() : null, ':id' => $id,
        ]);
        // Onay durumuna göre servis durumunu ilerlet
        if ($status === 'approved' && $rec['status'] !== 'closed') {
            service_change_status($id, 'approved', 'Müşteri onayı alındı');
        } elseif ($status === 'rejected') {
            service_change_status($id, 'rejected', 'Müşteri onayı reddedildi');
        }
    } catch (Throwable $e) {
        log_error('service_set_approval: ' . $e->getMessage());
        throw new RuntimeException('Onay kaydedilemedi.');
    }
}

/** Ödeme kaydı. */
function service_set_payment(int $id, string $status, ?string $method, ?float $amount, ?string $note): void
{
    if (!array_key_exists($status, service_payment_labels())) { throw new InvalidArgumentException('Geçersiz ödeme durumu.'); }
    try {
        db()->prepare(
            'UPDATE service_records SET payment_status=:st, payment_method=:m, paid_amount=:a,
                    paid_at=NOW(), payment_received_by_user_id=:uid, final_note=COALESCE(:note, final_note) WHERE id=:id'
        )->execute([
            ':st' => $status, ':m' => $method ?: null, ':a' => $amount,
            ':uid' => function_exists('current_user_id') ? current_user_id() : null,
            ':note' => ($note !== null && $note !== '' ? $note : null), ':id' => $id,
        ]);
    } catch (Throwable $e) {
        log_error('service_set_payment: ' . $e->getMessage());
        throw new RuntimeException('Ödeme kaydedilemedi.');
    }
}

/**
 * Servis kaydını ARŞİVLER (soft delete). Bağlı veriler korunur; kayıt listeden
 * ve public takipten kalkar. Transaction + denetim logu kullanır.
 */
function delete_service_record(int $id, ?int $userId = null): void
{
    if ($id <= 0) { throw new RuntimeException('Geçersiz kayıt.'); }
    $rec = get_service_record($id); // yalnızca is_deleted=0 kaydı bulur
    if (!$rec) { throw new RuntimeException('Kayıt bulunamadı veya zaten arşivlenmiş.'); }
    $ref = (string) ($rec['reference_code'] ?? '');
    $uid = $userId ?? (function_exists('current_user_id') ? current_user_id() : null);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            'UPDATE service_records
                SET is_deleted = 1, deleted_at = NOW(), deleted_by_user_id = :uid
              WHERE id = :id AND is_deleted = 0'
        );
        $st->execute([':uid' => $uid, ':id' => $id]);
        if ($st->rowCount() < 1) {
            $pdo->rollBack();
            throw new RuntimeException('Kayıt güncellenemedi.');
        }
        $pdo->commit();
        log_activity('service_record_delete_success', 'service', $id, $ref, 'success');
        // Silinen servis için bakım planlarını pasife al (§18).
        try {
            require_once __DIR__ . '/service_maintenance.php';
            smaint_on_service_deleted($id, $uid);
        } catch (Throwable $e2) {
            log_error('smaint on_service_deleted: ' . $e2->getMessage());
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        log_error('delete_service_record: ' . $e->getMessage());
        log_activity('service_record_delete_failed', 'service', $id, $ref, 'failed', $e->getMessage());
        throw new RuntimeException('Kayıt silinemedi.');
    }
}

/** WhatsApp linki üretir (wa.me). */
function service_whatsapp_link(?string $phone, string $message): ?string
{
    if (!$phone) { return null; }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') { return null; }
    // Türkiye: 0 ile başlıyorsa 90 ekle
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = '90' . substr($digits, 1);
    } elseif (strlen($digits) === 10) {
        $digits = '90' . $digits;
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

/** Hazır WhatsApp mesajları. */
function service_wa_messages(array $rec): array
{
    $ref = (string) $rec['reference_code'];
    $brand = (string) ($rec['brand_name'] ?? '');
    $model = (string) ($rec['device_model'] ?? '');
    $price = $rec['customer_price'] !== null ? fmt_money((float) $rec['customer_price']) . ' TL' : '-';
    $status = service_status_label((string) $rec['status']);
    return [
        'opened'  => "Merhaba, cihazınız servis kabul sürecine alınmıştır.\nReferans No: $ref\nDurum: $status\nPoyraz Toner",
        'cost'    => "Merhaba, $brand $model cihazınız için servis masrafı $price olarak bildirilmiştir.\nOnay vermeniz halinde işlem başlatılacaktır.\nReferans No: $ref",
        'ready'   => "Merhaba, $ref referans numaralı cihazınız teslimata hazırdır.\nLütfen teslim almak için bizimle iletişime geçiniz.",
    ];
}

/* ----------------------- Public Takip ----------------------- */

/** Güvenli public token üretir (64 hex karakter). */
function service_make_token(): string
{
    try {
        return bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        return hash('sha256', uniqid('srv', true) . mt_rand());
    }
}

/** Public takip URL'i (kök dizindeki servis-takip.php). */
function service_public_url(array $rec): ?string
{
    if (empty($rec['public_token']) || empty($rec['reference_code'])) {
        return null;
    }
    $base = rtrim(base_url(), '/');
    return $base . '/servis-takip.php?code=' . rawurlencode((string) $rec['reference_code'])
         . '&token=' . rawurlencode((string) $rec['public_token']);
}

/** Token yeniler (eski link geçersiz olur). */
function service_regenerate_token(int $id): string
{
    $token = service_make_token();
    try {
        db()->prepare('UPDATE service_records SET public_token = :t WHERE id = :id')
            ->execute([':t' => $token, ':id' => $id]);
    } catch (Throwable $e) {
        log_error('service_regenerate_token: ' . $e->getMessage());
        throw new RuntimeException('Takip linki yenilenemedi.');
    }
    return $token;
}

/** Public takibi açar/kapatır. */
function service_toggle_public(int $id, bool $enabled): void
{
    try {
        db()->prepare('UPDATE service_records SET public_tracking_enabled = :e WHERE id = :id')
            ->execute([':e' => $enabled ? 1 : 0, ':id' => $id]);
    } catch (Throwable $e) {
        log_error('service_toggle_public: ' . $e->getMessage());
        throw new RuntimeException('Ayar güncellenemedi.');
    }
}

/** Public erişim: code + token doğrularsa kaydı döndürür (kapalıysa null). */
function service_get_public(string $code, string $token): ?array
{
    if ($code === '' || $token === '') { return null; }
    try {
        $st = db()->prepare('SELECT * FROM service_records WHERE reference_code = :c AND is_deleted = 0 LIMIT 1');
        $st->execute([':c' => $code]);
        $rec = $st->fetch();
        if (!$rec) { return null; }
        if ((int) ($rec['public_tracking_enabled'] ?? 0) !== 1) { return null; }
        if (!hash_equals((string) ($rec['public_token'] ?? ''), $token)) { return null; }
        return $rec;
    } catch (Throwable $e) {
        log_error('service_get_public: ' . $e->getMessage());
        return null;
    }
}

/** Public durum geçmişi (yalnızca is_public=1). */
function get_service_public_history(int $recordId): array
{
    try {
        $st = db()->prepare(
            'SELECT old_status, new_status, note, created_at
             FROM service_status_history
             WHERE service_record_id = :rid AND is_public = 1
             ORDER BY id ASC'
        );
        $st->execute([':rid' => $recordId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_service_public_history: ' . $e->getMessage());
        return [];
    }
}

/** Takip linki WhatsApp mesajı. */
function service_tracking_wa(array $rec): string
{
    $url = service_public_url($rec) ?? '';
    return "Merhaba, servis sürecinizi aşağıdaki linkten takip edebilirsiniz.\n\n"
         . 'Referans No: ' . (string) $rec['reference_code'] . "\n"
         . 'Takip Linki: ' . $url . "\n\nPoyraz Toner";
}
