<?php
declare(strict_types=1);
/**
 * modules/international-customers/import.php — CSV içe aktarım.
 * Yükle → önizleme/işle → özet (eklenen/güncellenen/atlanan/hatalı) + hata CSV linki.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers-io.php';
auth_boot();
require_permission('international_customers.import');

$result = null; $formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mode = (string) ($_POST['mode'] ?? 'skip');
    if (!in_array($mode, ['skip', 'update'], true)) { $mode = 'skip'; }

    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $formError = 'Lütfen bir CSV dosyası seçin.';
    } else {
        $tmp = $_FILES['file']['tmp_name'];
        $name = (string) ($_FILES['file']['name'] ?? 'import.csv');
        $content = (string) file_get_contents($tmp);
        // BOM temizle
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) { $content = substr($content, 3); }
        // Ayraç tespiti (; veya ,)
        $firstLine = strtok($content, "\r\n");
        $delim = (substr_count((string) $firstLine, ';') >= substr_count((string) $firstLine, ',')) ? ';' : ',';

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $content);
        rewind($fh);
        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            $formError = 'CSV okunamadı veya boş.';
        } else {
            // Başlık anahtarlarını normalize et (küçük harf, tr karakter sadeleştir)
            $norm = static function (string $h): string {
                $h = mb_strtolower(trim($h));
                $h = strtr($h, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', ' ' => '_', '-' => '_']);
                return $h;
            };
            $known = ic_csv_headers();
            $headerKeys = [];
            foreach ($header as $h) {
                $k = $norm((string) $h);
                $headerKeys[] = in_array($k, $known, true) ? $k : $k; // bilinmeyen anahtar da tutulur ama eşleşmez
            }
            $rows = [];
            while (($r = fgetcsv($fh, 0, $delim)) !== false) {
                if (count($r) === 1 && trim((string) $r[0]) === '') { continue; } // boş satır
                $rows[] = $r;
            }
            fclose($fh);
            if (!$rows) {
                $formError = 'CSV içinde veri satırı bulunamadı.';
            } else {
                $result = ic_import_rows($rows, $headerKeys, $mode, current_user_id(), $name);
                log_activity('intl_customer_import', 'international_customer', $result['import_id'], null, 'success',
                    sprintf('İçe aktarım: %d eklendi, %d güncellendi, %d atlandı, %d hata', $result['inserted'], $result['updated'], $result['skipped'], count($result['errors'])));
            }
        }
    }
}

// İçe aktarım geçmişi
$history = [];
try { $history = db()->query('SELECT * FROM international_customer_imports ORDER BY id DESC LIMIT 15')->fetchAll(); } catch (Throwable $e) {}

layout_top('CSV İçe Aktar', 'international_customers');
?>
<div class="page-head">
    <h1 class="page-title">Yurtdışı Müşteri — CSV İçe Aktar</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/international-customers/index.php')) ?>">← Liste</a></div>
</div>
<?= render_flashes() ?>
<?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>

<?php if ($result): ?>
<div class="card"><div class="card-header"><strong>İçe Aktarım Özeti</strong></div><div class="card-body">
    <div class="stat-row">
        <div class="stat-card"><span class="stat-val"><?= (int) $result['total'] ?></span><span class="stat-lbl">Toplam Satır</span></div>
        <div class="stat-card"><span class="stat-val"><?= (int) $result['inserted'] ?></span><span class="stat-lbl">Eklenen</span></div>
        <div class="stat-card"><span class="stat-val"><?= (int) $result['updated'] ?></span><span class="stat-lbl">Güncellenen</span></div>
        <div class="stat-card"><span class="stat-val"><?= (int) $result['skipped'] ?></span><span class="stat-lbl">Atlanan (tekrar)</span></div>
        <div class="stat-card"><span class="stat-val"><?= count($result['errors']) ?></span><span class="stat-lbl">Hatalı</span></div>
    </div>
    <?php if ($result['errors']): ?>
        <a class="btn btn-sm" href="<?= e(url('modules/international-customers/import-errors.php?id=' . (int) $result['import_id'])) ?>"><?= icon('download') ?>Hatalı Satırları İndir (CSV)</a>
    <?php endif; ?>
    <a class="btn btn-primary btn-sm" href="<?= e(url('modules/international-customers/index.php')) ?>">Listeye Git</a>
</div></div>
<?php endif; ?>

<div class="card" style="max-width:720px"><div class="card-header"><strong>Dosya Yükle</strong></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/international-customers/import.php')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group"><label for="file">CSV Dosyası</label><input type="file" id="file" name="file" accept=".csv,text/csv" required></div>
        <div class="form-group"><label>Tekrar Eden Kayıtlar</label>
            <label class="check-inline"><input type="radio" name="mode" value="skip" checked> Atla (mevcut kaydı koru)</label>
            <label class="check-inline"><input type="radio" name="mode" value="update"> Güncelle (mevcut kaydı güncelle)</label>
        </div>
        <div class="field-hint">Tekrar tespiti: e-posta, telefon veya firma+ülke eşleşmesi. Ayraç <code>;</code> veya <code>,</code> otomatik algılanır. UTF-8 (Excel için BOM'lu) desteklenir.</div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= icon('upload') ?>İçe Aktar</button>
            <a class="btn" href="<?= e(url('modules/international-customers/sample-csv.php')) ?>"><?= icon('download') ?>Örnek CSV İndir</a>
        </div>
    </form>
</div></div>

<div class="card"><div class="card-header"><strong>İçe Aktarım Geçmişi</strong></div><div class="card-body">
    <?php if ($history): ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Tarih</th><th>Dosya</th><th>Mod</th><th>Toplam</th><th>Eklenen</th><th>Güncellenen</th><th>Atlanan</th><th>Hata</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?>
        <tr><td class="small nowrap"><?= e((string) $h['created_at']) ?></td><td class="small"><?= e((string) ($h['filename'] ?? '—')) ?></td>
            <td><?= $h['mode'] === 'update' ? 'Güncelle' : 'Atla' ?></td>
            <td><?= (int) $h['total_rows'] ?></td><td><?= (int) $h['inserted'] ?></td><td><?= (int) $h['updated'] ?></td><td><?= (int) $h['skipped'] ?></td>
            <td><?php if ((int) $h['errors'] > 0): ?><a href="<?= e(url('modules/international-customers/import-errors.php?id=' . (int) $h['id'])) ?>"><?= (int) $h['errors'] ?></a><?php else: ?>0<?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty empty-compact">Henüz içe aktarım yapılmamış.</div><?php endif; ?>
</div></div>
<?php layout_bottom();
