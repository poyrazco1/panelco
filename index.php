<?php
declare(strict_types=1);

/**
 * index.php
 * Herkese açık ana sayfa: tam ekran video hero + liquid-glass giriş.
 * poyraztech.com açıldığında bu görünür; "Giriş Yap" ile giriş modalı açılır.
 * Marka (yazı/logo/favicon) ve hero metinleri Ayarlar → Şirket Bilgileri'nden gelir.
 */
require_once __DIR__ . '/includes/auth.php';

auth_boot();

if (is_logged_in()) {
    redirect('dashboard.php');
}

// Giriş formu login.php'ye post eder; buraya yalnızca görünüm düşer.
$landing_error      = '';
$landing_identifier = '';

require __DIR__ . '/includes/landing_page.php';
