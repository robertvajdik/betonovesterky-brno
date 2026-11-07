<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Fotky k poptávce (co chce zákazník natřít / opravit)
//  - ukládají se mimo veřejné uploads: uploads/private/inquiries/{id}/ (přístup jen přes PHP)
//  - náhodné názvy, kontrola, že jde o obrázek, přeuložení přes GD (odstraní GPS a další metadata)
//  - vidí je admin (admin/file.php) a zákazník přes soukromý odkaz (/poptavka/…&photo=)
// ---------------------------------------------------------------

const INQ_MAX_PHOTOS = 5;
const INQ_MAX_BYTES  = 12 * 1024 * 1024;    // na fotku (prohlížeč fotky před odesláním zmenší, tohle je pojistka)
const INQ_MAX_SIDE   = 2400;                // delší strana po přeuložení

function inquiry_files_dir(int $inquiryId = 0): string
{
    $base = UPLOAD_DIR . '/private';
    if (!is_dir($base)) {
        @mkdir($base, 0775, true);
        // Apache: složku nikdy neservírovat přímo (nginx: přidejte „location /uploads/private/ { deny all; }“)
        @file_put_contents($base . '/.htaccess', "Require all denied\n");
        @file_put_contents($base . '/index.html', '');
    }
    return $base . '/inquiries' . ($inquiryId ? '/' . $inquiryId : '');
}

/** Odeslané fotky z formuláře (pole photos[]) – jen ty, které se skutečně nahrály */
function inquiry_uploaded_photos(): array
{
    $f = $_FILES['photos'] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) return [];
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => (string) $name, 'tmp' => (string) $f['tmp_name'][$i], 'size' => (int) $f['size'][$i], 'error' => (int) $f['error'][$i]];
    }
    return $out;
}

/** Kontrola před uložením poptávky: [] = v pořádku, jinak chybové hlášky */
function inquiry_photos_errors(array $photos): array
{
    $err = [];
    if (count($photos) > INQ_MAX_PHOTOS) $err[] = t('form.err_photos_count', ['max' => INQ_MAX_PHOTOS]);
    foreach ($photos as $p) {
        if (in_array($p['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $p['size'] > INQ_MAX_BYTES) {
            $err[] = t('form.err_photo_size', ['file' => $p['name']]);
        } elseif ($p['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($p['tmp'])) {
            $err[] = t('form.err_photo_upload', ['file' => $p['name']]);
        } elseif (!in_array(@getimagesize($p['tmp'])[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $err[] = t('form.err_photo_type', ['file' => $p['name']]);
        }
    }
    return array_values(array_unique($err));
}

/** Uloží zkontrolované fotky k poptávce, vrátí počet uložených */
function inquiry_store_photos(int $inquiryId, array $photos): int
{
    if (!$photos) return 0;
    $dir = inquiry_files_dir($inquiryId);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { error_log("Nelze vytvořit $dir"); return 0; }
    $n = 0;
    foreach (array_slice($photos, 0, INQ_MAX_PHOTOS) as $p) {
        $type = @getimagesize($p['tmp'])[2] ?? 0;
        $file = bin2hex(random_bytes(16)) . '.jpg';
        if (!inquiry_reencode($p['tmp'], $dir . '/' . $file, (int) $type)) {
            // bez GD: uložit původní soubor (prohlížeč ho obvykle už přeuložil bez metadat)
            $file = bin2hex(random_bytes(16)) . image_type_to_extension((int) $type);
            if (!move_uploaded_file($p['tmp'], $dir . '/' . $file)) continue;
        }
        q("INSERT INTO inquiry_files (inquiry_id, file, original, size) VALUES (?,?,?,?)",
          [$inquiryId, $file, mb_substr(basename($p['name']), 0, 160), (int) @filesize($dir . '/' . $file)]);
        $n++;
    }
    return $n;
}

/** Přeuložení přes GD do JPEG (zmenšení, bez metadat). false = GD chybí nebo obrázek nejde načíst */
function inquiry_reencode(string $src, string $dest, int $type): bool
{
    if (!function_exists('imagecreatetruecolor')) return false;
    $img = match ($type) {
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($src) : false,
        IMAGETYPE_PNG  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($src) : false,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default        => false,
    };
    if (!$img) return false;
    // otočení podle EXIF (fotky z mobilu), než metadata zahodíme
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($src)['Orientation'] ?? 1);
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg && ($r = imagerotate($img, $deg, 0))) { imagedestroy($img); $img = $r; }
    }
    [$w, $h] = [imagesx($img), imagesy($img)];
    $scale = min(1, INQ_MAX_SIDE / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));      // průhlednost PNG → bílá
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($out, $dest, 85);
    imagedestroy($img); imagedestroy($out);
    return $ok;
}

function inquiry_photos(int $inquiryId): array
{
    return q("SELECT * FROM inquiry_files WHERE inquiry_id = ? ORDER BY id", [$inquiryId])->fetchAll();
}

/** Pošle fotku do prohlížeče (volající už ověřil oprávnění) */
function inquiry_send_photo(array $row, bool $thumb = false): never
{
    $path = inquiry_files_dir((int) $row['inquiry_id']) . '/' . basename((string) $row['file']);
    if (!is_file($path)) { http_response_code(404); exit; }
    $type = @getimagesize($path)['mime'] ?? 'application/octet-stream';
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . preg_replace('~[^\w.-]+~u', '_', (string) $row['original']) . '"');
    readfile($path);
    exit;
}

/** Smaže fotky poptávky (soubory i záznamy) – při smazání poptávky */
function inquiry_delete_photos(int $inquiryId): void
{
    $dir = inquiry_files_dir($inquiryId);
    foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
    @rmdir($dir);
    q("DELETE FROM inquiry_files WHERE inquiry_id = ?", [$inquiryId]);
}
