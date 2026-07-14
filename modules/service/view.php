<?php
declare(strict_types=1);

/**
 * modules/service/view.php
 * Servis kaydı detayı + tüm işlemler.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';

auth_boot();
require_permission('service');

$id = (int) ($_GET['id'] ?? 0);
$s  = get_service_record($id);
if (!$s) {
    flash('error', 'Servis kaydı bulunamadı.');
    http_response_code(303);
    redirect('modules/service/index.php');
}

$statuses  = service_statuses();
$approvals = service_approval_labels();
$payments  = service_payment_labels();
$payMethods = service_payment_methods();
$appMethods = service_approval_methods();
$history   = get_service_history($id);
$wa        = service_wa_messages($s);
$waOpened  = service_whatsapp_link($s['customer_phone'] ?? null, $wa['opened']);
$waCost    = service_whatsapp_link($s['customer_phone'] ?? null, $wa['cost']);
$waReady   = service_whatsapp_link($s['customer_phone'] ?? null, $wa['ready']);

layout_top('Servis: ' . $s['reference_code'], 'service');
?>

<div class="page-head">
    <h1 class="page-title"><?= e($s['reference_code']) ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/service/index.php')) ?>">← Liste</a>
        <a class="btn btn-sm" href="<?= e(url('modules/service/intake.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a>
        <a class="btn btn-sm" href="<?= e(url('modules/service/form.php?id=' . $id)) ?>" target="_blank" rel="noopener">Form / Yazdır</a>
    </div>
</div>

<p>
    <span class="badge <?= service_status_class((string) $s['status']) ?>" style="font-size:13px"><?= e($statuses[$s['status']] ?? $s['status']) ?></span>
    <span class="badge badge-muted">Onay: <?= e($approvals[$s['approval_status']] ?? $s['approval_status']) ?></span>
    <span class="badge badge-muted">Ödeme: <?= e($payments[$s['payment_status']] ?? $s['payment_status']) ?></span>
</p>

<div class="svc-grid">
    <div>
        <div class="card">
            <div class="card-header"><h2>Müşteri & Cihaz</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Teslim eden</dt><dd><?= e($s['customer_name']) ?></dd>
                    <dt>Telefon</dt><dd><?= e($s['customer_phone'] ?? '—') ?></dd>
                    <dt>E-posta</dt><dd><?= e($s['customer_email'] ?? '—') ?></dd>
                    <dt>Adres</dt><dd><?= nl2br(e($s['customer_address'] ?? '—')) ?></dd>
                    <dt>Vergi/T.C. No</dt><dd><?= e($s['customer_tax_no'] ?? '—') ?></dd>
                    <dt>Cihaz türü</dt><dd><?= e($s['device_type'] ?? '—') ?></dd>
                    <dt>Marka / Model</dt><dd><?= e(trim(($s['brand_name'] ?? '') . ' ' . ($s['device_model'] ?? ''))) ?: '—' ?></dd>
                    <dt>Miktar</dt><dd><?= (int) $s['quantity'] ?></dd>
                    <dt>Seri No</dt><dd><?= e($s['serial_no'] ?? '—') ?></dd>
                    <dt>Aksesuarlar</dt><dd><?= e($s['accessories'] ?? '—') ?></dd>
                    <dt>Sorun</dt><dd><?= nl2br(e($s['problem_description'] ?? '—')) ?></dd>
                    <dt>Fiziksel durum</dt><dd><?= e($s['physical_condition'] ?? '—') ?></dd>
                    <dt>Teslim tarihi</dt><dd><?= e($s['received_at'] ?? '—') ?></dd>
                    <dt>Dijital onay</dt><dd><?= (int) $s['is_digital_approved'] === 1 ? 'Alındı (' . e((string) $s['digital_approved_at']) . ')' : 'Yok' ?></dd>
                </dl>
                <?php if (!empty($s['received_photo_path'])): ?>
                    <div style="margin-top:12px"><img src="<?= e(url($s['received_photo_path'])) ?>" alt="cihaz" style="max-width:200px;border-radius:6px;border:1px solid var(--border)"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Dış Tamirci & Mali</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Dış tamirci</dt><dd><?= e($s['repairer_name'] ?? '—') ?></dd>
                    <dt>Gönderim</dt><dd><?= e($s['sent_to_repairer_at'] ?? '—') ?></dd>
                    <dt>Dönüş</dt><dd><?= e($s['returned_from_repairer_at'] ?? '—') ?></dd>
                    <dt>Tamirci masrafı</dt><dd><?= $s['repairer_cost'] !== null ? fmt_money((float) $s['repairer_cost']) . ' TL' : '—' ?></dd>
                    <dt>Müşteri tutarı</dt><dd><?= $s['customer_price'] !== null ? fmt_money((float) $s['customer_price']) . ' TL' : '—' ?></dd>
                    <dt>Kâr / hizmet</dt><dd><?= $s['profit_amount'] !== null ? fmt_money((float) $s['profit_amount']) . ' TL' : '—' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Notlar</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>İç not (gizli)</dt><dd><?= !empty($s['internal_note']) ? nl2br(e($s['internal_note'])) : '—' ?></dd>
                    <dt>Müşteri notu (public)</dt><dd><?= !empty($s['customer_public_note']) ? nl2br(e($s['customer_public_note'])) : '—' ?></dd>
                    <dt>Tutar müşteriye açık</dt><dd><?= (int) ($s['show_price_public'] ?? 0) === 1 ? 'Evet' : 'Hayır' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Durum Geçmişi</h2></div>
            <div class="card-body">
                <?php if (!$history): ?>
                    <p class="muted">Kayıt yok.</p>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($history as $h): ?>
                            <li>
                                <div>
                                    <?php if (!empty($h['old_status'])): ?><span class="muted"><?= e(service_status_label((string) $h['old_status'])) ?></span> → <?php endif; ?>
                                    <strong><?= e(service_status_label((string) $h['new_status'])) ?></strong>
                                </div>
                                <?php if (!empty($h['note'])): ?><div class="small"><?= e($h['note']) ?></div><?php endif; ?>
                                <div class="tl-meta"><?= fmt_date($h['created_at']) ?><?= !empty($h['by_username']) ? ' · ' . e($h['by_username']) : '' ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <!-- Durum değiştir -->
        <div class="card">
            <div class="card-header"><h2>Durum Değiştir</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('modules/service/status.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <div class="form-group">
                        <label for="new_status">Yeni durum</label>
                        <select id="new_status" name="new_status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $s['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="status_note">Not</label><input type="text" id="status_note" name="note"></div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('refresh-cw') ?>Durumu güncelle</button>
                </form>
            </div>
        </div>

        <!-- Müşteri onayı -->
        <div class="card">
            <div class="card-header"><h2>Müşteri Onayı</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('modules/service/approve.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <div class="form-group">
                        <label for="approval_status">Onay durumu</label>
                        <select id="approval_status" name="approval_status">
                            <?php foreach ($approvals as $k => $l): ?><option value="<?= e($k) ?>"<?= $s['approval_status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="approval_method">Onay yöntemi</label>
                        <select id="approval_method" name="approval_method">
                            <option value="">—</option>
                            <?php foreach ($appMethods as $k => $l): ?><option value="<?= e($k) ?>"<?= ($s['approval_method'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="approval_note">Onay notu</label><input type="text" id="approval_note" name="approval_note" value="<?= e((string) ($s['approval_note'] ?? '')) ?>"></div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('check-circle') ?>Onayı kaydet</button>
                </form>
            </div>
        </div>

        <!-- Ödeme -->
        <div class="card">
            <div class="card-header"><h2>Ödeme Al</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('modules/service/payment.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <div class="form-group">
                        <label for="payment_status">Ödeme durumu</label>
                        <select id="payment_status" name="payment_status">
                            <?php foreach ($payments as $k => $l): ?><option value="<?= e($k) ?>"<?= $s['payment_status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Yöntem</label>
                        <select id="payment_method" name="payment_method">
                            <option value="">—</option>
                            <?php foreach ($payMethods as $k => $l): ?><option value="<?= e($k) ?>"<?= ($s['payment_method'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="paid_amount">Tahsil edilen tutar (TL)</label><input type="text" id="paid_amount" name="paid_amount" inputmode="decimal" value="<?= e((string) ($s['paid_amount'] ?? '')) ?>"></div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('credit-card') ?>Ödemeyi kaydet</button>
                </form>
            </div>
        </div>

        <!-- Hızlı işlemler -->
        <div class="card">
            <div class="card-header"><h2>Hızlı İşlemler</h2></div>
            <div class="card-body">
                <div class="row-actions" style="flex-wrap:wrap;gap:8px">
                    <form method="post" action="<?= e(url('modules/service/deliver.php')) ?>" style="display:inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                        <button type="submit" class="btn btn-sm"><?= icon('package-check') ?>Teslim edildi</button>
                    </form>
                    <form method="post" action="<?= e(url('modules/service/close.php')) ?>" style="display:inline"
                          data-confirm="<?= (in_array($s['payment_status'], ['pending','partial'], true)) ? 'Ödeme tamamlanmadı. Yine de kaydı kapatmak istiyor musunuz?' : 'Servis kaydı kapatılsın mı?' ?>">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                        <button type="submit" class="btn btn-sm"><?= icon('check-check') ?>Kaydı kapat</button>
                    </form>
                </div>
                <form method="post" action="<?= e(url('modules/service/delete.php')) ?>" style="margin-top:10px"
                      data-confirm="<?= e("Bu kayıt arşive taşınacaktır.\n\nReferans No: " . ($s['reference_code'] ?? '—') . "\nMüşteri: " . ($s['customer_name'] ?? '—') . "\n\nDevam edilsin mi?") ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash-2') ?>Kaydı sil (arşivle)</button>
                </form>
            </div>
        </div>

        <!-- Müşteri Takip Linki -->
        <?php $pubUrl = service_public_url($s); ?>
        <div class="card">
            <div class="card-header"><h2>Müşteri Takip Linki</h2></div>
            <div class="card-body">
                <p class="small" style="margin-top:0">
                    Durum: <?php if ((int) $s['public_tracking_enabled'] === 1): ?><span class="badge badge-success">Açık</span><?php else: ?><span class="badge badge-muted">Kapalı</span><?php endif; ?>
                </p>
                <?php if ((int) $s['public_tracking_enabled'] === 1 && $pubUrl): ?>
                    <div class="form-group">
                        <label for="trk">Link</label>
                        <input type="text" id="trk" value="<?= e($pubUrl) ?>" readonly onclick="this.select()">
                    </div>
                    <div class="row-actions" style="flex-wrap:wrap;gap:8px">
                        <button type="button" class="btn btn-sm" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('trk').value)"><?= icon('copy') ?>Kopyala</button>
                        <?php $waTrk = service_whatsapp_link($s['customer_phone'] ?? null, service_tracking_wa($s)); ?>
                        <?php if ($waTrk): ?><a class="btn btn-sm btn-wa" href="<?= e($waTrk) ?><?= icon('message-circle') ?>" target="_blank" rel="noopener">WhatsApp ile gönder</a><?php endif; ?>
                        <a class="btn btn-sm" href="<?= e($pubUrl) ?>" target="_blank" rel="noopener"><?= icon('eye') ?>Önizle</a>
                    </div>
                <?php else: ?>
                    <p class="muted small">Public takip kapalı.</p>
                <?php endif; ?>
                <div class="row-actions" style="flex-wrap:wrap;gap:8px;margin-top:10px">
                    <form method="post" action="<?= e(url('modules/service/token.php')) ?>" style="display:inline" data-confirm="Takip linki yenilensin mi? Eski link geçersiz olur.">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="do" value="regenerate">
                        <button type="submit" class="btn btn-sm"><?= icon('refresh-cw') ?>Linki yenile</button>
                    </form>
                    <form method="post" action="<?= e(url('modules/service/token.php')) ?>" style="display:inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                        <input type="hidden" name="do" value="<?= (int) $s['public_tracking_enabled'] === 1 ? 'disable' : 'enable' ?>">
                        <button type="submit" class="btn btn-sm"><?= (int) $s['public_tracking_enabled'] === 1 ? 'Takibi kapat' : 'Takibi aç' ?></button>
                    </form>
                </div>
                <?php
                require_once __DIR__ . '/../../includes/service-messages.php';
                $waStatus = can('service.whatsapp') ? service_status_whatsapp_link($s) : null;
                ?>
                <?php if (can('service.whatsapp') || can('service.mail')): ?>
                <div class="row-actions" style="flex-wrap:wrap;gap:8px;margin-top:10px;border-top:1px solid var(--border);padding-top:10px">
                    <span class="small muted" style="width:100%">Durum bilgilendirme (şablon: Ayarlar → Servis Mesaj Şablonları):</span>
                    <?php if ($waStatus): ?><a class="btn btn-sm btn-wa" href="<?= e($waStatus) ?>" target="_blank" rel="noopener"><?= icon('message-circle') ?>Durumu WhatsApp'tan bildir</a><?php endif; ?>
                    <?php if (can('service.mail') && !empty($s['customer_email'])): ?>
                    <form method="post" action="<?= e(url('modules/service/notify.php')) ?>" style="display:inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                        <button type="submit" class="btn btn-sm"><?= icon('mail') ?>Durumu e-posta ile bildir</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- WhatsApp -->
        <?php if ($waOpened || $waCost || $waReady): ?>
        <div class="card">
            <div class="card-header"><h2>WhatsApp Bilgilendirme</h2></div>
            <div class="card-body">
                <div class="wa-links">
                    <?php if ($waOpened): ?><a class="btn btn-sm btn-wa" href="<?= e($waOpened) ?><?= icon('message-circle') ?>" target="_blank" rel="noopener">Servis kaydı açıldı</a><?php endif; ?>
                    <?php if ($waCost): ?><a class="btn btn-sm btn-wa" href="<?= e($waCost) ?><?= icon('message-circle') ?>" target="_blank" rel="noopener">Masraf onayı iste</a><?php endif; ?>
                    <?php if ($waReady): ?><a class="btn btn-sm btn-wa" href="<?= e($waReady) ?><?= icon('message-circle') ?>" target="_blank" rel="noopener">Teslimata hazır</a><?php endif; ?>
                </div>
                <p class="field-hint" style="margin-top:8px">Müşteri telefonu ile wa.me üzerinden hazır mesaj açar.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$docType = 'service'; $docId = $id; $docRow = $s; $docBackUrl = 'modules/service/view.php?id=' . $id;
require __DIR__ . '/../../includes/_document_send_ui.php';
layout_bottom();
