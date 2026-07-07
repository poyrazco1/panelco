<?php
declare(strict_types=1);

/**
 * includes/personnel.php
 * Personel yönetimi iş mantığı. Panel kullanıcılarından (users) ayrıdır;
 * opsiyonel user_id ile bir panel hesabına bağlanabilir.
 * İleride teknik servis / satış / depo modüllerinde yeniden kullanılabilir.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if (!defined('PERSONNEL_UPLOAD_DIR')) {
    define('PERSONNEL_UPLOAD_DIR', APP_ROOT . '/uploads/personnel');
}
if (!defined('PERSONNEL_MAX_BYTES')) {
    define('PERSONNEL_MAX_BYTES', 2 * 1024 * 1024);
}

/**
 * Şema kendini onarır (idempotent).
 *
 * Puantaj / İK güncellemesiyle personnel tablosuna `birth_date` ve
 * `show_birthday_notifications` kolonları eklendi. Bu kolonlar eklenmeden
 * güncellenen kurulumlarda personel kaydı sırasında
 * "Unknown column 'birth_date'" hatası oluşur ve kullanıcıya
 * "Personel oluşturulamadı." olarak yansır.
 *
 * install.sql'deki "ADD COLUMN IF NOT EXISTS" yalnızca MariaDB'de geçerlidir;
 * bu fonksiyon INFORMATION_SCHEMA üzerinden hem MySQL hem MariaDB'de eksik
 * kolonları güvenle tamamlar. İstek başına en fazla bir kez çalışır.
 */
function personnel_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // kolon adı => eksikse eklenecek ALTER parçası (taşınabilir söz dizimi)
    $needed = [
        'birth_date'                  => 'ADD COLUMN `birth_date` DATE DEFAULT NULL',
        'show_birthday_notifications' => 'ADD COLUMN `show_birthday_notifications` TINYINT(1) NOT NULL DEFAULT 1',
    ];

    try {
        $st = db()->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $st->execute([':t' => 'personnel']);

        $have = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $have[strtolower((string) $col)] = true;
        }

        $missing = [];
        foreach ($needed as $col => $clause) {
            if (!isset($have[$col])) {
                $missing[$col] = $clause;
            }
        }

        if ($missing) {
            db()->exec('ALTER TABLE `personnel` ' . implode(', ', $missing));
            log_error('personnel_ensure_schema: eksik kolon(lar) eklendi: ' . implode(', ', array_keys($missing)));
        }
    } catch (Throwable $e) {
        // Onarım başarısız olsa bile akışı durdurma; asıl işlem anlamlı hata verir.
        log_error('personnel_ensure_schema: ' . $e->getMessage());
    }
}

/**
 * Çalışma durumu etiketleri.
 */
function personnel_status_labels(): array
{
    return [
        'active'   => 'Aktif',
        'passive'  => 'Pasif',
        'on_leave' => 'İzinli',
        'left'     => 'Ayrıldı',
    ];
}

/**
 * Varsayılan departman seçenekleri.
 */
function personnel_departments(): array
{
    return [
        'Yönetim', 'Muhasebe', 'Satış', 'Müşteri Hizmetleri', 'Teknik Servis',
        'Depo', 'Satın Alma', 'İhracat', 'E-Ticaret', 'Yazılım', 'Operasyon',
        'İnsan Kaynakları',
    ];
}

/**
 * Personel listesi. $filters: search, department, status, active, order.
 * Her satıra bağlı panel kullanıcısı bilgisi (u_username, u_email, u_role) eklenir.
 */
function get_personnel(array $filters = []): array
{
    $sql = 'SELECT p.*, u.username AS u_username, u.email AS u_email, r.name AS u_role
            FROM personnel p
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE 1=1';
    $params = [];

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $sql .= ' AND (p.full_name LIKE :q1 OR p.phone LIKE :q2 OR p.email LIKE :q3
                       OR p.department LIKE :q4 OR p.position LIKE :q5 OR p.personnel_code LIKE :q6)';
        $like = '%' . $search . '%';
        $params[':q1'] = $like; $params[':q2'] = $like; $params[':q3'] = $like;
        $params[':q4'] = $like; $params[':q5'] = $like; $params[':q6'] = $like;
    }
    if (!empty($filters['department'])) {
        $sql .= ' AND p.department = :dep';
        $params[':dep'] = (string) $filters['department'];
    }
    if (!empty($filters['status']) && array_key_exists($filters['status'], personnel_status_labels())) {
        $sql .= ' AND p.employment_status = :st';
        $params[':st'] = (string) $filters['status'];
    }
    $active = (string) ($filters['active'] ?? '');
    if ($active === 'active') {
        $sql .= ' AND p.is_active = 1';
    } elseif ($active === 'passive') {
        $sql .= ' AND p.is_active = 0';
    }

    $order = (string) ($filters['order'] ?? 'sort');
    $sql .= ($order === 'name') ? ' ORDER BY p.full_name ASC' : ' ORDER BY p.sort_order ASC, p.id ASC';

    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_personnel: ' . $e->getMessage());
        return [];
    }
}

/**
 * Yalnızca aktif personel.
 */
function get_active_personnel(): array
{
    return get_personnel(['active' => 'active']);
}

/**
 * Tek personel (id).
 */
function get_personnel_by_id(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM personnel WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('get_personnel_by_id: ' . $e->getMessage());
        return null;
    }
}

/**
 * Panel kullanıcısına bağlı personel (user_id).
 */
function get_personnel_by_user_id(int $userId): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM personnel WHERE user_id = :uid LIMIT 1');
        $st->execute([':uid' => $userId]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('get_personnel_by_user_id: ' . $e->getMessage());
        return null;
    }
}

/**
 * Dropdown için [id => 'Ad Soyad'].
 */
function get_personnel_options(bool $onlyActive = true): array
{
    $out = [];
    $list = $onlyActive ? get_active_personnel() : get_personnel();
    foreach ($list as $p) {
        $out[(int) $p['id']] = (string) $p['full_name'];
    }
    return $out;
}

/**
 * Panel kullanıcısı seçenekleri (personel formunda "kullanıcıyla bağla").
 */
function personnel_user_options(): array
{
    try {
        return db()->query('SELECT id, username, email FROM users ORDER BY username ASC')->fetchAll();
    } catch (Throwable $e) {
        log_error('personnel_user_options: ' . $e->getMessage());
        return [];
    }
}

/**
 * Personel kodu benzersiz mi? (boş kod serbest)
 */
function personnel_code_exists(string $code, ?int $excludeId = null): bool
{
    if ($code === '') {
        return false;
    }
    try {
        $sql = 'SELECT COUNT(*) FROM personnel WHERE personnel_code = :c' . ($excludeId !== null ? ' AND id <> :id' : '');
        $p = [':c' => $code];
        if ($excludeId !== null) { $p[':id'] = $excludeId; }
        $st = db()->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        log_error('personnel_code_exists: ' . $e->getMessage());
        return false;
    }
}

/**
 * Bu user_id başka personele bağlı mı?
 */
function personnel_user_taken(int $userId, ?int $excludeId = null): bool
{
    if ($userId <= 0) {
        return false;
    }
    try {
        $sql = 'SELECT COUNT(*) FROM personnel WHERE user_id = :uid' . ($excludeId !== null ? ' AND id <> :id' : '');
        $p = [':uid' => $userId];
        if ($excludeId !== null) { $p[':id'] = $excludeId; }
        $st = db()->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        log_error('personnel_user_taken: ' . $e->getMessage());
        return false;
    }
}

/**
 * E-posta zaten başka personelde var mı? (uyarı amaçlı; boş serbest)
 */
function personnel_email_exists(string $email, ?int $excludeId = null): bool
{
    if ($email === '') {
        return false;
    }
    try {
        $sql = 'SELECT COUNT(*) FROM personnel WHERE email = :e' . ($excludeId !== null ? ' AND id <> :id' : '');
        $p = [':e' => $email];
        if ($excludeId !== null) { $p[':id'] = $excludeId; }
        $st = db()->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        log_error('personnel_email_exists: ' . $e->getMessage());
        return false;
    }
}

/**
 * Telefon güvenli temizleme (rakam, +, boşluk, - ve parantez).
 */
function personnel_clean_phone(string $phone): string
{
    $p = preg_replace('/[^0-9+\-\s()]/', '', trim($phone)) ?? '';
    return substr($p, 0, 40);
}

/**
 * Güvenli fotoğraf yükleme.
 */
function personnel_upload_photo(array $file, ?string &$error = null): ?string
{
    $error = null;
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Dosya yüklenemedi (hata kodu ' . (int) $file['error'] . ').';
        return null;
    }
    if (($file['size'] ?? 0) > PERSONNEL_MAX_BYTES) {
        $error = 'Fotoğraf en fazla 2 MB olabilir.';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Geçersiz yükleme.';
        return null;
    }
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        $error = 'Yalnızca PNG, JPG/JPEG veya WEBP yükleyebilirsiniz.';
        return null;
    }
    if (@getimagesize($file['tmp_name']) === false) {
        $error = 'Dosya geçerli bir görsel değil.';
        return null;
    }
    if (!is_dir(PERSONNEL_UPLOAD_DIR)) {
        @mkdir(PERSONNEL_UPLOAD_DIR, 0775, true);
    }
    if (!is_writable(PERSONNEL_UPLOAD_DIR)) {
        $error = 'Yükleme klasörü yazılabilir değil (uploads/personnel izinlerini kontrol edin).';
        log_error('personnel upload: klasör yazılamıyor: ' . PERSONNEL_UPLOAD_DIR);
        return null;
    }
    $ext  = $allowed[$mime];
    $name = 'staff_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = PERSONNEL_UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = 'Dosya kaydedilemedi.';
        return null;
    }
    @chmod($dest, 0644);
    return 'uploads/personnel/' . $name;
}

/**
 * Fotoğrafı diskten siler (yalnızca uploads/personnel/ altındaysa).
 */
function personnel_delete_photo(?string $relPath): void
{
    if (!$relPath) {
        return;
    }
    $rel = ltrim($relPath, '/');
    if (strpos($rel, 'uploads/personnel/') !== 0) {
        return;
    }
    $full = APP_ROOT . '/' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * $data'dan normalize edilmiş kayıt alanları + doğrulama üretir.
 * Dönen: ['errors'=>[], 'fields'=>[...]]
 */
function personnel_prepare(array $data, ?int $excludeId = null): array
{
    $errors = [];

    $first = trim((string) ($data['first_name'] ?? ''));
    $last  = trim((string) ($data['last_name'] ?? ''));
    if ($first === '') {
        $errors[] = 'Ad boş olamaz.';
    }
    $full = trim($first . ' ' . $last);
    if ($full === '') {
        $full = $first;
    }

    $code = trim((string) ($data['personnel_code'] ?? ''));
    if ($code !== '' && personnel_code_exists($code, $excludeId)) {
        $errors[] = 'Bu personel kodu zaten kullanılıyor.';
    }

    $email = trim((string) ($data['email'] ?? ''));
    if ($email !== '' && !is_valid_email($email)) {
        $errors[] = 'Geçerli bir e-posta girin.';
    } elseif ($email !== '' && personnel_email_exists($email, $excludeId)) {
        $errors[] = 'Uyarı: Bu e-posta başka bir personelde de kayıtlı.';
    }

    $status = (string) ($data['employment_status'] ?? 'active');
    if (!array_key_exists($status, personnel_status_labels())) {
        $status = 'active';
    }

    $hire = trim((string) ($data['hire_date'] ?? ''));
    $term = trim((string) ($data['termination_date'] ?? ''));
    $hire = ($hire !== '' && strtotime($hire)) ? date('Y-m-d', (int) strtotime($hire)) : null;
    $term = ($term !== '' && strtotime($term)) ? date('Y-m-d', (int) strtotime($term)) : null;
    if ($hire !== null && $term !== null && $term < $hire) {
        $errors[] = 'İşten çıkış tarihi, işe giriş tarihinden önce olamaz.';
    }

    $sortRaw = trim((string) ($data['sort_order'] ?? '0'));
    if ($sortRaw !== '' && !preg_match('/^-?\d+$/', $sortRaw)) {
        $errors[] = 'Sıra no bir sayı olmalıdır.';
    }

    // user_id opsiyonel; başka personelde kullanılmışsa uyar
    $userId = (int) ($data['user_id'] ?? 0);
    if ($userId > 0 && personnel_user_taken($userId, $excludeId)) {
        $errors[] = 'Bu panel kullanıcısı zaten başka bir personele bağlı.';
    }

    $fields = [
        'user_id'           => $userId > 0 ? $userId : null,
        'first_name'        => $first,
        'last_name'         => ($last !== '' ? $last : null),
        'full_name'         => $full,
        'personnel_code'    => ($code !== '' ? $code : null),
        'department'        => (trim((string) ($data['department'] ?? '')) !== '' ? trim((string) $data['department']) : null),
        'position'          => (trim((string) ($data['position'] ?? '')) !== '' ? trim((string) $data['position']) : null),
        'phone'             => personnel_clean_phone((string) ($data['phone'] ?? '')) ?: null,
        'internal_phone'    => personnel_clean_phone((string) ($data['internal_phone'] ?? '')) ?: null,
        'email'             => ($email !== '' ? $email : null),
        'whatsapp'          => personnel_clean_phone((string) ($data['whatsapp'] ?? '')) ?: null,
        'birth_date'        => ((trim((string) ($data['birth_date'] ?? '')) !== '' && strtotime((string) $data['birth_date'])) ? date('Y-m-d', (int) strtotime((string) $data['birth_date'])) : null),
        'show_birthday_notifications' => !empty($data['show_birthday_notifications']) ? 1 : 0,
        'hire_date'         => $hire,
        'termination_date'  => $term,
        'employment_status' => $status,
        'description'       => (trim((string) ($data['description'] ?? '')) !== '' ? trim((string) $data['description']) : null),
        'sort_order'        => (int) $sortRaw,
        'is_active'         => !empty($data['is_active']) ? 1 : 0,
    ];

    return ['errors' => $errors, 'fields' => $fields];
}

/**
 * Personel oluşturur. Dönen: yeni id. Hata olursa RuntimeException.
 */
function create_personnel(array $data, ?array $file = null): int
{
    personnel_ensure_schema();
    $prep = personnel_prepare($data);
    if ($prep['errors']) {
        throw new RuntimeException(implode(' ', $prep['errors']));
    }
    $f = $prep['fields'];

    $photoPath = null;
    if ($file !== null) {
        $err = null;
        $photoPath = personnel_upload_photo($file, $err);
        if ($err !== null) {
            throw new RuntimeException($err);
        }
    }
    $f['photo_path'] = $photoPath;

    try {
        $cols = array_keys($f);
        $ph   = array_map(fn ($c) => ':' . $c, $cols);
        $sql  = 'INSERT INTO personnel (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
        $bind = [];
        foreach ($f as $k => $v) { $bind[':' . $k] = $v; }
        db()->prepare($sql)->execute($bind);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        personnel_delete_photo($photoPath);
        log_error('create_personnel: ' . $e->getMessage());
        $msg = 'Personel oluşturulamadı.';
        if (defined('DEBUG') && DEBUG) {
            $msg .= ' (' . $e->getMessage() . ')';
        }
        throw new RuntimeException($msg);
    }
}

/**
 * Personel günceller. $data['remove_photo'] true ise fotoğraf silinir.
 */
function update_personnel(int $id, array $data, ?array $file = null): void
{
    personnel_ensure_schema();
    $current = get_personnel_by_id($id);
    if (!$current) {
        throw new RuntimeException('Personel bulunamadı.');
    }
    $prep = personnel_prepare($data, $id);
    if ($prep['errors']) {
        throw new RuntimeException(implode(' ', $prep['errors']));
    }
    $f = $prep['fields'];

    $newPhoto = null;
    if ($file !== null) {
        $err = null;
        $newPhoto = personnel_upload_photo($file, $err);
        if ($err !== null) {
            throw new RuntimeException($err);
        }
    }

    try {
        $photoPath = $current['photo_path'];
        if ($newPhoto !== null) {
            personnel_delete_photo($current['photo_path']);
            $photoPath = $newPhoto;
        } elseif (!empty($data['remove_photo'])) {
            personnel_delete_photo($current['photo_path']);
            $photoPath = null;
        }
        $f['photo_path'] = $photoPath;

        $sets = [];
        $bind = [':id' => $id];
        foreach ($f as $k => $v) {
            $sets[] = "$k = :$k";
            $bind[':' . $k] = $v;
        }
        $sql = 'UPDATE personnel SET ' . implode(', ', $sets) . ' WHERE id = :id';
        db()->prepare($sql)->execute($bind);
    } catch (Throwable $e) {
        personnel_delete_photo($newPhoto);
        log_error('update_personnel: ' . $e->getMessage());
        $msg = 'Personel güncellenemedi.';
        if (defined('DEBUG') && DEBUG) {
            $msg .= ' (' . $e->getMessage() . ')';
        }
        throw new RuntimeException($msg);
    }
}

/**
 * Personel siler (fotoğrafıyla birlikte).
 */
function delete_personnel(int $id): void
{
    $p = get_personnel_by_id($id);
    if (!$p) {
        return;
    }
    try {
        db()->prepare('DELETE FROM personnel WHERE id = :id')->execute([':id' => $id]);
        personnel_delete_photo($p['photo_path'] ?? null);
    } catch (Throwable $e) {
        log_error('delete_personnel: ' . $e->getMessage());
        throw new RuntimeException('Personel silinemedi.');
    }
}

/**
 * Fotoğraf avatar HTML'i (tablo için yuvarlak).
 */
function personnel_photo_html(?string $photoPath, string $name): string
{
    if ($photoPath && is_file(APP_ROOT . '/' . ltrim($photoPath, '/'))) {
        return '<img src="' . e(url($photoPath)) . '" alt="' . e($name) . '" class="staff-avatar">';
    }
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $ini .= mb_substr($p, 0, 1, 'UTF-8');
    }
    $ini = mb_strtoupper($ini !== '' ? $ini : '?', 'UTF-8');
    return '<span class="staff-avatar staff-avatar-ph">' . e($ini) . '</span>';
}
