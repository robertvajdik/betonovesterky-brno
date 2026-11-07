<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Příspěvky z Facebook stránky přes Graph API (stahuje server, ne prohlížeč návštěvníka)
//  - výsledek se ukládá do uploads/fb/posts.json na 1 hodinu, fotky do uploads/fb/
//  - návštěvník se k Facebooku vůbec nepřipojuje → žádné cookies, žádný souhlas
//  - přístup: config.php → 'facebook' => ['page_id' => '…', 'token' => '…'] (Page access token)
// ---------------------------------------------------------------

const FB_CACHE_TTL = 3600;

function fb_configured(): bool
{
    global $CONFIG;
    return !empty($CONFIG['facebook']['page_id']) && !empty($CONFIG['facebook']['token']);
}

/**
 * Posledních $limit příspěvků: [['id','text','date','url','image'], …], nejnovější první.
 * Při chybě API vrátí poslední uložené příspěvky (nebo []), web tím nikdy nespadne.
 */
function fb_posts(int $limit = 6): array
{
    if (!fb_configured()) return [];
    $dir   = UPLOAD_DIR . '/fb';
    $cache = $dir . '/posts.json';
    $data  = is_file($cache) ? (json_decode((string) file_get_contents($cache), true) ?: []) : [];
    $fresh = $data && (time() - (int) ($data['fetched'] ?? 0)) < FB_CACHE_TTL;

    if (!$fresh && (is_dir($dir) || @mkdir($dir, 0775, true))) {
        // jen jeden požadavek najednou obnovuje cache, ostatní zatím použijí starou verzi
        $lock = @fopen($dir . '/.lock', 'c');
        if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
            $posts = fb_fetch(12);
            if ($posts !== null) {
                $data = ['fetched' => time(), 'posts' => $posts];
                file_put_contents($cache . '.tmp', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                rename($cache . '.tmp', $cache);
                fb_cleanup_images($dir, $posts);
            } elseif ($data) {
                $data['fetched'] = time() - FB_CACHE_TTL + 600;   // chyba API → zkusit znovu za 10 minut
                @file_put_contents($cache, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            flock($lock, LOCK_UN);
        }
        if ($lock) fclose($lock);
    }
    return array_slice((array) ($data['posts'] ?? []), 0, $limit);
}

/** Stáhne příspěvky z Graph API; null = chyba */
function fb_fetch(int $limit): ?array
{
    global $CONFIG;
    $fb  = $CONFIG['facebook'];
    $url = 'https://graph.facebook.com/' . rawurlencode((string) $fb['page_id']) . '/posts?' . http_build_query([
        'fields'       => 'message,created_time,permalink_url,full_picture,is_hidden',
        'limit'        => $limit,
        'access_token' => (string) $fb['token'],
    ]);
    $res = fb_http($url, 8);
    if ($res === null) return null;
    $json = json_decode($res, true);
    if (!is_array($json) || isset($json['error']) || !isset($json['data'])) {
        error_log('Facebook API: ' . (is_array($json) ? ($json['error']['message'] ?? 'neplatná odpověď') : 'neplatná odpověď'));
        return null;
    }
    $posts = [];
    foreach ($json['data'] as $p) {
        $text = trim((string) ($p['message'] ?? ''));
        if (!empty($p['is_hidden']) || ($text === '' && empty($p['full_picture']))) continue;
        $id = preg_replace('~[^0-9_]~', '', (string) ($p['id'] ?? ''));
        if ($id === '') continue;
        $posts[] = [
            'id'    => $id,
            'text'  => $text,
            'date'  => substr((string) ($p['created_time'] ?? ''), 0, 10),
            'url'   => preg_match('~^https://(www\.)?facebook\.com/~', (string) ($p['permalink_url'] ?? '')) ? $p['permalink_url'] : '',
            'image' => !empty($p['full_picture']) ? fb_store_image((string) $p['full_picture'], $id) : null,
        ];
    }
    return $posts;
}

/** Uloží fotku příspěvku do uploads/fb (odkazy na CDN Facebooku po čase expirují) */
function fb_store_image(string $src, string $id): ?string
{
    $rel = 'fb/' . $id . '.jpg';
    $abs = UPLOAD_DIR . '/' . $rel;
    if (is_file($abs)) return $rel;
    if (!preg_match('~^https://[a-z0-9.-]+\.(fbcdn\.net|facebook\.com)/~i', $src)) return null;
    $bin = fb_http($src, 10, 8 * 1024 * 1024);
    if ($bin === null) return null;
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return null;
    if ($info[2] !== IMAGETYPE_JPEG) $rel = 'fb/' . $id . image_type_to_extension($info[2]);
    return file_put_contents(UPLOAD_DIR . '/' . $rel, $bin) !== false ? $rel : null;
}

/** Smaže fotky příspěvků, které už nejsou mezi posledními */
function fb_cleanup_images(string $dir, array $posts): void
{
    $keep = array_filter(array_map(fn($p) => $p['image'] ? basename($p['image']) : null, $posts));
    foreach (glob($dir . '/*.{jpg,png,webp}', GLOB_BRACE) ?: [] as $f) {
        if (!in_array(basename($f), $keep, true)) @unlink($f);
    }
}

function fb_http(string $url, int $timeout, int $maxBytes = 2 * 1024 * 1024): ?string
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'betonovesterky-web/1.0',
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => fn($c, $dlTotal, $dl) => $dl > $maxBytes ? 1 : 0,   // příliš velké → přerušit
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false) error_log('Facebook: ' . curl_error($ch) . ' (' . strtok($url, '?') . ')');
    curl_close($ch);
    if (!is_string($body) || ($code >= 400 && $code !== 400)) return null;   // 400 = chyba API v JSONu, zpracuje volající
    return $body;
}

/** Text příspěvku zkrácený pro kartu */
function fb_excerpt(string $text, int $max = 220): string
{
    $text = trim(preg_replace('~\s+~u', ' ', $text));
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max);
    $sp  = mb_strrpos($cut, ' ');
    return rtrim($sp > $max * .6 ? mb_substr($cut, 0, $sp) : $cut, " ,.;:–-") . '…';
}
