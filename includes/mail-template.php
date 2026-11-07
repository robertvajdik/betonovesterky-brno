<?php
declare(strict_types=1);

/*
 * HTML podoba e-mailů zákazníkům (vedle textové verze – posílá se multipart/alternative).
 * Text e-mailu z administrace (Texty CZ/EN → E-maily zákazníkům) se převede na odstavce;
 * řádek, který obsahuje jen odkaz, se zobrazí jako tlačítko. Tabulky a inline styly kvůli e-mailovým klientům.
 */

/** Popisek tlačítka podle druhu e-mailu (texty mail.btn_*) */
function mail_button_label(string $key): string
{
    $map = ['track' => 'track', 'reply' => 'track', 'invoice' => 'invoice', 'invoice_deposit' => 'invoice', 'verify' => 'verify', 'reset' => 'reset'];
    $k = 'mail.btn_' . ($map[$key] ?? 'open');
    $v = t($k);
    return $v !== $k ? $v : (lang() === 'en' ? 'Open' : 'Otevřít');
}

function mail_html(string $text, string $subject, string $button): string
{
    $ink = '#1f1e1c'; $accent = '#b5643a'; $muted = '#6b665f'; $bg = '#f3f1ee';
    $font = "font-family:Helvetica,Arial,sans-serif;";
    $linkify = function (string $s) use ($accent): string {
        return preg_replace_callback('~https?://[^\s<]+~', fn($m) => '<a href="' . $m[0] . '" style="color:' . $accent . ';">' . $m[0] . '</a>', $s);
    };

    $blocks = '';
    foreach (preg_split("~\n{2,}~", trim(str_replace("\r", '', $text))) as $para) {
        $lines = array_map('trim', explode("\n", $para));
        $html = [];
        foreach ($lines as $line) {
            if (preg_match('~^https?://\S+$~', $line)) {           // samostatný odkaz → tlačítko + odkaz pro kopírování
                $u = e($line);
                if ($html) { $blocks .= '<p style="margin:0 0 16px;' . $font . 'font-size:16px;line-height:1.6;color:' . $ink . ';">' . implode('<br>', $html) . '</p>'; $html = []; }
                $blocks .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 12px;"><tr><td style="border-radius:4px;background:' . $accent . ';">'
                         . '<a href="' . $u . '" style="display:inline-block;padding:14px 26px;' . $font . 'font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:4px;">' . e($button) . '</a>'
                         . '</td></tr></table>'
                         . '<p style="margin:0 0 20px;' . $font . 'font-size:12px;line-height:1.5;color:' . $muted . ';">' . te('mail.link_hint') . '<br>'
                         . '<a href="' . $u . '" style="color:' . $muted . ';word-break:break-all;">' . $u . '</a></p>';
                continue;
            }
            $html[] = $linkify(e($line));
        }
        if ($html) $blocks .= '<p style="margin:0 0 16px;' . $font . 'font-size:16px;line-height:1.6;color:' . $ink . ';">' . implode('<br>', $html) . '</p>';
    }

    $web   = abs_url(route_url('home'));
    $phone = site('phone');
    $foot  = e(site('owner')) . ($phone !== '' ? ' · <a href="' . e(tel_href($phone)) . '" style="color:' . $muted . ';">' . e($phone) . '</a>' : '')
           . ' · <a href="' . e($web) . '" style="color:' . $muted . ';">' . e(preg_replace('~^https?://~', '', rtrim($web, '/'))) . '</a>';

    return '<!doctype html><html lang="' . lang() . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><title>' . e($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background:' . $bg . ';">'
        . '<div style="display:none;max-height:0;overflow:hidden;">' . e(mb_substr(preg_replace('~\s+~', ' ', preg_replace('~https?://\S+~', '', $text)), 0, 110)) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $bg . ';"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:6px;overflow:hidden;">'
        . '<tr><td style="background:' . $ink . ';padding:20px 28px;"><a href="' . e($web) . '" style="' . $font . 'font-size:18px;font-weight:bold;color:#ffffff;text-decoration:none;">'
        . e(site('name')) . '</a><div style="' . $font . 'font-size:13px;color:#cfcac2;margin-top:2px;">' . e(t('site.tagline')) . '</div></td></tr>'
        . '<tr><td style="padding:28px 28px 12px;">' . $blocks . '</td></tr>'
        . '<tr><td style="padding:16px 28px 22px;border-top:1px solid #e6e2dc;' . $font . 'font-size:12px;line-height:1.6;color:' . $muted . ';">' . $foot . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}
