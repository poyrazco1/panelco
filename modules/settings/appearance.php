<?php
declare(strict_types=1);

/**
 * modules/settings/appearance.php
 * Kişisel görünüm ayarları (tema + sidebar). Kullanıcı bazlıdır.
 * Her giriş yapmış kullanıcı erişebilir. POST + CSRF + PRG (JS gerekmeden çalışır).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/preferences.php';

auth_boot();
if (!is_logged_in()) { http_response_code(303); redirect('login.php'); }

$uid = current_user_id() ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $theme = (string) ($_POST['theme'] ?? 'system');
    $sidebar = (string) ($_POST['sidebar_state'] ?? 'expanded');
    if (!pref_is_valid_theme($theme)) { $theme = 'system'; }
    if (!pref_is_valid_sidebar($sidebar)) { $sidebar = 'expanded'; }
    set_user_preference($uid, 'theme', $theme);
    set_user_preference($uid, 'sidebar_state', $sidebar);
    flash('success', 'Görünüm tercihleriniz kaydedildi.');
    http_response_code(303);
    redirect('modules/settings/appearance.php');
}

$prefs = get_user_ui_preferences($uid);
$curTheme = pref_is_valid_theme($prefs['theme'] ?? '') ? $prefs['theme'] : 'system';
$curSidebar = pref_is_valid_sidebar($prefs['sidebar_state'] ?? '') ? $prefs['sidebar_state'] : 'expanded';

$themeOpts = ['light' => ['Light', 'sun'], 'dark' => ['Dark', 'moon'], 'system' => ['Sistem Varsayılanı', 'monitor']];
$sidebarOpts = ['expanded' => ['Geniş (ikon + yazı)', 'panel-left-open'], 'collapsed' => ['Dar (sadece ikon)', 'panel-left-close'], 'hidden' => ['Gizli', 'menu']];

layout_top('Kişisel Görünüm Ayarları', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Kişisel Görünüm Ayarları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<p class="muted" style="margin-top:-6px">Bu ayarlar yalnızca sizin hesabınız içindir; diğer kullanıcıları etkilemez. Header'daki tema ve kenar çubuğu butonlarıyla da anında değiştirebilirsiniz.</p>

<form method="post" action="<?= e(url('modules/settings/appearance.php')) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:640px">
        <div class="card-header"><h2>Tema</h2></div>
        <div class="card-body">
            <div class="pref-choices">
                <?php foreach ($themeOpts as $val => [$label, $ic]): ?>
                    <label class="pref-choice<?= $curTheme === $val ? ' is-selected' : '' ?>">
                        <input type="radio" name="theme" value="<?= e($val) ?>"<?= $curTheme === $val ? ' checked' : '' ?>>
                        <span class="pref-ic"><?= icon($ic) ?></span>
                        <span class="pref-label"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:640px">
        <div class="card-header"><h2>Kenar Çubuğu (Sidebar)</h2></div>
        <div class="card-body">
            <div class="pref-choices">
                <?php foreach ($sidebarOpts as $val => [$label, $ic]): ?>
                    <label class="pref-choice<?= $curSidebar === $val ? ' is-selected' : '' ?>">
                        <input type="radio" name="sidebar_state" value="<?= e($val) ?>"<?= $curSidebar === $val ? ' checked' : '' ?>>
                        <span class="pref-ic"><?= icon($ic) ?></span>
                        <span class="pref-label"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="field-hint">Mobil görünümde kenar çubuğu her zaman hamburger menüyle açılır; bu tercih masaüstünü etkiler.</p>
        </div>
    </div>

    <div class="form-actions" style="max-width:640px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/settings/index.php')) ?>">Vazgeç</a>
    </div>
</form>

<?php
layout_bottom();
