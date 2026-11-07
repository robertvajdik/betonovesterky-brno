<?php
/**
 * Stáhne fotky a grafiku z původního webu www.tomas-rucker.cz do /uploads/orig
 * a vygeneruje SQL (database/import-original.sql), které je přiřadí ke stránkám.
 *
 * Spuštění:  php tools/import-original.php
 * Poté:      mysql -u root -p betonovesterky < database/import-original.sql
 */
declare(strict_types=1);

const ORIGIN = 'https://www.tomas-rucker.cz';
$root   = dirname(__DIR__);
$outDir = $root . '/uploads/orig';
@mkdir($outDir, 0775, true);

// původní cesta => [typ, slug na novém webu]
$pages = [
    '/betonove-sterky/'           => ['c', 'betonove-sterky'],
    '/do-kuchyne/'                => ['c', 'do-kuchyne'],
    '/do-koupelny/'               => ['c', 'do-koupelny'],
    '/do-obyvaciho-pokoje/'       => ['c', 'do-obyvaciho-pokoje'],
    '/na-podlahu/'                => ['c', 'na-podlahu'],
    '/na-schody/'                 => ['c', 'na-schody'],
    '/na-nabytek/'                => ['c', 'na-nabytek'],
    '/na-krb/'                    => ['c', 'na-krb'],
    '/fasada/'                    => ['c', 'fasada'],
    '/plot/'                      => ['c', 'plot'],
    '/venkovni-betonova-sterka/'  => ['c', 'venkovni-betonova-sterka'],
    '/dekorativni-omitky/'        => ['c', 'dekorativni-omitky'],
    '/malirske-prace/'            => ['c', 'malirske-prace'],
    '/oprava-betonove-terasy-v-beskydech/'           => ['p', 'oprava-betonove-terasy-v-beskydech'],
    '/betonova-sterka-na-krb-s-mandalami/'           => ['p', 'betonova-sterka-na-krb-s-mandalami'],
    '/realizace-mikrocementove-sterky-na-schodech/'  => ['p', 'realizace-mikrocementove-sterky-na-schodech'],
    '/betonova-sterka-na-stenach-kolem-schodiste/'   => ['p', 'betonova-sterka-na-stenach-kolem-schodiste'],
    '/uprava-schodu-betonovou-sterkou/'              => ['p', 'uprava-schodu-betonovou-sterkou'],
    '/reference/'                 => ['r', ''],
];

// grafika šablony => [kam, klíč]
$template = [
    '/template/img/logo/logo.png'                 => ['prop', 'img.logo'],
    '/template/img/favicon.ico'                   => ['prop', 'img.favicon'],
    '/template/img/sekce-3/slide1.jpg'            => ['prop', 'img.hero'],
    '/template/img/sekce-6/background.jpg'        => ['prop', 'img.why_bg'],
    '/template/img/sekce-5/vyhoda.png'            => ['prop', 'img.benefit_icon'],
    '/template/img/sekce-4/kuchyne.jpg'           => ['cover', 'do-kuchyne'],
    '/template/img/sekce-4/koupelna2.jpg'         => ['cover', 'do-koupelny'],
    '/template/img/sekce-4/obyvak.jpg'            => ['cover', 'do-obyvaciho-pokoje'],
    '/template/img/sekce-4/podlahy.jpg'           => ['cover', 'na-podlahu'],
    '/template/img/sekce-4/schody2.jpg'           => ['cover', 'na-schody'],
    '/template/img/sekce-4/koupelna.jpg'          => ['cover', 'na-nabytek'],
    '/template/img/sekce-4/krby.jpg'              => ['cover', 'na-krb'],
    '/template/img/sekce-4/betonove-sterky-1.jpg' => ['cover', 'betonove-sterky'],
    '/template/img/sekce-4/dekorativni_omitky.jpg'=> ['cover', 'dekorativni-omitky'],
    '/template/img/sekce-4/malirske_prace.jpg'    => ['cover', 'malirske-prace'],
    '/template/img/sekce-7/betonove-sterky-2.jpg' => ['cover', 'venkovni-betonova-sterka'],
    '/template/img/sekce-7/fasada.jpg'            => ['cover', 'fasada'],
    '/template/img/sekce-7/plot.jpg'              => ['cover', 'plot'],
];

function fetch(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (site migration)',
    ]);
    // Windows bez CA bundle: použij systémové úložiště certifikátů
    if (defined('CURLSSLOPT_NATIVE_CA')) curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $code === 200) ? $body : null;
}

function save_image(string $path, string $outDir): ?string
{
    $name = strtolower(preg_replace('~[^a-z0-9._-]+~i', '-', basename(parse_url($path, PHP_URL_PATH))));
    if (str_contains($path, '/template/')) $name = 'tpl-' . basename(dirname($path)) . '-' . $name;
    $dest = $outDir . '/' . $name;
    if (is_file($dest)) return 'orig/' . $name;
    $data = fetch(ORIGIN . $path);
    if ($data === null) { fwrite(STDERR, "  ! nelze stáhnout $path\n"); return null; }
    $isIco = str_ends_with($name, '.ico');
    if (!$isIco && @getimagesizefromstring($data) === false) { fwrite(STDERR, "  ! není obrázek $path\n"); return null; }
    file_put_contents($dest, $data);
    return 'orig/' . $name;
}

function sq(?string $s): string { return $s === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'"; }

$sql = ["-- Vygenerováno tools/import-original.php " . date('Y-m-d H:i'), 'SET NAMES utf8mb4;', ''];
$seen = [];

echo "Grafika šablony…\n";
foreach ($template as $path => [$kind, $key]) {
    $file = save_image($path, $outDir);
    if (!$file) continue;
    $sql[] = $kind === 'prop'
        ? "UPDATE properties SET value_cs = " . sq($file) . " WHERE pkey = " . sq($key) . ";"
        : "UPDATE categories SET cover_image = " . sq($file) . " WHERE slug = " . sq($key) . ";";
}
$sql[] = '';
$sql[] = "DELETE FROM projects WHERE slug = 'ukazkova-realizace-koupelna';";

foreach ($pages as $path => [$kind, $slug]) {
    echo "Stránka $path\n";
    $html = fetch(ORIGIN . $path);
    if ($html === null) { fwrite(STDERR, "  ! stránka nedostupná\n"); continue; }

    if ($kind === 'p') {
        $title = preg_match('~<h1[^>]*>(.*?)</h1>~is', $html, $m) ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')) : $slug;
        $sql[] = "INSERT INTO projects (slug, title, summary) VALUES (" . sq($slug) . ", " . sq($title) . ", NULL) ON DUPLICATE KEY UPDATE title = VALUES(title);";
    }

    preg_match_all('~(?:https?://www\.tomas-rucker\.cz)?(/foto/(?!.*?/zm/)[A-Za-z0-9_./-]+\.(?:jpe?g|png|webp))~i', $html, $mm);
    $n = 0;
    foreach (array_unique($mm[1]) as $img) {
        if (preg_match('~/(zm|mini|thumb)/~i', $img)) continue;
        $file = save_image($img, $outDir);
        if (!$file) continue;
        if (isset($seen[$file])) continue;          // každá fotka jen jednou
        $seen[$file] = true;
        $n++;
        $cat  = $kind === 'c' ? "(SELECT id FROM categories WHERE slug = " . sq($slug) . ")" : 'NULL';
        $proj = $kind === 'p' ? "(SELECT id FROM projects WHERE slug = " . sq($slug) . ")" : 'NULL';
        $sql[] = "INSERT INTO gallery_images (category_id, project_id, file, in_references, sort) VALUES ($cat, $proj, " . sq($file) . ", 1, $n);";
    }
    if ($kind === 'p' && $n) {
        $first = array_key_last($seen);
        $sql[] = "UPDATE projects SET cover_image = (SELECT file FROM (SELECT file FROM gallery_images WHERE project_id = (SELECT id FROM projects WHERE slug = " . sq($slug) . ") ORDER BY sort LIMIT 1) t) WHERE slug = " . sq($slug) . ";";
    }
    echo "  fotek: $n\n";
}

file_put_contents($root . '/database/import-original.sql', implode("\n", $sql) . "\n");
echo "\nHotovo: " . count(glob($outDir . '/*')) . " souborů v uploads/orig, SQL v database/import-original.sql\n";
