<?php
declare(strict_types=1);

/**
 * includes/csrf.php
 * CSRF token üretimi ve doğrulaması.
 * Her POST formunda csrf_field(), her POST işleminde csrf_check() kullanılır.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Oturuma bağlı CSRF token'ı döndürür (yoksa üretir).
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/**
 * Formlara eklenecek gizli CSRF alanı.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Gelen token'ı sabit zamanlı karşılaştırır.
 */
function csrf_verify(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $expected = $_SESSION['_csrf'] ?? '';
    return $expected !== '' && is_string($token) && $token !== '' && hash_equals($expected, $token);
}

/**
 * POST işleminde CSRF doğrular; başarısızsa 419 ile durur.
 */
function csrf_check(): void
{
    if (!csrf_verify($_POST['_csrf'] ?? '')) {
        log_error('CSRF doğrulaması başarısız. IP: ' . client_ip());
        http_response_code(419);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">'
           . '<div style="font:15px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;'
           . 'max-width:520px;margin:60px auto;padding:24px;border:1px solid #E2E5EA;'
           . 'border-radius:8px;color:#1F2937;background:#fff">'
           . 'Oturum doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.'
           . '</div>';
        exit;
    }
}
