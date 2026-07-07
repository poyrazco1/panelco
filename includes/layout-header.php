<?php
declare(strict_types=1);
/**
 * includes/layout-header.php — sayfa başlığı, <head>, app.css, kabuk açılışı.
 * Kullanıcı tema + sidebar tercihleri sunucu tarafında okunur ve doğrudan
 * <html data-theme> / <body class> içine yazılır (tema flash'ı olmaz).
 */
require_once __DIR__ . '/preferences.php';

$__uid = function_exists('current_user_id') ? current_user_id() : null;
$__prefs = $__uid ? get_user_ui_preferences($__uid) : [];

$uiTheme = $__prefs['theme'] ?? 'system';
if (!pref_is_valid_theme($uiTheme)) { $uiTheme = 'system'; }

$uiSidebar = $__prefs['sidebar_state'] ?? 'expanded';
if (!pref_is_valid_sidebar($uiSidebar)) { $uiSidebar = 'expanded'; }
?>
<!doctype html>
<html lang="tr" data-theme="<?= e($uiTheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title><?= e(page_title()) ?> · <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app sidebar-<?= e($uiSidebar) ?>">
<div class="layout">
