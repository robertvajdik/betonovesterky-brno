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
    'track'      => ['poptavka', 'enquiry'],             // soukromý odkaz zákazníka (bez účtu)
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
        // texty administrace: když řádek v DB chybí (ještě neproběhla aktualizace), použije se česká záloha
        static $admDefaults = null;
        if (str_starts_with($key, 'adm.')) $admDefaults ??= (array) require __DIR__ . '/admin-texts.php';
        $v = $admDefaults[$key] ?? $key;
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

/** Odkaz na chat ve WhatsAppu (prázdné číslo v Nastavení = '') s předvyplněnou zprávou */
function whatsapp_url(): string
{
    $n = preg_replace('~\D~', '', site('whatsapp'));
    if (strlen($n) < 8) return '';
    if (strlen($n) === 9) $n = '420' . $n;              // české číslo bez předvolby
    $msg = t('contact.wa_text');
    return 'https://wa.me/' . $n . ($msg !== '' && $msg !== 'contact.wa_text' ? '?text=' . rawurlencode($msg) : '');
}

/** Measurement ID Google Analytics, pokud je měření zapnuté a ID má správný tvar; jinak '' */
function ga_id(): string
{
    // odstranit i neviditelné znaky (nezlomitelná mezera, zero-width…) zkopírované z rozhraní Google
    $id = preg_replace('~[^A-Z0-9-]~', '', strtoupper((string) (props()['ga.id']['value_cs'] ?? '')));
    return prop_bool('ga.enabled') && preg_match('~^G-[A-Z0-9]{4,}$~', $id) ? $id : '';
}

/**
 * Meta description 110–160 znaků: vlastní text, doplněný větami z obsahu stránky ($more)
 * a nakonec dovětkem seo.desc_suffix („Brno a okolí, zaměření a vzorky zdarma.“).
 */
function seo_desc(?string $text, ?string $more = null): string
{
    $clean = fn(?string $s): string => trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ['. ', ' ', '. ', '. ', '. '], (string) $s)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $d = rtrim($clean($text), ' .');
    $d = $d !== '' ? $d . '.' : '';
    if (mb_strlen($d) < 110 && $more) {
        foreach (preg_split('~(?<=[.!?])\s+~u', $clean($more)) as $s) {
            $s = trim(preg_replace('~\.{2,}~', '.', $s));
            if (mb_strlen($s) < 20 || mb_stripos($d, rtrim($s, '.')) !== false) continue;
            if (mb_strlen($d . ' ' . $s) > 160) break;
            $d = trim($d . ' ' . $s);
            if (mb_strlen($d) >= 110) break;
        }
    }
    $suffix = trim(t('seo.desc_suffix'));
    if (mb_strlen($d) < 110 && $suffix !== '' && $suffix !== 'seo.desc_suffix' && mb_strlen($d . ' ' . $suffix) <= 160) $d = trim($d . ' ' . $suffix);
    if (mb_strlen($d) > 160) $d = rtrim(mb_substr($d, 0, (int) (mb_strrpos(mb_substr($d, 0, 158), ' ') ?: 158)), ' ,.;–-') . '…';
    return $d;
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
    // adresa webu: config.php → 'domain' (má přednost), jinak Nastavení → Firma → Doména webu, jinak aktuální adresa požadavku
    global $CONFIG;
    $domain = rtrim((string) ($CONFIG['domain'] ?? '') ?: site('domain'), '/');
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
/**
 * ' width="…" height="…"' podle skutečného rozměru obrázku z /uploads (prohlížeč si podle nich vyhradí místo
 * a stránka při načítání neposkakuje – Core Web Vitals / CLS). Externí nebo chybějící soubor = ''.
 */
function img_dims(?string $file): string
{
    static $cache = [];
    if (!$file || preg_match('~^(https?:)?//|^/~', $file)) return '';
    if (!array_key_exists($file, $cache)) {
        $path = UPLOAD_DIR . '/' . $file;
        $s = is_file($path) ? @getimagesize($path) : false;
        $cache[$file] = $s && $s[0] > 0 ? ' width="' . (int) $s[0] . '" height="' . (int) $s[1] . '"' : '';
    }
    return $cache[$file];
}

function img(?string $file, string $alt, string $class = '', bool $eager = false): string
{
    $src = image_url($file);
    if ($src) {
        return '<img src="' . e($src) . '" alt="' . e($alt) . '" class="' . e($class) . '"' . img_dims($file) . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
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
            . '<img src="' . e($src) . '" alt="' . e($cap) . '"' . img_dims($im['file']) . ' loading="lazy" decoding="async"' . md('itemprop="thumbnailUrl"') . '>'
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

/** Základní pole poptávky – ukládají se i do sloupců tabulky inquiries, v administraci je nelze smazat */
const FORM_SYSTEM_FIELDS = ['name', 'email', 'phone', 'location', 'message'];

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

/**
 * Načte a zkontroluje odeslaná pole poptávky (f_*), vrátí [data, chyby].
 * $required = false: povinnost polí se nekontroluje (ruční zadání v administraci).
 */
function inquiry_collect(array $fields, bool $required = true): array
{
    $errors = [];
    $data = [];
    foreach ($fields as $f) {
        $v = post('f_' . $f['fkey'], $f['type'] === 'textarea' ? 5000 : 300);
        if ($f['type'] === 'select' && $v !== '' && !in_array($v, field_options($f), true)) $v = '';
        if ($f['type'] === 'checkbox') $v = $v !== '' ? '✓' : '';
        if ($f['type'] === 'date' && $v !== '') {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            $v = $d && $d->format('Y-m-d') === $v ? $v : '';
        }
        if ($required && $f['is_required'] && $v === '') {
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
    return [$data, $errors];
}

/** Data v e-mailu a administraci česky: 2026-11-07 → 7. 11. 2026 */
function inquiry_format_dates(array $fields, array &$data): void
{
    foreach ($fields as $f) {
        if ($f['type'] === 'date' && ($data[$f['fkey']]['value'] ?? '') !== '') {
            $data[$f['fkey']]['value'] = date('j. n. Y', strtotime($data[$f['fkey']]['value']));
        }
    }
}

/** E-mail zákazníka z dat poptávky (pole „email“, jinak první pole typu e-mail) */
function inquiry_email(array $fields, array $data): string
{
    $email = $data['email']['value'] ?? '';
    if ($email === '') {
        foreach ($fields as $f) if ($f['type'] === 'email') { $email = $data[$f['fkey']]['value'] ?? ''; break; }
    }
    return $email;
}

/** Zpracuje odeslaný poptávkový formulář; při úspěchu přesměruje na $returnUrl */
function handle_inquiry(string $source, string $returnUrl): array
{
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['form'] ?? '') !== 'inquiry') return $errors;

    if (!empty($_POST['website'])) redirect($returnUrl);       // honeypot
    if (!csrf_ok()) return [t('form.err_csrf')];
    if (!recaptcha_ok('inquiry')) return [t('form.err_captcha')];

    $fields = form_fields();
    [$data, $errors] = inquiry_collect($fields);
    if (empty($_POST['gdpr'])) $errors[] = t('form.err_gdpr');
    $photos = inquiry_uploaded_photos();                   // fotky „co chcete natřít“ (includes/inquiry-files.php)
    $errors = array_merge($errors, inquiry_photos_errors($photos));
    if ($errors) return $errors;

    inquiry_format_dates($fields, $data);
    $get   = fn(string $k) => $data[$k]['value'] ?? '';
    $email = inquiry_email($fields, $data);

    $customer = customer();
    q("INSERT INTO inquiries (customer_id, name, email, phone, location, message, data, source, lang, ip) VALUES (?,?,?,?,?,?,?,?,?,?)", [
        $customer['id'] ?? null,
        $get('name') ?: '—', $email, $get('phone') ?: null, $get('location') ?: null, $get('message') ?: null,
        json_encode($data, JSON_UNESCAPED_UNICODE), $source, lang(), $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $newId = (int) db()->lastInsertId();
    $nPhotos = inquiry_store_photos($newId, $photos);
    send_track_link($newId);                                // zákazníkovi odkaz na stav poptávky, smlouvu a faktury

    global $CONFIG;
    if (!empty($CONFIG['notify_email'])) {
        $body = "Nová poptávka č. {$newId} z webu ({$source}, " . strtoupper(lang()) . ")"
              . ($customer ? "\nRegistrovaný zákazník: {$customer['name']} <{$customer['email']}>" : '') . "\n\n";
        foreach ($data as $d) if ($d['value'] !== '') $body .= "{$d['label']}: {$d['value']}\n";
        if ($nPhotos) $body .= "Přiložené fotky: {$nPhotos} (zobrazíte je v administraci)\n";
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
       . '<?xml-stylesheet type="text/xsl" href="' . e(url('assets/sitemap.xsl')) . '"?>' . "\n"
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
