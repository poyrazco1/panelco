<?php
declare(strict_types=1);

/**
 * modules/rma/import.php
 * CSV içe aktarma. Aynı kayıt (telefon + ürün modeli + işlem tarihi) atlanır.
 * POST + CSRF. İşlem sonrası özet gösterir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';
require_once __DIR__ . '/../../includes/shipping.php';

auth_boot();
require_permission('rma');

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (empty($_FILES['csv']['name']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Lütfen geçerli bir CSV dosyası seçin.';
    } elseif (!is_uploaded_file($_FILES['csv']['tmp_name'])) {
        $errors[] = 'Geçersiz yükleme.';
    } elseif (($_FILES['csv']['size'] ?? 0) > 5 * 1024 * 1024) {
        $errors[] = 'Dosya en fazla 5 MB olabilir.';
    } else {
        $tmp = $_FILES['csv']['tmp_name'];
        $added = 0; $skipped = 0; $failed = 0; $line = 0;

        $fh = @fopen($tmp, 'r');
        if ($fh === false) {
            $errors[] = 'Dosya okunamadı.';
        } else {
            // BOM temizle
            $first = fgets($fh);
            if ($first !== false) {
                $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
                // ayraç tespiti: ; veya ,
                $delim = (substr_count($first, ';') >= substr_count($first, ',')) ? ';' : ',';
                rewind($fh);
                // ilk satırı yeniden oku (başlık)
                $headerRow = fgetcsv($fh, 0, $delim);
                if ($headerRow !== false) {
                    // BOM'u ilk hücreden de temizle
                    $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0]);
                    $headerIndex = [];
                    foreach ($headerRow as $i => $h) {
                        $headerIndex[trim((string) $h)] = $i;
                    }
                    // Zorunlu başlık kontrolü (en azından Firma sütunu)
                    if (!isset($headerIndex['FİRMA / AD-SOYAD'])) {
                        $errors[] = 'Başlık satırı beklenen sütunları içermiyor. Önce “CSV Dışa Aktar” ile örnek biçimi indirin.';
                    } else {
                        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
                            $line++;
                            // tamamen boş satırları atla
                            if (count(array_filter($row, static fn ($c) => trim((string) $c) !== '')) === 0) {
                                continue;
                            }
                            $data = rma_row_to_data($row, $headerIndex);
                            if (trim((string) $data['customer_name']) === '') {
                                $failed++;
                                continue;
                            }
                            // normalize edilmiş tarih ile dedup
                            $prep = rma_normalize($data);
                            $pd = $prep['fields']['process_date'];
                            if (rma_duplicate_exists($data['customer_phone'] ?? '', $data['product_model'] ?? '', $pd)) {
                                $skipped++;
                                continue;
                            }
                            try {
                                create_rma_record($data);
                                $added++;
                            } catch (Throwable $e) {
                                $failed++;
                                log_error('rma import satır ' . $line . ': ' . $e->getMessage());
                            }
                        }
                    }
                } else {
                    $errors[] = 'CSV başlığı okunamadı.';
                }
            } else {
                $errors[] = 'Dosya boş.';
            }
            fclose($fh);
        }

        if (!$errors) {
            $result = ['added' => $added, 'skipped' => $skipped, 'failed' => $failed];
        }
    }
}

layout_top('CSV İçe Aktar', 'rma');
?>

<div class="page-head">
    <h1 class="page-title">CSV İçe Aktar</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/rma/index.php')) ?>">← Liste</a></div>
</div>

<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<?php if ($result !== null): ?>
    <div class="alert alert-success">
        İçe aktarma tamamlandı: <strong><?= (int) $result['added'] ?></strong> eklendi,
        <strong><?= (int) $result['skipped'] ?></strong> tekrar (atlandı),
        <strong><?= (int) $result['failed'] ?></strong> hatalı.
    </div>
<?php endif; ?>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <p class="muted">
            CSV başlıkları “CSV Dışa Aktar” çıktısıyla aynı olmalıdır. Ayraç olarak
            noktalı virgül (;) veya virgül (,) desteklenir. Aynı <em>telefon + ürün modeli + işlem tarihi</em>
            olan kayıtlar tekrar eklenmez.
        </p>
        <form method="post" action="<?= e(url('modules/rma/import.php')) ?>" enctype="multipart/form-data" data-lock-on-submit>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="csv">CSV dosyası</label>
                <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('upload') ?>İçe aktar</button>
                <a class="btn" href="<?= e(url('modules/rma/sample-csv.php')) ?>"><?= icon('download') ?>Örnek CSV indir</a>
                <a class="btn" href="<?= e(url('modules/rma/export.php')) ?>">Mevcut kayıtları dışa aktar</a>
            </div>
        </form>
    </div>
</div>

<?php
layout_bottom();
