<?php
declare(strict_types=1);

/**
 * includes/preferences.php
 * Kullanıcı bazlı arayüz tercihleri (tema, sidebar durumu vb.).
 * Yalnızca verilen user_id için çalışır; kendi tercihi kısıtı çağıran katmanda
 * (endpoint/sayfa) current_user_id() ile sağlanır. Tüm sorgular prepared.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Tek bir tercihi döndürür (yoksa $default). */
function get_user_preference(int $userId, string $key, $default = null)
{
    if ($userId <= 0 || $key === '') { return $default; }
    try {
        $st = db()->prepare(
            'SELECT preference_value FROM user_preferences
             WHERE user_id = :u AND preference_key = :k LIMIT 1'
        );
        $st->execute([':u' => $userId, ':k' => $key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string) $v;
    } catch (Throwable $e) {
        log_error('get_user_preference: ' . $e->getMessage());
        return $default;
    }
}

/** Tercihi kaydeder/günceller (upsert). */
function set_user_preference(int $userId, string $key, $value): bool
{
    if ($userId <= 0 || $key === '') { return false; }
    try {
        db()->prepare(
            'INSERT INTO user_preferences (user_id, preference_key, preference_value)
             VALUES (:u, :k, :v)
             ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)'
        )->execute([
            ':u' => $userId,
            ':k' => $key,
            ':v' => ($value === null ? null : (string) $value),
        ]);
        return true;
    } catch (Throwable $e) {
        log_error('set_user_preference: ' . $e->getMessage());
        return false;
    }
}

/** Tek bir tercihi siler. */
function delete_user_preference(int $userId, string $key): bool
{
    if ($userId <= 0 || $key === '') { return false; }
    try {
        db()->prepare('DELETE FROM user_preferences WHERE user_id = :u AND preference_key = :k')
            ->execute([':u' => $userId, ':k' => $key]);
        return true;
    } catch (Throwable $e) {
        log_error('delete_user_preference: ' . $e->getMessage());
        return false;
    }
}

/** Kullanıcının tüm UI tercihlerini [key => value] döndürür. */
function get_user_ui_preferences(int $userId): array
{
    if ($userId <= 0) { return []; }
    try {
        $st = db()->prepare('SELECT preference_key, preference_value FROM user_preferences WHERE user_id = :u');
        $st->execute([':u' => $userId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(string) $r['preference_key']] = (string) ($r['preference_value'] ?? '');
        }
        return $out;
    } catch (Throwable $e) {
        log_error('get_user_ui_preferences: ' . $e->getMessage());
        return [];
    }
}

/** Geçerli tema değeri mi? */
function pref_is_valid_theme(string $v): bool
{
    return in_array($v, ['light', 'dark', 'system'], true);
}

/** Geçerli sidebar durumu mu? */
function pref_is_valid_sidebar(string $v): bool
{
    return in_array($v, ['expanded', 'collapsed', 'hidden'], true);
}
