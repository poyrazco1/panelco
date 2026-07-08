<?php
declare(strict_types=1);

define('DEBUG', false);

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'sgdqdtnu_poyraztech_eticaret');
define('DB_USER', 'sgdqdtnu_poyraztech_eticaret_user');
define('DB_PASS', 'SALwhi51c&?dpjl2');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', '');
define('SITE_NAME', 'PoyrazTech Yönetim Paneli');
define('DEFAULT_TIMEZONE', 'Europe/Istanbul');

define('FRANKFURTER_API_URL', 'https://api.frankfurter.app');

// T-Soft REST API entegrasyonu (Part 1A: bağlantı + token + ham ürün testi)
define('TSOFT_BASE_URL', 'http://www.poyraztoner.com/rest1');
// auth/login için opsiyonel kimlik alanları. Mağaza IP tabanlı yetki veriyorsa boş bırakın.
// (Örn. T-Soft panelinde tanımlıysa: 'user' => '...', 'pass' => '...')
define('TSOFT_LOGIN_FIELDS', '');   // '' ya da 'k1=v1&k2=v2' biçiminde
define('TSOFT_TIMEOUT', 30);        // saniye
define('TSOFT_CONNECT_TIMEOUT', 10);
define('TSOFT_SSL_VERIFY', true);   // https kullanılıyorsa sertifika doğrulaması

define('SESSION_NAME', 'poyraztech_panel_session');
define('REMEMBER_COOKIE_NAME', 'poyraztech_remember_token');
define('REMEMBER_COOKIE_DAYS', 30);

/* =========================================================================
 |  SMTP / MAIL AYARLARI
 |  Mail gönderimi yalnızca buradaki ayarlar üzerinden yapılır (.env KULLANILMAZ).
 |  Sunucuda SMTP_PASSWORD alanına gerçek şifre yazılmalıdır; placeholder
 |  ('BURAYA_MAIL_SIFRESI_YAZILACAK') kaldığı sürece mail GÖNDERİLMEZ.
 |  Sabitler yeniden tanımlanmaya karşı korumalıdır (redeclare warning oluşmaz).
 * ====================================================================== */
if (!defined('SMTP_HOST'))       { define('SMTP_HOST', 'poyraztech.com'); }
if (!defined('SMTP_PORT'))       { define('SMTP_PORT', 465); }
if (!defined('SMTP_USERNAME'))   { define('SMTP_USERNAME', 'panel@poyraztech.com'); }
if (!defined('SMTP_PASSWORD'))   { define('SMTP_PASSWORD', 'BURAYA_MAIL_SIFRESI_YAZILACAK'); }
if (!defined('SMTP_ENCRYPTION')) { define('SMTP_ENCRYPTION', 'ssl'); } // ssl | tls | ''

if (!defined('MAIL_FROM'))          { define('MAIL_FROM', 'panel@poyraztech.com'); }
if (!defined('MAIL_FROM_NAME'))     { define('MAIL_FROM_NAME', 'PoyrazTech Panel'); }
if (!defined('MAIL_REPLY_TO'))      { define('MAIL_REPLY_TO', ''); }
if (!defined('MAIL_REPLY_TO_NAME')) { define('MAIL_REPLY_TO_NAME', ''); }

if (!defined('TEST_MAIL_TO')) { define('TEST_MAIL_TO', 'panel@poyraztech.com'); }

// Gönderen (From) e-postasını işlemi yapan kullanıcının adresine ayarlamayı dener.
// Çoğu SMTP sunucusu, kimlik doğrulanan hesaptan (SMTP_USERNAME) farklı bir From
// adresini reddeder. Bu yüzden VARSAYILAN GÜVENLİ MOD kapalıdır: From daima
// MAIL_FROM olur, kullanıcı adı From görünen adında + Reply-To olarak taşınır.
if (!defined('MAIL_ALLOW_ACTOR_FROM')) { define('MAIL_ALLOW_ACTOR_FROM', false); }

// Placeholder sabiti — mail servisi bu değeri "yapılandırılmamış" olarak kabul eder.
if (!defined('SMTP_PASSWORD_PLACEHOLDER')) { define('SMTP_PASSWORD_PLACEHOLDER', 'BURAYA_MAIL_SIFRESI_YAZILACAK'); }

/* =========================================================================
 |  ŞİFRE KASASI ŞİFRELEME ANAHTARI (Faz D — password_vault)
 |  Kasadaki şifreler AES-256-GCM ile şifrelenir. Anahtar SUNUCUDA burada
 |  saklanır; asla veritabanında düz tutulmaz. Placeholder kaldıkça kasa
 |  çalışmaz (şifreleme/çözme reddedilir).
 |
 |  ÖNERİLEN BİÇİM: "base64:" ön ekiyle 32 baytlık (256-bit) rastgele anahtar.
 |  Güçlü anahtar üretmek için (herhangi biri):
 |     php tools/generate-vault-key.php
 |     php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
 |  Üretilen satırı buraya YAPIŞTIRIN. Anahtarı değiştirirseniz eski kayıtlar
 |  ÇÖZÜLEMEZ; kasada saklı şifreler yeniden girilmelidir.
 |
 |  GÜVENLİK: Anahtar bir kez üretildikten sonra sabit kalmalıdır. Bu değeri
 |  paylaşmayın; sunucu dışına çıkarmayın.
 * ====================================================================== */
if (!defined('VAULT_KEY'))             { define('VAULT_KEY', 'base64:mqk3nrr9d0HXVYvhedSaj1V7kLqZn0BZC1PY4TUG1Rg='); }
if (!defined('VAULT_KEY_PLACEHOLDER')) { define('VAULT_KEY_PLACEHOLDER', 'BURAYA_KASA_ANAHTARI_YAZILACAK'); }
// Kasa dışa aktarma (export) — güvenlik gereği VARSAYILAN KAPALI.
if (!defined('VAULT_ALLOW_EXPORT'))    { define('VAULT_ALLOW_EXPORT', false); }

// Temel ayar: zaman dilimini uygula (log ve tarih tutarlılığı için)
date_default_timezone_set(DEFAULT_TIMEZONE);
