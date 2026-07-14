<?php
declare(strict_types=1);

/**
 * modules/leads/view.php — Lead detayı: bilgiler + sekmeli takip (aktivite akışı,
 * arama, WhatsApp, not, hatırlatma, durum geçmişi). Tüm işlemler lead-action.php.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';
require_once __DIR__ . '/../../includes/personnel.php';

auth_boot();
require_permission('leads.view');

$id = (int) ($_GET['id'] ?? 0);
$l  = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }

$blacklisted = lead_is_blacklisted($l);
$people      = get_personnel_options(false);
$activities  = lead_activities($id);
$calls       = lead_call_logs($id);
$waLogs      = lead_wa_logs($id);
$notes       = lead_notes_list($id);
$reminders   = lead_reminders_for_lead($id);
$history     = lead_status_history($id);
$waTemplates = lead_wa_templates();
$tab         = (string) ($_GET['tab'] ?? 'activity');

$actUrl = e(url('modules/leads/lead-action.php'));
layout_top('Lead: ' . $l['company_name'], 'leads');
$row = static fn(string $lab, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($lab) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
$actTypeLabel = static fn(string $t): string => [
    'status' => 'Durum', 'call' => 'Arama', 'whatsapp' => 'WhatsApp', 'note' => 'Not',
    'reminder' => 'Hatırlatma', 'assign' => 'Atama', 'created' => 'Oluşturma', 'mail' => 'E-posta',
][$t] ?? $t;
?>
<div class="page-head">
    <h1 class="page-title"><?= e($l['company_name']) ?> <span class="badge <?= e(lead_status_class((string) $l['status'])) ?>"><?= e(lead_status_label((string) $l['status'])) ?></span></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Liste</a>
        <?php if (can('leads.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/leads/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
        <?php if (can('leads.delete')): ?>
        <form method="post" action="<?= e(url('modules/leads/delete.php')) ?>" style="display:inline" onsubmit="return confirm('Bu lead çöp kutusuna taşınsın mı?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-sm"><?= icon('trash-2') ?>Sil</button></form>
        <?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>

<div class="lead-detail-grid" style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start">
    <!-- SOL: Sekmeler -->
    <div>
        <div class="tab-row">
            <?php
            $tabs = ['activity' => 'Akış (' . count($activities) . ')', 'calls' => 'Aramalar (' . count($calls) . ')',
                     'whatsapp' => 'WhatsApp (' . count($waLogs) . ')', 'notes' => 'Notlar (' . count($notes) . ')',
                     'reminders' => 'Hatırlatmalar (' . count($reminders) . ')', 'history' => 'Durum Geçmişi'];
            foreach ($tabs as $tk => $tl): ?>
                <a class="tab-chip<?= $tab === $tk ? ' is-active' : '' ?>" href="<?= e(url('modules/leads/view.php?id=' . $id . '&tab=' . $tk)) ?>"><?= e($tl) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'activity'): ?>
            <div class="card"><div class="card-body">
                <?php if (!$activities): ?><div class="empty">Henüz aktivite yok.</div><?php else: ?>
                <ul class="timeline" style="list-style:none;margin:0;padding:0">
                    <?php foreach ($activities as $a): ?>
                        <li style="padding:9px 0;border-bottom:1px solid var(--border,#eee)">
                            <span class="badge badge-muted"><?= e($actTypeLabel((string) $a['type'])) ?></span>
                            <?= e((string) $a['summary']) ?>
                            <div class="muted" style="font-size:12px"><?= e((string) $a['created_at']) ?><?= $a['full_name'] ? ' · ' . e((string) $a['full_name']) : '' ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div></div>

        <?php elseif ($tab === 'calls'): ?>
            <?php if (can('leads.call') && !$blacklisted): ?>
            <div class="card"><div class="card-header"><strong>Arama Kaydı Ekle</strong></div><div class="card-body">
                <form method="post" action="<?= $actUrl ?>"><?= csrf_field() ?><input type="hidden" name="action" value="call"><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-row">
                        <div class="form-group"><label>Sonuç</label><select name="result"><?php foreach (lead_call_results() as $rk => $rl): ?><option value="<?= e($rk) ?>"><?= e($rl) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Görüşülen kişi</label><input type="text" name="contact_person"></div>
                        <div class="form-group"><label>Süre (sn)</label><input type="number" name="duration_sec" min="0" style="max-width:110px"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:2"><label>Not</label><input type="text" name="note" placeholder="Görüşme özeti"></div>
                        <div class="form-group"><label>Sonraki aksiyon tarihi</label><input type="datetime-local" name="next_at"></div>
                    </div>
                    <button class="btn btn-primary btn-sm"><?= icon('phone') ?>Kaydet</button>
                </form>
            </div></div>
            <?php endif; ?>
            <div class="card"><div class="card-body">
                <?php if (!$calls): ?><div class="empty">Arama kaydı yok.</div><?php else: ?>
                <div class="table-wrap"><table class="table"><thead><tr><th>Tarih</th><th>Sonuç</th><th>Kişi</th><th>Not</th><th>Kullanıcı</th></tr></thead><tbody>
                    <?php foreach ($calls as $c): ?><tr>
                        <td class="nowrap"><?= e((string) $c['created_at']) ?></td>
                        <td><span class="badge badge-info"><?= e(lead_call_result_label((string) $c['result'])) ?></span></td>
                        <td><?= e((string) ($c['contact_person'] ?? '')) ?: '—' ?></td>
                        <td><?= e((string) ($c['note'] ?? '')) ?: '—' ?></td>
                        <td><?= e((string) ($c['full_name'] ?? '')) ?: '—' ?></td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div></div>

        <?php elseif ($tab === 'whatsapp'): ?>
            <?php if (can('leads.whatsapp') && !$blacklisted): ?>
            <div class="card"><div class="card-header"><strong>WhatsApp Mesajı</strong></div><div class="card-body">
                <form method="post" action="<?= $actUrl ?>" id="waForm"><?= csrf_field() ?><input type="hidden" name="action" value="wa"><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><label>Şablon</label>
                        <select id="waTpl" onchange="document.getElementById('waMsg').value=this.value">
                            <?php foreach ($waTemplates as $ti => $t): ?><option value="<?= e(lead_wa_render((string) $t['body'], $l)) ?>"><?= e((string) $t['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Mesaj</label><textarea id="waMsg" name="message" rows="4"><?= e(lead_wa_render((string) ($waTemplates[0]['body'] ?? ''), $l)) ?></textarea></div>
                    <?php $waLink = lead_wa_link_for($l, lead_wa_render((string) ($waTemplates[0]['body'] ?? ''), $l)); ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="btn btn-sm quick-action-btn quick-action-whatsapp" formtarget="_blank" onclick="var d=this.form.message.value; window.open('https://wa.me/<?= e(ltrim(preg_replace('/\D+/','', lead_normalize_phone((string)($l['whatsapp'] ?? $l['phone'] ?? ''))) ?? '', '0')) ?>?text='+encodeURIComponent(d),'_blank'); return true;"><?= icon('message-circle') ?>Aç ve Gönderildi İşaretle</button>
                    </div>
                    <div class="field-hint">Butona basınca WhatsApp yeni sekmede açılır ve gönderim kaydı oluşturulur. Otomatik toplu gönderim yoktur.</div>
                </form>
            </div></div>
            <?php elseif ($blacklisted): ?>
            <div class="alert alert-warning">Kara listedeki lead'e mesaj gönderilemez.</div>
            <?php endif; ?>
            <div class="card"><div class="card-body">
                <?php if (!$waLogs): ?><div class="empty">WhatsApp kaydı yok.</div><?php else: ?>
                <div class="table-wrap"><table class="table"><thead><tr><th>Tarih</th><th>Telefon</th><th>Mesaj</th><th>Kullanıcı</th></tr></thead><tbody>
                    <?php foreach ($waLogs as $w): ?><tr>
                        <td class="nowrap"><?= e((string) $w['created_at']) ?></td>
                        <td class="nowrap"><?= e((string) $w['phone']) ?></td>
                        <td><?= e(mb_strimwidth((string) ($w['message'] ?? ''), 0, 80, '…')) ?></td>
                        <td><?= e((string) ($w['full_name'] ?? '')) ?: '—' ?></td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div></div>

        <?php elseif ($tab === 'notes'): ?>
            <?php if (can('leads.note')): ?>
            <div class="card"><div class="card-body">
                <form method="post" action="<?= $actUrl ?>"><?= csrf_field() ?><input type="hidden" name="action" value="note"><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><textarea name="note" rows="3" placeholder="Not ekleyin…" required></textarea></div>
                    <button class="btn btn-primary btn-sm"><?= icon('plus') ?>Not Ekle</button>
                </form>
            </div></div>
            <?php endif; ?>
            <div class="card"><div class="card-body">
                <?php if (!$notes): ?><div class="empty">Not yok.</div><?php else: ?>
                <?php foreach ($notes as $n): ?>
                    <div style="padding:8px 0;border-bottom:1px solid var(--border,#eee)"><?= nl2br(e((string) $n['note'])) ?>
                        <div class="muted" style="font-size:12px"><?= e((string) $n['created_at']) ?><?= $n['full_name'] ? ' · ' . e((string) $n['full_name']) : '' ?></div></div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div></div>

        <?php elseif ($tab === 'reminders'): ?>
            <?php if (can('leads.remind')): ?>
            <div class="card"><div class="card-header"><strong>Hatırlatma Ekle</strong></div><div class="card-body">
                <form method="post" action="<?= $actUrl ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reminder"><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-row">
                        <div class="form-group"><label>Tür</label><select name="type"><?php foreach (lead_reminder_types() as $rk => $rl): ?><option value="<?= e($rk) ?>"><?= e($rl) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Tarih/saat</label><input type="datetime-local" name="remind_at" required></div>
                        <div class="form-group"><label>Sorumlu</label><select name="assigned_to"><option value="0">Ben</option><?php foreach ($people as $pid => $pn): ?><option value="<?= (int) $pid ?>"><?= e($pn) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="form-group"><label>Not</label><input type="text" name="note"></div>
                    <button class="btn btn-primary btn-sm"><?= icon('bell') ?>Ekle</button>
                </form>
            </div></div>
            <?php endif; ?>
            <div class="card"><div class="card-body">
                <?php if (!$reminders): ?><div class="empty">Hatırlatma yok.</div><?php else: ?>
                <?php foreach ($reminders as $rm): $done = (int) $rm['is_done'] === 1; ?>
                    <div style="padding:8px 0;border-bottom:1px solid var(--border,#eee);display:flex;justify-content:space-between;gap:10px;<?= $done ? 'opacity:.55' : '' ?>">
                        <div><span class="badge badge-muted"><?= e(lead_reminder_types()[$rm['type']] ?? $rm['type']) ?></span>
                            <strong><?= e((string) $rm['remind_at']) ?></strong> <?= e((string) ($rm['note'] ?? '')) ?>
                            <div class="muted" style="font-size:12px"><?= e((string) ($rm['assignee'] ?? '')) ?></div></div>
                        <?php if (!$done && can('leads.remind')): ?>
                        <form method="post" action="<?= $actUrl ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reminder_done"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="reminder_id" value="<?= (int) $rm['id'] ?>"><button class="btn btn-xs"><?= icon('check') ?>Tamam</button></form>
                        <?php elseif ($done): ?><span class="badge badge-success">Tamamlandı</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div></div>

        <?php elseif ($tab === 'history'): ?>
            <div class="card"><div class="card-body">
                <?php if (!$history): ?><div class="empty">Durum değişikliği yok.</div><?php else: ?>
                <div class="table-wrap"><table class="table"><thead><tr><th>Tarih</th><th>Eski</th><th>Yeni</th><th>Not</th><th>Kullanıcı</th></tr></thead><tbody>
                    <?php foreach ($history as $h): ?><tr>
                        <td class="nowrap"><?= e((string) $h['created_at']) ?></td>
                        <td><span class="badge <?= e(lead_status_class((string) $h['old_status'])) ?>"><?= e(lead_status_label((string) $h['old_status'])) ?></span></td>
                        <td><span class="badge <?= e(lead_status_class((string) $h['new_status'])) ?>"><?= e(lead_status_label((string) $h['new_status'])) ?></span></td>
                        <td><?= e((string) ($h['note'] ?? '')) ?: '—' ?></td>
                        <td><?= e((string) ($h['full_name'] ?? '')) ?: '—' ?></td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div></div>
        <?php endif; ?>
    </div>

    <!-- SAĞ: Bilgi kartı + hızlı işlemler -->
    <div>
        <div class="card"><div class="card-header"><strong>Bilgiler</strong></div><div class="card-body"><div class="dl">
            <?= $row('Yetkili', $l['contact_name']) ?>
            <?= $row('Telefon', $l['phone']) ?>
            <?= $row('Alternatif tel.', $l['alt_phone'] ?? '') ?>
            <?= $row('WhatsApp', $l['whatsapp']) ?>
            <?= $row('E-posta', $l['email']) ?>
            <?= $row('Web sitesi', $l['website']) ?>
            <?= $row('Kategori', $l['main_category'] ?? $l['sector']) ?>
            <?= $row('İl / İlçe', trim((string) ($l['city'] ?? '') . ' / ' . (string) ($l['district'] ?? ''), ' /')) ?>
            <?= $row('Adres', $l['address'] ?? '') ?>
            <?= $row('Puan', $l['google_rating'] !== null ? (string) $l['google_rating'] . ' (' . (int) ($l['review_count'] ?? 0) . ')' : '') ?>
            <?= $row('Çalışma durumu', $l['working_status'] ?? '') ?>
            <?= $row('Kaynak', $l['source']) ?>
            <?= $row('Aranan kelime', $l['search_keyword'] ?? '') ?>
            <?= $row('Son iletişim', $l['last_contact_at'] ?? '') ?>
            <?php if (!empty($l['maps_url'])): ?><div class="dl-row"><span class="dl-k">Harita</span><span class="dl-v"><a href="<?= e((string) $l['maps_url']) ?>" target="_blank" rel="noopener">Google Maps</a></span></div><?php endif; ?>
        </div></div></div>

        <?php if (can('leads.edit')): ?>
        <div class="card"><div class="card-header"><strong>Durum & Atama</strong></div><div class="card-body">
            <form method="post" action="<?= $actUrl ?>" style="margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $id ?>">
                <div class="form-group"><label>Durum</label><select name="status"><?php foreach (lead_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) $l['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><input type="text" name="status_note" placeholder="Durum notu (ops.)"></div>
                <button class="btn btn-sm btn-primary"><?= icon('refresh-cw') ?>Durumu Güncelle</button>
            </form>
            <form method="post" action="<?= $actUrl ?>"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="<?= $id ?>">
                <div class="form-group"><label>Sorumlu personel</label><select name="personnel_id"><option value="0">— Yok —</option><?php foreach ($people as $pid => $pn): ?><option value="<?= (int) $pid ?>"<?= (int) ($l['assigned_personnel_id'] ?? 0) === (int) $pid ? ' selected' : '' ?>><?= e($pn) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-sm"><?= icon('user-check') ?>Ata</button>
            </form>
        </div></div>
        <?php endif; ?>

        <?php if (can('leads.worklist')): ?>
        <div class="card"><div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap">
            <?php foreach (lead_worklist_types() as $wt => $wl): $inList = lead_worklist_has($id, $wt); ?>
                <form method="post" action="<?= $actUrl ?>"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $inList ? 'worklist_remove' : 'worklist_add' ?>">
                    <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="list_type" value="<?= e($wt) ?>">
                    <button class="btn btn-xs <?= $inList ? 'btn-primary' : '' ?>"><?= icon($inList ? 'check' : 'plus', 'icon-xs') ?><?= e($wl) ?></button>
                </form>
            <?php endforeach; ?>
        </div></div>
        <?php endif; ?>
    </div>
</div>
<?php layout_bottom();
