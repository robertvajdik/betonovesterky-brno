<?php
declare(strict_types=1);

const LANGS = ['cs', 'en'];

// Statické stránky: interní název => [slug CZ, slug EN]
const ROUTES = [
    'home'       => ['', ''],
    'references' => ['reference', 'references'],
    'reviews'    => ['hodnoceni', 'reviews'],
    'pricing'    => ['cenik', 'pricing'],
    'contact'    => ['kontakt', 'contact'],
    'privacy'    => ['ochrana-osobnich-udaju', 'privacy-policy'],
    // zákaznický účet (neindexuje se, není v sitemapě)
    'login'      => ['prihlaseni', 'login'],
    'register'   => ['registrace', 'register'],
    'forgot'     => ['zapomenute-heslo', 'forgot-password'],
    'account'    => ['muj-ucet', 'my-account'],
];

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Aktuální jazyk (cs | en) */
function lang(?string $set = null): string
{
    static $l = 'cs';
    if ($set !== null && in_array($set, LANGS, true)) $l = $set;
    return $l;
}

// ---------------------------------------------------------------
// Vlastnosti (tabulka properties)
// ---------------------------------------------------------------

function props(bool $reload = false): array
{
    static $p = null;
    if ($p === null || $reload) {
        $p = [];
        try {
            foreach (q("SELECT * FROM properties ORDER BY grp, sort, pkey")->fetchAll() as $r) $p[$r['pkey']] = $r;
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
        }
    }
    return $p;
}

/** Přeložený text podle aktuálního jazyka. Proměnné: {years}, {avg}, {field} … */
function t(string $key, array $vars = []): string
{
    $r = props()[$key] ?? null;
    if ($r === null) {
        $v = $key;
    } else {
        $v = (lang() === 'en' && ($r['value_en'] ?? '') !== '') ? $r['value_en'] : (string) $r['value_cs'];
    }
    $vars += ['years' => (string) (props()['site.years']['value_cs'] ?? '')];
    $map = [];
    foreach ($vars as $k => $val) $map['{' . $k . '}'] = (string) $val;
    return strtr($v, $map);
}

/** Escapovaný text */
function te(string $key, array $vars = []): string
{
    return e(t($key, $vars));
}

/** Údaje o firmě (site.*) */
function site(string $key): string
{
    return t('site.' . $key);
}

function prop_bool(string $key): bool
{
    return (string) (props()[$key]['value_cs'] ?? '0') === '1';
}

/** Hodnota z řádku DB podle jazyka: title / title_en … */
function tr(?array $row, string $col): string
{
    if (!$row) return '';
    if (lang() === 'en' && !empty($row[$col . '_en'])) return (string) $row[$col . '_en'];
    // české hodnoty jsou ve sloupci „col“, u form_fields v „col_cs“
    return (string) ($row[$col] ?? $row[$col . '_cs'] ?? '');
}

// ---------------------------------------------------------------
// URL
// ---------------------------------------------------------------

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL v daném jazyce (EN má prefix /en/) */
function lurl(string $path = '', ?string $lang = null): string
{
    $lang ??= lang();
    $path = trim($path, '/');
    if ($lang === 'en') return url('en/' . ($path !== '' ? $path . '/' : ''));
    return url($path !== '' ? $path . '/' : '');
}

function route_url(string $name, ?string $lang = null): string
{
    $lang ??= lang();
    return lurl(ROUTES[$name][$lang === 'en' ? 1 : 0], $lang);
}

/** Zásady ochrany osobních údajů: externí odkaz z nastavení, jinak vlastní stránka webu */
function privacy_url(): string
{
    return site('gdpr_url') !== '' ? site('gdpr_url') : route_url('privacy');
}

/** URL kategorie nebo realizace */
function item_url(array $row, ?string $lang = null): string
{
    $lang ??= lang();
    $slug = ($lang === 'en' && !empty($row['slug_en'])) ? $row['slug_en'] : $row['slug'];
    return lurl($slug, $lang);
}

function abs_url(string $path): string
{
    $domain = rtrim(site('domain'), '/');
    if ($domain === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $domain = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    // BASE_URL je součástí $path; doména se zadává bez podadresáře
    return $domain . $path;
}

function asset(string $path): string
{
    $file = ROOT_DIR . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return url('assets/' . $path) . '?v=' . $v;
}

/** Obrázek může být externí URL, absolutní cesta nebo soubor v /uploads */
function image_url(?string $file): ?string
{
    if (!$file) return null;
    if (preg_match('~^(https?:)?//|^/~', $file)) return $file;
    return url('uploads/' . $file);
}

/** <img> nebo stylový placeholder, pokud fotka chybí */
function img(?string $file, string $alt, string $class = '', bool $eager = false): string
{
    $src = image_url($file);
    if ($src) {
        return '<img src="' . e($src) . '" alt="' . e($alt) . '" class="' . e($class) . '"' . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
    }
    return '<div class="ph ' . e($class) . '" role="img" aria-label="' . e($alt) . '"><span>' . e($alt) . '</span></div>';
}

function tel_href(string $phone): string
{
    return 'tel:' . preg_replace('~[^\d+]~', '', $phone);
}

/** Přesměrování na URL (už sestavenou přes lurl/route_url) */
function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function flash(string $key, ?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return null;
    }
    $m = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $m;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['_csrf']) && hash_equals(csrf_token(), (string) $_POST['_csrf']);
}

function post(string $key, int $max = 2000): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function slugify(string $s): string
{
    $s = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return strtolower(trim((string) preg_replace('~[^a-z0-9]+~i', '-', $s), '-'));
}

// ---------------------------------------------------------------
// Data
// ---------------------------------------------------------------

function services(): array
{
    return q("SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort, id")->fetchAll();
}

function subcategories(int $parentId, ?string $area = null): array
{
    $sql = "SELECT * FROM categories WHERE parent_id = ? AND is_active = 1";
    $params = [$parentId];
    if ($area) {
        $sql .= " AND area = ?";
        $params[] = $area;
    }
    return q($sql . " ORDER BY sort, id", $params)->fetchAll();
}

/** Najde kategorii nebo realizaci podle slugu v daném jazyce */
function find_by_slug(string $table, string $slug, string $lang): ?array
{
    $active = $table === 'projects' ? 'is_published = 1' : 'is_active = 1';
    if ($lang === 'en') {
        $row = q("SELECT * FROM $table WHERE $active AND (slug_en = ? OR (slug_en IS NULL AND slug = ?))", [$slug, $slug])->fetch();
    } else {
        $row = q("SELECT * FROM $table WHERE $active AND slug = ?", [$slug])->fetch();
    }
    return $row ?: null;
}

function category_by_slug(string $slug): ?array
{
    return q("SELECT * FROM categories WHERE slug = ? AND is_active = 1", [$slug])->fetch() ?: null;
}

function gallery_for(string $col, int $id): array
{
    $col = $col === 'project' ? 'project_id' : 'category_id';
    return q("SELECT * FROM gallery_images WHERE $col = ? ORDER BY sort, id", [$id])->fetchAll();
}

function gallery_html(array $images, string $alt): string
{
    if (!$images) return '';
    $h = '<div class="gallery">';
    foreach ($images as $im) {
        $src = image_url($im['file']);
        $cap = tr($im, 'caption') ?: $alt;
        $h .= '<a href="' . e($src) . '" class="gallery__item" data-lightbox data-caption="' . e($cap) . '"'
            . md('itemscope itemtype="https://schema.org/ImageObject" itemprop="contentUrl"') . '>'
            . '<img src="' . e($src) . '" alt="' . e($cap) . '" loading="lazy" decoding="async"' . md('itemprop="thumbnailUrl"') . '>'
            . (schema_on('microdata') ? '<meta itemprop="caption" content="' . e($cap) . '">' : '') . '</a>';
    }
    return $h . '</div>';
}

function stars(int $n): string
{
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

// ---------------------------------------------------------------
// Poptávkový formulář – pole z tabulky form_fields
// ---------------------------------------------------------------

function form_fields(): array
{
    return q("SELECT * FROM form_fields WHERE is_active = 1 ORDER BY sort, id")->fetchAll();
}

function field_options(array $f): array
{
    $raw = tr($f, 'options');
    return array_values(array_filter(array_map('trim', preg_split('~\R~', $raw)), 'strlen'));
}

function render_field(array $f): string
{
    $name  = 'f_' . $f['fkey'];
    $label = '<span class="field__label">' . e(tr($f, 'label')) . ($f['is_required'] ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</span>';
    $ph    = tr($f, 'placeholder');
    $req   = $f['is_required'] ? ' required' : '';
    $val   = post($name, 5000);
    // přihlášený zákazník: předvyplnit kontaktní údaje z profilu
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($c = customer()) && in_array($f['fkey'], ['name', 'email', 'phone', 'location'], true)) {
        $val = (string) ($c[$f['fkey']] ?? '');
    }
    $auto  = ['name' => 'name', 'email' => 'email', 'phone' => 'tel'][$f['fkey']] ?? 'off';
    $cls   = $f['is_wide'] || $f['type'] === 'textarea' ? 'field field--wide' : 'field';

    switch ($f['type']) {
        case 'textarea':
            $input = '<textarea name="' . $name . '" rows="5" placeholder="' . e($ph) . '"' . $req . '>' . e($val) . '</textarea>';
            break;
        case 'select':
            $input = '<select name="' . $name . '"' . $req . '><option value="">' . te('form.choose') . '</option>';
            foreach (field_options($f) as $o) {
                $input .= '<option' . ($val === $o ? ' selected' : '') . '>' . e($o) . '</option>';
            }
            $input .= '</select>';
            break;
        case 'checkbox':
            return '<label class="check field field--wide"><input type="checkbox" name="' . $name . '" value="1"' . ($val ? ' checked' : '') . $req . '><span>' . $label . '</span></label>';
        default:
            $type  = in_array($f['type'], ['email', 'tel', 'number', 'date'], true) ? $f['type'] : 'text';
            $extra = $type === 'number' ? ' min="0" step="any" inputmode="decimal"'
                   : ($type === 'date' ? ' min="' . date('Y-m-d') . '"' : '');
            $input = '<input type="' . $type . '" name="' . $name . '" value="' . e($val) . '" placeholder="' . e($ph) . '" autocomplete="' . $auto . '"' . $extra . $req . '>';
    }
    return '<label class="' . $cls . '">' . $label . $input . '</label>';
}

/** Zpracuje odeslaný poptávkový formulář; při úspěchu přesměruje na $returnUrl */
function handle_inquiry(string $source, string $returnUrl): array
{
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['form'] ?? '') !== 'inquiry') return $errors;

    if (!empty($_POST['website'])) redirect($returnUrl);       // honeypot
    if (!csrf_ok()) return [t('form.err_csrf')];

    $fields = form_fields();
    $data = [];
    foreach ($fields as $f) {
        $v = post('f_' . $f['fkey'], $f['type'] === 'textarea' ? 5000 : 300);
        if ($f['type'] === 'select' && $v !== '' && !in_array($v, field_options($f), true)) $v = '';
        if ($f['type'] === 'checkbox') $v = $v !== '' ? '✓' : '';
        if ($f['type'] === 'date' && $v !== '') {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            $v = $d && $d->format('Y-m-d') === $v ? $v : '';
        }
        if ($f['is_required'] && $v === '') {
            $errors[] = t('form.err_required', ['field' => tr($f, 'label')]);
        }
        if ($f['type'] === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('form.err_email');
        }
        $data[$f['fkey']] = ['label' => $f['label_cs'], 'value' => $v];
    }
    // preferovaný termín: „do“ nesmí být před „od“ (ISO data lze porovnat jako text)
    $from = $data['term_from']['value'] ?? '';
    $to   = $data['term_to']['value'] ?? '';
    if ($from !== '' && $to !== '' && $to < $from) $errors[] = t('form.err_dates');
    if (empty($_POST['gdpr'])) $errors[] = t('form.err_gdpr');
    if ($errors) return $errors;

    // data v e-mailu a administraci česky: 2026-11-07 → 7. 11. 2026
    foreach ($fields as $f) {
        if ($f['type'] === 'date' && ($data[$f['fkey']]['value'] ?? '') !== '') {
            $data[$f['fkey']]['value'] = date('j. n. Y', strtotime($data[$f['fkey']]['value']));
        }
    }

    $get = fn(string $k) => $data[$k]['value'] ?? '';
    $email = $get('email');
    if ($email === '') {                                   // e-mail pod jiným klíčem
        foreach ($fields as $f) if ($f['type'] === 'email') { $email = $get($f['fkey']); break; }
    }

    $customer = customer();
    q("INSERT INTO inquiries (customer_id, name, email, phone, location, message, data, source, lang, ip) VALUES (?,?,?,?,?,?,?,?,?,?)", [
        $customer['id'] ?? null,
        $get('name') ?: '—', $email, $get('phone') ?: null, $get('location') ?: null, $get('message') ?: null,
        json_encode($data, JSON_UNESCAPED_UNICODE), $source, lang(), $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $newId = (int) db()->lastInsertId();

    global $CONFIG;
    if (!empty($CONFIG['notify_email'])) {
        $body = "Nová poptávka č. {$newId} z webu ({$source}, " . strtoupper(lang()) . ")"
              . ($customer ? "\nRegistrovaný zákazník: {$customer['name']} <{$customer['email']}>" : '') . "\n\n";
        foreach ($data as $d) if ($d['value'] !== '') $body .= "{$d['label']}: {$d['value']}\n";
        $body .= "\nAdministrace: " . abs_url(url('admin/'));
        send_mail($CONFIG['notify_email'], "Nová poptávka č. {$newId} z webu", $body, $email);
    }

    if ($customer) {                                       // zákazník → rovnou na detail poptávky v účtu
        flash('acc', t('form.thanks'));
        redirect(route_url('account') . '?id=' . $newId);
    }
    flash('inquiry', t('form.thanks'));
    redirect($returnUrl . '#formular');
}

// ---------------------------------------------------------------
// robots.txt + sitemap.xml
// ---------------------------------------------------------------

function sitemap_urls(): array
{
    $urls = [];
    $add = function (string $cs, string $en, string $prio, ?string $mod = null) use (&$urls) {
        $urls[] = ['cs' => abs_url($cs), 'en' => abs_url($en), 'prio' => $prio, 'mod' => $mod];
    };
    foreach (array_keys(ROUTES) as $r) {
        if (in_array($r, PRIVATE_ROUTES, true)) continue;
        $add(route_url($r, 'cs'), route_url($r, 'en'), $r === 'home' ? '1.0' : '0.6');
    }
    foreach (q("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort")->fetchAll() as $c) {
        $add(item_url($c, 'cs'), item_url($c, 'en'), $c['parent_id'] ? '0.7' : '0.9');
    }
    foreach (q("SELECT * FROM projects WHERE is_published = 1 ORDER BY created_at DESC")->fetchAll() as $p) {
        $add(item_url($p, 'cs'), item_url($p, 'en'), '0.5', substr((string) $p['created_at'], 0, 10));
    }
    return $urls;
}

function build_sitemap(): string
{
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
       . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    foreach (sitemap_urls() as $u) {
        foreach (['cs', 'en'] as $l) {
            $x .= "  <url>\n    <loc>" . e($u[$l]) . "</loc>\n";
            if ($u['mod']) $x .= "    <lastmod>{$u['mod']}</lastmod>\n";
            $x .= "    <priority>{$u['prio']}</priority>\n";
            $x .= '    <xhtml:link rel="alternate" hreflang="cs" href="' . e($u['cs']) . "\"/>\n";
            $x .= '    <xhtml:link rel="alternate" hreflang="en" href="' . e($u['en']) . "\"/>\n";
            $x .= "  </url>\n";
        }
    }
    return $x . "</urlset>\n";
}

function build_robots(): string
{
    $r = "User-agent: *\n";
    if (!prop_bool('seo.indexing')) {
        return $r . "Disallow: /\n";
    }
    $r .= "Allow: /\n";
    $extra = trim((string) (props()['seo.robots_extra']['value_cs'] ?? ''));
    if ($extra !== '') $r .= str_replace("\r", '', $extra) . "\n";
    if (accounts_on()) {
        foreach (PRIVATE_ROUTES as $p) foreach (['cs', 'en'] as $l) $r .= 'Disallow: ' . route_url($p, $l) . "\n";
    }
    return $r . "\nSitemap: " . abs_url(url('sitemap.xml')) . "\n";
}

/** Zapíše robots.txt a sitemap.xml do kořene webu */
function write_seo_files(): array
{
    $res = [];
    foreach (['robots.txt' => build_robots(), 'sitemap.xml' => build_sitemap()] as $file => $content) {
        $res[$file] = file_put_contents(ROOT_DIR . '/' . $file, $content) !== false;
    }
    return $res;
}
