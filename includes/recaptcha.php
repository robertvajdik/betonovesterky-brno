<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Google reCAPTCHA v3 (neviditelná ochrana formulářů proti spamu)
//  - nastavení: Administrace → Nastavení → reCAPTCHA (vlastnosti rc.*)
//  - záloha v config.php → 'recaptcha' => ['site_key', 'secret', 'min_score'] (použije se, když je pole v administraci prázdné)
//  - config.php 'contact' / 'admin' => false formulář natvrdo vypne (nouzový přístup, když reCAPTCHA zablokuje přihlášení)
//  - bez klíčů je ochrana vypnutá a formuláře fungují jako dřív
// ---------------------------------------------------------------

/** Akce formuláře → vypínač (akce bez vypínače jsou chráněné vždy, když jsou klíče) */
const RECAPTCHA_SWITCH = ['inquiry' => 'contact', 'admin' => 'admin'];

/** Sloučené nastavení: administrace (DB) má přednost, config.php je záloha */
function recaptcha_cfg(): array
{
    global $CONFIG;
    $f = (array) ($CONFIG['recaptcha'] ?? []);
    $p = props();
    $db = fn(string $k): string => trim((string) ($p['rc.' . $k]['value_cs'] ?? ''));
    $score = $db('min_score') !== '' ? (float) str_replace(',', '.', $db('min_score')) : (float) ($f['min_score'] ?? 0.5);
    return [
        'site_key'  => $db('site_key') ?: trim((string) ($f['site_key'] ?? '')),
        'secret'    => $db('secret') ?: trim((string) ($f['secret'] ?? '')),
        'min_score' => max(0.0, min(1.0, $score)),
        'badge'     => isset($p['rc.badge']) && prop_bool('rc.badge'),
    ];
}

/** Klíče vyplněné; s $action navíc ověří vypínač daného formuláře */
function recaptcha_on(string $action = ''): bool
{
    global $CONFIG;
    $rc = recaptcha_cfg();
    if ($rc['site_key'] === '' || $rc['secret'] === '') return false;
    $sw = RECAPTCHA_SWITCH[$action] ?? null;
    if ($sw === null) return true;
    if (($CONFIG['recaptcha'][$sw] ?? true) === false) return false;        // nouzové vypnutí v config.php
    return !isset(props()['rc.' . $sw]) || prop_bool('rc.' . $sw);
}

/** Atributy pro <form>: data-recaptcha="akce" data-sitekey="…" (prázdné, když je ochrana vypnutá) */
function recaptcha_attrs(string $action): string
{
    if (!recaptcha_on($action)) return '';
    $rc = recaptcha_cfg();
    return ' data-recaptcha="' . e($action) . '" data-sitekey="' . e($rc['site_key']) . '"' . ($rc['badge'] ? ' data-rc-badge="1"' : '');
}

/** Povinný text místo plovoucího odznaku Googlu (podmínky reCAPTCHA); se zapnutým odznakem nic */
function recaptcha_note(string $action = ''): string
{
    if (!recaptcha_on($action) || recaptcha_cfg()['badge']) return '';
    return '<style>.grecaptcha-badge{visibility:hidden!important}</style><p class="recaptcha-note">' . str_replace(
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
    if (!recaptcha_on($action)) return true;
    $token = (string) ($_POST['g-recaptcha-response'] ?? '');
    if ($token === '' || strlen($token) > 4000) return false;

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => recaptcha_cfg()['secret'], 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']),
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
    $min = recaptcha_cfg()['min_score'];
    return ($r['action'] ?? '') === $action && (float) ($r['score'] ?? 0) >= $min;
}
