<?php
declare(strict_types=1);

/**
 * modules/international-customers/view.php — Yurtdışı müşteri kartı.
 * Tüm detaylar + kişiler + markalar + tip/kategori + notlar + durum geçmişi
 * + mesaj geçmişi + hızlı işlemler (durum değiştir, kara liste, mesaj hazırla).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';

auth_boot();
require_permission('international_customers.view');

$id = (int) ($_GET['id'] ?? 0);
$c  = ic_get($id);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }

$contacts = ic_contacts($id);
$brands   = ic_brands($id);
$typeIds  = ic_customer_type_ids($id);
$catIds   = ic_customer_category_ids($id);
$notes    = ic_notes($id);
$logs     = ic_status_logs($id);
$messages = ic_messages($id);
$statuses = ic_statuses();
$allTypes = ic_company_types();
$allCats  = ic_categories();
$typeNames = [];
foreach ($allTypes as $t) { if (in_array((int) $t['id'], $typeIds, true)) { $typeNames[] = $t['name']; } }
$bl = ic_is_blacklisted($c);

layout_top($c['company_name'], 'international_customers');
?>
<div class="page-head">
    <h1 class="page-title"><?= e($c['company_name']) ?>
        <?php if (!empty($c['status_name'])): ?><span class="badge <?= e(ic_status_badge_class((string) $c['status_color'])) ?>"><?= e($c['status_name']) ?></span><?php endif; ?>
        <?php if ($bl): ?><span class="badge badge-danger">Kara Liste</span><?php endif; ?>
        <?php if ((int) $c['contact_permission'] === 0): ?><span class="badge badge-muted">İletişim İzni Yok</span><?php endif; ?>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/international-customers/index.php')) ?>">← Liste</a>
        <?php if ((can('international_customers.message_mail') || can('international_customers.message_whatsapp')) && !$bl): ?>
            <a class="btn btn-sm" href="<?= e(url('modules/international-customers/message.php?ids[]=' . $id)) ?>"><?= icon('send') ?>Mesaj Hazırla</a>
        <?php endif; ?>
        <?php if (can('international_customers.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/international-customers/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
    </div>
</div>
<div class="muted small" style="margin:-6px 0 12px"><?= e($c['record_no']) ?> · <span class="badge badge-info"><?= e(ic_company_role_label((string) $c['company_role'])) ?></span></div>
<?= render_flashes() ?>

<div class="ic-view-grid">
  <div class="ic-view-main">
    <!-- Firma detayları -->
    <div class="card"><div class="card-header"><strong>Firma Bilgileri</strong></div><div class="card-body">
        <dl class="detail-list">
            <div><dt>Ülke / Şehir</dt><dd><?= e((string) ($c['country'] ?? '')) ?: '—' ?><?= !empty($c['city']) ? ' · ' . e($c['city']) : '' ?></dd></div>
            <div><dt>Adres</dt><dd><?= nl2br(e((string) ($c['address'] ?? ''))) ?: '—' ?></dd></div>
            <div><dt>E-posta</dt><dd><?php if (!empty($c['email'])): ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php else: ?>—<?php endif; ?></dd></div>
            <div><dt>Web</dt><dd><?php if (!empty($c['website'])): ?><a href="<?= e($c['website']) ?>" target="_blank" rel="noopener"><?= e($c['website']) ?></a><?php else: ?>—<?php endif; ?></dd></div>
            <div><dt>Telefon</dt><dd><?= e((string) ($c['phone'] ?? '')) ?: '—' ?></dd></div>
            <div><dt>WhatsApp</dt><dd><?= e((string) ($c['whatsapp'] ?? '')) ?: '—' ?></dd></div>
            <div><dt>Vergi / Kayıt No</dt><dd><?= e((string) ($c['tax_no'] ?? '')) ?: '—' ?></dd></div>
            <div><dt>Para Birimi / Dil</dt><dd><?= e((string) ($c['currency'] ?? '')) ?: '—' ?><?= !empty($c['language']) ? ' · ' . e($c['language']) : '' ?></dd></div>
            <div><dt>Şirket Tipleri</dt><dd><?= $typeNames ? e(implode(', ', $typeNames)) : '—' ?></dd></div>
            <div><dt>Kategoriler</dt><dd><?php $cn = [];foreach ($catIds as $ci) { $cn[] = ic_category_label($ci, $allCats); } echo $cn ? e(implode(', ', array_filter($cn))) : '—'; ?></dd></div>
            <div><dt>Veri Kaynağı</dt><dd><?= e((string) ($c['data_source'] ?? '')) ?: '—' ?></dd></div>
            <div><dt>Fuar / Etkinlik</dt><dd><?= e((string) ($c['event_name'] ?? '')) ?: '—' ?></dd></div>
            <div><dt>İlişki / İletişim Durumu</dt><dd><?= e((string) ($c['relationship_status'] ?? '')) ?: '—' ?><?= !empty($c['communication_status']) ? ' · ' . e($c['communication_status']) : '' ?></dd></div>
        </dl>
        <?php if (!empty($c['notes'])): ?><div class="note-box"><strong>Açıklama:</strong><br><?= nl2br(e((string) $c['notes'])) ?></div><?php endif; ?>
    </div></div>

    <!-- Kişiler -->
    <?php if (can('international_customers.view_contacts')): ?>
    <div class="card"><div class="card-header"><strong>Kişiler</strong> <span class="muted small">(<?= count($contacts) ?>)</span></div><div class="card-body">
        <?php if ($contacts): ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Ad Soyad</th><th>Ünvan / Departman</th><th>İletişim</th><th></th></tr></thead><tbody>
        <?php foreach ($contacts as $ct): ?>
            <tr>
                <td><strong><?= e($ct['full_name']) ?></strong><?php if ((int) $ct['is_primary'] === 1): ?> <span class="badge badge-success">Birincil</span><?php endif; ?></td>
                <td><?= e((string) ($ct['title'] ?? '')) ?><?= !empty($ct['department']) ? ' · ' . e($ct['department']) : '' ?></td>
                <td class="small"><?= e((string) ($ct['email'] ?? '')) ?><?php if (!empty($ct['phone'])): ?><br><?= e($ct['phone']) ?><?php endif; ?></td>
                <td class="nowrap"><?php if (can('international_customers.delete_contacts')): ?>
                    <form method="post" action="<?= e(url('modules/international-customers/contact-delete.php')) ?>" style="display:inline" onsubmit="return confirm('Kişi silinsin mi?')">
                        <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>"><input type="hidden" name="id" value="<?= (int) $ct['id'] ?>">
                        <button class="btn btn-xs"><?= icon('trash-2', 'icon-xs') ?></button>
                    </form><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="empty empty-compact">Kişi eklenmemiş.</div><?php endif; ?>
        <?php if (can('international_customers.create_contacts')): ?>
        <details class="mt"><summary class="btn btn-sm"><?= icon('plus') ?>Kişi Ekle</summary>
        <form method="post" action="<?= e(url('modules/international-customers/contact-save.php')) ?>" class="inline-form mt">
            <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>">
            <div class="form-row">
                <div class="form-group"><label>Ad Soyad *</label><input type="text" name="full_name" required></div>
                <div class="form-group"><label>Ünvan</label><input type="text" name="title"></div>
                <div class="form-group"><label>Departman</label><input type="text" name="department"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>E-posta</label><input type="email" name="email"></div>
                <div class="form-group"><label>Telefon</label><input type="text" name="phone"></div>
                <div class="form-group"><label>WhatsApp</label><input type="text" name="whatsapp"></div>
            </div>
            <label class="check-inline"><input type="checkbox" name="is_primary" value="1"> Birincil kişi</label>
            <div class="form-actions"><button class="btn btn-primary btn-sm">Kişiyi Kaydet</button></div>
        </form></details>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <!-- Markalar -->
    <?php if (can('international_customers.view_brands')): ?>
    <div class="card"><div class="card-header"><strong>Markalar</strong></div><div class="card-body">
        <?php if ($brands): ?>
        <div class="chip-list">
        <?php foreach ($brands as $b): ?>
            <span class="brand-chip"><?= e($b['brand_name']) ?> <em><?= e(ic_brand_relation_label((string) $b['relation_type'])) ?></em>
            <?php if (can('international_customers.edit_brands')): ?>
                <form method="post" action="<?= e(url('modules/international-customers/brand-delete.php')) ?>" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                    <button class="chip-x" title="Kaldır">×</button>
                </form><?php endif; ?></span>
        <?php endforeach; ?>
        </div>
        <?php else: ?><div class="empty empty-compact">Marka eklenmemiş.</div><?php endif; ?>
        <?php if (can('international_customers.edit_brands')): ?>
        <form method="post" action="<?= e(url('modules/international-customers/brand-add.php')) ?>" class="inline-form mt">
            <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>">
            <div class="form-row">
                <div class="form-group"><label>Marka</label><input type="text" name="brand_name" list="brandOptions" required></div>
                <div class="form-group"><label>İlişki</label><select name="relation_type"><?php foreach (ic_brand_relation_types() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="form-group" style="align-self:end"><button class="btn btn-sm"><?= icon('plus') ?>Ekle</button></div>
            </div>
        </form>
        <datalist id="brandOptions"><?php foreach (db()->query("SELECT name FROM brands WHERE is_active=1 ORDER BY sort_order")->fetchAll(PDO::FETCH_COLUMN) as $bn): ?><option value="<?= e((string) $bn) ?>"><?php endforeach; ?></datalist>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <!-- Notlar -->
    <?php if (can('international_customers.view_notes') || can('international_customers.notes')): ?>
    <div class="card"><div class="card-header"><strong>Notlar</strong></div><div class="card-body">
        <?php if (can('international_customers.notes')): ?>
        <form method="post" action="<?= e(url('modules/international-customers/note-save.php')) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>">
            <div class="form-group"><textarea name="body" rows="2" placeholder="Yeni not…" required></textarea></div>
            <div class="form-actions"><button class="btn btn-sm">Not Ekle</button></div>
        </form>
        <?php endif; ?>
        <?php if ($notes): ?><ul class="timeline">
            <?php foreach ($notes as $n): ?><li><div class="tl-body"><?= nl2br(e((string) $n['body'])) ?></div><div class="tl-meta muted small"><?= e((string) $n['created_at']) ?></div></li><?php endforeach; ?>
        </ul><?php else: ?><div class="empty empty-compact">Not yok.</div><?php endif; ?>
    </div></div>
    <?php endif; ?>

    <!-- Mesaj geçmişi -->
    <div class="card"><div class="card-header"><strong>Mesaj Geçmişi</strong></div><div class="card-body">
        <?php if ($messages): ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Tarih</th><th>Kanal</th><th>Konu</th><th>Durum</th></tr></thead><tbody>
        <?php foreach ($messages as $m): ?>
            <tr><td class="small nowrap"><?= e((string) $m['created_at']) ?></td>
                <td><span class="badge badge-info"><?= $m['channel'] === 'whatsapp' ? 'WhatsApp' : 'Mail' ?></span></td>
                <td class="small"><?= e((string) ($m['subject'] ?? '')) ?: '—' ?></td>
                <td><span class="badge badge-muted"><?= e((string) $m['status']) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="empty empty-compact">Henüz mesaj gönderilmemiş.</div><?php endif; ?>
    </div></div>
  </div>

  <!-- Yan panel: hızlı işlemler -->
  <div class="ic-view-side">
    <?php if (can('international_customers.status_change')): ?>
    <div class="card"><div class="card-header"><strong>Durum</strong></div><div class="card-body">
        <form method="post" action="<?= e(url('modules/international-customers/status-change.php')) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>">
            <div class="form-group"><select name="status_id">
                <option value="">—</option>
                <?php foreach ($statuses as $s): ?><option value="<?= (int) $s['id'] ?>"<?= (int) $c['status_id'] === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><input type="text" name="note" placeholder="Not (opsiyonel)"></div>
            <button class="btn btn-sm btn-primary">Durumu Güncelle</button>
        </form>
    </div></div>
    <?php endif; ?>

    <?php if (can('international_customers.blacklist')): ?>
    <div class="card"><div class="card-header"><strong>Kara Liste</strong></div><div class="card-body">
        <form method="post" action="<?= e(url('modules/international-customers/blacklist.php')) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>">
            <input type="hidden" name="on" value="<?= $bl ? '0' : '1' ?>">
            <?php if (!$bl): ?><div class="form-group"><input type="text" name="reason" placeholder="Sebep (opsiyonel)"></div><?php endif; ?>
            <button class="btn btn-sm <?= $bl ? '' : 'btn-danger' ?>"><?= $bl ? 'Kara Listeden Çıkar' : 'Kara Listeye Al' ?></button>
        </form>
        <p class="muted small mt">Kara listedeki müşteriye mail/WhatsApp gönderimi engellenir.</p>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><strong>Durum Geçmişi</strong></div><div class="card-body">
        <?php if ($logs): ?><ul class="timeline sm">
            <?php foreach (array_slice($logs, 0, 15) as $lg): ?><li><div class="tl-body small"><?= e((string) ($lg['old_label'] ?? '—')) ?> → <strong><?= e((string) ($lg['new_label'] ?? '—')) ?></strong><?= !empty($lg['note']) ? '<br><span class="muted">' . e((string) $lg['note']) . '</span>' : '' ?></div><div class="tl-meta muted small"><?= e((string) $lg['created_at']) ?></div></li><?php endforeach; ?>
        </ul><?php else: ?><div class="empty empty-compact">Kayıt yok.</div><?php endif; ?>
    </div></div>
  </div>
</div>
<?php layout_bottom();
