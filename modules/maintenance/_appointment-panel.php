<?php
declare(strict_types=1);
/**
 * modules/maintenance/_appointment-panel.php
 * view.php içine dahil edilir. Beklenen: $r, $id, $actUrl, $ret, $users.
 * Müşteri cevabı (§10) + bakım randevusu + "Bakım Servisi Aç" (§11).
 */
$results   = smaint_response_results();
$svcTypes  = smaint_appointment_service_types();
$locations = smaint_appointment_locations();
$hasAppt   = !empty($r['appointment_at']);
?>
<div class="card">
    <div class="card-header"><h2><?= icon('phone') ?> Müşteri Cevabı</h2></div>
    <div class="card-body">
        <?php if (!empty($r['response_result'])): ?>
            <div class="kv"><span>Son Cevap</span><strong><?= e(smaint_response_label((string) $r['response_result'])) ?></strong></div>
            <?php if (!empty($r['response_note'])): ?><div class="kv"><span>Not</span><span><?= e((string) $r['response_note']) ?></span></div><?php endif; ?>
        <?php endif; ?>
        <?php if (can_maint_message()): ?>
        <form method="post" action="<?= e($actUrl) ?>" id="respForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="return" value="<?= e($ret) ?>">
            <input type="hidden" name="action" value="record_response">
            <div class="form-group"><label for="respResult">İşlem sonucu</label>
                <select id="respResult" name="result">
                    <?php foreach ($results as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="respDateWrap" style="display:none">
                <label for="respDate">Yeni arama tarihi/saati (zorunlu)</label>
                <input type="datetime-local" id="respDate" name="new_contact_at">
            </div>
            <div class="form-group"><label for="respNote">Açıklama</label><input type="text" id="respNote" name="note"></div>
            <button class="btn btn-sm" type="submit">Cevabı Kaydet</button>
        </form>
        <script>
        (function(){
            var sel = document.getElementById('respResult');
            var wrap = document.getElementById('respDateWrap');
            var dt = document.getElementById('respDate');
            function upd(){
                var later = sel.value === 'call_later';
                wrap.style.display = later ? '' : 'none';
                if (dt) dt.required = later;
            }
            sel.addEventListener('change', upd); upd();
        })();
        </script>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= icon('calendar-check') ?> Bakım Randevusu</h2></div>
    <div class="card-body">
        <?php if ($hasAppt): ?>
            <div class="kv"><span>Randevu</span><strong><?= e(fmt_date((string) $r['appointment_at'])) ?></strong></div>
            <div class="kv"><span>Tür</span><span><?= e($svcTypes[(string) ($r['appointment_service_type'] ?? '')] ?? '—') ?></span></div>
            <div class="kv"><span>Yer</span><span><?= e($locations[(string) ($r['appointment_location'] ?? '')] ?? '—') ?></span></div>
            <?php if (!empty($r['appointment_note'])): ?><div class="kv"><span>Not</span><span><?= e((string) $r['appointment_note']) ?></span></div><?php endif; ?>
        <?php endif; ?>
        <?php if (can_maint_appointment()): ?>
        <form method="post" action="<?= e($actUrl) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="return" value="<?= e($ret) ?>">
            <input type="hidden" name="action" value="create_appointment">
            <div class="form-row">
                <div class="form-group"><label>Tarih/Saat</label><input type="datetime-local" name="appointment_at" required></div>
                <div class="form-group"><label>Servis türü</label>
                    <select name="service_type"><?php foreach ($svcTypes as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Yer</label>
                    <select name="location"><?php foreach ($locations as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Teknisyen</label>
                    <select name="technician_id"><option value="0">—</option><?php foreach ($users as $uidk => $un): ?><option value="<?= (int) $uidk ?>"><?= e($un) ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-group"><label>Randevu notu</label><input type="text" name="appointment_note"></div>
            <button class="btn btn-sm btn-primary" type="submit"><?= icon('calendar') ?><?= $hasAppt ? 'Randevuyu Güncelle' : 'Randevu Oluştur' ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (can_maint_convert()): ?>
<div class="card">
    <div class="card-header"><h2><?= icon('wrench') ?> Bakım Servisi Aç</h2></div>
    <div class="card-body">
        <?php if (!empty($r['converted_service_id'])): ?>
            <div class="alert" style="margin-bottom:8px">Bu bakım kaydından servis açıldı.
                <a href="<?= e(url('modules/service/view.php?id=' . (int) $r['converted_service_id'])) ?>">Servis kaydını görüntüle →</a>
            </div>
        <?php else: ?>
            <p class="text-muted" style="margin-top:0">Müşteri bakımı kabul ettiğinde, mevcut müşteri/cihaz/önceki servis bilgileriyle yeni bir teknik servis kaydı açar (servis sebebi: Periyodik Bakım).</p>
            <form method="post" action="<?= e($actUrl) ?>" onsubmit="return confirm('Bu bakım kaydından yeni servis kaydı açılsın mı?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="open_service">
                <button class="btn btn-sm btn-primary" type="submit"><?= icon('plus') ?>Bakım Servisi Aç</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
