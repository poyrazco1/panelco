<?php
declare(strict_types=1);

/**
 * includes/help.php — Yardım Merkezi iş mantığı.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Yardım kategorileri (key => etiket). */
function help_categories(): array
{
    return [
        'dashboard'      => 'Dashboard',
        'customers'      => 'Müşteriler',
        'quotes'         => 'Teklifler',
        'orders'         => 'Siparişler',
        'reconciliation' => 'Mutabakat',
        'suppliers'      => 'Tedarikçiler',
        'tsoft_products' => 'T-Soft Ürünler',
        'rma'            => 'İade / Değişim',
        'service'        => 'Teknik Servis',
        'service_track'  => 'Servis Takip',
        'shipments'      => 'Sevkiyat Takibi',
        'commissions'    => 'Primler',
        'reports'        => 'Raporlar',
        'file_manager'   => 'Dosya Yöneticisi',
        'password_vault' => 'Şifre Kasası',
        'inventory'      => 'Envanter',
        'settings'       => 'Ayarlar',
        'roles'          => 'Roller ve Yetkiler',
        'integrations'   => 'API Entegrasyonları',
    ];
}
function help_category_label(string $k): string { return help_categories()[$k] ?? $k; }

/** İlgili modülün panel URL'i ("ilgili modüle git" butonu). */
function help_module_url(?string $module): ?string
{
    $map = [
        'dashboard'      => 'dashboard.php',
        'customers'      => 'modules/customers/index.php',
        'quotes'         => 'modules/quotes/index.php',
        'orders'         => 'modules/orders/index.php',
        'reconciliation' => 'modules/reconciliation/index.php',
        'suppliers'      => 'modules/suppliers/index.php',
        'tsoft_products' => 'modules/tsoft-products/index.php',
        'rma'            => 'modules/rma/index.php',
        'service'        => 'modules/service/index.php',
        'shipments'      => 'modules/shipments/index.php',
        'commissions'    => 'modules/commissions/index.php',
        'reports'        => 'modules/reports/index.php',
        'file_manager'   => 'modules/file-manager/index.php',
        'password_vault' => 'modules/password-vault/index.php',
        'inventory'      => 'modules/inventory/index.php',
        'settings'       => 'modules/settings/index.php',
        'roles'          => 'modules/settings/roles.php',
        'integrations'   => 'modules/integrations/index.php',
    ];
    $module = (string) $module;
    return isset($map[$module]) ? url($map[$module]) : null;
}

/** Yardım konuları (filtreli + arama). Yönetici olmayan yalnız aktif konuları görür. */
function get_help_articles(array $f = [], bool $activeOnly = true): array
{
    $where = [];
    $params = [];
    if ($activeOnly) { $where[] = 'is_active = 1'; }
    if (!empty($f['category']) && isset(help_categories()[$f['category']])) { $where[] = 'category = :cat'; $params[':cat'] = $f['category']; }
    if (!empty($f['search'])) {
        $where[] = '(title LIKE :q OR short_desc LIKE :q OR content LIKE :q OR tags LIKE :q OR search_keywords LIKE :q OR category LIKE :q)';
        $params[':q'] = '%' . $f['search'] . '%';
    }
    $sql = 'SELECT * FROM help_articles';
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY sort ASC, title ASC LIMIT 500';
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_help_articles: ' . $e->getMessage()); return []; }
}

function get_help_article(int $id): ?array
{
    if ($id <= 0) { return null; }
    try {
        $st = db()->prepare('SELECT * FROM help_articles WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('get_help_article: ' . $e->getMessage()); return null; }
}

function help_category_counts(bool $activeOnly = true): array
{
    $out = ['all' => 0];
    foreach (array_keys(help_categories()) as $c) { $out[$c] = 0; }
    try {
        $sql = 'SELECT category, COUNT(*) c FROM help_articles' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' GROUP BY category';
        foreach (db()->query($sql)->fetchAll() as $r) {
            $out['all'] += (int) $r['c'];
            $out[(string) $r['category']] = (int) $r['c'];
        }
    } catch (Throwable $e) { log_error('help_category_counts: ' . $e->getMessage()); }
    return $out;
}

function help_fields_from_input(array $in): array
{
    $cat = (string) ($in['category'] ?? 'dashboard');
    if (!isset(help_categories()[$cat])) { $cat = 'dashboard'; }
    return [
        'title'           => trim((string) ($in['title'] ?? '')),
        'category'        => $cat,
        'short_desc'      => trim((string) ($in['short_desc'] ?? '')),
        'content'         => trim((string) ($in['content'] ?? '')),
        'steps'           => trim((string) ($in['steps'] ?? '')),
        'screen_path'     => trim((string) ($in['screen_path'] ?? '')),
        'related_module'  => trim((string) ($in['related_module'] ?? '')) ?: null,
        'search_keywords' => trim((string) ($in['search_keywords'] ?? '')),
        'tags'            => trim((string) ($in['tags'] ?? '')),
        'sort'            => (int) ($in['sort'] ?? 0),
        'is_active'       => isset($in['is_active']) ? 1 : 0,
    ];
}

function help_validate(array $d): array
{
    $errors = [];
    if (($d['title'] ?? '') === '') { $errors[] = 'Başlık zorunludur.'; }
    return $errors;
}

/** Benzersiz slug üretir. */
function help_unique_slug(string $title, ?int $excludeId = null): string
{
    $base = function_exists('slugify') ? slugify($title) : preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
    $base = $base !== '' ? $base : 'konu';
    $slug = $base; $i = 2;
    try {
        while (true) {
            $sql = 'SELECT COUNT(*) FROM help_articles WHERE slug = :s' . ($excludeId !== null ? ' AND id <> :id' : '');
            $p = [':s' => $slug];
            if ($excludeId !== null) { $p[':id'] = $excludeId; }
            $st = db()->prepare($sql); $st->execute($p);
            if ((int) $st->fetchColumn() === 0) { return $slug; }
            $slug = $base . '-' . $i++; if ($i > 100) { return $base . '-' . substr((string) time(), -5); }
        }
    } catch (Throwable $e) { return $base . '-' . substr((string) time(), -5); }
}

function help_bind(array $d, ?int $userId, bool $isNew, string $slug): array
{
    $p = [
        ':title' => $d['title'], ':cat' => $d['category'], ':short' => $d['short_desc'] ?: null,
        ':content' => $d['content'] ?: null, ':steps' => $d['steps'] ?: null, ':screen' => $d['screen_path'] ?: null,
        ':related' => $d['related_module'], ':kw' => $d['search_keywords'] ?: null, ':tags' => $d['tags'] ?: null,
        ':sort' => $d['sort'], ':active' => $d['is_active'], ':uby' => $userId,
    ];
    if ($isNew) { $p[':slug'] = $slug; $p[':cby'] = $userId; }
    return $p;
}

function create_help_article(array $d, ?int $userId): int
{
    try {
        $slug = help_unique_slug($d['title']);
        $st = db()->prepare(
            'INSERT INTO help_articles
                (slug, title, category, short_desc, content, steps, screen_path, related_module,
                 search_keywords, tags, sort, is_active, created_by, updated_by)
             VALUES
                (:slug,:title,:cat,:short,:content,:steps,:screen,:related,:kw,:tags,:sort,:active,:cby,:uby)'
        );
        $st->execute(help_bind($d, $userId, true, $slug));
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('create_help_article: ' . $e->getMessage()); return 0; }
}

function update_help_article(int $id, array $d, ?int $userId): bool
{
    try {
        $st = db()->prepare(
            'UPDATE help_articles SET
                title=:title, category=:cat, short_desc=:short, content=:content, steps=:steps,
                screen_path=:screen, related_module=:related, search_keywords=:kw, tags=:tags,
                sort=:sort, is_active=:active, updated_by=:uby
             WHERE id=:id'
        );
        $params = help_bind($d, $userId, false, '');
        $params[':id'] = $id;
        return $st->execute($params);
    } catch (Throwable $e) { log_error('update_help_article: ' . $e->getMessage()); return false; }
}

function delete_help_article(int $id, ?int $userId): bool
{
    try { return db()->prepare('DELETE FROM help_articles WHERE id = :id')->execute([':id' => $id]); }
    catch (Throwable $e) { log_error('delete_help_article: ' . $e->getMessage()); return false; }
}

/** Adım metnini satırlara böler. */
function help_steps_lines(?string $steps): array
{
    $steps = (string) $steps;
    if (trim($steps) === '') { return []; }
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $steps)), static fn($s) => $s !== ''));
}
