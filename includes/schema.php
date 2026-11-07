<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Strukturovaná data (schema.org): JSON-LD, Microdata, RDFa
//   JSON-LD   – <head>: firma, web, stránka, služba / realizace
//   Microdata – hodnocení (Review, AggregateRating), fotky v galeriích (ImageObject)
//   RDFa      – drobečková navigace (BreadcrumbList), kontakt v patičce
// Všechny formáty odkazují na stejnou firmu přes #business, takže se spojí v jednu entitu.
// ---------------------------------------------------------------

/** Zapnutý formát? (výchozí = zapnuto, i když vlastnost v DB chybí) */
function schema_on(string $format): bool
{
    return (string) (props()['seo.' . $format]['value_cs'] ?? '1') === '1';
}

/** Atributy Microdata / RDFa – vypíšou se jen při zapnutém formátu */
function md(string $attrs): string   { return schema_on('microdata') ? ' ' . $attrs : ''; }
function rdfa(string $attrs): string { return schema_on('rdfa') ? ' ' . $attrs : ''; }

function business_id(): string { return abs_url(url('')) . '#business'; }
function website_id(): string  { return abs_url(url('')) . '#website'; }

/** Adresa „Ulice 1, 615 00 Brno“ → části PostalAddress */
function address_parts(): array
{
    $a = trim(site('address'));
    if (preg_match('~^(.+?),\s*(\d{3}\s?\d{2})\s+(.+)$~u', $a, $m)) {
        return ['street' => trim($m[1]), 'zip' => $m[2], 'city' => trim($m[3])];
    }
    return ['street' => $a, 'zip' => '', 'city' => ''];
}

function schema_address(): array
{
    $p = address_parts();
    return array_filter([
        '@type'           => 'PostalAddress',
        'streetAddress'   => $p['street'],
        'postalCode'      => $p['zip'],
        'addressLocality' => $p['city'],
        'addressCountry'  => 'CZ',
    ]);
}

function abs_image(?string $file): ?string
{
    $u = image_url($file);
    if (!$u) return null;
    return preg_match('~^(https?:)?//~', $u) ? $u : abs_url($u);
}

function schema_business(): array
{
    $logo  = abs_image(props()['img.logo']['value_cs'] ?? '');
    $image = abs_image(props()['img.hero']['value_cs'] ?? '') ?: $logo;
    $city  = address_parts()['city'];

    $offers = [];
    try {
        foreach (services() as $s) {
            $offers[] = ['@type' => 'Offer', 'itemOffered' => [
                '@type' => 'Service', 'name' => tr($s, 'title'), 'url' => abs_url(item_url($s)),
            ]];
        }
    } catch (PDOException) {
    }

    return array_filter([
        '@type'      => 'HomeAndConstructionBusiness',
        '@id'        => business_id(),
        'name'       => site('name'),
        'description'=> t('site.tagline'),
        'url'        => abs_url(route_url('home')),
        'logo'       => $logo,
        'image'      => $image,
        'telephone'  => site('phone'),
        'email'      => email_protected() ? null : site('email'),
        'address'    => schema_address(),
        'areaServed' => $city !== '' ? ['@type' => 'City', 'name' => $city] : null,
        'founder'    => site('owner') !== '' ? ['@type' => 'Person', 'name' => site('owner')] : null,
        'identifier' => site('ico') !== '' ? ['@type' => 'PropertyValue', 'propertyID' => 'IČO', 'value' => site('ico')] : null,
        'sameAs'     => array_values(array_filter([site('facebook'), site('instagram')])) ?: null,
        'hasOfferCatalog' => $offers ? ['@type' => 'OfferCatalog', 'name' => t('site.tagline'), 'itemListElement' => $offers] : null,
    ], fn($v) => $v !== null && $v !== '');
}

/**
 * Celý JSON-LD graf pro aktuální stránku.
 * $extra = uzly přidané šablonou stránky (Service, CreativeWork …)
 */
function jsonld(string $page, string $canonical, string $title, string $desc, ?string $image, array $extra = []): string
{
    if (!schema_on('jsonld')) return '';

    $types = ['home' => 'WebPage', 'references' => 'CollectionPage', 'contact' => 'ContactPage',
              'reviews' => 'WebPage', 'pricing' => 'WebPage', 'category' => 'WebPage', 'project' => 'WebPage'];
    $pageNode = array_filter([
        '@type'      => $types[$page] ?? 'WebPage',
        '@id'        => $canonical . '#webpage',
        'url'        => $canonical,
        'name'       => $title,
        'description'=> $desc,
        'inLanguage' => lang(),
        'isPartOf'   => ['@id' => website_id()],
        'about'      => in_array($page, ['home', 'contact', 'reviews'], true) ? ['@id' => business_id()] : null,
        'primaryImageOfPage' => $image ? ['@type' => 'ImageObject', 'url' => $image] : null,
    ]);
    foreach ($extra as $node) {
        if (!empty($node['@id'])) $pageNode['mainEntity'] = ['@id' => $node['@id']];
    }

    $graph = [
        schema_business(),
        ['@type' => 'WebSite', '@id' => website_id(), 'url' => abs_url(route_url('home')), 'name' => site('name'),
         'inLanguage' => ['cs', 'en'], 'publisher' => ['@id' => business_id()]],
        $pageNode,
        ...$extra,
    ];
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT);
    return '<script type="application/ld+json">' . "\n" . $json . "\n</script>\n";
}

/**
 * Drobečková navigace s RDFa (BreadcrumbList).
 * $items = [[popisek, url], …, [popisek]] – úvodní stránka se doplní automaticky.
 */
function breadcrumbs(array $items): string
{
    array_unshift($items, [t('nav.home'), route_url('home')]);
    $h = '<nav class="crumbs" aria-label="breadcrumb"><ol' . rdfa('vocab="https://schema.org/" typeof="BreadcrumbList"') . '>';
    foreach ($items as $i => [$label, $href]) {
        $pos  = $i + 1;
        $last = $i === count($items) - 1;
        $h .= '<li' . rdfa('property="itemListElement" typeof="ListItem"') . '>';
        if (!$last && $href) {
            $h .= '<a href="' . e($href) . '"' . rdfa('property="item" typeof="WebPage"') . '><span' . rdfa('property="name"') . '>' . e($label) . '</span></a>';
        } else {
            $h .= '<span' . rdfa('property="name"') . ' aria-current="page">' . e($label) . '</span>';
        }
        $h .= schema_on('rdfa') ? '<meta property="position" content="' . $pos . '">' : '';
        $h .= '</li>';
    }
    return $h . '</ol></nav>';
}

// ---------------------------------------------------------------
// Ochrana e-mailů před roboty
// V HTML není adresa v čitelné podobě: je uložena pozpátku v base64
// a teprve JavaScript z ní složí odkaz mailto:. Bez JS se zobrazí „info [at] domena.cz“.
// ---------------------------------------------------------------

function email_protected(): bool
{
    return (string) (props()['site.email_protect']['value_cs'] ?? '1') === '1';
}

function email_link(string $email, string $class = ''): string
{
    if ($email === '') return '';
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
    if (!email_protected()) {
        return '<a href="mailto:' . e($email) . '"' . $cls . '>' . e($email) . '</a>';
    }
    $code = base64_encode(strrev($email));
    $fallback = '';
    foreach (preg_split('//u', str_replace(['@', '.'], [' [at] ', ' [dot] '], $email), -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $fallback .= '&#' . mb_ord($ch) . ';';
    }
    return '<a href="#" data-m="' . e($code) . '" rel="nofollow"' . $cls . '>' . $fallback . '</a>';
}
