<?php
declare(strict_types=1);

/**
 * includes/brands.php
 * Markalar iş mantığı. Ürün/servis/stok modüllerinde yeniden kullanılabilir.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if (!defined('BRAND_UPLOAD_DIR')) {
    define('BRAND_UPLOAD_DIR', APP_ROOT . '/uploads/brands');
}
if (!defined('BRAND_MAX_BYTES')) {
    define('BRAND_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
}

/**
 * Markaları getirir (opsiyonel: yalnızca aktif). Arama/filtre/sıralama destekli.
 */
function get_brands(bool $onlyActive = false, string $search = '', string $activeFilter = '', string $order = 'sort'): array
{
    $sql = 'SELECT * FROM brands WHERE 1=1';
    $params = [];
    if ($onlyActive) {
        $sql .= ' AND is_active = 1';
    } elseif ($activeFilter === 'active') {
        $sql .= ' AND is_active = 1';
    } elseif ($activeFilter === 'passive') {
        $sql .= ' AND is_active = 0';
    }
    if ($search !== '') {
        $sql .= ' AND (name LIKE :q1 OR code LIKE :q2)';
        $params[':q1'] = '%' . $search . '%';
        $params[':q2'] = '%' . $search . '%';
    }
    $sql .= ($order === 'name') ? ' ORDER BY name ASC' : ' ORDER BY sort_order ASC, id ASC';

    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('get_brands: ' . $e->getMessage());
        return [];
    }
}

/**
 * Tek marka (id ile).
 */
function get_brand_by_id(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM brands WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('get_brand_by_id: ' . $e->getMessage());
        return null;
    }
}

/**
 * Tek marka (slug ile).
 */
function get_brand_by_slug(string $slug): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM brands WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $slug]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('get_brand_by_slug: ' . $e->getMessage());
        return null;
    }
}

/**
 * Ürün ekranı vb. için aktif markaları [id => name] döndürür.
 */
function get_active_brand_options(): array
{
    $out = [];
    foreach (get_brands(true) as $b) {
        $out[(int) $b['id']] = (string) $b['name'];
    }
    return $out;
}

/**
 * Ad'dan benzersiz slug üretir.
 */
function brand_unique_slug(string $name, ?int $excludeId = null): string
{
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    try {
        while (true) {
            $sql = 'SELECT COUNT(*) FROM brands WHERE slug = :s' . ($excludeId !== null ? ' AND id <> :id' : '');
            $p = [':s' => $slug];
            if ($excludeId !== null) { $p[':id'] = $excludeId; }
            $st = db()->prepare($sql);
            $st->execute($p);
            if ((int) $st->fetchColumn() === 0) { return $slug; }
            $slug = $base . '-' . $i++;
            if ($i > 100) { return $base . '-' . substr((string) time(), -5); }
        }
    } catch (Throwable $e) {
        log_error('brand_unique_slug: ' . $e->getMessage());
        return $base . '-' . substr((string) time(), -5);
    }
}

/**
 * Aynı ada sahip başka marka var mı? (kullanıcı uyarısı için)
 */
function brand_name_exists(string $name, ?int $excludeId = null): bool
{
    try {
        $sql = 'SELECT COUNT(*) FROM brands WHERE name = :n' . ($excludeId !== null ? ' AND id <> :id' : '');
        $p = [':n' => $name];
        if ($excludeId !== null) { $p[':id'] = $excludeId; }
        $st = db()->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        log_error('brand_name_exists: ' . $e->getMessage());
        return false;
    }
}

/**
 * Güvenli logo yükleme. Başarılıysa göreli yol döner, hata olursa $error dolar.
 */
function brand_upload_logo(array $file, ?string &$error = null): ?string
{
    $error = null;

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Dosya yüklenemedi (hata kodu ' . (int) $file['error'] . ').';
        return null;
    }
    if (($file['size'] ?? 0) > BRAND_MAX_BYTES) {
        $error = 'Logo en fazla 2 MB olabilir.';
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

    if (!is_dir(BRAND_UPLOAD_DIR)) {
        @mkdir(BRAND_UPLOAD_DIR, 0775, true);
    }
    if (!is_writable(BRAND_UPLOAD_DIR)) {
        $error = 'Yükleme klasörü yazılabilir değil (uploads/brands izinlerini kontrol edin).';
        log_error('brand upload: klasör yazılamıyor: ' . BRAND_UPLOAD_DIR);
        return null;
    }

    $ext  = $allowed[$mime];
    $name = 'brand_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = BRAND_UPLOAD_DIR . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = 'Dosya kaydedilemedi.';
        return null;
    }
    @chmod($dest, 0644);

    return 'uploads/brands/' . $name;
}

/**
 * Logo dosyasını diskten siler (yalnızca uploads/brands/ altındaysa).
 */
function brand_delete_logo(?string $relPath): void
{
    if (!$relPath) {
        return;
    }
    $rel = ltrim($relPath, '/');
    if (strpos($rel, 'uploads/brands/') !== 0) {
        return;
    }
    $full = APP_ROOT . '/' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Marka oluşturur. $data: name, code, description, sort_order, is_active.
 * $file: $_FILES['logo'] (opsiyonel). Dönen: yeni id (int) veya hata fırlatır/-1.
 */
function create_brand(array $data, ?array $file = null): int
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Marka adı boş olamaz.');
    }
    $code = trim((string) ($data['code'] ?? ''));
    if ($code === '') {
        $code = slugify($name);
    }
    $slug = brand_unique_slug($name);

    $logoPath = null;
    if ($file !== null) {
        $err = null;
        $logoPath = brand_upload_logo($file, $err);
        if ($err !== null) {
            throw new RuntimeException($err);
        }
    }

    try {
        db()->prepare(
            'INSERT INTO brands (name, code, slug, logo_path, description, sort_order, is_active)
             VALUES (:n, :c, :s, :l, :d, :so, :a)'
        )->execute([
            ':n' => $name, ':c' => ($code !== '' ? $code : null), ':s' => $slug, ':l' => $logoPath,
            ':d' => (trim((string) ($data['description'] ?? '')) !== '' ? trim((string) $data['description']) : null),
            ':so' => (int) ($data['sort_order'] ?? 0),
            ':a' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        brand_delete_logo($logoPath);
        log_error('create_brand: ' . $e->getMessage());
        throw new RuntimeException('Marka oluşturulamadı.');
    }
}

/**
 * Marka günceller. $data içinde remove_logo=true ise mevcut logo silinir.
 */
function update_brand(int $id, array $data, ?array $file = null): void
{
    $brand = get_brand_by_id($id);
    if (!$brand) {
        throw new RuntimeException('Marka bulunamadı.');
    }
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Marka adı boş olamaz.');
    }
    $code = trim((string) ($data['code'] ?? ''));
    if ($code === '') {
        $code = slugify($name);
    }
    $slug = brand_unique_slug($name, $id);

    $newLogo = null;
    if ($file !== null) {
        $err = null;
        $newLogo = brand_upload_logo($file, $err);
        if ($err !== null) {
            throw new RuntimeException($err);
        }
    }

    try {
        $logoPath = $brand['logo_path'];
        if ($newLogo !== null) {
            brand_delete_logo($brand['logo_path']);
            $logoPath = $newLogo;
        } elseif (!empty($data['remove_logo'])) {
            brand_delete_logo($brand['logo_path']);
            $logoPath = null;
        }

        db()->prepare(
            'UPDATE brands SET name=:n, code=:c, slug=:s, logo_path=:l, description=:d,
                    sort_order=:so, is_active=:a WHERE id=:id'
        )->execute([
            ':n' => $name, ':c' => ($code !== '' ? $code : null), ':s' => $slug, ':l' => $logoPath,
            ':d' => (trim((string) ($data['description'] ?? '')) !== '' ? trim((string) $data['description']) : null),
            ':so' => (int) ($data['sort_order'] ?? 0),
            ':a' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $id,
        ]);
    } catch (Throwable $e) {
        brand_delete_logo($newLogo);
        log_error('update_brand: ' . $e->getMessage());
        throw new RuntimeException('Marka güncellenemedi.');
    }
}

/**
 * Marka siler (logosuyla birlikte).
 */
function delete_brand(int $id): void
{
    $brand = get_brand_by_id($id);
    if (!$brand) {
        return;
    }
    try {
        db()->prepare('DELETE FROM brands WHERE id = :id')->execute([':id' => $id]);
        brand_delete_logo($brand['logo_path'] ?? null);
    } catch (Throwable $e) {
        log_error('delete_brand: ' . $e->getMessage());
        throw new RuntimeException('Marka silinemedi.');
    }
}

/**
 * Logo <img> ya da placeholder (tablo için küçük).
 */
function brand_logo_html(?string $logoPath, string $name): string
{
    if ($logoPath && is_file(APP_ROOT . '/' . ltrim($logoPath, '/'))) {
        return '<img src="' . e(url($logoPath)) . '" alt="' . e($name) . '" class="cargo-logo">';
    }
    $initial = mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1, 'UTF-8'), 'UTF-8');
    return '<span class="cargo-logo cargo-logo-ph">' . e($initial) . '</span>';
}
