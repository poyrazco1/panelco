<?php
declare(strict_types=1);

/**
 * install-check.php
 * Kurulum tanılaması. Sunucu gereksinimlerini ve veritabanını denetler.
 * GÜVENLİK: Her şey yeşil olduğunda BU DOSYAYI SUNUCUDAN SİLİN.
 */

$checks = [];
function chk(array &$l, string $label, string $status, string $detail = ''): void
{
    $l[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
}

// PHP sürümü
$phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
chk($checks, 'PHP sürümü', $phpOk ? 'ok' : 'fail', 'Mevcut: ' . PHP_VERSION . ' (gereken ≥ 8.2)');

// Eklentiler
foreach (['pdo_mysql', 'openssl', 'mbstring', 'json', 'curl'] as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? 'ok' : ($ext === 'curl' ? 'warn' : 'fail');
    chk($checks, 'Eklenti: ' . $ext, $status, $loaded ? 'yüklü' : 'yüklü değil');
}

// config.php
$configPath = __DIR__ . '/config.php';
$hasConfig = is_file($configPath);
chk($checks, 'config.php', $hasConfig ? 'ok' : 'fail', $hasConfig ? 'bulundu' : 'bulunamadı');

// kur.php erişimi
$hasKur = is_file(__DIR__ . '/kur.php');
chk($checks, 'kur.php erişimi', $hasKur ? 'ok' : 'fail', $hasKur ? 'bulundu' : 'bulunamadı');

$dbOk = false;
$hasFail = !$phpOk || !$hasConfig;

if ($hasConfig) {
    require_once $configPath;

    $defined = defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER');
    chk($checks, 'Veritabanı ayarları', $defined ? 'ok' : 'fail', $defined ? 'tanımlı' : 'DB_* eksik');

    // logs/ yazılabilirlik
    $logDir = __DIR__ . '/logs';
    $writable = is_dir($logDir) && is_writable($logDir);
    chk($checks, 'Yazılabilir: logs/', $writable ? 'ok' : 'warn',
        $writable ? 'yazılabilir' : 'yazılamıyor (izinleri kontrol edin)');

    /* ------------------------------------------------------------------ *
     |  ŞİFRE KASASI ANAHTARI (VAULT_KEY) — değer ASLA gösterilmez.
     |  Yalnızca: tanımlı mı, biçim doğru mu, 32 bayta çözülüyor mu,
     |  bellek içi şifrele/çöz turu çalışıyor mu.
     * ------------------------------------------------------------------ */
    (function () use (&$checks) {
        if (!extension_loaded('openssl')) {
            chk($checks, 'Şifre Kasası (VAULT_KEY)', 'fail', 'openssl eklentisi yok — şifreleme yapılamaz');
            return;
        }
        if (!defined('VAULT_KEY')) {
            chk($checks, 'Şifre Kasası (VAULT_KEY)', 'fail', 'config.php içinde VAULT_KEY tanımlı değil');
            return;
        }
        $ph = defined('VAULT_KEY_PLACEHOLDER') ? (string) VAULT_KEY_PLACEHOLDER : '';
        $key = (string) VAULT_KEY;
        if ($key === '' || $key === $ph) {
            chk($checks, 'Şifre Kasası (VAULT_KEY)', 'fail',
                'Anahtar tanımlı değil/placeholder — tools/generate-vault-key.php ile üretip config.php içine ekleyin');
            return;
        }
        // 32 baytlık ham anahtarı türet (vault_key() ile aynı mantık, değeri sızdırmadan)
        $raw = null; $fmt = 'sha256 türetme';
        if (stripos($key, 'base64:') === 0) {
            $d = base64_decode(substr($key, 7), true); $fmt = 'base64';
            if ($d !== false && strlen($d) === 32) { $raw = $d; }
        } elseif (stripos($key, 'hex:') === 0) {
            $h = substr($key, 4); $fmt = 'hex';
            if (strlen($h) === 64 && ctype_xdigit($h)) { $raw = (string) hex2bin($h); }
        }
        if ($raw === null) {
            if (stripos($key, 'base64:') === 0 || stripos($key, 'hex:') === 0) {
                chk($checks, 'Şifre Kasası — anahtar biçimi', 'fail', $fmt . ' ön eki var ama 32 bayta çözülemiyor');
                return;
            }
            $raw = hash('sha256', $key, true); // düz metin → 32 bayt
        }
        chk($checks, 'Şifre Kasası — anahtar biçimi', 'ok', $fmt . ' · 32 bayt (256-bit) hazır');

        // Bellek içi şifrele/çöz turu (DB'ye dokunmaz, değer gösterilmez)
        try {
            $sample = 'vault-selftest-' . bin2hex(random_bytes(4));
            $iv = random_bytes(12); $tag = '';
            $ct = openssl_encrypt($sample, 'aes-256-gcm', $raw, OPENSSL_RAW_DATA, $iv, $tag);
            $pt = ($ct !== false)
                ? openssl_decrypt($ct, 'aes-256-gcm', $raw, OPENSSL_RAW_DATA, $iv, $tag)
                : false;
            $roundOk = ($ct !== false && $pt === $sample);
            chk($checks, 'Şifre Kasası — şifrele/çöz testi', $roundOk ? 'ok' : 'fail',
                $roundOk ? 'AES-256-GCM turu başarılı' : 'test başarısız — anahtar/openssl sorunu');
        } catch (Throwable $e) {
            chk($checks, 'Şifre Kasası — şifrele/çöz testi', 'fail', 'test hatası');
        }
    })();

    if ($defined) {
        // Yapılandırılan değerleri göster (şifre gizli) — Plesk ile karşılaştırın
        chk($checks, 'Ayar → host:port', 'ok', DB_HOST . ' : ' . DB_PORT);
        chk($checks, 'Ayar → veritabanı adı', 'ok', DB_NAME);
        chk($checks, 'Ayar → kullanıcı', 'ok', DB_USER);
        chk($checks, 'Ayar → şifre', DB_PASS !== '' ? 'ok' : 'fail',
            DB_PASS !== '' ? ('tanımlı (' . strlen(DB_PASS) . ' karakter)') : 'BOŞ');

        // AŞAMA 1: Sadece MySQL sunucusuna bağlan (veritabanı adı OLMADAN)
        // -> Bu başarısızsa sorun host/kullanıcı/şifredir.
        $serverOk = false;
        try {
            $dsn1 = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;
            $pdo1 = new PDO($dsn1, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $serverOk = true;
            chk($checks, 'Aşama 1 — MySQL sunucusu', 'ok', 'host + kullanıcı adı + şifre DOĞRU');
        } catch (Throwable $e) {
            chk($checks, 'Aşama 1 — MySQL sunucusu', 'fail', $e->getMessage());
        }

        // AŞAMA 2: Veritabanına bağlan (yalnızca sunucu bağlantısı başarılıysa)
        // -> Bu başarısızsa veritabanı adı yanlış ya da veritabanı henüz oluşturulmamış.
        if ($serverOk) {
            try {
                $dsn2 = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $pdo = new PDO($dsn2, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $dbOk = true;
                chk($checks, 'Aşama 2 — Veritabanı erişimi', 'ok', '“' . DB_NAME . '” açıldı');

                $need  = ['users', 'roles', 'password_resets', 'login_logs', 'app_settings', 'currency_cache'];
                $found = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                foreach ($need as $t) {
                    $has = in_array($t, $found, true);
                    chk($checks, 'Tablo: ' . $t, $has ? 'ok' : 'fail', $has ? 'var' : 'yok — install.sql içe aktarıldı mı?');
                }
                if (in_array('users', $found, true)) {
                    $cnt = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                    chk($checks, 'Kullanıcı kaydı', $cnt > 0 ? 'ok' : 'warn', $cnt > 0 ? ($cnt . ' kullanıcı') : 'hiç kullanıcı yok');
                }
                if (in_array('roles', $found, true)) {
                    $rc = (int) $pdo->query('SELECT COUNT(*) FROM roles WHERE is_system = 1')->fetchColumn();
                    chk($checks, 'Yönetici rolü', $rc > 0 ? 'ok' : 'warn', $rc > 0 ? 'tanımlı' : 'sistem rolü yok');
                }
                if (in_array('users', $found, true)) {
                    // GERÇEK login zinciriyle test et — login.php ile AYNI fonksiyon.
                    // Böylece "install-check yeşil ama login kırmızı" durumu oluşamaz.
                    try {
                        require_once __DIR__ . '/includes/auth.php';
                        $reason = null;
                        $ok = auth_verify_credentials('admin', 'Admin1234!', $reason);
                        $map = [
                            'ok'                     => 'admin / Admin1234! DOĞRULANDI — giriş çalışır',
                            'user_not_found'         => 'admin kullanıcısı yok → install.sql import edilmemiş',
                            'inactive_user'          => 'admin PASİF (is_active=0)',
                            'password_verify_failed' => 'şifre eşleşmedi → hash yanlış/eski',
                            'db_exception'           => 'sorgu hatası → logs/app-*.log dosyasına bakın',
                            'empty_input'            => 'boş girdi',
                        ];
                        chk($checks, 'GERÇEK login testi (login.php ile aynı fonksiyon)',
                            $ok ? 'ok' : 'fail', $map[$reason] ?? (string) $reason);
                    } catch (Throwable $e) {
                        chk($checks, 'GERÇEK login testi', 'fail', $e->getMessage());
                    }
                }
            } catch (Throwable $e) {
                chk($checks, 'Aşama 2 — Veritabanı erişimi', 'fail',
                    $e->getMessage() . ' — veritabanı adı doğru mu / oluşturuldu mu / kullanıcı bu veritabanına atanmış mı?');
            }
        }
    }
}

foreach ($checks as $c) {
    if ($c['status'] === 'fail') { $hasFail = true; break; }
}

$cssHref = is_file(__DIR__ . '/assets/css/app.css')
    ? ((defined('BASE_URL') && BASE_URL !== '') ? rtrim(BASE_URL, '/') . '/assets/css/app.css' : 'assets/css/app.css')
    : '';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Kurulum Kontrolü</title>
    <?php if ($cssHref): ?><link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES) ?>"><?php endif; ?>
    <style>
        body{font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#F5F6F8;color:#1F2937;margin:0;padding:24px}
        .box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #E2E5EA;border-radius:8px;overflow:hidden}
        .box h1{font-size:18px;margin:0;padding:16px 18px;border-bottom:1px solid #E2E5EA}
        table{width:100%;border-collapse:collapse;font-size:14px}
        td{padding:11px 16px;border-bottom:1px solid #EEF0F3;vertical-align:top}
        tr:last-child td{border-bottom:none}
        .s{font-weight:600;white-space:nowrap}
        .ok{color:#276749}.warn{color:#9a6b00}.fail{color:#9B1C22}
        .d{color:#6B7280}
        .banner{max-width:720px;margin:0 auto 16px;padding:12px 14px;border-radius:6px;font-size:14px}
        .banner.warn{background:#FFF6E5;color:#8a5a00}
        .banner.good{background:#E9F3EC;color:#276749}
        .note{max-width:720px;margin:16px auto 0;font-size:13px;color:#6B7280}
    </style>
</head>
<body>
    <div class="banner <?= $hasFail ? 'warn' : 'good' ?>">
        <?= $hasFail
            ? 'Bazı zorunlu kontroller başarısız. Aşağıdaki “✕ fail” satırlarını giderin.'
            : 'Tüm zorunlu kontroller geçti. Panel kullanıma hazır.' ?>
    </div>
    <div class="box">
        <h1>Kurulum Kontrolü — <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'Panel', ENT_QUOTES) ?></h1>
        <table>
            <?php foreach ($checks as $c): ?>
                <tr>
                    <td class="s <?= $c['status'] ?>">
                        <?= $c['status'] === 'ok' ? '✓' : ($c['status'] === 'warn' ? '!' : '✕') ?>
                        <?= htmlspecialchars($c['label'], ENT_QUOTES) ?>
                    </td>
                    <td class="d"><?= htmlspecialchars($c['detail'], ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <p class="note">
        Güvenlik: Kurulum bittiğinde <code>install-check.php</code> dosyasını silin.
        <?php if ($dbOk && !$hasFail): ?>
            · <a href="<?= htmlspecialchars((defined('BASE_URL') && BASE_URL !== '' ? rtrim(BASE_URL, '/') : '') . '/login.php', ENT_QUOTES) ?>">Girişe git →</a>
        <?php endif; ?>
    </p>
</body>
</html>
