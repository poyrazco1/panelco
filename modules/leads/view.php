<?php
declare(strict_types=1);

/** modules/leads/view.php — Lead detayı + WhatsApp (tekil, onaylı) + durum. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';

auth_boot();
require_permission('leads.view');

$id = (int) ($_GET['id'] ?? 0);
$l  = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }

$wa = can('leads.whatsapp') ? lead_whatsapp_link($l) : null;
$blacklisted = lead_is_blacklisted($l);

layout_top('Lead: ' . $l['company_name'], 'leads');
$row = static fn(string $lab, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($lab) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title"><?= e($l['company_name']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>">← Lead Yönetimi</a>
    <?php if (can('leads.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/leads/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<div class="card" style="max-width:820px"><div class="card-header"><h2>Lead Bilgileri</h2>
    <span class="badge <?= e(lead_status_class((string) $l['status'])) ?>"><?= e(lead_status_label((string) $l['status'])) ?></span></div>
    <div class="card-body"><div class="dl">
        <?= $row('Yetkili', $l['contact_name']) ?>
        <?= $row('Telefon', $l['phone']) ?>
        <?= $row('WhatsApp', $l['whatsapp']) ?>
        <?= $row('E-posta', $l['email']) ?>
        <?= $row('Web sitesi', $l['website']) ?>
        <?= $row('Instagram', $l['instagram']) ?>
        <?= $row('Google Maps', $l['maps_url']) ?>
        <?= $row('Sektör', $l['sector']) ?>
        <?= $row('İl / İlçe', trim((string) ($l['city'] ?? '') . ' / ' . (string) ($l['district'] ?? ''), ' /')) ?>
        <?= $row('Kaynak', $l['source']) ?>
        <?= $row('Son mesaj', $l['last_message_at']) ?>
        <?= $row('Not', $l['notes']) ?>
    </div></div>
</div>

<div class="card" style="max-width:820px"><div class="card-header"><h2>İşlemler</h2></div><div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <?php if (can('leads.whatsapp')): ?>
        <?php if ($blacklisted): ?>
            <span class="birthday-note">Kara listedeki lead'e mesaj gönderilemez.</span>
        <?php elseif ($wa): ?>
            <a class="btn btn-sm quick-action-btn quick-action-whatsapp" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>WhatsApp Mesajı Aç</a>
            <form method="post" action="<?= e(url('modules/leads/message.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                <button type="submit" class="btn btn-sm"><?= icon('check-circle') ?>Gönderildi İşaretle</button>
            </form>
        <?php else: ?>
            <span class="birthday-note">WhatsApp linki için telefon/WhatsApp numarası gerekli.</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (can('leads.edit')): ?>
    <form method="post" action="<?= e(url('modules/leads/status.php')) ?>" style="display:flex;gap:8px;align-items:flex-end;margin-left:auto">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group" style="margin:0"><label for="new_status">Durum</label>
            <select id="new_status" name="status"><?php foreach (lead_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) $l['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-sm">Güncelle</button>
    </form>
    <?php endif; ?>
</div></div>

<p class="muted small" style="max-width:820px">Not: Bu panelde otomatik toplu mesaj gönderimi yoktur. WhatsApp mesajı yalnızca yukarıdaki butonla, kullanıcı onayıyla açılır.</p>
<?php layout_bottom();
