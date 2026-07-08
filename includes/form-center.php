<?php
declare(strict_types=1);

/**
 * includes/form-center.php — Form Merkezi iş mantığı (TEK KAYNAK).
 *
 * İç/dış formlar, gönderimler, onay akışı, dış token linkleri, dosya ekleri.
 * NOT: includes/forms.php (belge/PDF yardımcıları) ile İLGİSİ YOKTUR; o dosya
 * kullanılmaz/değiştirilmez. Merkezi form sistemi yalnızca burada yürür.
 *
 * İLKELER:
 *  - Fiziksel silme yok; deleted_at / is_active kullanılır.
 *  - Tüm veri fonksiyonları hataya dayanıklıdır (fatal yerine boş/0 döner).
 *  - Alan tanımları fields_json içinde standart yapıdadır.
 *  - Dış form gönderimi login gerektirmez; token ile korunur (aktif/süre/kullanım).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/leave.php'; // app_setting_get / app_setting_set

/* =========================================================================
 |  SABİT LİSTELER
 * ====================================================================== */
function form_types(): array { return ['internal' => 'İç Form', 'external' => 'Dış Form', 'both' => 'İç + Dış']; }
function form_type_label(string $k): string { return form_types()[$k] ?? $k; }

function form_field_types(): array
{
    return ['text' => 'Metin', 'textarea' => 'Uzun Metin', 'phone' => 'Telefon', 'email' => 'E-posta',
            'number' => 'Sayı', 'date' => 'Tarih', 'select' => 'Seçim', 'checkbox' => 'Onay Kutusu',
            'file' => 'Dosya', 'hidden' => 'Gizli'];
}

function form_statuses(): array
{
    return [
        'new'              => 'Yeni',
        'reviewing'        => 'İnceleniyor',
        'waiting_approval' => 'Onay Bekliyor',
        'approved'         => 'Onaylandı',
        'rejected'         => 'Reddedildi',
        'in_progress'      => 'İşlemde',
        'completed'        => 'Tamamlandı',
        'cancelled'        => 'İptal',
    ];
}
function form_status_label(string $k): string { return form_statuses()[$k] ?? $k; }
function form_status_class(string $k): string
{
    return match ($k) {
        'approved', 'completed' => 'badge-success',
        'rejected', 'cancelled' => 'badge-danger',
        'waiting_approval'      => 'badge-leave',
        'reviewing', 'in_progress' => 'badge-info',
        default                 => 'badge-muted', // new
    };
}

function form_approval_statuses(): array
{
    return ['none' => 'Gerekmiyor', 'pending' => 'Onay Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'];
}
function form_approval_label(string $k): string { return form_approval_statuses()[$k] ?? $k; }
function form_approval_class(string $k): string
{
    return match ($k) {
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'pending'  => 'badge-leave',
        default    => 'badge-muted',
    };
}

/* =========================================================================
 |  GÖRÜNÜRLÜK / ROLLER
 * ====================================================================== */
/** Form kayıtlarını tüm kapsamda yönetebilir mi? */
function form_center_can_manage(): bool
{
    $perms = function_exists('current_permissions') ? current_permissions() : [];
    return in_array('all', $perms, true) || can('forms.submissions.manage') || can('forms.approve');
}

/* =========================================================================
 |  KATEGORİLER
 * ====================================================================== */
function form_center_categories(bool $onlyActive = false): array
{
    $where = ['deleted_at IS NULL'];
    if ($onlyActive) { $where[] = 'is_active = 1'; }
    try {
        $st = db()->query('SELECT * FROM form_categories WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, name ASC');
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('form_center_categories: ' . $e->getMessage()); return []; }
}
function form_center_category_find(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM form_categories WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('form_center_category_find: ' . $e->getMessage()); return null; }
}
function form_center_save_category(array $d, ?int $id, ?int $userId): int
{
    $name = trim((string) ($d['name'] ?? ''));
    if ($name === '') { return 0; }
    try {
        if ($id) {
            db()->prepare('UPDATE form_categories SET name=:n, description=:d, sort_order=:s, is_active=:a WHERE id=:id')
                ->execute([':n' => $name, ':d' => trim((string) ($d['description'] ?? '')) ?: null,
                           ':s' => (int) ($d['sort_order'] ?? 0), ':a' => !empty($d['is_active']) ? 1 : 0, ':id' => $id]);
            return $id;
        }
        db()->prepare('INSERT INTO form_categories (name, description, sort_order, is_active) VALUES (:n,:d,:s,:a)')
            ->execute([':n' => $name, ':d' => trim((string) ($d['description'] ?? '')) ?: null,
                       ':s' => (int) ($d['sort_order'] ?? 0), ':a' => isset($d['is_active']) ? (int) !empty($d['is_active']) : 1]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('form_center_save_category: ' . $e->getMessage()); return 0; }
}
function form_center_delete_category(int $id): bool
{
    try { return db()->prepare('UPDATE form_categories SET deleted_at = NOW() WHERE id = :id')->execute([':id' => $id]); }
    catch (Throwable $e) { log_error('form_center_delete_category: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  ŞABLONLAR
 * ====================================================================== */
/**
 * Şablon listesi. Filtreler: search, category_id, form_type, active('','0','1'), only_active(bool)
 */
function form_center_templates(array $f = []): array
{
    $where = ['t.deleted_at IS NULL'];
    $params = [];
    if (!empty($f['only_active'])) { $where[] = 't.is_active = 1'; }
    if (isset($f['active']) && $f['active'] !== '') { $where[] = 't.is_active = :ia'; $params[':ia'] = (int) $f['active']; }
    if (!empty($f['category_id'])) { $where[] = 't.category_id = :cid'; $params[':cid'] = (int) $f['category_id']; }
    if (!empty($f['form_type']) && isset(form_types()[$f['form_type']])) { $where[] = 't.form_type = :ft'; $params[':ft'] = $f['form_type']; }
    if (!empty($f['external_only'])) { $where[] = "t.form_type IN ('external','both')"; }
    if (!empty($f['search'])) { $where[] = '(t.form_name LIKE :q OR t.description LIKE :q OR t.form_key LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    try {
        $st = db()->prepare('SELECT t.*, c.name AS category_name
                             FROM form_templates t LEFT JOIN form_categories c ON c.id = t.category_id
                             WHERE ' . implode(' AND ', $where) . '
                             ORDER BY t.sort_order ASC, t.form_name ASC LIMIT 500');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('form_center_templates: ' . $e->getMessage()); return []; }
}

/** Şablonu id veya form_key ile bulur (silinmemiş). */
function form_center_template_find(int|string $idOrKey): ?array
{
    try {
        if (is_int($idOrKey) || ctype_digit((string) $idOrKey)) {
            $st = db()->prepare('SELECT t.*, c.name AS category_name FROM form_templates t LEFT JOIN form_categories c ON c.id = t.category_id WHERE t.id = :id AND t.deleted_at IS NULL LIMIT 1');
            $st->execute([':id' => (int) $idOrKey]);
        } else {
            $st = db()->prepare('SELECT t.*, c.name AS category_name FROM form_templates t LEFT JOIN form_categories c ON c.id = t.category_id WHERE t.form_key = :k AND t.deleted_at IS NULL LIMIT 1');
            $st->execute([':k' => (string) $idOrKey]);
        }
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('form_center_template_find: ' . $e->getMessage()); return null; }
}

/** fields_json → alan dizisi (güvenli). */
function form_center_fields(array $template): array
{
    $raw = (string) ($template['fields_json'] ?? '');
    if ($raw === '') { return []; }
    $arr = json_decode($raw, true);
    if (!is_array($arr)) { return []; }
    $out = [];
    foreach ($arr as $fld) {
        if (!is_array($fld) || empty($fld['name'])) { continue; }
        $type = (string) ($fld['type'] ?? 'text');
        if (!isset(form_field_types()[$type])) { $type = 'text'; }
        $out[] = [
            'label'       => (string) ($fld['label'] ?? $fld['name']),
            'name'        => (string) $fld['name'],
            'type'        => $type,
            'required'    => !empty($fld['required']),
            'placeholder' => (string) ($fld['placeholder'] ?? ''),
            'help_text'   => (string) ($fld['help_text'] ?? ''),
            'options'     => array_values(array_filter(array_map('strval', (array) ($fld['options'] ?? [])))),
        ];
    }
    return $out;
}

/** Benzersiz form_key üretir. */
function form_center_unique_key(string $base, ?int $excludeId = null): string
{
    $base = slugify($base) ?: 'form';
    $base = str_replace('-', '_', $base);
    $key = $base; $i = 2;
    try {
        while (true) {
            $sql = 'SELECT COUNT(*) FROM form_templates WHERE form_key = :k' . ($excludeId ? ' AND id <> :id' : '');
            $p = [':k' => $key]; if ($excludeId) { $p[':id'] = $excludeId; }
            $st = db()->prepare($sql); $st->execute($p);
            if ((int) $st->fetchColumn() === 0) { return $key; }
            $key = $base . '_' . $i++; if ($i > 200) { return $base . '_' . substr((string) time(), -5); }
        }
    } catch (Throwable $e) { return $base . '_' . substr((string) time(), -5); }
}

/** Şablon kaydeder/günceller. $fields: normalize edilmiş alan dizisi. */
function form_center_save_template(array $d, array $fields, ?int $id, ?int $userId): int
{
    $name = trim((string) ($d['form_name'] ?? ''));
    if ($name === '') { return 0; }
    $type = (string) ($d['form_type'] ?? 'internal');
    if (!isset(form_types()[$type])) { $type = 'internal'; }
    $isExt = $type === 'internal' ? 0 : 1;
    $json = json_encode(array_values($fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        if ($id) {
            db()->prepare('UPDATE form_templates SET category_id=:cat, form_name=:n, form_type=:t, description=:d, fields_json=:fj, requires_approval=:ra, is_external=:ie, is_active=:a, sort_order=:s, updated_by=:uby WHERE id=:id AND deleted_at IS NULL')
                ->execute([':cat' => (int) ($d['category_id'] ?? 0) ?: null, ':n' => $name, ':t' => $type,
                           ':d' => trim((string) ($d['description'] ?? '')) ?: null, ':fj' => $json,
                           ':ra' => !empty($d['requires_approval']) ? 1 : 0, ':ie' => $isExt,
                           ':a' => !empty($d['is_active']) ? 1 : 0, ':s' => (int) ($d['sort_order'] ?? 0),
                           ':uby' => $userId, ':id' => $id]);
            return $id;
        }
        $key = form_center_unique_key((string) ($d['form_key'] ?? $name));
        db()->prepare('INSERT INTO form_templates (category_id, form_name, form_key, form_type, description, fields_json, requires_approval, is_external, is_active, sort_order, created_by, updated_by)
                       VALUES (:cat,:n,:k,:t,:d,:fj,:ra,:ie,:a,:s,:cby,:uby)')
            ->execute([':cat' => (int) ($d['category_id'] ?? 0) ?: null, ':n' => $name, ':k' => $key, ':t' => $type,
                       ':d' => trim((string) ($d['description'] ?? '')) ?: null, ':fj' => $json,
                       ':ra' => !empty($d['requires_approval']) ? 1 : 0, ':ie' => $isExt,
                       ':a' => isset($d['is_active']) ? (int) !empty($d['is_active']) : 1, ':s' => (int) ($d['sort_order'] ?? 0),
                       ':cby' => $userId, ':uby' => $userId]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('form_center_save_template: ' . $e->getMessage()); return 0; }
}
function form_center_delete_template(int $id, ?int $userId): bool
{
    try { return db()->prepare('UPDATE form_templates SET deleted_at = NOW(), updated_by = :uby WHERE id = :id')->execute([':uby' => $userId, ':id' => $id]); }
    catch (Throwable $e) { log_error('form_center_delete_template: ' . $e->getMessage()); return false; }
}

/** POST'tan alan dizisini normalize eder (şablon editörü). */
function form_center_fields_from_input(array $in): array
{
    $out = [];
    $labels = (array) ($in['f_label'] ?? []);
    foreach ($labels as $i => $label) {
        $label = trim((string) $label);
        $name = trim((string) ($in['f_name'][$i] ?? ''));
        if ($label === '' && $name === '') { continue; }
        if ($name === '') { $name = str_replace('-', '_', slugify($label)) ?: ('alan_' . $i); }
        $type = (string) ($in['f_type'][$i] ?? 'text');
        if (!isset(form_field_types()[$type])) { $type = 'text'; }
        $opts = array_values(array_filter(array_map('trim', explode(',', (string) ($in['f_options'][$i] ?? '')))));
        $out[] = [
            'label' => $label ?: $name, 'name' => $name, 'type' => $type,
            'required' => !empty($in['f_required'][$i]),
            'placeholder' => trim((string) ($in['f_placeholder'][$i] ?? '')),
            'help_text' => trim((string) ($in['f_help'][$i] ?? '')),
            'options' => $opts,
        ];
    }
    return $out;
}

/* =========================================================================
 |  ALAN RENDER (iç submit + public form ortak)
 * ====================================================================== */
/** Form alanlarını HTML olarak basar (XSS-güvenli). */
function form_center_render_fields(array $fields, array $values = [], array $errors = []): void
{
    foreach ($fields as $fld) {
        $name = $fld['name'];
        $inName = $fld['type'] === 'file' ? 'file_' . $name : 'f_' . $name;
        $id = 'ff_' . preg_replace('/[^a-z0-9_]/i', '', $name);
        $val = (string) ($values[$name] ?? '');
        $req = !empty($fld['required']);
        $hasErr = isset($errors[$name]);
        if ($fld['type'] === 'hidden') {
            echo '<input type="hidden" name="' . e($inName) . '" value="' . e($val) . '">';
            continue;
        }
        echo '<div class="form-group' . ($hasErr ? ' has-error' : '') . '">';
        echo '<label for="' . e($id) . '">' . e($fld['label']) . ($req ? ' <span class="req">*</span>' : '') . '</label>';
        $ph = $fld['placeholder'] !== '' ? ' placeholder="' . e($fld['placeholder']) . '"' : '';
        $rq = $req ? ' required' : '';
        switch ($fld['type']) {
            case 'textarea':
                echo '<textarea id="' . e($id) . '" name="' . e($inName) . '" rows="3"' . $ph . $rq . '>' . e($val) . '</textarea>';
                break;
            case 'select':
                echo '<select id="' . e($id) . '" name="' . e($inName) . '"' . $rq . '>';
                echo '<option value="">— Seçin —</option>';
                foreach ($fld['options'] as $opt) {
                    echo '<option value="' . e($opt) . '"' . ($val === $opt ? ' selected' : '') . '>' . e($opt) . '</option>';
                }
                echo '</select>';
                break;
            case 'checkbox':
                echo '<label class="check-inline"><input type="checkbox" id="' . e($id) . '" name="' . e($inName) . '" value="1"' . ($val !== '' && $val !== '0' ? ' checked' : '') . '> ' . e($fld['help_text'] ?: 'Evet') . '</label>';
                break;
            case 'file':
                echo '<input type="file" id="' . e($id) . '" name="' . e($inName) . '" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"' . $rq . '>';
                break;
            case 'number':
                echo '<input type="number" step="any" id="' . e($id) . '" name="' . e($inName) . '" value="' . e($val) . '"' . $ph . $rq . '>';
                break;
            case 'date':
                echo '<input type="date" id="' . e($id) . '" name="' . e($inName) . '" value="' . e($val) . '"' . $rq . '>';
                break;
            case 'email':
                echo '<input type="email" id="' . e($id) . '" name="' . e($inName) . '" value="' . e($val) . '"' . $ph . $rq . '>';
                break;
            case 'phone':
                echo '<input type="tel" id="' . e($id) . '" name="' . e($inName) . '" value="' . e($val) . '"' . $ph . $rq . ' inputmode="tel">';
                break;
            default: // text
                echo '<input type="text" id="' . e($id) . '" name="' . e($inName) . '" value="' . e($val) . '"' . $ph . $rq . '>';
        }
        if ($fld['type'] !== 'checkbox' && $fld['help_text'] !== '') {
            echo '<div class="field-hint">' . e($fld['help_text']) . '</div>';
        }
        if ($hasErr) { echo '<div class="field-error">' . e($errors[$name]) . '</div>'; }
        echo '</div>';
    }
}

/* =========================================================================
 |  GÖNDERİM DOĞRULAMA + OLUŞTURMA
 * ====================================================================== */
/**
 * Girdileri şablon alanlarına göre doğrular.
 * @return array{errors:array<string,string>, data:array<string,string>, has_files:bool}
 */
function form_center_validate_submission(array $template, array $input): array
{
    $errors = []; $data = []; $hasFiles = false;
    foreach (form_center_fields($template) as $fld) {
        $name = $fld['name'];
        if ($fld['type'] === 'file') {
            $hasFiles = true;
            $fk = 'file_' . $name;
            $present = isset($_FILES[$fk]) && ($_FILES[$fk]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($fld['required'] && !$present) { $errors[$name] = $fld['label'] . ' zorunludur.'; }
            continue;
        }
        $v = trim((string) ($input['f_' . $name] ?? ''));
        if ($fld['required'] && $v === '') { $errors[$name] = $fld['label'] . ' zorunludur.'; continue; }
        if ($v !== '') {
            if ($fld['type'] === 'email' && !is_valid_email($v)) { $errors[$name] = 'Geçerli bir e-posta girin.'; }
            if ($fld['type'] === 'number' && !is_numeric(str_replace(',', '.', $v))) { $errors[$name] = 'Geçerli bir sayı girin.'; }
            if ($fld['type'] === 'select' && $fld['options'] && !in_array($v, $fld['options'], true)) { $errors[$name] = 'Geçersiz seçim.'; }
        }
        $data[$name] = $v;
    }
    return ['errors' => $errors, 'data' => $data, 'has_files' => $hasFiles];
}

/** Benzersiz talep no üretir (FRM-YYYY-####). */
function form_center_generate_submission_no(): string
{
    $year = date('Y');
    for ($i = 0; $i < 50; $i++) {
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM form_submissions WHERE submission_no LIKE :p');
            $st->execute([':p' => 'FRM-' . $year . '-%']);
            $seq = (int) $st->fetchColumn() + 1 + $i;
        } catch (Throwable $e) { $seq = (int) substr((string) time(), -5) + $i; }
        $no = 'FRM-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        try {
            $c = db()->prepare('SELECT COUNT(*) FROM form_submissions WHERE submission_no = :n'); $c->execute([':n' => $no]);
            if ((int) $c->fetchColumn() === 0) { return $no; }
        } catch (Throwable $e) { return $no; }
    }
    return 'FRM-' . $year . '-' . substr((string) time(), -5);
}

/** Veriden iletişim alanlarını (ad/firma/telefon/eposta) tahmin eder. */
function form_center_extract_contact(array $data): array
{
    $pick = static function (array $data, array $keys): string {
        foreach ($keys as $k) { if (!empty($data[$k])) { return (string) $data[$k]; } }
        return '';
    };
    return [
        'customer_name' => $pick($data, ['ad_soyad', 'ad_firma', 'yetkili', 'firma_musteri', 'teslim_alan']),
        'company_name'  => $pick($data, ['firma', 'firma_adi', 'firma_musteri', 'ad_firma']),
        'phone'         => $pick($data, ['telefon', 'phone']),
        'email'         => $pick($data, ['email', 'eposta']),
    ];
}

/**
 * Gönderim oluşturur. $meta: submitted_by_user_id, external_token_id, contact overrides.
 * Onay gerekiyorsa status=waiting_approval, approval_status=pending.
 * @return int yeni submission id (0 = hata)
 */
function form_center_create_submission(array $template, array $data, array $meta = []): int
{
    $requiresApproval = !empty($template['requires_approval']);
    $status = $requiresApproval ? 'waiting_approval' : 'new';
    $approval = $requiresApproval ? 'pending' : 'none';
    $contact = form_center_extract_contact($data);
    foreach (['customer_name', 'company_name', 'phone', 'email'] as $k) {
        if (!empty($meta[$k])) { $contact[$k] = (string) $meta[$k]; }
    }
    try {
        $no = form_center_generate_submission_no();
        $st = db()->prepare('INSERT INTO form_submissions
            (form_template_id, submission_no, submitted_by_user_id, external_token_id, customer_name, company_name, phone, email, data_json, status, approval_status)
            VALUES (:tpl,:no,:uid,:tok,:cn,:co,:ph,:em,:dj,:st,:ap)');
        $st->execute([
            ':tpl' => (int) $template['id'], ':no' => $no,
            ':uid' => !empty($meta['submitted_by_user_id']) ? (int) $meta['submitted_by_user_id'] : null,
            ':tok' => !empty($meta['external_token_id']) ? (int) $meta['external_token_id'] : null,
            ':cn' => $contact['customer_name'] ?: null, ':co' => $contact['company_name'] ?: null,
            ':ph' => $contact['phone'] ?: null, ':em' => $contact['email'] ?: null,
            ':dj' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':st' => $status, ':ap' => $approval,
        ]);
        $id = (int) db()->lastInsertId();
        form_center_log($id, '', $status, $requiresApproval ? 'Gönderildi (onay bekliyor)' : 'Gönderildi',
            !empty($meta['submitted_by_user_id']) ? (int) $meta['submitted_by_user_id'] : null);
        return $id;
    } catch (Throwable $e) { log_error('form_center_create_submission: ' . $e->getMessage()); return 0; }
}

/* =========================================================================
 |  GÖNDERİM LİSTE / DETAY
 * ====================================================================== */
/**
 * Gönderim listesi. Yönetici tümünü; normal kullanıcı yalnızca kendi
 * gönderdiği veya kendisine atanan kayıtları görür.
 * Filtreler: template_id, category_id, status, approval_status, assigned_user_id,
 *            date_from, date_to, form_type, phone, customer, search
 */
function form_center_submissions(array $f = []): array
{
    $where = ['s.deleted_at IS NULL'];
    $params = [];
    if (!form_center_can_manage()) {
        $uid = (int) (current_user_id() ?? 0);
        $where[] = '(s.submitted_by_user_id = :own OR s.assigned_user_id = :own2)';
        $params[':own'] = $uid ?: -1; $params[':own2'] = $uid ?: -1;
    }
    if (!empty($f['template_id'])) { $where[] = 's.form_template_id = :tpl'; $params[':tpl'] = (int) $f['template_id']; }
    if (!empty($f['category_id'])) { $where[] = 't.category_id = :cat'; $params[':cat'] = (int) $f['category_id']; }
    if (!empty($f['status']) && isset(form_statuses()[$f['status']])) { $where[] = 's.status = :st'; $params[':st'] = $f['status']; }
    if (!empty($f['approval_status']) && isset(form_approval_statuses()[$f['approval_status']])) { $where[] = 's.approval_status = :ap'; $params[':ap'] = $f['approval_status']; }
    if (!empty($f['assigned_user_id'])) { $where[] = 's.assigned_user_id = :au'; $params[':au'] = (int) $f['assigned_user_id']; }
    if (!empty($f['form_type']) && isset(form_types()[$f['form_type']])) { $where[] = 't.form_type = :ft'; $params[':ft'] = $f['form_type']; }
    if (!empty($f['date_from'])) { $where[] = 'DATE(s.created_at) >= :df'; $params[':df'] = $f['date_from']; }
    if (!empty($f['date_to']))   { $where[] = 'DATE(s.created_at) <= :dt'; $params[':dt'] = $f['date_to']; }
    if (!empty($f['phone'])) { $where[] = 's.phone LIKE :ph'; $params[':ph'] = '%' . $f['phone'] . '%'; }
    if (!empty($f['customer'])) { $where[] = '(s.customer_name LIKE :cu OR s.company_name LIKE :cu)'; $params[':cu'] = '%' . $f['customer'] . '%'; }
    if (!empty($f['search'])) { $where[] = '(s.submission_no LIKE :q OR s.customer_name LIKE :q OR s.company_name LIKE :q OR s.phone LIKE :q)'; $params[':q'] = '%' . $f['search'] . '%'; }
    if (!empty($f['pending_approval'])) { $where[] = "s.approval_status = 'pending'"; }
    try {
        $st = db()->prepare('SELECT s.*, t.form_name, t.form_type, t.category_id, c.name AS category_name,
                                    u.full_name AS submitter_name, au.full_name AS assignee_name
                             FROM form_submissions s
                             INNER JOIN form_templates t ON t.id = s.form_template_id
                             LEFT JOIN form_categories c ON c.id = t.category_id
                             LEFT JOIN users u ON u.id = s.submitted_by_user_id
                             LEFT JOIN users au ON au.id = s.assigned_user_id
                             WHERE ' . implode(' AND ', $where) . '
                             ORDER BY s.id DESC LIMIT 2000');
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('form_center_submissions: ' . $e->getMessage()); return []; }
}

function form_center_submission_find(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT s.*, t.form_name, t.form_type, t.fields_json, t.requires_approval, t.category_id, c.name AS category_name,
                                    u.full_name AS submitter_name, au.full_name AS assignee_name,
                                    ap.full_name AS approver_name, rj.full_name AS rejecter_name
                             FROM form_submissions s
                             INNER JOIN form_templates t ON t.id = s.form_template_id
                             LEFT JOIN form_categories c ON c.id = t.category_id
                             LEFT JOIN users u ON u.id = s.submitted_by_user_id
                             LEFT JOIN users au ON au.id = s.assigned_user_id
                             LEFT JOIN users ap ON ap.id = s.approved_by
                             LEFT JOIN users rj ON rj.id = s.rejected_by
                             WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) { return null; }
        if (!form_center_can_manage()) {
            $uid = (int) (current_user_id() ?? 0);
            if ((int) $row['submitted_by_user_id'] !== $uid && (int) $row['assigned_user_id'] !== $uid) { return null; }
        }
        return $row;
    } catch (Throwable $e) { log_error('form_center_submission_find: ' . $e->getMessage()); return null; }
}

function form_center_submission_uploads(int $submissionId): array
{
    try { $st = db()->prepare('SELECT * FROM form_uploads WHERE submission_id = :id ORDER BY id ASC'); $st->execute([':id' => $submissionId]); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function form_center_submission_logs(int $submissionId): array
{
    try {
        $st = db()->prepare('SELECT l.*, u.full_name FROM form_submission_logs l LEFT JOIN users u ON u.id = l.created_by WHERE l.submission_id = :id ORDER BY l.id DESC LIMIT 100');
        $st->execute([':id' => $submissionId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Özet sayaçlar (kart/rozet için). */
function form_center_counts(): array
{
    $out = ['templates' => 0, 'submissions' => 0, 'pending_approval' => 0, 'new' => 0];
    try {
        $out['templates'] = (int) db()->query('SELECT COUNT(*) FROM form_templates WHERE deleted_at IS NULL AND is_active = 1')->fetchColumn();
        $scope = ''; $params = [];
        if (!form_center_can_manage()) {
            $uid = (int) (current_user_id() ?? 0);
            $scope = ' AND (submitted_by_user_id = ' . ($uid ?: -1) . ' OR assigned_user_id = ' . ($uid ?: -1) . ')';
        }
        $out['submissions'] = (int) db()->query('SELECT COUNT(*) FROM form_submissions WHERE deleted_at IS NULL' . $scope)->fetchColumn();
        $out['pending_approval'] = (int) db()->query("SELECT COUNT(*) FROM form_submissions WHERE deleted_at IS NULL AND approval_status = 'pending'" . $scope)->fetchColumn();
        $out['new'] = (int) db()->query("SELECT COUNT(*) FROM form_submissions WHERE deleted_at IS NULL AND status = 'new'" . $scope)->fetchColumn();
    } catch (Throwable $e) { log_error('form_center_counts: ' . $e->getMessage()); }
    return $out;
}

/* =========================================================================
 |  DURUM / ATAMA / ONAY
 * ====================================================================== */
function form_center_log(int $submissionId, string $old, string $new, ?string $note, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO form_submission_logs (submission_id, old_status, new_status, note, created_by) VALUES (:s,:o,:n,:note,:by)')
            ->execute([':s' => $submissionId, ':o' => $old ?: null, ':n' => $new, ':note' => $note ?: null, ':by' => $userId]);
    } catch (Throwable $e) { log_error('form_center_log: ' . $e->getMessage()); }
}

function form_center_update_submission_status(int $id, string $status, ?string $note, ?int $userId): bool
{
    if (!isset(form_statuses()[$status])) { return false; }
    $cur = form_center_submission_find($id);
    if (!$cur) { return false; }
    try {
        db()->prepare('UPDATE form_submissions SET status = :s, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL')
            ->execute([':s' => $status, ':id' => $id]);
        form_center_log($id, (string) $cur['status'], $status, $note, $userId);
        return true;
    } catch (Throwable $e) { log_error('form_center_update_submission_status: ' . $e->getMessage()); return false; }
}

function form_center_assign_submission(int $id, int $assigneeUserId, ?int $userId): bool
{
    $cur = form_center_submission_find($id);
    if (!$cur) { return false; }
    try {
        db()->prepare('UPDATE form_submissions SET assigned_user_id = :a, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL')
            ->execute([':a' => $assigneeUserId ?: null, ':id' => $id]);
        form_center_log($id, (string) $cur['status'], (string) $cur['status'], $assigneeUserId ? 'Kullanıcıya atandı' : 'Atama kaldırıldı', $userId);
        return true;
    } catch (Throwable $e) { log_error('form_center_assign_submission: ' . $e->getMessage()); return false; }
}

function form_center_approve_submission(int $id, ?string $note, ?int $userId): bool
{
    $cur = form_center_submission_find($id);
    if (!$cur) { return false; }
    try {
        db()->prepare("UPDATE form_submissions SET approval_status='approved', status='approved', approved_by=:by, approved_at=NOW(), updated_at=NOW() WHERE id=:id AND deleted_at IS NULL")
            ->execute([':by' => $userId, ':id' => $id]);
        form_center_log($id, (string) $cur['status'], 'approved', $note ?: 'Onaylandı', $userId);
        return true;
    } catch (Throwable $e) { log_error('form_center_approve_submission: ' . $e->getMessage()); return false; }
}

function form_center_reject_submission(int $id, string $reason, ?int $userId): bool
{
    $cur = form_center_submission_find($id);
    if (!$cur) { return false; }
    try {
        db()->prepare("UPDATE form_submissions SET approval_status='rejected', status='rejected', rejected_by=:by, rejected_at=NOW(), rejection_reason=:r, updated_at=NOW() WHERE id=:id AND deleted_at IS NULL")
            ->execute([':by' => $userId, ':r' => $reason ?: null, ':id' => $id]);
        form_center_log($id, (string) $cur['status'], 'rejected', $reason ? ('Red: ' . $reason) : 'Reddedildi', $userId);
        return true;
    } catch (Throwable $e) { log_error('form_center_reject_submission: ' . $e->getMessage()); return false; }
}
function form_center_delete_submission(int $id, ?int $userId): bool
{
    try { return db()->prepare('UPDATE form_submissions SET deleted_at = NOW() WHERE id = :id')->execute([':id' => $id]); }
    catch (Throwable $e) { log_error('form_center_delete_submission: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  DIŞ TOKEN LİNKLERİ
 * ====================================================================== */
function form_center_create_external_token(int $templateId, array $opts, ?int $userId): array
{
    try {
        $token = bin2hex(random_bytes(20)); // 40 hex
        $expires = trim((string) ($opts['expires_at'] ?? ''));
        $maxUses = (int) ($opts['max_uses'] ?? 0);
        db()->prepare('INSERT INTO form_external_tokens (form_template_id, token, title, expires_at, max_uses, is_active, created_by)
                       VALUES (:tpl,:tok,:title,:exp,:mu,1,:by)')
            ->execute([
                ':tpl' => $templateId, ':tok' => $token, ':title' => trim((string) ($opts['title'] ?? '')) ?: null,
                ':exp' => $expires !== '' ? $expires : null, ':mu' => $maxUses > 0 ? $maxUses : null, ':by' => $userId,
            ]);
        return ['id' => (int) db()->lastInsertId(), 'token' => $token];
    } catch (Throwable $e) { log_error('form_center_create_external_token: ' . $e->getMessage()); return ['id' => 0, 'token' => '']; }
}

function form_center_external_tokens(array $f = []): array
{
    try {
        $st = db()->query('SELECT tk.*, t.form_name, t.form_type
                           FROM form_external_tokens tk INNER JOIN form_templates t ON t.id = tk.form_template_id
                           WHERE tk.deleted_at IS NULL ORDER BY tk.id DESC LIMIT 500');
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('form_center_external_tokens: ' . $e->getMessage()); return []; }
}

/**
 * Token'ı doğrular. Aktif + süresi geçmemiş + kullanım hakkı olan + form dış'a
 * açık (external/both) ise token+template döndürür; aksi halde hata koduyla null.
 * @return array{ok:bool, reason:string, token:?array, template:?array}
 */
function form_center_validate_external_token(string $token): array
{
    $token = trim($token);
    $fail = static fn(string $r) => ['ok' => false, 'reason' => $r, 'token' => null, 'template' => null];
    if ($token === '' || !ctype_xdigit($token)) { return $fail('invalid'); }
    try {
        $st = db()->prepare('SELECT * FROM form_external_tokens WHERE token = :t AND deleted_at IS NULL LIMIT 1');
        $st->execute([':t' => $token]);
        $row = $st->fetch();
        if (!$row) { return $fail('not_found'); }
        if ((int) $row['is_active'] !== 1) { return $fail('inactive'); }
        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) !== false && strtotime((string) $row['expires_at']) < time()) {
            return $fail('expired');
        }
        if ($row['max_uses'] !== null && (int) $row['used_count'] >= (int) $row['max_uses']) { return $fail('used_up'); }
        $tpl = form_center_template_find((int) $row['form_template_id']);
        if (!$tpl || (int) $tpl['is_active'] !== 1 || !in_array((string) $tpl['form_type'], ['external', 'both'], true)) {
            return $fail('form_unavailable');
        }
        return ['ok' => true, 'reason' => 'ok', 'token' => $row, 'template' => $tpl];
    } catch (Throwable $e) { log_error('form_center_validate_external_token: ' . $e->getMessage()); return $fail('error'); }
}

/** Token kullanım sayacını artırır (gönderim sonrası). */
function form_center_increment_token_use(int $tokenId): void
{
    try { db()->prepare('UPDATE form_external_tokens SET used_count = used_count + 1 WHERE id = :id')->execute([':id' => $tokenId]); }
    catch (Throwable $e) { log_error('form_center_increment_token_use: ' . $e->getMessage()); }
}
function form_center_toggle_token(int $id, bool $active): bool
{
    try { return db()->prepare('UPDATE form_external_tokens SET is_active = :a WHERE id = :id')->execute([':a' => $active ? 1 : 0, ':id' => $id]); }
    catch (Throwable $e) { return false; }
}

/* =========================================================================
 |  DOSYA YÜKLEME (uploads/forms/)
 * ====================================================================== */
function form_center_upload_config(): array
{
    $maxMb = (int) app_setting_get('form_upload_max_mb', '8');
    if ($maxMb <= 0 || $maxMb > 32) { $maxMb = 8; }
    $typesRaw = (string) app_setting_get('form_upload_types', 'jpg,jpeg,png,webp,pdf');
    $types = array_values(array_filter(array_map(static fn($t) => strtolower(trim($t)), explode(',', $typesRaw))));
    if (!$types) { $types = ['jpg', 'jpeg', 'png', 'webp', 'pdf']; }
    return ['max_bytes' => $maxMb * 1024 * 1024, 'max_mb' => $maxMb, 'types' => $types];
}

/**
 * Yüklenen dosyayı doğrular, uploads/forms/ altına güvenli adla kaydeder ve
 * form_uploads'a yazar. Zararlı dosya çalıştırma engellenir (.htaccess + tip).
 * @return ?array kayıt bilgisi (null = dosya yok/hata)
 */
function form_center_save_upload(int $submissionId, string $fieldName, ?int $userId): ?array
{
    $fk = 'file_' . $fieldName;
    if (empty($_FILES[$fk]) || ($_FILES[$fk]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
    $f = $_FILES[$fk];
    if ($f['error'] !== UPLOAD_ERR_OK) { log_error('form upload error code ' . $f['error']); return null; }
    $cfg = form_center_upload_config();
    if ($f['size'] <= 0 || $f['size'] > $cfg['max_bytes']) { return null; }
    $ext = strtolower((string) pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $cfg['types'], true)) { return null; }
    // Gerçek içerik türü kontrolü (görsel veya pdf)
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE); $mime = (string) finfo_file($fi, (string) $f['tmp_name']); finfo_close($fi);
        $okMime = strpos($mime, 'image/') === 0 || $mime === 'application/pdf';
        if ($mime !== '' && !$okMime) { return null; }
    }
    $dir = APP_ROOT . '/uploads/forms';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "php_flag engine off\n<FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|php8|pht|phar|cgi|pl|py|sh)$\">\n    Require all denied\n</FilesMatch>\nRemoveHandler .php .phtml .phar\nAddType text/plain .php .phtml .phar\n");
    }
    $safe = 'form-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $rel = 'uploads/forms/' . $safe;
    if (!move_uploaded_file((string) $f['tmp_name'], APP_ROOT . '/' . $rel)) { return null; }
    try {
        db()->prepare('INSERT INTO form_uploads (submission_id, field_name, file_path, original_name, mime_type, file_size, uploaded_by)
                       VALUES (:s,:fn,:path,:name,:mime,:size,:by)')
            ->execute([':s' => $submissionId, ':fn' => $fieldName, ':path' => $rel, ':name' => (string) $f['name'],
                       ':mime' => $mime ?: null, ':size' => (int) $f['size'], ':by' => $userId]);
    } catch (Throwable $e) { log_error('form_center_save_upload insert: ' . $e->getMessage()); }
    return ['path' => $rel, 'name' => (string) $f['name'], 'mime' => $mime, 'size' => (int) $f['size']];
}

/** Şablonun tüm dosya alanları için yüklemeleri işler. */
function form_center_process_uploads(int $submissionId, array $template, ?int $userId): void
{
    foreach (form_center_fields($template) as $fld) {
        if ($fld['type'] === 'file') { form_center_save_upload($submissionId, $fld['name'], $userId); }
    }
}

/* =========================================================================
 |  MESAJ ŞABLONLARI + WHATSAPP
 * ====================================================================== */
function form_center_message_templates(): array
{
    return [
        'received' => (string) app_setting_get('form_msg_received', ''),
        'approved' => (string) app_setting_get('form_msg_approved', ''),
        'rejected' => (string) app_setting_get('form_msg_rejected', ''),
    ];
}
function form_center_save_message_templates(array $in): void
{
    app_setting_set('form_msg_received', trim((string) ($in['received'] ?? '')));
    app_setting_set('form_msg_approved', trim((string) ($in['approved'] ?? '')));
    app_setting_set('form_msg_rejected', trim((string) ($in['rejected'] ?? '')));
}

/** Gönderim için WhatsApp mesaj linki (tıklanabilir; otomatik gönderim yok). */
function form_center_wa_link(array $submission, string $which = 'received'): ?string
{
    $phone = trim((string) ($submission['phone'] ?? ''));
    if ($phone === '') { return null; }
    $tpls = form_center_message_templates();
    $tpl = $tpls[$which] ?? '';
    if ($tpl === '') { return null; }
    if (!function_exists('build_whatsapp_message_link')) { require_once __DIR__ . '/notifications.php'; }
    $vars = [
        '{ad_soyad}' => (string) ($submission['customer_name'] ?? ''),
        '{firma}'    => (string) ($submission['company_name'] ?? ''),
        '{form_adi}' => (string) ($submission['form_name'] ?? ''),
        '{talep_no}' => (string) ($submission['submission_no'] ?? ''),
        '{sebep}'    => (string) ($submission['rejection_reason'] ?? ''),
    ];
    return build_whatsapp_message_link($phone, strtr($tpl, $vars));
}
