<?php
declare(strict_types=1);

/**
 * modules/rma/view.php
 * İade-değişim kaydı detayı + çoklu ürün + durum + takip linki + WhatsApp + geçmiş.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';

auth_boot();
require_permission('rma');

$id = (int) ($_GET['id'] ?? 0);
$r  = get_rma_record($id);
if (!$r) {
    flash('error', 'Kayıt bulunamadı.');
    http_response_code(303);
    redirect('modules/rma/index.php');
}

$statuses = rma_statuses();
$types    = rma_process_types();
$history  = get_rma_history($id);
$recv     = get_rma_received_items($id);
$sent     = get_rma_sent_items($id);
$pub      = rma_public_url($r);
$waTrk    = rma_whatsapp_link($r['customer_phone'] ?? null, rma_tracking_wa($r));

// ürün satırı kartı
$itemCard = static function (array $it, bool $sent): string {
    $title = trim(((string) ($it['product_name'] ?? '')) . ' ' . ((string) ($it['product_model'] ?? '')));
    if ($title === '') { $title = '(isimsiz ürün)'; }
    $h = '<div class="item-row"><div class="item-grid">';
    $h .= '<div><strong>' . e($title) . '</strong>';
    if (!empty($it['brand_name'])) { $h .= ' <span class="badge badge-info">' . e($it['brand_name']) . '</span>'; }
    $h .= '</div>';
    $h .= '<div class="small muted">Adet: ' . (int) ($it['quantity'] ?? 1);
    if (!empty($it['serial_no'])) { $h .= ' · Seri: ' . e($it['serial_no']); }
    $h .= '</div>';
    if (!$sent && !empty($it['received_status'])) { $h .= '<div class="small">Durum: ' . e($it['received_status']) . '</div>'; }
    if ($sent && !empty($it['sent_status'])) { $h .= '<div class="small">Gönderi: ' . e($it['sent_status']) . '</div>'; }
    // kargo
    if (!empty($it['cargo_tracking_no'])) {
        $trk = e((string) $it['cargo_tracking_no']);
        $co = !empty($it['cargo_company']) ? e((string) $it['cargo_company']) . ' · ' : '';
        if (!empty($it['cargo_tracking_url'])) {
            $h .= '<div class="small">Kargo: ' . $co . '<a href="' . e((string) $it['cargo_tracking_url']) . '" target="_blank" rel="noopener">' . $trk . '</a></div>';
        } else {
            $h .= '<div class="small">Kargo: ' . $co . $trk . '</div>';
        }
    }
    if (!empty($it['note'])) { $h .= '<div class="small muted">' . e((string) $it['note']) . '</div>'; }
    $h .= '</div></div>';
    return $h;
};

layout_top('İade-Değişim: ' . ($r['reference_code'] ?? $r['customer_name']), 'rma');
?>

<div class="page-head">
    <h1 class="page-title"><?= e($r['reference_code'] ?? $r['customer_name']) ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/rma/index.php')) ?>">← Liste</a>
        <a class="btn btn-sm" href="<?= e(url('modules/rma/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a>
    </div>
</div>

<p>
    <span class="badge <?= rma_status_class((string) $r['status']) ?>" style="font-size:13px"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
    <span class="badge badge-muted"><?= e($types[$r['process_type']] ?? $r['process_type']) ?></span>
    <?php if (!empty($r['reason_type'])): ?><span class="badge badge-leave"><?= e($r['reason_type']) ?></span><?php endif; ?>
    <?php if (!empty($r['platform'])): ?><span class="badge badge-info"><?= e($r['platform']) ?></span><?php endif; ?>
</p>

<div class="svc-grid">
    <div>
        <div class="card">
            <div class="card-header"><h2>Temel Bilgiler</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Referans</dt><dd><?= e($r['reference_code'] ?? '—') ?></dd>
                    <dt>İşlem tarihi</dt><dd><?= e($r['process_date'] ?? '—') ?></dd>
                    <dt>Firma / Ad Soyad</dt><dd><?= e($r['customer_name']) ?></dd>
                    <dt>Telefon</dt><dd><?= e($r['customer_phone'] ?? '—') ?></dd>
                    <dt>E-posta</dt><dd><?= e($r['customer_email'] ?? '—') ?></dd>
                    <dt>Fatura tarihi</dt><dd><?= e($r['invoice_date'] ?? '—') ?></dd>
                    <dt>Platform</dt><dd><?= e($r['platform'] ?? '—') ?></dd>
                    <dt>İşlem tipi</dt><dd><?= e($types[$r['process_type']] ?? $r['process_type']) ?></dd>
                    <dt>Sebep</dt><dd><?= e($r['reason_type'] ?? '—') ?></dd>
                    <dt>Tedarikçi</dt><dd><?= e($r['supplier'] ?? '—') ?></dd>
                    <dt>Zarar</dt><dd><?= fmt_money((float) ($r['loss_amount'] ?? 0)) ?> TL</dd>
                    <dt>Açıklama</dt><dd><?= nl2br(e($r['description'] ?? '—')) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Müşteriden Alınan Ürünler (<?= count($recv) ?>)</h2></div>
            <div class="card-body">
                <?php if (!$recv): ?><p class="muted">Ürün eklenmemiş.</p>
                <?php else: ?><div class="item-list"><?php foreach ($recv as $it) { echo $itemCard($it, false); } ?></div><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Müşteriye Gönderilen Ürünler (<?= count($sent) ?>)</h2></div>
            <div class="card-body">
                <?php if (!$sent): ?><p class="muted">Ürün eklenmemiş.</p>
                <?php else: ?><div class="item-list"><?php foreach ($sent as $it) { echo $itemCard($it, true); } ?></div><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Notlar</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>İç not (gizli)</dt><dd><?= !empty($r['internal_note']) ? nl2br(e($r['internal_note'])) : '—' ?></dd>
                    <dt>Müşteri notu (public)</dt><dd><?= !empty($r['customer_public_note']) ? nl2br(e($r['customer_public_note'])) : '—' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Durum Geçmişi</h2></div>
            <div class="card-body">
                <?php if (!$history): ?><p class="muted">Kayıt yok.</p>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($history as $h): ?>
                            <li>
                                <div>
                                    <?php if (!empty($h['old_status'])): ?><span class="muted"><?= e(rma_status_label((string) $h['old_status'])) ?></span> → <?php endif; ?>
                                    <strong><?= e(rma_status_label((string) $h['new_status'])) ?></strong>
                                    <?php if (isset($h['is_public']) && (int) $h['is_public'] === 0): ?><span class="badge badge-muted">gizli</span><?php endif; ?>
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
        <div class="card">
            <div class="card-header"><h2>Durum Değiştir</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('modules/rma/status.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <div class="form-group">
                        <label for="new_status">Yeni durum</label>
                        <select id="new_status" name="new_status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="note">Not</label><input type="text" id="note" name="note"></div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('refresh-cw') ?>Durumu güncelle</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Müşteri Takip Linki</h2></div>
            <div class="card-body">
                <p class="small" style="margin-top:0">Durum: <?php if ((int) $r['public_tracking_enabled'] === 1): ?><span class="badge badge-success">Açık</span><?php else: ?><span class="badge badge-muted">Kapalı</span><?php endif; ?></p>
                <?php if ((int) $r['public_tracking_enabled'] === 1 && $pub): ?>
                    <div class="form-group"><label for="trk">Link</label><input type="text" id="trk" value="<?= e($pub) ?>" readonly onclick="this.select()"></div>
                    <div class="row-actions" style="flex-wrap:wrap;gap:8px">
                        <button type="button" class="btn btn-sm" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('trk').value)"><?= icon('copy') ?>Kopyala</button>
                        <?php if ($waTrk): ?><a class="btn btn-sm btn-wa" href="<?= e($waTrk) ?><?= icon('message-circle') ?>" target="_blank" rel="noopener">WhatsApp ile gönder</a><?php endif; ?>
                        <a class="btn btn-sm" href="<?= e($pub) ?>" target="_blank" rel="noopener"><?= icon('eye') ?>Önizle</a>
                    </div>
                <?php else: ?><p class="muted small">Public takip kapalı.</p><?php endif; ?>
                <div class="row-actions" style="flex-wrap:wrap;gap:8px;margin-top:10px">
                    <form method="post" action="<?= e(url('modules/rma/token.php')) ?>" style="display:inline" data-confirm="Takip linki yenilensin mi? Eski link geçersiz olur.">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="do" value="regenerate">
                        <button type="submit" class="btn btn-sm"><?= icon('refresh-cw') ?>Linki yenile</button>
                    </form>
                    <form method="post" action="<?= e(url('modules/rma/token.php')) ?>" style="display:inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                        <input type="hidden" name="do" value="<?= (int) $r['public_tracking_enabled'] === 1 ? 'disable' : 'enable' ?>">
                        <button type="submit" class="btn btn-sm"><?= (int) $r['public_tracking_enabled'] === 1 ? 'Takibi kapat' : 'Takibi aç' ?></button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Kayıt Bilgisi</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Oluşturulma</dt><dd><?= fmt_date($r['created_at']) ?></dd>
                    <dt>Güncellenme</dt><dd><?= $r['updated_at'] ? fmt_date($r['updated_at']) : '—' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card" style="border-color:#F0D9D9">
            <div class="card-header"><h2>Tehlikeli bölge</h2></div>
            <div class="card-body">
                <p class="muted small" style="margin-top:0">Kayıt arşive taşınır; liste ve public takipten kalkar.</p>
                <form method="post" action="<?= e(url('modules/rma/delete.php')) ?>"
                      data-confirm="<?= e("Bu kayıt arşive taşınacaktır.\n\nReferans No: " . ($r['reference_code'] ?? '—') . "\nMüşteri: " . ($r['customer_name'] ?? '—') . "\n\nDevam edilsin mi?") ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash-2') ?>Kaydı sil (arşivle)</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
layout_bottom();
