<?php
declare(strict_types=1);

/**
 * login.php
 * Kullanıcı adı veya e-posta ile güvenli giriş.
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

$flashes = get_flashes();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Giriş · <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-wrap">
        <div class="auth-card">
            <div class="auth-head">
                <div class="auth-logo" aria-hidden="true"></div>
                <h1><?= e(SITE_NAME) ?></h1>
                <p>Hesabınıza giriş yapın</p>
            </div>

            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= e(url('login.php')) ?>"
                  data-lock-on-submit novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="identifier">Kullanıcı adı veya e-posta</label>
                    <input type="text" id="identifier" name="identifier"
                           value="<?= e($identifier) ?>" autocomplete="username" autofocus required>
                </div>

                <div class="field">
                    <label for="password">Şifre</label>
                    <div class="pw">
                        <input type="password" id="password" name="password"
                               autocomplete="current-password" required>
                        <button type="button" class="pw-toggle"
                                data-toggle-password data-target="#password"
                                aria-pressed="false">Göster</button>
                    </div>
                </div>

                <div class="auth-row">
                    <label class="check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Beni hatırla</span>
                    </label>
                    <a href="<?= e(url('forgot-password.php')) ?>">Şifremi unuttum</a>
                </div>

                <button type="submit" class="btn-primary">Giriş Yap</button>
            </form>
        </div>

        <p class="auth-foot">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
    </main>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
