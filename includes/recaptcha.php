<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Google reCAPTCHA v3 (neviditelná ochrana formulářů proti spamu)
//  - klíče v config.php → 'recaptcha' => ['site_key', 'secret', 'min_score']
//  - bez klíčů je ochrana vypnutá a formuláře fungují jako dřív
//  - skript Googlu se načte až ve chvíli, kdy návštěvník začne formulář vyplňovat (main.js)
// ---------------------------------------------------------------

function recaptcha_on(): bool
{
    global $CONFIG;
    return !empty($CONFIG['recaptcha']['site_key']) && !empty($CONFIG['recaptcha']['secret']);
}

/** Atributy pro <form>: data-recaptcha="akce" data-sitekey="…" (prázdné, když je ochrana vypnutá) */
function recaptcha_attrs(string $action): string
{
    global $CONFIG;
    if (!recaptcha_on()) return '';
    return ' data-recaptcha="' . e($action) . '" data-sitekey="' . e((string) $CONFIG['recaptcha']['site_key']) . '"';
}

/** Povinný text místo plovoucího odznaku Googlu (podmínky reCAPTCHA) */
function recaptcha_note(): string
{
    if (!recaptcha_on()) return '';
    return '<p class="recaptcha-note">' . str_replace(
        ['{privacy}', '{terms}'],
        ['<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">' . te('form.recaptcha_privacy') . '</a>',
         '<a href="https://policies.google.com/terms" target="_blank" rel="noopener">' . te('form.recaptcha_terms') . '</a>'],
        te('form.recaptcha_note')) . '</p>';
}

/**
 * Ověří token z formuláře. true = člověk (nebo ochrana vypnutá / Google nedostupný – výpadek Googlu nesmí zablokovat poptávky).
 * false = chybějící/neplatný token, jiná akce nebo nízké skóre.
 */
function recaptcha_ok(string $action): bool
{
    global $CONFIG;
    if (!recaptcha_on()) return true;
    $token = (string) ($_POST['g-recaptcha-response'] ?? '');
    if ($token === '' || strlen($token) > 4000) return false;

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => (string) $CONFIG['recaptcha']['secret'], 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']),
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        error_log('reCAPTCHA: ' . curl_error($ch) . ' – formulář propuštěn bez ověření');
        curl_close($ch);
        return true;
    }
    curl_close($ch);
    $r = json_decode((string) $body, true);
    if (!is_array($r)) return true;
    if (empty($r['success'])) {
        // chyba konfigurace (špatný secret / doména) nesmí tiše blokovat zákazníky
        $codes = (array) ($r['error-codes'] ?? []);
        if (array_intersect($codes, ['invalid-input-secret', 'missing-input-secret'])) {
            error_log('reCAPTCHA: neplatný secret v config.php – formulář propuštěn');
            return true;
        }
        return false;
    }
    $min = (float) ($CONFIG['recaptcha']['min_score'] ?? 0.5);
    return ($r['action'] ?? '') === $action && (float) ($r['score'] ?? 0) >= $min;
}
