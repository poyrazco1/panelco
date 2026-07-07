<?php
declare(strict_types=1);

/**
 * modules/password-vault/reveal.php — Şifreyi çöz + göster (AJAX).
 * GÜVENLİK: POST + login + password_vault.reveal + CSRF + KULLANICININ PANEL
 * ŞİFRESİNİ YENİDEN GİRMESİ zorunludur. Her başarılı görüntüleme loglanır.
 * Düz şifre yalnızca JSON yanıtında döner; sunucuda saklanmaz/loglanmaz.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/vault.php';

auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$fail = static function (string $m, int $c = 400): void {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('Yalnızca POST.', 405); }
if (!is_logged_in()) { $fail('Oturum bulunamadı.', 403); }
if (!can('password_vault.reveal')) { $fail('Bu işlem için yetkiniz bulunmuyor.', 403); }
if (!csrf_verify($_POST['_csrf'] ?? '')) { $fail('Oturum doğrulaması başarısız.', 419); }

if (!vault_is_configured()) { $fail('Şifre kasası yapılandırılmamış.', 503); }

$id = (int) ($_POST['id'] ?? 0);
$panelPassword = (string) ($_POST['password'] ?? '');
$uid = (int) (current_user_id() ?? 0);

if (!vault_verify_panel_password($uid, $panelPassword)) {
    // Başarısız deneme de loglanır (şifre YAZILMAZ).
    log_activity('vault_reveal_denied', 'vault', $id, null, 'failed', 'Panel şifresi doğrulanamadı');
    $fail('Panel şifreniz doğrulanamadı.', 403);
}

$item = get_vault_item($id);
if (!$item) { $fail('Kayıt bulunamadı veya görüntüleme yetkiniz yok.', 404); }

$secret = vault_decrypt((string) ($item['secret_enc'] ?? ''));
if ($secret === null) {
    // Şifre yok ya da çözülemedi (anahtar değişmiş olabilir).
    $fail('Şifre çözülemedi. Kayıtta şifre olmayabilir veya anahtar değişmiş olabilir.', 422);
}

vault_log_access($id, 'reveal');

echo json_encode(['ok' => true, 'secret' => $secret, 'username' => (string) ($item['username'] ?? '')], JSON_UNESCAPED_UNICODE);
