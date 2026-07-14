<?php
/** modules/reconciliation/_recon-form.php — ortak mutabakat form alanları. Beklenen: $r, $customers */
$r = isset($r) && is_array($r) ? $r : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($r[$k] ?? $def));
$cur = (string) ($r['currency'] ?? 'TRY');
$customers = $customers ?? [];
?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>Mutabakat Bilgileri</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="customer_id">Müşteri seç</label>
            <select id="customer_id" name="customer_id"><option value="0">— Manuel giriş —</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>" data-name="<?= e($c['company_name']) ?>"<?= (int) ($r['customer_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['company_name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="customer_name">Firma / Müşteri *</label><input type="text" id="customer_name" name="customer_name" value="<?= $val('customer_name') ?>" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="cari_code">Cari kod</label><input type="text" id="cari_code" name="cari_code" value="<?= $val('cari_code') ?>"></div>
        <div class="form-group"><label for="period">Dönem</label><input type="text" id="period" name="period" value="<?= $val('period') ?>" placeholder="2026 / Q1, Ocak 2026…"></div>
        <div class="form-group"><label for="recon_date">Tarih</label><input type="date" id="recon_date" name="recon_date" value="<?= $val('recon_date', date('Y-m-d')) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="recon_type">Mutabakat türü</label>
            <select id="recon_type" name="recon_type"><?php foreach (recon_types() as $tv => $tl): ?><option value="<?= e($tv) ?>"<?= (string) ($r['recon_type'] ?? 'cari') === $tv ? ' selected' : '' ?>><?= e($tl) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="period_start">Dönem başlangıç</label><input type="date" id="period_start" name="period_start" value="<?= $val('period_start') ?>"></div>
        <div class="form-group"><label for="period_end">Dönem bitiş</label><input type="date" id="period_end" name="period_end" value="<?= $val('period_end') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="debit">Borç</label><input type="text" id="debit" name="debit" value="<?= $val('debit', '0') ?>" inputmode="decimal"></div>
        <div class="form-group"><label for="credit">Alacak</label><input type="text" id="credit" name="credit" value="<?= $val('credit', '0') ?>" inputmode="decimal"></div>
        <div class="form-group"><label for="currency">Para birimi</label>
            <select id="currency" name="currency"><?php foreach (quote_currencies() as $cv => $cl): ?><option value="<?= e($cv) ?>"<?= $cur === $cv ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="agreement">Mutabakat sonucu</label>
            <select id="agreement" name="agreement"><?php foreach (recon_agreements() as $av => $al): ?><option value="<?= e($av) ?>"<?= (string) ($r['agreement'] ?? 'pending') === $av ? ' selected' : '' ?>><?= e($al) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="authorized_name">Yetkili adı soyadı</label><input type="text" id="authorized_name" name="authorized_name" value="<?= $val('authorized_name') ?>"></div>
    </div>
    <div class="form-group"><label for="description">Açıklama</label><textarea id="description" name="description" rows="3"><?= $val('description') ?></textarea></div>
    <div class="form-group"><label for="extra_note">Ek not</label><textarea id="extra_note" name="extra_note" rows="2"><?= $val('extra_note') ?></textarea></div>
</div></div>
<script>(function(){var s=document.getElementById('customer_id');if(!s)return;s.addEventListener('change',function(){var o=s.options[s.selectedIndex];if(!o||o.value==='0')return;var n=document.getElementById('customer_name'),v=o.getAttribute('data-name');if(n&&v)n.value=v;});})();</script>
