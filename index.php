<?php
declare(strict_types=1);

// Vestavěný PHP server: statické soubory servíruj přímo
if (PHP_SAPI === 'cli-server') {
    $f = str_replace('\\', '/', __DIR__) . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $dirIndex = rtrim($f, '/') . '/index.php';
    $self = str_replace('\\', '/', __FILE__);
    if ((is_file($f) && $f !== $self) || (is_file($dirIndex) && $dirIndex !== $self)) {
        return false;
    }
}

require __DIR__ . '/includes/bootstrap.php';

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (BASE_URL !== '' && stripos($path, BASE_URL) === 0) {
    $path = substr($path, strlen(BASE_URL));
}
$slug = trim($path, '/');

try {
    // robots.txt / sitemap.xml – dynamicky, pokud ještě nebyly vygenerovány do souboru
    if ($slug === 'robots.txt') {
        header('Content-Type: text/plain; charset=UTF-8');
        exit(build_robots());
    }
    if ($slug === 'sitemap.xml') {
        header('Content-Type: application/xml; charset=UTF-8');
        exit(build_sitemap());
    }

    // Jazyk: /en/... = angličtina
    if ($slug === 'en' || str_starts_with($slug, 'en/')) {
        lang('en');
        $slug = trim(substr($slug, 2), '/');
    }
    $L = lang();

    $page = null;
    $category = $project = null;
    $alt = null;                       // URL stejné stránky v obou jazycích

    // /404.php (ErrorDocument Apache) vynutí chybovou stránku
    if (!defined('FORCE_404')) foreach (ROUTES as $name => $slugs) {
        if ($slugs[$L === 'en' ? 1 : 0] === $slug) {
            $page = $name;
            $alt  = ['cs' => route_url($name, 'cs'), 'en' => route_url($name, 'en')];
            break;
        }
    }
    if ($page === null && !defined('FORCE_404') && preg_match('~^[a-z0-9-]+$~', $slug)) {
        if ($category = find_by_slug('categories', $slug, $L)) {
            $page = 'category';
            $alt  = ['cs' => item_url($category, 'cs'), 'en' => item_url($category, 'en')];
        } elseif ($project = find_by_slug('projects', $slug, $L)) {
            $page = 'project';
            $alt  = ['cs' => item_url($project, 'cs'), 'en' => item_url($project, 'en')];
        }
    }
    if ($page === null) {
        http_response_code(404);
        $page = '404';
        $alt  = ['cs' => route_url('home', 'cs'), 'en' => route_url('home', 'en')];
    }

    $title    = '';
    $metaDesc = t('site.tagline');
    $current  = $page;
    $currentSlug = $category['slug'] ?? '';
    $schema   = [];                    // JSON-LD uzly přidané stránkou

    ob_start();
    require __DIR__ . "/pages/{$page}.php";
    $content = ob_get_clean();

    require __DIR__ . '/includes/layout.php';
} catch (PDOException $ex) {
    http_response_code(500);
    error_log($ex->getMessage());
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Chyba databáze</title>'
       . '<p style="font-family:sans-serif;padding:2rem">Nepodařilo se připojit k databázi. '
       . 'Zkontrolujte nastavení v <code>config.php</code> a import <code>database/schema.sql</code>.</p>';
}
