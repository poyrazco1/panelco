<?php
declare(strict_types=1);

/**
 * includes/email_system.php
 * Belge e-posta sistemi motoru: SMTP profilleri, departman e-posta hesapları,
 * e-posta şablonları, gönderim logları ve kullanıcı e-posta tercihleri.
 *
 * Tüm sorgular prepared; hatalar loglanır, fatal atılmaz. SMTP şifreleri
 * vault.php (AES-256-GCM, VAULT_KEY) ile şifreli saklanır — düz metin tutulmaz.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vault.php';

/** Şablonlarda kullanılabilecek değişkenler (dokümantasyon/UI için). */
function email_template_variables(): array
{
    return [
        'firma_unvani', 'musteri_adi', 'yetkili_adi', 'belge_turu', 'belge_no',
        'belge_tarihi', 'donem_baslangic', 'donem_bitis', 'borc', 'alacak',
        'bakiye', 'para_birimi', 'personel_adi', 'personel_email', 'departman',
        'belge_linki', 'onay_linki', 'sirket_adi', 'sirket_telefon', 'sirket_email',
    ];
}

/** Şifreleme türleri (UI). */
function smtp_encryptions(): array
{
    return ['ssl' => 'SSL', 'tls' => 'TLS (STARTTLS)', 'none' => 'Yok'];
}

/* =========================================================================
 |  SMTP PROFİLLERİ
 * ====================================================================== */

function smtp_profiles_all(bool $onlyActive = false): array
{
    try {
        $sql = 'SELECT * FROM smtp_profiles' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY is_default DESC, name ASC';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) { log_error('smtp_profiles_all: ' . $e->getMessage()); return []; }
}

function smtp_profile_get(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM smtp_profiles WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('smtp_profile_get: ' . $e->getMessage()); return null; }
}

function smtp_profile_default(): ?array
{
    try {
        $row = db()->query('SELECT * FROM smtp_profiles WHERE is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1')->fetch();
        return $row ?: null;
    } catch (Throwable $e) { log_error('smtp_profile_default: ' . $e->getMessage()); return null; }
}

/** Profil şifresini çözer (gönderim anında). Çözülemezse ''.  */
function smtp_profile_password(array $profile): string
{
    $enc = (string) ($profile['password_enc'] ?? '');
    if ($enc === '') { return ''; }
    $plain = vault_decrypt($enc);
    return is_string($plain) ? $plain : '';
}

/**
 * SMTP profilini kaydeder (ekle/güncelle). Şifre yalnızca verildiyse güncellenir.
 * @return array{ok:bool, id:int, errors:array}
 */
function smtp_profile_save(array $in, ?int $userId): array
{
    $errors = [];
    $id   = (int) ($in['id'] ?? 0);
    $name = trim((string) ($in['name'] ?? ''));
    $host = trim((string) ($in['host'] ?? ''));
    $port = (int) ($in['port'] ?? 0);
    $enc  = strtolower(trim((string) ($in['encryption'] ?? 'ssl')));
    $user = trim((string) ($in['username'] ?? ''));
    $fromEmail = trim((string) ($in['from_email'] ?? ''));
    $fromName  = trim((string) ($in['from_name'] ?? ''));
    $timeout   = (int) ($in['timeout'] ?? 20);
    $testEmail = trim((string) ($in['test_email'] ?? ''));
    $isActive  = !empty($in['is_active']) ? 1 : 0;
    $isDefault = !empty($in['is_default']) ? 1 : 0;
    $passwordPlain = (string) ($in['password'] ?? '');

    if ($name === '')  { $errors[] = 'Profil adı zorunludur.'; }
    if ($host === '')  { $errors[] = 'SMTP host zorunludur.'; }
    if ($port <= 0 || $port > 65535) { $errors[] = 'Geçerli bir port girin.'; }
    if (!isset(smtp_encryptions()[$enc])) { $enc = 'ssl'; }
    if ($fromEmail !== '' && !is_valid_email($fromEmail)) { $errors[] = 'Geçerli bir gönderen e-posta girin.'; }
    if ($testEmail !== '' && !is_valid_email($testEmail)) { $errors[] = 'Geçerli bir test e-postası girin.'; }
    if ($timeout <= 0 || $timeout > 300) { $timeout = 20; }

    // Yeni kayıtta şifre zorunlu; güncellemede boşsa mevcut korunur.
    if ($id === 0 && $passwordPlain === '') { $errors[] = 'SMTP şifresi zorunludur.'; }
    if ($passwordPlain !== '' && !vault_is_configured()) {
        $errors[] = 'Şifre güvenli saklanamıyor: uygulama anahtarı (VAULT_KEY) yapılandırılmamış.';
    }
    if ($errors) { return ['ok' => false, 'id' => $id, 'errors' => $errors]; }

    $passwordEnc = null;
    if ($passwordPlain !== '') {
        $passwordEnc = vault_encrypt($passwordPlain);
        if ($passwordEnc === null) { return ['ok' => false, 'id' => $id, 'errors' => ['Şifre şifrelenemedi.']]; }
    }

    try {
        db()->beginTransaction();
        if ($isDefault === 1) { db()->exec('UPDATE smtp_profiles SET is_default = 0'); }

        if ($id > 0) {
            $sql = 'UPDATE smtp_profiles SET name=:name, host=:host, port=:port, encryption=:enc, username=:user,
                    from_email=:fe, from_name=:fn, timeout=:to, test_email=:te, is_active=:ia, is_default=:idf'
                 . ($passwordEnc !== null ? ', password_enc=:pw' : '')
                 . ' WHERE id=:id';
            $params = [':name'=>$name, ':host'=>$host, ':port'=>$port, ':enc'=>$enc, ':user'=>$user,
                       ':fe'=>$fromEmail, ':fn'=>$fromName, ':to'=>$timeout, ':te'=>$testEmail,
                       ':ia'=>$isActive, ':idf'=>$isDefault, ':id'=>$id];
            if ($passwordEnc !== null) { $params[':pw'] = $passwordEnc; }
            db()->prepare($sql)->execute($params);
        } else {
            db()->prepare(
                'INSERT INTO smtp_profiles (name, host, port, encryption, username, password_enc, from_email,
                    from_name, timeout, test_email, is_active, is_default, created_by)
                 VALUES (:name,:host,:port,:enc,:user,:pw,:fe,:fn,:to,:te,:ia,:idf,:cb)'
            )->execute([':name'=>$name, ':host'=>$host, ':port'=>$port, ':enc'=>$enc, ':user'=>$user,
                        ':pw'=>$passwordEnc, ':fe'=>$fromEmail, ':fn'=>$fromName, ':to'=>$timeout, ':te'=>$testEmail,
                        ':ia'=>$isActive, ':idf'=>$isDefault, ':cb'=>$userId]);
            $id = (int) db()->lastInsertId();
        }
        // En az bir varsayılan kalsın
        if (db()->query('SELECT COUNT(*) FROM smtp_profiles WHERE is_default = 1')->fetchColumn() == 0) {
            db()->prepare('UPDATE smtp_profiles SET is_default = 1 WHERE id = ?')->execute([$id]);
        }
        db()->commit();
        return ['ok' => true, 'id' => $id, 'errors' => []];
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('smtp_profile_save: ' . $e->getMessage());
        return ['ok' => false, 'id' => $id, 'errors' => ['Kaydetme sırasında bir sorun oluştu.']];
    }
}

function smtp_profile_delete(int $id): bool
{
    try {
        db()->prepare('DELETE FROM smtp_profiles WHERE id = ?')->execute([$id]);
        return true;
    } catch (Throwable $e) { log_error('smtp_profile_delete: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  DEPARTMAN E-POSTA HESAPLARI
 * ====================================================================== */

function dept_accounts_all(bool $onlyActive = false): array
{
    try {
        $sql = 'SELECT * FROM department_email_accounts' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY department_name ASC';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) { log_error('dept_accounts_all: ' . $e->getMessage()); return []; }
}

function dept_account_get(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM department_email_accounts WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('dept_account_get: ' . $e->getMessage()); return null; }
}

/** Bir modül için eşleşen (aktif) departman hesabı; yoksa null. */
function dept_account_for_module(string $moduleKey): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM department_email_accounts WHERE is_active = 1 AND module_key = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$moduleKey]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('dept_account_for_module: ' . $e->getMessage()); return null; }
}

function dept_account_save(array $in, ?int $userId): array
{
    $errors = [];
    $id   = (int) ($in['id'] ?? 0);
    $dep  = trim((string) ($in['department_name'] ?? ''));
    $mod  = trim((string) ($in['module_key'] ?? ''));
    $fromName  = trim((string) ($in['from_name'] ?? ''));
    $fromEmail = trim((string) ($in['from_email'] ?? ''));
    $mode = strtolower(trim((string) ($in['reply_to_mode'] ?? 'user')));
    $fixed= trim((string) ($in['reply_to_fixed'] ?? ''));
    $cc   = trim((string) ($in['default_cc'] ?? ''));
    $bcc  = trim((string) ($in['default_bcc'] ?? ''));
    $fwd  = trim((string) ($in['forward_to'] ?? ''));
    $smtp = (int) ($in['smtp_profile_id'] ?? 0) ?: null;
    $tpl  = (int) ($in['template_id'] ?? 0) ?: null;
    $isActive = !empty($in['is_active']) ? 1 : 0;

    if ($dep === '') { $errors[] = 'Departman adı zorunludur.'; }
    if ($fromEmail === '' || !is_valid_email($fromEmail)) { $errors[] = 'Geçerli bir gönderen (From) e-postası girin.'; }
    if (!in_array($mode, ['user', 'department', 'fixed'], true)) { $mode = 'user'; }
    if ($mode === 'fixed' && ($fixed === '' || !is_valid_email($fixed))) { $errors[] = 'Sabit Reply-To için geçerli bir e-posta girin.'; }
    if ($errors) { return ['ok' => false, 'id' => $id, 'errors' => $errors]; }

    try {
        if ($id > 0) {
            db()->prepare(
                'UPDATE department_email_accounts SET department_name=:dep, module_key=:mod, from_name=:fn, from_email=:fe,
                    reply_to_mode=:rm, reply_to_fixed=:rf, default_cc=:cc, default_bcc=:bcc, forward_to=:fw,
                    smtp_profile_id=:smtp, template_id=:tpl, is_active=:ia WHERE id=:id'
            )->execute([':dep'=>$dep, ':mod'=>$mod, ':fn'=>$fromName, ':fe'=>$fromEmail, ':rm'=>$mode, ':rf'=>$fixed,
                        ':cc'=>$cc, ':bcc'=>$bcc, ':fw'=>$fwd, ':smtp'=>$smtp, ':tpl'=>$tpl, ':ia'=>$isActive, ':id'=>$id]);
        } else {
            db()->prepare(
                'INSERT INTO department_email_accounts (department_name, module_key, from_name, from_email, reply_to_mode,
                    reply_to_fixed, default_cc, default_bcc, forward_to, smtp_profile_id, template_id, is_active, created_by)
                 VALUES (:dep,:mod,:fn,:fe,:rm,:rf,:cc,:bcc,:fw,:smtp,:tpl,:ia,:cb)'
            )->execute([':dep'=>$dep, ':mod'=>$mod, ':fn'=>$fromName, ':fe'=>$fromEmail, ':rm'=>$mode, ':rf'=>$fixed,
                        ':cc'=>$cc, ':bcc'=>$bcc, ':fw'=>$fwd, ':smtp'=>$smtp, ':tpl'=>$tpl, ':ia'=>$isActive, ':cb'=>$userId]);
            $id = (int) db()->lastInsertId();
        }
        return ['ok' => true, 'id' => $id, 'errors' => []];
    } catch (Throwable $e) {
        log_error('dept_account_save: ' . $e->getMessage());
        return ['ok' => false, 'id' => $id, 'errors' => ['Kaydetme sırasında bir sorun oluştu.']];
    }
}

function dept_account_delete(int $id): bool
{
    try {
        db()->prepare('DELETE FROM department_email_accounts WHERE id = ?')->execute([$id]);
        return true;
    } catch (Throwable $e) { log_error('dept_account_delete: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  E-POSTA ŞABLONLARI
 * ====================================================================== */

function email_templates_all(string $moduleKey = ''): array
{
    try {
        if ($moduleKey !== '') {
            $st = db()->prepare('SELECT * FROM email_templates WHERE module_key = ? ORDER BY is_default DESC, name ASC');
            $st->execute([$moduleKey]);
            return $st->fetchAll();
        }
        return db()->query('SELECT * FROM email_templates ORDER BY module_key ASC, is_default DESC, name ASC')->fetchAll();
    } catch (Throwable $e) { log_error('email_templates_all: ' . $e->getMessage()); return []; }
}

function email_template_get(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM email_templates WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('email_template_get: ' . $e->getMessage()); return null; }
}

function email_template_for_module(string $moduleKey): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM email_templates WHERE is_active = 1 AND module_key = ? ORDER BY is_default DESC, id ASC LIMIT 1');
        $st->execute([$moduleKey]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('email_template_for_module: ' . $e->getMessage()); return null; }
}

function email_template_save(array $in, ?int $userId): array
{
    $errors = [];
    $id   = (int) ($in['id'] ?? 0);
    $mod  = trim((string) ($in['module_key'] ?? ''));
    $name = trim((string) ($in['name'] ?? ''));
    $subject = trim((string) ($in['subject'] ?? ''));
    $body = (string) ($in['body'] ?? '');
    $isActive  = !empty($in['is_active']) ? 1 : 0;
    $isDefault = !empty($in['is_default']) ? 1 : 0;

    if ($name === '')    { $errors[] = 'Şablon adı zorunludur.'; }
    if ($subject === '') { $errors[] = 'Konu zorunludur.'; }
    if ($errors) { return ['ok' => false, 'id' => $id, 'errors' => $errors]; }

    try {
        db()->beginTransaction();
        if ($isDefault === 1 && $mod !== '') {
            db()->prepare('UPDATE email_templates SET is_default = 0 WHERE module_key = ?')->execute([$mod]);
        }
        if ($id > 0) {
            db()->prepare('UPDATE email_templates SET module_key=:mod, name=:name, subject=:sub, body=:body, is_active=:ia, is_default=:idf WHERE id=:id')
                ->execute([':mod'=>$mod, ':name'=>$name, ':sub'=>$subject, ':body'=>$body, ':ia'=>$isActive, ':idf'=>$isDefault, ':id'=>$id]);
        } else {
            db()->prepare('INSERT INTO email_templates (module_key, name, subject, body, is_active, is_default, created_by) VALUES (:mod,:name,:sub,:body,:ia,:idf,:cb)')
                ->execute([':mod'=>$mod, ':name'=>$name, ':sub'=>$subject, ':body'=>$body, ':ia'=>$isActive, ':idf'=>$isDefault, ':cb'=>$userId]);
            $id = (int) db()->lastInsertId();
        }
        db()->commit();
        return ['ok' => true, 'id' => $id, 'errors' => []];
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('email_template_save: ' . $e->getMessage());
        return ['ok' => false, 'id' => $id, 'errors' => ['Kaydetme sırasında bir sorun oluştu.']];
    }
}

function email_template_delete(int $id): bool
{
    try {
        db()->prepare('DELETE FROM email_templates WHERE id = ?')->execute([$id]);
        return true;
    } catch (Throwable $e) { log_error('email_template_delete: ' . $e->getMessage()); return false; }
}

/** {{degisken}} yer tutucularını $vars ile değiştirir. Bilinmeyenler silinmez. */
function email_template_render(string $text, array $vars): string
{
    $out = $text;
    foreach ($vars as $k => $v) {
        $out = str_replace('{{' . $k . '}}', (string) $v, $out);
    }
    return $out;
}

/* =========================================================================
 |  GÖNDERİM LOGLARI (belge gönderim geçmişi)
 * ====================================================================== */

function document_email_log_insert(array $d): int
{
    try {
        db()->prepare(
            'INSERT INTO document_email_logs (document_type, document_id, document_no, sent_by_user_id, sent_by_name,
                department, smtp_profile_id, from_email, reply_to, to_email, cc, bcc, subject, body, has_pdf, pdf_path,
                doc_link, status, error_message, message_id, resend_of_id)
             VALUES (:dt,:did,:dno,:uid,:uname,:dep,:smtp,:from,:reply,:to,:cc,:bcc,:sub,:body,:pdf,:pdfp,:link,:st,:err,:mid,:ro)'
        )->execute([
            ':dt'=>(string)($d['document_type']??''), ':did'=>($d['document_id']??null), ':dno'=>(string)($d['document_no']??''),
            ':uid'=>($d['sent_by_user_id']??null), ':uname'=>(string)($d['sent_by_name']??''), ':dep'=>(string)($d['department']??''),
            ':smtp'=>($d['smtp_profile_id']??null), ':from'=>(string)($d['from_email']??''), ':reply'=>(string)($d['reply_to']??''),
            ':to'=>(string)($d['to_email']??''), ':cc'=>(string)($d['cc']??''), ':bcc'=>(string)($d['bcc']??''),
            ':sub'=>(string)($d['subject']??''), ':body'=>(string)($d['body']??''), ':pdf'=>!empty($d['has_pdf'])?1:0,
            ':pdfp'=>(string)($d['pdf_path']??''), ':link'=>(string)($d['doc_link']??''),
            ':st'=>($d['status']??'failed')==='sent'?'sent':'failed', ':err'=>($d['error_message']??null),
            ':mid'=>(string)($d['message_id']??''), ':ro'=>($d['resend_of_id']??null),
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('document_email_log_insert: ' . $e->getMessage()); return 0; }
}

/** Son gönderim logları (global geçmiş görünümü için). */
function document_email_logs_recent(int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));
    try {
        return db()->query('SELECT * FROM document_email_logs ORDER BY created_at DESC, id DESC LIMIT ' . $limit)->fetchAll();
    } catch (Throwable $e) { log_error('document_email_logs_recent: ' . $e->getMessage()); return []; }
}

function document_email_logs_for(string $type, int $id): array
{
    try {
        $st = db()->prepare('SELECT * FROM document_email_logs WHERE document_type = ? AND document_id = ? ORDER BY created_at DESC, id DESC');
        $st->execute([$type, $id]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('document_email_logs_for: ' . $e->getMessage()); return []; }
}

function document_email_log_get(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM document_email_logs WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('document_email_log_get: ' . $e->getMessage()); return null; }
}

/* =========================================================================
 |  KULLANICI E-POSTA TERCİHLERİ
 * ====================================================================== */

function user_email_pref_get(int $userId): array
{
    $def = ['user_id'=>$userId, 'corporate_email'=>'', 'department'=>'', 'default_account_id'=>null,
            'can_receive_replies'=>1, 'auto_cc'=>0, 'is_active'=>1];
    try {
        $st = db()->prepare('SELECT * FROM user_email_preferences WHERE user_id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch();
        return $row ?: $def;
    } catch (Throwable $e) { log_error('user_email_pref_get: ' . $e->getMessage()); return $def; }
}

/** Kullanıcının kurumsal e-postası (tercih override → users.email). */
function user_corporate_email(int $userId): string
{
    $pref = user_email_pref_get($userId);
    $c = trim((string) ($pref['corporate_email'] ?? ''));
    if ($c !== '' && is_valid_email($c)) { return $c; }
    try {
        $st = db()->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $e = trim((string) ($st->fetchColumn() ?: ''));
        return is_valid_email($e) ? $e : '';
    } catch (Throwable $e) { log_error('user_corporate_email: ' . $e->getMessage()); return ''; }
}

function user_email_pref_save(int $userId, array $in): bool
{
    try {
        db()->prepare(
            'INSERT INTO user_email_preferences (user_id, corporate_email, department, default_account_id, can_receive_replies, auto_cc, is_active)
             VALUES (:uid,:ce,:dep,:acc,:crr,:cc,:ia)
             ON DUPLICATE KEY UPDATE corporate_email=VALUES(corporate_email), department=VALUES(department),
                default_account_id=VALUES(default_account_id), can_receive_replies=VALUES(can_receive_replies),
                auto_cc=VALUES(auto_cc), is_active=VALUES(is_active)'
        )->execute([
            ':uid'=>$userId, ':ce'=>trim((string)($in['corporate_email']??'')), ':dep'=>trim((string)($in['department']??'')),
            ':acc'=>((int)($in['default_account_id']??0) ?: null), ':crr'=>!empty($in['can_receive_replies'])?1:0,
            ':cc'=>!empty($in['auto_cc'])?1:0, ':ia'=>!empty($in['is_active'])?1:0,
        ]);
        return true;
    } catch (Throwable $e) { log_error('user_email_pref_save: ' . $e->getMessage()); return false; }
}
