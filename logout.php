<?php
declare(strict_types=1);

/**
 * logout.php
 * Oturumu ve "beni hatırla" token'ını temizler.
 */
require_once __DIR__ . '/includes/auth.php';

auth_boot();
auth_logout();

// Flash gösterebilmek için yeni oturum başlat
auth_boot();
flash('info', 'Çıkış yapıldı.');
redirect('login.php');
