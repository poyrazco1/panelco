<?php
declare(strict_types=1);

/**
 * tools/generate-vault-key.php
 * -------------------------------------------------------------------------
 * Şifre Kasası (password_vault) için GÜVENLİ VAULT_KEY üretir.
 *
 *   - random_bytes(32) → base64_encode → "base64:<...>" biçimi (AES-256).
 *   - Üretilen anahtarı config.php içine eklenecek satır olarak GÖSTERİR.
 *   - Anahtarı VERİTABANINA YAZMAZ ve LOGLARA BASMAZ.
 *   - Yalnızca kurulum/rotasyon anında kullanılır.
 *
 * KULLANIM:
 *   CLI :  php tools/generate-vault-key.php
 *   Web :  Yalnızca Süper Admin erişebilir. Kullanımdan sonra bu dosyayı
 *          sunucudan SİLİN veya erişimi kapatın (production'da açık kalmamalı).
 *
 * NOT: Anahtar değiştirilirse eski kasa kayıtları çözülemez (yeniden girilir).
 */

/** Anahtarı üret (32 bayt = 256 bit). */
function vault_generate_key_line(): string
{
    $key = 'base64:' . base64_encode(random_bytes(32));
    return "define('VAULT_KEY', '" . $key . "');";
}

/* ---------------------------------------------------------------------------
 |  CLI MODU
 * ------------------------------------------------------------------------- */
if (PHP_SAPI === 'cli') {
    $line = vault_generate_key_line();
    fwrite(STDOUT, "\n");
    fwrite(STDOUT, "Güvenli VAULT_KEY üretildi. Aşağıdaki satırı config.php içine ekleyin\n");
    fwrite(STDOUT, "(mevcut VAULT_KEY tanımını bununla değiştirin):\n\n");
    fwrite(STDOUT, "    " . $line . "\n\n");
    fwrite(STDOUT, "UYARI: Bu anahtarı güvenli saklayın. Değiştirilirse eski kayıtlar çözülemez.\n");
    fwrite(STDOUT, "Bu değer hiçbir yere kaydedilmedi (DB/log). Yalnızca burada gösterildi.\n\n");
    exit(0);
}

/* ---------------------------------------------------------------------------
 |  WEB MODU — yalnızca Süper Admin
 * ------------------------------------------------------------------------- */
require_once __DIR__ . '/../includes/permissions.php';
auth_boot();
require_login();

// Yalnızca "all" yetkisine (Süper Admin) sahip kullanıcı erişebilir.
$perms = function_exists('current_permissions') ? current_permissions() : [];
if (!in_array('all', $perms, true)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;max-width:520px;margin:60px auto">Bu araca yalnızca sistem yöneticisi erişebilir.</p>';
    exit;
}

// Anahtarı yalnızca kullanıcı butona bastığında üret (sayfa her açılışta değil).
$generated = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $generated = vault_generate_key_line();
}

$configured = defined('VAULT_KEY') && (string) VAULT_KEY !== '' && (!defined('VAULT_KEY_PLACEHOLDER') || (string) VAULT_KEY !== (string) VAULT_KEY_PLACEHOLDER);

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>VAULT_KEY Üret</title>
    <style>
        body{font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#F5F6F8;color:#1F2937;margin:0;padding:24px}
        .box{max-width:680px;margin:0 auto;background:#fff;border:1px solid #E2E5EA;border-radius:8px;padding:24px}
        h1{font-size:19px;margin:0 0 6px}
        p{color:#4b5563;font-size:14px;line-height:1.55}
        code{display:block;background:#0f172a;color:#e7ecf3;padding:14px 16px;border-radius:6px;font-size:13px;word-break:break-all;margin:12px 0}
        .btn{display:inline-block;padding:10px 16px;border-radius:6px;font-size:14px;border:1px solid #1F2A44;background:#1F2A44;color:#fff;cursor:pointer}
        .warn{background:#FFF6E5;color:#8a5a00;padding:12px 14px;border-radius:6px;font-size:13px;margin:14px 0}
        ol{font-size:14px;color:#4b5563;line-height:1.6}
        a{color:#1F2A44}
    </style>
</head>
<body>
    <div class="box">
        <h1>Şifre Kasası Anahtarı (VAULT_KEY) Üret</h1>
        <p>Bu araç 256-bit güvenli bir anahtar üretir. Anahtar <strong>hiçbir yere kaydedilmez</strong>
           (veritabanı/log). Aşağıdaki satırı kopyalayıp <code style="display:inline;padding:2px 5px">config.php</code> içindeki
           <code style="display:inline;padding:2px 5px">VAULT_KEY</code> tanımıyla değiştirin.</p>

        <?php if ($configured): ?>
            <div class="warn">Sistemde zaten bir VAULT_KEY tanımlı görünüyor. Anahtarı DEĞİŞTİRİRSENİZ mevcut kasa kayıtları çözülemez hale gelir. Yalnızca ilk kurulumda veya bilinçli rotasyonda değiştirin.</div>
        <?php endif; ?>

        <form method="post">
            <button type="submit" class="btn">Yeni Anahtar Üret</button>
        </form>

        <?php if ($generated !== null): ?>
            <p style="margin-top:18px"><strong>config.php içine yapıştırın:</strong></p>
            <code><?= htmlspecialchars($generated, ENT_QUOTES) ?></code>
            <ol>
                <li>Yukarıdaki satırı kopyalayın.</li>
                <li><code style="display:inline;padding:2px 5px">config.php</code> dosyasını açın ve mevcut <code style="display:inline;padding:2px 5px">VAULT_KEY</code> satırını bununla değiştirin.</li>
                <li>Kaydedin. Şifre Kasası artık şifreleme yapabilir.</li>
                <li>Güvenlik: İşiniz bitince bu dosyayı (<code style="display:inline;padding:2px 5px">tools/generate-vault-key.php</code>) sunucudan silin veya erişimini kapatın.</li>
            </ol>
        <?php endif; ?>

        <p style="margin-top:16px"><a href="<?= htmlspecialchars((defined('BASE_URL') && BASE_URL !== '' ? rtrim(BASE_URL, '/') : '') . '/modules/password-vault/index.php', ENT_QUOTES) ?>">← Şifre Kasası'na dön</a></p>
    </div>
</body>
</html>
