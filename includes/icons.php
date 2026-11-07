<?php
declare(strict_types=1);

/**
 * Ikony jako inline SVG (bez ikonového fontu). Barva = currentColor.
 * icon('phone'), icon('mail', 20) …
 */
function icon(string $name, int $size = 18, string $class = ''): string
{
    static $f = 'fill="currentColor"';
    static $s = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    $paths = [
        'phone'     => [$f, '<path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/>'],
        'mobile'    => [$s, '<rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/>'],
        'mail'      => [$f, '<path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5z"/>'],
        'pin'       => [$f, '<path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>'],
        'clock'     => [$s, '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'user'      => [$f, '<path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z"/>'],
        'id'        => [$s, '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4M7 11h10"/>'],
        'facebook'  => [$f, '<path d="M13.5 22v-8.2h2.8l.4-3.3h-3.2V8.4c0-.95.27-1.6 1.63-1.6h1.72V3.86A23 23 0 0 0 14.35 3.7c-2.47 0-4.15 1.5-4.15 4.27v2.53H7.4v3.3h2.8V22z"/>'],
        'instagram' => [$s, '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>'],
        'logout'    => [$s, '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>'],
        'plus'      => [$s, '<path d="M12 5v14M5 12h14"/>'],
    ];
    if (!isset($paths[$name])) return '';
    [$attr, $body] = $paths[$name];
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" ' . $attr . ' aria-hidden="true" focusable="false">' . $body . '</svg>';
}

/**
 * Ikona na plochu telefonu (apple-touch-icon, manifest).
 * Zdroj = čtvercový obrázek img.app_icon z Nastavení → Grafika; velikosti se vygenerují přes GD.
 */
function app_icon_url(int $size): ?string
{
    $src = (string) (props()['img.app_icon']['value_cs'] ?? '');
    if ($src === '' || preg_match('~^(https?:)?//~', $src)) return $src !== '' ? $src : null;
    $gen = 'icons/' . pathinfo($src, PATHINFO_FILENAME) . "-$size.png";
    if (is_file(UPLOAD_DIR . '/' . $gen) || make_square_png(UPLOAD_DIR . '/' . $src, UPLOAD_DIR . '/' . $gen, $size)) {
        return image_url($gen);
    }
    return image_url($src);   // bez GD: původní soubor
}

function make_square_png(string $from, string $to, int $size): bool
{
    if (!function_exists('imagecreatefromstring') || !is_file($from)) return false;
    $im = @imagecreatefromstring((string) file_get_contents($from));
    if (!$im) return false;
    $w = imagesx($im); $h = imagesy($im); $side = min($w, $h);
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $im, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);
    if (!is_dir(dirname($to))) @mkdir(dirname($to), 0775, true);
    $ok = imagepng($out, $to);
    imagedestroy($im); imagedestroy($out);
    return $ok;
}

function build_manifest(): string
{
    $icons = [];
    foreach ([192, 512] as $s) {
        if ($u = app_icon_url($s)) $icons[] = ['src' => $u, 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any'];
    }
    return (string) json_encode([
        'name'             => site('name'),
        'short_name'       => mb_substr(site('name'), 0, 20),
        'description'      => t('site.tagline'),
        'lang'             => lang(),
        'start_url'        => route_url('home'),
        'scope'            => url(''),
        'display'          => 'browser',
        'background_color' => '#f4f2ee',
        'theme_color'      => '#1f1e1c',
        'icons'            => $icons,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
