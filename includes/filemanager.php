<?php
declare(strict_types=1);

/**
 * includes/filemanager.php — Güvenli dosya yöneticisi iş mantığı.
 *
 * GÜVENLİK:
 *  - Kök YALNIZCA uploads/ altıdır. Tüm yollar realpath ile bu kökün İÇİNDE
 *    olmak zorundadır (path traversal engellenir).
 *  - PHP ve çalıştırılabilir uzantılar yüklenemez / oluşturulamaz.
 *  - .htaccess, .gitkeep ve gizli dosyalar silinemez/oluşturulamaz.
 *  - config.php, includes/, database/, logs/ ERİŞİLEMEZ (kök dışı).
 */

require_once __DIR__ . '/helpers.php';

/** Yönetilebilir kök (mutlak). */
function fm_root(): string
{
    return str_replace('\\', '/', (string) realpath(APP_ROOT . '/uploads'));
}

/** Yasaklı uzantılar (yükleme + oluşturma). */
function fm_blocked_extensions(): array
{
    return ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phar', 'phps',
            'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'htaccess', 'htpasswd',
            'ini', 'so', 'dll', 'jsp', 'asp', 'aspx'];
}

/** İzinli yükleme uzantıları (whitelist). */
function fm_allowed_extensions(): array
{
    return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'rtf', 'odt', 'ods',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
            'zip', 'rar', '7z', 'mp4', 'mp3', 'wav'];
}

/**
 * Göreli yolu güvenli mutlak yola çözer. Kök dışına çıkarsa null.
 * @return string|null
 */
function fm_resolve(string $rel): ?string
{
    $rel = str_replace('\\', '/', trim($rel));
    $rel = ltrim($rel, '/');
    // Ham traversal reddi
    if ($rel !== '' && (strpos($rel, '..') !== false)) { return null; }
    $root = fm_root();
    if ($root === '') { return null; }
    $target = $rel === '' ? $root : $root . '/' . $rel;
    $abs = str_replace('\\', '/', (string) realpath($target));
    if ($abs === '') { return null; }
    if ($abs !== $root && strpos($abs, $root . '/') !== 0) { return null; }
    return $abs;
}

/** Mutlak yolu köke göre göreli yola çevirir. */
function fm_relative(string $abs): string
{
    $root = fm_root();
    $abs = str_replace('\\', '/', $abs);
    if ($abs === $root) { return ''; }
    if (strpos($abs, $root . '/') === 0) { return substr($abs, strlen($root) + 1); }
    return '';
}

/** Dosya/klasör adı güvenli mi? (oluşturma için) */
function fm_safe_name(string $name): bool
{
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') { return false; }
    if ($name[0] === '.') { return false; } // gizli dosya yok
    if (preg_match('/[\/\\\\:\*\?"<>\|]/', $name)) { return false; }
    return (bool) preg_match('/^[A-Za-z0-9ğüşıöçĞÜŞİÖÇ _\-\.\(\)]+$/u', $name);
}

/** Uzantı yasaklı mı? */
function fm_ext_blocked(string $name): bool
{
    $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    return $ext === '' || in_array($ext, fm_blocked_extensions(), true) || !in_array($ext, fm_allowed_extensions(), true);
}

/** Silme/oluşturma korumalı dosya mı? */
function fm_protected_name(string $name): bool
{
    return in_array($name, ['.htaccess', '.gitkeep', '.git'], true) || $name === '' || $name[0] === '.';
}

/**
 * Bir dizini listeler.
 * @return array{dirs:array, files:array}
 */
function fm_list(string $absDir): array
{
    $dirs = [];
    $files = [];
    foreach ((array) @scandir($absDir) as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $path = $absDir . '/' . $entry;
        if (is_dir($path)) {
            $dirs[] = ['name' => $entry, 'rel' => fm_relative(str_replace('\\', '/', (string) realpath($path)))];
        } elseif (is_file($path)) {
            $files[] = [
                'name'     => $entry,
                'rel'      => fm_relative(str_replace('\\', '/', (string) realpath($path))),
                'size'     => (int) @filesize($path),
                'modified' => (int) @filemtime($path),
                'is_htaccess' => fm_protected_name($entry),
            ];
        }
    }
    usort($dirs, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($files, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return ['dirs' => $dirs, 'files' => $files];
}

/** İnsan-okur boyut. */
function fm_human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $v = (float) $bytes;
    while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
    return number_format($v, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
}

/** "Breadcrumb" parçaları: [ [name, rel], ... ] */
function fm_breadcrumbs(string $rel): array
{
    $crumbs = [['name' => 'uploads', 'rel' => '']];
    $rel = trim($rel, '/');
    if ($rel === '') { return $crumbs; }
    $acc = '';
    foreach (explode('/', $rel) as $part) {
        $acc = $acc === '' ? $part : $acc . '/' . $part;
        $crumbs[] = ['name' => $part, 'rel' => $acc];
    }
    return $crumbs;
}
