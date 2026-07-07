<?php
declare(strict_types=1);
/** Ortak dış tamirci formu. Beklenen: $formAction, $submitLabel, $isEdit, $r, $specialties. */
defined('APP_ROOT') or exit;
$r = $r ?? [];
$v = static fn(string $k, $d='') => e((string) ($r[$k] ?? $d));
$isEdit = $isEdit ?? false;
$specialties = $specialties ?? [];
$curSpec = (string) ($r['specialty'] ?? '');
?>
<div class="card" style="max-width:680px">
    <div class="card-body">
        <form method="post" action="<?= e($formAction) ?>" data-lock-on-submit novalidate>
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Ad</label>
                    <input type="text" id="name" name="name" value="<?= $v('name') ?>" required>
                </div>
                <div class="form-group">
                    <label for="company_name">Firma</label>
                    <input type="text" id="company_name" name="company_name" value="<?= $v('company_name') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Telefon</label>
                    <input type="text" id="phone" name="phone" value="<?= $v('phone') ?>" inputmode="tel">
                </div>
                <div class="form-group">
                    <label for="email">E-posta</label>
                    <input type="email" id="email" name="email" value="<?= $v('email') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="specialty">Uzmanlık alanı</label>
                <input type="text" id="specialty" name="specialty" value="<?= $v('specialty') ?>" list="spec_list" placeholder="Yazıcı Tamiri, Fuser Bakımı...">
                <datalist id="spec_list">
                    <?php foreach ($specialties as $sp): ?><option value="<?= e($sp) ?>"></option><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label for="address">Adres</label>
                <textarea id="address" name="address" rows="2"><?= $v('address') ?></textarea>
            </div>
            <div class="form-group">
                <label for="description">Not</label>
                <textarea id="description" name="description" rows="2"><?= $v('description') ?></textarea>
            </div>
            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:220px">
                    <input type="checkbox" name="is_active" value="1" <?= (int) ($r['is_active'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
                    <span>Aktif</span>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
                <a class="btn" href="<?= e(url('modules/service/repairers.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>
