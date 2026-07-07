<?php
declare(strict_types=1);

/**
 * includes/shipping.php
 * Kargo yöntemleri iş mantığı: listeleme, logo yükleme, desi ücret doğrulama,
 * ve yeniden kullanılabilir get_shipping_price().
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if (!defined('CARGO_UPLOAD_DIR')) {
    define('CARGO_UPLOAD_DIR', APP_ROOT . '/uploads/cargo');
}
if (!defined('CARGO_MAX_BYTES')) {
    define('CARGO_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
}

/**
 * Tüm kargo yöntemleri (fiyat sayısıyla). Arama/filtre/sıralama destekli.
 */
function shipping_all(string $search = '', string $activeFilter = '', string $order = 'sort'): array
{
    $sql = 'SELECT s.*, (SELECT COUNT(*) FROM shipping_method_prices p WHERE p.shipping_method_id = s.id) AS price_count
            FROM shipping_methods s WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND s.name LIKE :q';
        $params[':q'] = '%' . $search . '%';
    }
    if ($activeFilter === 'active') {
        $sql .= ' AND s.is_active = 1';
    } elseif ($activeFilter === 'passive') {
        $sql .= ' AND s.is_active = 0';
    }
    $sql .= ($order === 'name') ? ' ORDER BY s.name ASC' : ' ORDER BY s.sort_order ASC, s.id ASC';

    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('shipping_all: ' . $e->getMessage());
        return [];
    }
}

/**
 * Tek kargo kaydı.
 */
function shipping_find(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM shipping_methods WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('shipping_find: ' . $e->getMessage());
        return null;
    }
}

/**
 * Bir kargonun ücret satırları (artan desi).
 */
function shipping_prices(int $methodId): array
{
    try {
        $st = db()->prepare(
            'SELECT * FROM shipping_method_prices WHERE shipping_method_id = :id ORDER BY min_desi ASC'
        );
        $st->execute([':id' => $methodId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('shipping_prices: ' . $e->getMessage());
        return [];
    }
}

/**
 * Ad'dan benzersiz code (slug) üretir.
 */
function shipping_unique_code(string $name, ?int $excludeId = null): string
{
    $base = slugify($name);
    $code = $base;
    $i = 2;
    try {
        while (true) {
            $sql = 'SELECT COUNT(*) FROM shipping_methods WHERE code = :c' . ($excludeId !== null ? ' AND id <> :id' : '');
            $p = [':c' => $code];
            if ($excludeId !== null) { $p[':id'] = $excludeId; }
            $st = db()->prepare($sql);
            $st->execute($p);
            if ((int) $st->fetchColumn() === 0) { return $code; }
            $code = $base . '-' . $i++;
            if ($i > 100) { return $base . '-' . substr((string) time(), -5); }
        }
    } catch (Throwable $e) {
        log_error('shipping_unique_code: ' . $e->getMessage());
        return $base . '-' . substr((string) time(), -5);
    }
}

/**
 * Ücret satırı doğrulama + çakışma kontrolü.
 * $rows: [['min'=>.., 'max'=>.., 'price'=>..], ...] (string/float)
 * Dönen: ['errors'=>[], 'clean'=>[[min,max,price], ...]]
 */
function shipping_validate_prices(array $rows): array
{
    $errors = [];
    $clean  = [];

    foreach ($rows as $idx => $r) {
        $minRaw = trim((string) ($r['min'] ?? ''));
        $maxRaw = trim((string) ($r['max'] ?? ''));
        $prcRaw = trim((string) ($r['price'] ?? ''));

        // Tamamen boş satırı atla (silinmiş sayılır)
        if ($minRaw === '' && $maxRaw === '' && $prcRaw === '') {
            continue;
        }

        $n = $idx + 1;
        if ($minRaw === '' || $maxRaw === '' || $prcRaw === '') {
            $errors[] = "Satır $n: Alt limit, üst limit ve tutar boş olamaz.";
            continue;
        }

        $min = (float) str_replace(',', '.', $minRaw);
        $max = (float) str_replace(',', '.', $maxRaw);
        $prc = (float) str_replace(',', '.', $prcRaw);

        if (!is_numeric(str_replace(',', '.', $minRaw)) || !is_numeric(str_replace(',', '.', $maxRaw)) || !is_numeric(str_replace(',', '.', $prcRaw))) {
            $errors[] = "Satır $n: Geçerli sayısal değer girin.";
            continue;
        }
        if ($min < 0 || $max < 0 || $prc < 0) {
            $errors[] = "Satır $n: Negatif değer kabul edilmez.";
            continue;
        }
        if ($min > $max) {
            $errors[] = "Satır $n: Alt limit ($min) üst limitten ($max) büyük olamaz.";
            continue;
        }
        $clean[] = ['min' => round($min, 2), 'max' => round($max, 2), 'price' => round($prc, 2)];
    }

    // Çakışma kontrolü (aralıklar üst üste binmemeli)
    $sorted = $clean;
    usort($sorted, fn ($a, $b) => $a['min'] <=> $b['min']);
    for ($i = 1; $i < count($sorted); $i++) {
        if ($sorted[$i]['min'] <= $sorted[$i - 1]['max']) {
            $errors[] = 'Desi aralıkları çakışıyor: '
                . rtrim(rtrim(number_format($sorted[$i - 1]['min'], 2, '.', ''), '0'), '.') . '-' . rtrim(rtrim(number_format($sorted[$i - 1]['max'], 2, '.', ''), '0'), '.')
                . ' ile ' . rtrim(rtrim(number_format($sorted[$i]['min'], 2, '.', ''), '0'), '.') . '-' . rtrim(rtrim(number_format($sorted[$i]['max'], 2, '.', ''), '0'), '.');
            break;
        }
    }

    return ['errors' => $errors, 'clean' => $clean];
}

/**
 * Bir kargonun ücret satırlarını topluca yeniden yazar (transaction).
 */
function shipping_replace_prices(int $methodId, array $clean): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM shipping_method_prices WHERE shipping_method_id = :id')
            ->execute([':id' => $methodId]);
        $ins = $pdo->prepare(
            'INSERT INTO shipping_method_prices (shipping_method_id, min_desi, max_desi, price)
             VALUES (:m, :mn, :mx, :pr)'
        );
        foreach ($clean as $c) {
            $ins->execute([':m' => $methodId, ':mn' => $c['min'], ':mx' => $c['max'], ':pr' => $c['price']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_error('shipping_replace_prices: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Güvenli logo yükleme. Başarılıysa göreli yol (uploads/cargo/xxx) döner.
 * Hata olursa $error doldurulur ve null döner.
 */
function shipping_upload_logo(array $file, ?string &$error = null): ?string
{
    $error = null;

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // dosya seçilmemiş
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Dosya yüklenemedi (hata kodu ' . (int) $file['error'] . ').';
        return null;
    }
    if (($file['size'] ?? 0) > CARGO_MAX_BYTES) {
        $error = 'Logo en fazla 2 MB olabilir.';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Geçersiz yükleme.';
        return null;
    }

    // MIME kontrolü (gerçek içerik)
    $allowed = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        $error = 'Yalnızca PNG, JPG veya WEBP yükleyebilirsiniz.';
        return null;
    }
    // Görsel gerçekten geçerli mi?
    if (@getimagesize($file['tmp_name']) === false) {
        $error = 'Dosya geçerli bir görsel değil.';
        return null;
    }

    if (!is_dir(CARGO_UPLOAD_DIR)) {
        @mkdir(CARGO_UPLOAD_DIR, 0775, true);
    }
    if (!is_writable(CARGO_UPLOAD_DIR)) {
        $error = 'Yükleme klasörü yazılabilir değil (uploads/cargo izinlerini kontrol edin).';
        log_error('shipping upload: klasör yazılamıyor: ' . CARGO_UPLOAD_DIR);
        return null;
    }

    $ext  = $allowed[$mime];
    $name = 'cargo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = CARGO_UPLOAD_DIR . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = 'Dosya kaydedilemedi.';
        return null;
    }
    @chmod($dest, 0644);

    return 'uploads/cargo/' . $name;
}

/**
 * Logo dosyasını diskten siler (yol uploads/cargo/ altındaysa).
 */
function shipping_delete_logo(?string $relPath): void
{
    if (!$relPath) {
        return;
    }
    $rel = ltrim($relPath, '/');
    if (strpos($rel, 'uploads/cargo/') !== 0) {
        return; // güvenlik: sadece bu klasör
    }
    $full = APP_ROOT . '/' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * YENİDEN KULLANILABİLİR: verilen desi için kargo ücretini döndürür.
 * Kargo pasifse veya uygun aralık yoksa null döner.
 */
function get_shipping_price(int $shipping_method_id, float $desi): ?float
{
    try {
        $st = db()->prepare('SELECT is_active FROM shipping_methods WHERE id = :id LIMIT 1');
        $st->execute([':id' => $shipping_method_id]);
        $active = $st->fetchColumn();
        if ($active === false || (int) $active !== 1) {
            return null;
        }

        $ps = db()->prepare(
            'SELECT price FROM shipping_method_prices
             WHERE shipping_method_id = :id AND :d1 >= min_desi AND :d2 <= max_desi
             ORDER BY min_desi ASC LIMIT 1'
        );
        $ps->execute([':id' => $shipping_method_id, ':d1' => $desi, ':d2' => $desi]);
        $price = $ps->fetchColumn();
        return $price === false ? null : (float) $price;
    } catch (Throwable $e) {
        log_error('get_shipping_price: ' . $e->getMessage());
        return null;
    }
}

/**
 * Logo <img> ya da placeholder döndürür (tablo için küçük).
 */
function shipping_logo_html(?string $logoPath, string $name): string
{
    if ($logoPath && is_file(APP_ROOT . '/' . ltrim($logoPath, '/'))) {
        return '<img src="' . e(url($logoPath)) . '" alt="' . e($name) . '" class="cargo-logo">';
    }
    $initial = mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1, 'UTF-8'), 'UTF-8');
    return '<span class="cargo-logo cargo-logo-ph">' . e($initial) . '</span>';
}
