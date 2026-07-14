<?php
declare(strict_types=1);

/**
 * login.php
 * Giriş işleyici. Görünüm includes/landing_page.php ile ana sayfayla aynıdır
 * (tam ekran video hero + liquid-glass giriş modalı). React yoktur.
 */
require_once __DIR__ . '/includes/auth.php';

auth_boot();

// Zaten giriş yapılmışsa panele
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error      = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $identifier = input('identifier');
    $password   = $_POST['password'] ?? '';
    $remember   = !empty($_POST['remember']);

    if ($identifier === '' || $password === '') {
        $error = 'Kullanıcı adı/e-posta ve şifre gereklidir.';
    } elseif (auth_login($identifier, $password, $remember)) {
        redirect('dashboard.php');
    } else {
        // Ayrıntısız, güvenli mesaj (deneme login_logs'a yazıldı)
        $error = 'Kullanıcı adı/e-posta veya şifre hatalı. Hesabınız pasif de olabilir.';
    }
}

// Giriş sayfası görünümünü (hata varsa modal açık) ana sayfayla aynı şablondan bas.
$landing_error      = $error;
$landing_identifier = $identifier;
$landing_noindex    = true;

require __DIR__ . '/includes/landing_page.php';
