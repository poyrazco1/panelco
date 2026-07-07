<?php
declare(strict_types=1);

/**
 * forgot-password.php
 * Şifre sıfırlama talebi. Token password_resets tablosuna yazılır.
 * SMTP yapılandırılmadığı için (geliştirme) bağlantı ekranda gösterilir.
 */
require_once __DIR__ . '/includes/auth.php';

auth_boot();

if (is_logged_in()) {
    redirect('dashboard.php');
}

$done      = false;
$error     = '';
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $identifier = input('identifier');

    if ($identifier === '') {
        $error = 'Kullanıcı adı veya e-posta girin.';
    } else {
        try {
            $stmt = db()->prepare(
                'SELECT id, email, username, is_active FROM users
                 WHERE username = :u OR email = :e LIMIT 1'
            );
            $stmt->execute([':u' => $identifier, ':e' => $identifier]);
            $user = $stmt->fetch();

            if ($user && (int) $user['is_active'] === 1) {
                $token   = bin2hex(random_bytes(32));
                $hash    = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 saat

                // Aynı kullanıcının eski kullanılmamış token'larını geçersiz kıl
                db()->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid AND used = 0')
                    ->execute([':uid' => $user['id']]);

                db()->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at, used)
                     VALUES (:uid, :t, :e, 0)'
                )->execute([':uid' => $user['id'], ':t' => $hash, ':e' => $expires]);

                $link = url('reset-password.php?token=' . $token);
                log_error('Şifre sıfırlama bağlantısı (' . $user['username'] . '): ' . $link);

                // SMTP eklendiğinde e-posta gönderilir; şu an ekranda gösterilir.
                if (!send_password_reset_email((string) $user['email'], $link)) {
                    $resetLink = $link;
                }
            } else {
                log_error('Şifre sıfırlama: eşleşme yok/pasif -> ' . $identifier);
            }
        } catch (Throwable $ex) {
            log_error('forgot-password hata: ' . $ex->getMessage());
        }
    }

    if ($error === '') {
        $done = true;
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Şifremi unuttum · <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-wrap">
        <div class="auth-card">
            <div class="auth-head">
                <div class="auth-logo" aria-hidden="true"></div>
                <h1>Şifremi unuttum</h1>
                <p>Sıfırlama bağlantısı oluşturalım</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($done): ?>
                <div class="alert alert-info">
                    Girdiğiniz hesap kayıtlıysa, şifre sıfırlama bağlantısı oluşturuldu.
                </div>

                <?php if ($resetLink !== null): ?>
                    <div class="dev-note">
                        <strong>Geliştirme notu:</strong> E-posta gönderimi (SMTP) henüz
                        yapılandırılmadığı için bağlantı burada gösteriliyor:<br>
                        <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
                    </div>
                <?php endif; ?>

                <div class="auth-links">
                    <a href="<?= e(url('login.php')) ?>">Girişe dön</a>
                </div>
            <?php else: ?>
                <form class="auth-form" method="post" action="<?= e(url('forgot-password.php')) ?>"
                      data-lock-on-submit novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="identifier">Kullanıcı adı veya e-posta</label>
                        <input type="text" id="identifier" name="identifier" autofocus required>
                    </div>
                    <button type="submit" class="btn-primary">Sıfırlama bağlantısı oluştur</button>
                </form>
                <div class="auth-links">
                    <a href="<?= e(url('login.php')) ?>">Girişe dön</a>
                </div>
            <?php endif; ?>
        </div>

        <p class="auth-foot">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
    </main>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
