<?php
declare(strict_types=1);

/**
 * modules/leads/my-lists.php — Kullanıcının çalışma listeleri (§12):
 * Arama Listem / WhatsApp Listem. Sıradaki lead'i hızlıca aç, işini yap, çıkar.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';

auth_boot();
require_permission('leads.view');

$pid  = current_personnel_id();
$type = (string) ($_GET['type'] ?? 'call');
if (!isset(lead_worklist_types()[$type])) { $type = 'call'; }

$items = $pid > 0 ? lead_worklist_items($pid, $type) : [];

layout_top('Çalışma Listem', 'leads');
$actUrl = e(url('modules/leads/lead-action.php'));
?>
<div class="page-head">
    <h1 class="page-title">Çalışma Listem</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Lead Listesi</a></div>
</div>
<?= render_flashes() ?>

<div class="tab-row">
    <?php foreach (lead_worklist_types() as $wt => $wl): $cnt = $pid > 0 ? lead_worklist_count($pid, $wt) : 0; ?>
        <a class="tab-chip<?= $type === $wt ? ' is-active' : '' ?>" href="<?= e(url('modules/leads/my-lists.php?type=' . $wt)) ?>"><?= e($wl) ?> <span class="tab-count"><?= (int) $cnt ?></span></a>
    <?php endforeach; ?>
</div>

<?php if ($pid <= 0): ?>
    <div class="alert alert-warning">Çalışma listesi kullanabilmek için hesabınıza bağlı bir personel kaydı gerekli. Yöneticinizle görüşün.</div>
<?php elseif (!$items): ?>
    <div class="card"><div class="card-body"><div class="empty"><?= e(lead_worklist_types()[$type]) ?> boş. Lead detayından “<?= e(lead_worklist_types()[$type]) ?>” ile ekleyebilirsiniz.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Firma</th><th class="nowrap">Telefon</th><th>Şehir/İlçe</th><th>Durum</th><th class="nowrap">Sonraki aksiyon</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($items as $l):
        $waLink = $type === 'whatsapp' ? lead_wa_link_for($l, lead_wa_render((string) (lead_wa_templates()[0]['body'] ?? ''), $l)) : null; ?>
        <tr>
            <td><a href="<?= e(url('modules/leads/view.php?id=' . (int) $l['id'])) ?>"><strong><?= e((string) $l['company_name']) ?></strong></a></td>
            <td class="nowrap"><?= e((string) ($l['phone'] ?? '')) ?: '—' ?></td>
            <td><?= e(trim((string) ($l['city'] ?? '') . ' / ' . (string) ($l['district'] ?? ''), ' /')) ?: '—' ?></td>
            <td><span class="badge <?= e(lead_status_class((string) $l['status'])) ?>"><?= e(lead_status_label((string) $l['status'])) ?></span></td>
            <td class="nowrap"><?= e((string) ($l['next_action_at'] ?? '')) ?: '—' ?></td>
            <td class="nowrap">
                <?php if ($type === 'call' && !empty($l['phone'])): ?>
                    <a class="btn btn-xs" href="tel:<?= e(lead_normalize_phone((string) $l['phone'])) ?>"><?= icon('phone', 'icon-xs') ?>Ara</a>
                <?php elseif ($type === 'whatsapp' && $waLink): ?>
                    <a class="btn btn-xs quick-action-whatsapp" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>WhatsApp</a>
                <?php endif; ?>
                <a class="btn btn-xs" href="<?= e(url('modules/leads/view.php?id=' . (int) $l['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
                <form method="post" action="<?= $actUrl ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="worklist_remove"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="list_type" value="<?= e($type) ?>"><button class="btn btn-xs" title="Listeden çıkar"><?= icon('x', 'icon-xs') ?></button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
