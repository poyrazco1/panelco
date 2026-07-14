<?php
declare(strict_types=1);

/** modules/finance/statement.php — Cari hesap ekstresi (müşteri + tarih aralığı). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/finance.php';
require_once __DIR__ . '/../../includes/customers.php';
require_once __DIR__ . '/../../includes/email_system.php';
require_once __DIR__ . '/../../includes/mail.php';

auth_boot();
require_permission('finance');

$cid  = (int) ($_GET['customer'] ?? 0);
$from = trim((string) ($_GET['from'] ?? ''));
$to   = trim((string) ($_GET['to'] ?? ''));

$customers = [];
try { $customers = db()->query('SELECT id, company_name, email FROM customers WHERE is_deleted = 0 AND is_active = 1 ORDER BY company_name ASC LIMIT 2000')->fetchAll(); }
catch (Throwable $e) { /* sessiz */ }

$cust = $cid > 0 ? get_customer_by_id($cid) : null;
$stmt = $cid > 0 ? fin_statement($cid, $from, $to) : null;
$sym  = 'TRY';
if ($stmt && !empty($stmt['rows'])) { $sym = (string) ($stmt['rows'][0]['currency'] ?? 'TRY'); }
$sym = quote_currency_symbol($sym);
$qs = 'customer=' . $cid . '&from=' . urlencode($from) . '&to=' . urlencode($to);

layout_top('Cari Ekstre', 'finance');
?>
<div class="page-head">
    <h1 class="page-title">Cari Ekstre<?= $cust ? ' · ' . e((string) $cust['company_name']) : '' ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/finance/index.php')) ?>"><?= icon('chevron-left') ?>Cari Hareketler</a>
        <?php if ($stmt && can('finance.pdf')): ?><a class="btn btn-sm" href="<?= e(url('modules/finance/statement-pdf.php?' . $qs)) ?>"><?= icon('file-down') ?>PDF indir</a><?php endif; ?>
        <?php if ($stmt && can('finance.mail')): ?><button type="button" class="btn btn-primary btn-sm" data-open-send><?= icon('mail') ?>E-posta gönder</button><?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>

<div class="card" style="max-width:1000px"><div class="card-body">
    <form method="get" action="<?= e(url('modules/finance/statement.php')) ?>" class="form-row" style="align-items:flex-end">
        <div class="form-group"><label for="s-cust">Müşteri</label>
            <select id="s-cust" name="customer" required>
                <option value="">— Seçin —</option>
                <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $cid === (int) $c['id'] ? ' selected' : '' ?>><?= e((string) $c['company_name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="s-from">Başlangıç</label><input type="date" id="s-from" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group"><label for="s-to">Bitiş</label><input type="date" id="s-to" name="to" value="<?= e($to) ?>"></div>
        <div class="form-group" style="margin:0"><button type="submit" class="btn btn-primary">Göster</button></div>
    </form>
</div></div>

<?php if ($stmt): ?>
<div class="card" style="max-width:1000px"><div class="card-body">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tarih</th><th>Belge / Açıklama</th><th class="nowrap">Borç</th><th class="nowrap">Alacak</th><th class="nowrap">Bakiye</th></tr></thead>
            <tbody>
                <tr><td></td><td><em>Devir / Açılış bakiyesi</em></td><td></td><td></td><td class="nowrap"><strong><?= e(fmt_money((float) $stmt['opening']) . ' ' . $sym) ?></strong></td></tr>
                <?php foreach ($stmt['rows'] as $m): ?>
                    <tr>
                        <td class="nowrap"><?= e((string) $m['movement_date']) ?></td>
                        <td><?= e(trim((string) $m['receipt_no'] . ' ' . (string) $m['description'])) ?: '—' ?></td>
                        <td class="nowrap"><?= (float) $m['_debit'] > 0 ? e(fmt_money((float) $m['_debit'])) : '' ?></td>
                        <td class="nowrap"><?= (float) $m['_credit'] > 0 ? e(fmt_money((float) $m['_credit'])) : '' ?></td>
                        <td class="nowrap"><?= e(fmt_money((float) $m['_balance']) . ' ' . $sym) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="2" style="text-align:right">Dönem Toplamı / Kapanış</th>
                    <th class="nowrap"><?= e(fmt_money((float) $stmt['total_debit'])) ?></th>
                    <th class="nowrap"><?= e(fmt_money((float) $stmt['total_credit'])) ?></th>
                    <th class="nowrap"><?= e(fmt_money((float) $stmt['closing']) . ' ' . $sym) ?></th></tr>
            </tfoot>
        </table>
    </div>
    <?php if (empty($stmt['rows'])): ?><p class="muted">Seçilen dönemde hareket yok.</p><?php endif; ?>
</div></div>

<?php if (can('finance.mail')):
    $toDefault = (string) ($cust['email'] ?? '');
    $dept = dept_account_for_module('finance');
    $subjectDefault = 'Cari Ekstre · ' . (string) ($cust['company_name'] ?? '');
    $bodyDefault = "Sayın " . (string) ($cust['company_name'] ?? '') . ",\n\n"
        . ($from !== '' || $to !== '' ? ($from . ' - ' . $to . " dönemine ait ") : '') . "cari hesap ekstreniz ektedir.\n"
        . "Kapanış bakiyesi: " . fmt_money((float) $stmt['closing']) . " " . $sym . "\n\n"
        . "Saygılarımızla,\n" . (function_exists('pub_brand_name') ? pub_brand_name() : '');
?>
<dialog id="sendDialog" class="send-dialog">
    <form method="post" action="<?= e(url('modules/finance/statement-send.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="customer" value="<?= (int) $cid ?>">
        <input type="hidden" name="from" value="<?= e($from) ?>">
        <input type="hidden" name="to" value="<?= e($to) ?>">
        <div class="send-dialog-head"><h2>Ekstre Gönder</h2><button type="button" class="btn btn-sm action-icon-btn" data-close-send><?= icon('x') ?></button></div>
        <div class="send-dialog-body">
            <div class="form-group"><label for="e-to">Alıcı</label><input type="email" id="e-to" name="to_email" value="<?= e($toDefault) ?>" required></div>
            <div class="form-row">
                <div class="form-group"><label for="e-cc">CC</label><input type="text" id="e-cc" name="cc" value="<?= e((string) ($dept['default_cc'] ?? '')) ?>"></div>
                <div class="form-group"><label for="e-bcc">BCC</label><input type="text" id="e-bcc" name="bcc" value="<?= e((string) ($dept['default_bcc'] ?? '')) ?>"></div>
            </div>
            <div class="form-group"><label for="e-sub">Konu</label><input type="text" id="e-sub" name="subject" value="<?= e($subjectDefault) ?>" required></div>
            <div class="form-group"><label for="e-msg">Mesaj</label><textarea id="e-msg" name="message" rows="8"><?= e($bodyDefault) ?></textarea></div>
            <div class="form-check"><label><input type="checkbox" name="attach_pdf" checked> PDF ekle</label></div>
        </div>
        <div class="send-dialog-foot"><button type="submit" class="btn btn-primary"><?= icon('mail') ?>Gönder</button><button type="button" class="btn" data-close-send>Vazgeç</button></div>
    </form>
</dialog>
<style>
.send-dialog { width: min(620px,94vw); border:1px solid var(--border,#e2e5ea); border-radius:12px; padding:0; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.send-dialog::backdrop { background: rgba(17,24,39,.45); }
.send-dialog-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border,#e2e5ea); }
.send-dialog-head h2 { margin:0; font-size:16px; }
.send-dialog-body { padding:16px 18px; max-height:70vh; overflow-y:auto; }
.send-dialog-foot { display:flex; gap:8px; padding:14px 18px; border-top:1px solid var(--border,#e2e5ea); }
</style>
<script>
(function(){ var d=document.getElementById('sendDialog'); if(!d) return;
  document.querySelectorAll('[data-open-send]').forEach(function(b){b.addEventListener('click',function(){d.showModal?d.showModal():d.setAttribute('open','');});});
  document.querySelectorAll('[data-close-send]').forEach(function(b){b.addEventListener('click',function(){d.close?d.close():d.removeAttribute('open');});});
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php layout_bottom();
