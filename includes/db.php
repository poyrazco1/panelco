<?php
declare(strict_types=1);

/**
 * includes/db.php
 * config.php değerlerini kullanarak tekil (singleton) PDO bağlantısı kurar.
 * Bağlantı hatasında teknik detay kullanıcıya gösterilmez; logs/ klasörüne yazılır.
 */

require_once __DIR__ . '/../config.php';

/**
 * Yalnızca bu dosyanın kullandığı, bağımsız çalışan basit hata kaydı.
 * (helpers.php yüklüyse oradaki log_error kullanılır.)
 */
function db_log_error(string $message): void
{
    if (function_exists('log_error')) {
        log_error($message);
        return;
    }
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * PDO bağlantısını döndürür (bir kez kurar, sonra yeniden kullanır).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        db_log_error('DB baglanti hatasi: ' . $e->getMessage());

        http_response_code(500);
        $message = DEBUG
            ? 'Veritabanı hatası: ' . $e->getMessage()
            : 'Veritabanına şu anda bağlanılamıyor. Lütfen daha sonra tekrar deneyin.';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">'
           . '<div style="font:15px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;'
           . 'max-width:520px;margin:60px auto;padding:24px;border:1px solid #E2E5EA;'
           . 'border-radius:8px;color:#1F2937;background:#fff">'
           . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
           . '</div>';
        exit;
    }
}
