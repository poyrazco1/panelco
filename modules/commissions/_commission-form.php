<?php
/**
 * modules/commissions/_commission-form.php — ortak prim form alanları. Beklenen: $c, $people
 */
$c = isset($c) && is_array($c) ? $c : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($c[$k] ?? $def));
$people = $people ?? [];
$cur = (string) ($c['currency'] ?? 'TRY');
?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>Prim Bilgileri</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="personnel_id">Personel *</label>
            <select id="personnel_id" name="personnel_id" required>
                <option value="0">— Seçiniz —</option>
                <?php foreach ($people as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= (int) ($c['personnel_id'] ?? 0) === (int) $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="currency">Para birimi</label>
            <select id="currency" name="currency"><?php foreach (quote_currencies() as $cv => $cl): ?><option value="<?= e($cv) ?>"<?= $cur === $cv ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="period_start">Dönem başlangıç</label><input type="date" id="period_start" name="period_start" value="<?= $val('period_start') ?>"></div>
        <div class="form-group"><label for="period_end">Dönem bitiş</label><input type="date" id="period_end" name="period_end" value="<?= $val('period_end') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="sales_amount">Satış tutarı</label><input type="text" id="sales_amount" name="sales_amount" value="<?= $val('sales_amount', '0') ?>" inputmode="decimal" data-calc></div>
        <div class="form-group"><label for="profit_amount">Kâr tutarı</label><input type="text" id="profit_amount" name="profit_amount" value="<?= $val('profit_amount', '0') ?>" inputmode="decimal" data-calc><div class="field-hint">Kâr girilirse prim kâr üzerinden hesaplanır.</div></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="commission_rate">Prim oranı (%)</label><input type="text" id="commission_rate" name="commission_rate" value="<?= $val('commission_rate', '0') ?>" inputmode="decimal" data-calc></div>
        <div class="form-group"><label for="fixed_commission">Sabit prim</label><input type="text" id="fixed_commission" name="fixed_commission" value="<?= $val('fixed_commission', '0') ?>" inputmode="decimal" data-calc></div>
        <div class="form-group"><label for="target_amount">Hedef tutarı</label><input type="text" id="target_amount" name="target_amount" value="<?= $val('target_amount', '0') ?>" inputmode="decimal" data-calc></div>
    </div>
    <div class="quote-total-table" style="margin-top:8px">
        <table class="quote-total-table">
            <tr><td>Hedef gerçekleşme</td><td id="calcRatio">—</td></tr>
            <tr class="grand"><td>Hesaplanan prim</td><td id="calcCommission">—</td></tr>
        </table>
    </div>
    <div class="form-group"><label for="description">Açıklama</label><textarea id="description" name="description" rows="2"><?= $val('description') ?></textarea></div>
</div></div>
<script>
(function () {
    var f = document.querySelectorAll('[data-calc]');
    function num(v){ v=parseFloat(String(v||'').replace(',','.')); return isFinite(v)?v:0; }
    function money(v){ return v.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function calc(){
        var sales=num(document.getElementById('sales_amount').value);
        var profit=num(document.getElementById('profit_amount').value);
        var rate=num(document.getElementById('commission_rate').value);
        var fixed=num(document.getElementById('fixed_commission').value);
        var target=num(document.getElementById('target_amount').value);
        var base=profit>0?profit:sales;
        var comm=base*rate/100+fixed;
        var ratio=target>0?(sales/target*100):0;
        document.getElementById('calcCommission').textContent=money(comm);
        document.getElementById('calcRatio').textContent=(target>0?ratio.toFixed(1)+' %':'—');
    }
    f.forEach(function(el){ el.addEventListener('input', calc); });
    calc();
})();
</script>
