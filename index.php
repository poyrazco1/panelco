<?php
declare(strict_types=1);

/**
 * index.php
 * Herkese açık tek sayfalık tanıtım. "Giriş Yap" login.php'ye götürür.
 */
require_once __DIR__ . '/includes/auth.php';

auth_boot();

if (is_logged_in()) {
    redirect('dashboard.php');
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app">
    <div class="landing">
        <header class="landing-header">
            <div class="landing-brand">
                <span class="brand-mark" aria-hidden="true"></span>
                <span><?= e(SITE_NAME) ?></span>
            </div>
            <a class="btn btn-primary btn-sm" href="<?= e(url('login.php')) ?>">Giriş Yap</a>
        </header>

        <main class="landing-main">
            <div class="landing-hero">
                <h1><?= e(SITE_NAME) ?></h1>
                <p>Kullanıcılar, roller, kur çevirici ve ayarların tek yerden yönetildiği
                   sade ve kurumsal yönetim paneli.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Giriş Yap</a>
                </div>
            </div>
        </main>

        <footer class="landing-footer">
            <span class="muted small">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
        </footer>
    </div>
</body>
</html>
