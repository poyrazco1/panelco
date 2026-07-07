<?php
declare(strict_types=1);

/**
 * reset-password.php
 * Geçerli (kullanılmamış, süresi dolmamış) token ile yeni şifre belirleme.
 */
require_once __DIR__ . '/includes/auth.php';

auth_boot();

if (is_logged_in()) {
    redirect('dashboard.php');
}

/**
 * Token'a karşılık gelen geçerli sıfırlama kaydını bulur.
 */
function reset_find(string $rawToken): ?array
{
    if ($rawToken === '') {
        return null;
    }
    $hash = hash('sha256', $rawToken);
    try {
        $stmt = db()->prepare(
            'SELECT pr.id AS pr_id, pr.user_id, u.username
             FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = :t AND pr.used = 0 AND pr.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':t' => $hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('reset_find hatası: ' . $e->getMessage());
        return null;
    }
}

$error    = '';
$rawToken = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['token'] ?? '')
    : (string) ($_GET['token'] ?? '');
$rawToken = trim($rawToken);

$record = reset_find($rawToken);
$valid  = $record !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!$valid) {
        $error = 'Sıfırlama bağlantısı geçersiz ya da süresi dolmuş.';
    } else {
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password_confirm'] ?? '';

        if (strlen($pass) < 8) {
            $error = 'Şifre en az 8 karakter olmalıdır.';
        } elseif ($pass !== $pass2) {
            $error = 'Şifreler eşleşmiyor.';
        } else {
            try {
                $pdo = db();
                // Yeni şifre
                $pdo->prepare('UPDATE users SET password_hash = :h, remember_token = NULL WHERE id = :id')
                    ->execute([':h' => password_hash($pass, PASSWORD_DEFAULT), ':id' => $record['user_id']]);
                // Token(ler)i kullanılmış işaretle (tek kullanım)
                $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :uid AND used = 0')
                    ->execute([':uid' => $record['user_id']]);

                flash('success', 'Şifreniz güncellendi. Şimdi giriş yapabilirsiniz.');
                redirect('login.php');
            } catch (Throwable $ex) {
                $error = safe_error('Şifre güncellenemedi, lütfen tekrar deneyin.',
                    'reset-password update: ' . $ex->getMessage());
            }
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Yeni şifre · <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-wrap">
        <div class="auth-card">
            <div class="auth-head">
                <div class="auth-logo" aria-hidden="true"></div>
                <h1>Yeni şifre belirle</h1>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (!$valid && $error === ''): ?>
                <div class="alert alert-error">
                    Sıfırlama bağlantısı geçersiz ya da süresi dolmuş. Lütfen yeni bir bağlantı isteyin.
                </div>
                <div class="auth-links">
                    <a href="<?= e(url('forgot-password.php')) ?>">Yeni bağlantı iste</a>
                </div>
            <?php else: ?>
                <form class="auth-form" method="post" action="<?= e(url('reset-password.php')) ?>"
                      data-lock-on-submit novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($rawToken) ?>">

                    <div class="field">
                        <label for="password">Yeni şifre</label>
                        <div class="pw">
                            <input type="password" id="password" name="password"
                                   autocomplete="new-password" required>
                            <button type="button" class="pw-toggle"
                                    data-toggle-password data-target="#password"
                                    aria-pressed="false">Göster</button>
                        </div>
                        <div class="field-hint">En az 8 karakter.</div>
                    </div>

                    <div class="field">
                        <label for="password_confirm">Yeni şifre (tekrar)</label>
                        <div class="pw">
                            <input type="password" id="password_confirm" name="password_confirm"
                                   autocomplete="new-password" required>
                            <button type="button" class="pw-toggle"
                                    data-toggle-password data-target="#password_confirm"
                                    aria-pressed="false">Göster</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Şifreyi güncelle</button>
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
