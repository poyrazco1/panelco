<?php
declare(strict_types=1);

/**
 * auth-self-test.php
 * Sunucu tarafı auth teşhisi. login.php ile AYNI fonksiyonları çağırır.
 * GÜVENLİK: Teşhis bitince BU DOSYAYI SİLİN.
 */
require_once __DIR__ . '/includes/auth.php'; // config + helpers + db + csrf + auth

header('Content-Type: text/plain; charset=utf-8');

function line(string $k, $v): void { echo str_pad($k, 42, '.') . ' ' . $v . "\n"; }

echo "=== PoyrazTech — Auth Self-Test ===\n\n";

line('Config yüklendi', defined('DB_NAME') ? 'evet' : 'HAYIR');
line('DB adı', defined('DB_NAME') ? DB_NAME : '?');
line('DB host:port', (defined('DB_HOST') ? DB_HOST : '?') . ':' . (defined('DB_PORT') ? DB_PORT : '?'));
line('DEBUG', DEBUG ? 'true' : 'false');
echo "\n-- admin kaydı --\n";

try {
    $u = auth_find_user_by_identifier('admin');
    if (!$u) {
        line('admin bulundu', 'HAYIR (install.sql import edilmemiş olabilir)');
    } else {
        line('admin bulundu', 'evet');
        line('  id', $u['id'] ?? '?');
        line('  is_active', var_export($u['is_active'] ?? null, true));
        line('  role_id', var_export($u['role_id'] ?? null, true));
        line('  role_name', $u['role_name'] ?? '(yok)');
        line('  password_hash var', !empty($u['password_hash']) ? 'evet' : 'HAYIR');
        line('  password_verify(Admin1234!)',
            password_verify('Admin1234!', (string) ($u['password_hash'] ?? '')) ? 'TRUE' : 'FALSE');
        line('  remember_token kolonu', array_key_exists('remember_token', $u) ? 'var' : 'YOK');
    }
} catch (Throwable $e) {
    line('admin sorgusu HATASI', $e->getMessage());
}

echo "\n-- gerçek doğrulama zinciri --\n";
$reason = null;
$ok = auth_verify_credentials('admin', 'Admin1234!', $reason);
line('auth_verify_credentials(admin)', ($ok ? 'TRUE' : 'FALSE') . ' (sebep: ' . $reason . ')');

echo "\n-- oturum + tam giriş --\n";
try {
    auth_boot();
    $_SESSION['__selftest'] = 'ok';
    line('session yazılabiliyor', (($_SESSION['__selftest'] ?? '') === 'ok') ? 'evet' : 'HAYIR');
    line('session id', session_id() !== '' ? 'üretildi' : 'YOK');

    $full = auth_login('admin', 'Admin1234!', false);
    line('auth_login(admin,Admin1234!)', $full ? 'TRUE (giriş çalışıyor)' : 'FALSE');
    line('is_logged_in()', is_logged_in() ? 'evet' : 'hayır');

    // Test oturumunu kapat (giriş bırakma)
    auth_logout();
    line('temizlik', 'test oturumu kapatıldı');
} catch (Throwable $e) {
    line('oturum/giriş HATASI', $e->getMessage());
}

echo "\nSONUÇ: 'auth_login(admin,Admin1234!)' TRUE ise login.php çalışır.\n";
echo "GÜVENLİK: auth-self-test.php dosyasını canlıda SİLİN.\n";
