<?php
declare(strict_types=1);

/** modules/integrations/index.php — Entegrasyon merkezi (durum + son test). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/integrations.php';

auth_boot();
require_permission('integrations.view');

$items = get_integrations();
layout_top('Entegrasyonlar', 'integrations');
?>
<div class="page-head"><h1 class="page-title">Entegrasyonlar</h1></div>
<?= render_flashes() ?>

<?php if (!vault_is_configured()): ?>
    <div class="alert alert-info">Not: Secret alanları (token/key/salt) şifreli saklamak için <code>VAULT_KEY</code> gereklidir. Anahtar tanımlanmadan gizli bilgiler kaydedilemez.</div>
<?php endif; ?>

<div class="settings-grid">
    <?php foreach ($items as $key => $it): $def = $it['def']; ?>
        <div class="set-card">
            <span class="set-ic icon-circle"><?= icon($def['icon'] ?? 'plug', 'icon-lg') ?></span>
            <span class="set-body">
                <span class="set-title"><?= e($def['name']) ?>
                    <?= (int) ($it['is_active'] ?? 0) === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?>
                    <?php if (!empty($def['readonly'])): ?><span class="badge badge-info">config.php</span><?php endif; ?>
                </span>
                <span class="set-desc">
                    <?php if (!empty($it['last_test_at'])): ?>
                        Son test: <?= e(fmt_date((string) $it['last_test_at'])) ?>
                        <?= !empty($it['last_error']) ? '· <span style="color:var(--error-fg)">hata</span>' : '· <span style="color:var(--success-fg)">başarılı</span>' ?>
                    <?php else: ?>Henüz test edilmedi.<?php endif; ?>
                </span>
                <span style="margin-top:8px;display:inline-flex;gap:6px">
                    <?php if (can('integrations.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/integrations/edit.php?key=' . $key)) ?>"><?= icon('pencil', 'icon-xs') ?>Ayarlar</a><?php endif; ?>
                </span>
            </span>
        </div>
    <?php endforeach; ?>
</div>
<?php layout_bottom();
