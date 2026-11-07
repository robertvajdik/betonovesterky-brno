<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Zákaznické účty: registrace, přihlášení, obnova hesla, e-maily
// ---------------------------------------------------------------

const PRIVATE_ROUTES = ['login', 'register', 'forgot', 'account', 'track'];

const INQUIRY_STATUSES = ['new', 'in_progress', 'quoted', 'done', 'cancelled'];

function accounts_on(): bool
{
    return (string) (props()['site.accounts']['value_cs'] ?? '1') === '1';
}

/** Přihlášený zákazník nebo null */
function customer(bool $reload = false): ?array
{
    static $c = false;
    if ($c === false || $reload) {
        $c  = null;
        $id = (int) ($_SESSION['customer_id'] ?? 0);
        if ($id && accounts_on()) {
            $c = q("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$id])->fetch() ?: null;
            if (!$c) unset($_SESSION['customer_id']);
        }
    }
    return $c;
}

function customer_login(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $id;
    unset($_SESSION['login_fail']);
    q("UPDATE customers SET last_login = NOW() WHERE id = ?", [$id]);
}

function customer_logout(): void
{
    unset($_SESSION['customer_id']);
    session_regenerate_id(true);
}

/** Omezení pokusů o přihlášení (5 chyb / 10 minut na relaci) */
function login_throttled(): bool
{
    $f = $_SESSION['login_fail'] ?? ['n' => 0, 't' => 0];
    if (time() - $f['t'] > 600) return false;
    return $f['n'] >= 5;
}

function login_failed(): void
{
    $f = $_SESSION['login_fail'] ?? ['n' => 0, 't' => time()];
    if (time() - $f['t'] > 600) $f = ['n' => 0, 't' => time()];
    $f['n']++;
    $_SESSION['login_fail'] = $f;
    usleep(400000);
}

/** Bezpečný návrat jen na adresu tohoto webu */
function safe_next(?string $next): string
{
    $next = (string) $next;
    $base = BASE_URL . '/';
    if ($next !== '' && str_starts_with($next, $base) && !str_starts_with($next, '//') && !preg_match('~[\r\n\\\\]~', $next)) {
        return $next;
    }
    return route_url('account');
}

/** Stránka jen pro přihlášené – jinak přesměruje na přihlášení */
function require_customer(): array
{
    $c = customer();
    if (!$c) redirect(route_url('login') . '?next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '')));
    return $c;
}

/** Soukromá stránka: necachovat, neindexovat */
function private_page(): void
{
    header('Cache-Control: private, no-store');
    $GLOBALS['noindex'] = true;
}

/** Náhodný token: [token do odkazu, hash do DB] */
function new_token(): array
{
    $t = bin2hex(random_bytes(32));
    return [$t, hash('sha256', $t)];
}

/**
 * Tajný klíč webu pro odkazy zákazníků: config.php → 'app_key', jinak se jednou vygeneruje do includes/.app_key
 * (složka includes je z webu nepřístupná). Bez klíče z DB samotné odkazy spočítat nejde.
 */
function app_key(): string
{
    global $CONFIG;
    if (!empty($CONFIG['app_key'])) return (string) $CONFIG['app_key'];
    $file = __DIR__ . '/.app_key';
    $key = is_file($file) ? trim((string) file_get_contents($file)) : '';
    if (strlen($key) < 32) {
        $key = bin2hex(random_bytes(32));
        if (@file_put_contents($file, $key, LOCK_EX) === false) error_log('Nelze zapsat includes/.app_key – doplňte app_key do config.php');
    }
    return $key;
}

/** Token odkazu: HMAC(id + náhodná sůl poptávky). Sůl je v inquiries.access_hash; nová sůl = staré odkazy přestanou platit. */
function track_token(int $inquiryId, bool $create = true): ?string
{
    $salt = (string) q("SELECT access_hash FROM inquiries WHERE id = ?", [$inquiryId])->fetchColumn();
    if ($salt === '') {
        if (!$create) return null;
        $salt = bin2hex(random_bytes(32));
        q("UPDATE inquiries SET access_hash = ? WHERE id = ?", [$salt, $inquiryId]);
    }
    return hash_hmac('sha256', $inquiryId . ':' . $salt, app_key());
}

function track_token_ok(int $inquiryId, string $token): bool
{
    $expected = preg_match('~^[a-f0-9]{64}$~', $token) ? track_token($inquiryId, false) : null;
    return $expected !== null && hash_equals($expected, $token);
}

/** Soukromý odkaz na poptávku (stále stejný, dokud se sůl nezmění). $extra např. '&invoice=5' */
function track_link(int $inquiryId, ?string $lang = null, string $extra = ''): string
{
    return abs_url(route_url('track', $lang)) . '?id=' . $inquiryId . '&t=' . track_token($inquiryId) . $extra;
}

/** Pošle zákazníkovi e-mail s odkazem na poptávku (v jazyce poptávky) */
function send_track_link(int $inquiryId): bool
{
    $inq = q("SELECT id, email, lang FROM inquiries WHERE id = ?", [$inquiryId])->fetch();
    if (!$inq || !filter_var($inq['email'], FILTER_VALIDATE_EMAIL)) return false;
    $lang = in_array($inq['lang'], LANGS, true) ? $inq['lang'] : 'cs';
    return send_customer_mail($inq['email'], $lang, 'track', ['id' => $inquiryId, 'url' => track_link($inquiryId, $lang)]);
}

function status_label(string $s): string
{
    return t('status.' . $s);
}

// ---------------------------------------------------------------
// E-maily
// ---------------------------------------------------------------

function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    global $CONFIG;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = (string) ($CONFIG['mail_from'] ?? '') ?: site('email');
    $name = '=?UTF-8?B?' . base64_encode(site('name')) . '?=';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    if (filter_var($from, FILTER_VALIDATE_EMAIL)) $headers .= "From: {$name} <{$from}>\r\n";
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers .= "Reply-To: {$replyTo}\r\n";
    if (smtp_on()) {                                       // SMTP z config.php (includes/mailer.php)
        // odesílatel musí patřit k SMTP účtu (server jinak e-mail odmítne): smtp.from → přihlašovací jméno účtu → e-mail firmy
        $sender = filter_var((string) ($CONFIG['smtp']['from'] ?? ''), FILTER_VALIDATE_EMAIL)
               ?: (filter_var((string) ($CONFIG['smtp']['user'] ?? ''), FILTER_VALIDATE_EMAIL) ?: $from);
        if (smtp_send($to, $subject, $body, $sender, site('name'), $replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null)) return true;
        // SMTP server nedostupný (hosting blokuje port apod.) → záložně přes PHP mail(), ať se e-mail neztratí.
        // Při jiné chybě (špatné heslo, odmítnutý odesílatel) se záloha nepoužije – chybu je potřeba opravit.
        if (!str_starts_with(smtp_error(), 'připojení k')) return false;
        $log = smtp_log();
        // i záloha odesílá ze schránky SMTP účtu (existující doména) – hlavička From i obálka (-f), jinak Gmail e-mail zahodí
        $fbHeaders = str_replace("From: {$name} <{$from}>\r\n", '', $headers) . "From: {$name} <{$sender}>\r\n";
        $params = preg_match('~^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$~', $sender) ? '-f' . $sender : '';
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', str_replace("\r", '', $body), $fbHeaders, $params);
        smtp_log(null, true);
        foreach ($log as $line) smtp_log(substr($line, 10));
        smtp_log('záloha: PHP mail() z ' . $sender . ' → ' . $to . ': ' . ($ok ? 'předáno serveru k odeslání' : 'CHYBA – server e-mail nepřijal'));
        return $ok;
    }
    smtp_log(null, true);
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', str_replace("\r", '', $body), $headers);
    smtp_log('PHP mail() → ' . $to . ': ' . ($ok ? 'předáno serveru k odeslání' : 'CHYBA – server e-mail nepřijal (nastavte SMTP v config.php)'));
    return $ok;
}

/** E-mail zákazníkovi v jeho jazyce; texty z vlastností mail.* */
function send_customer_mail(string $to, string $lang, string $key, array $vars): bool
{
    $prev = lang();
    lang($lang);
    $vars += ['site' => site('name')];
    // texty e-mailů: doslovné „\n“ (starší import do DB) převést na skutečné konce řádků
    $nl = fn(string $s) => str_replace(['\r\n', '\n'], "\n", $s);
    $ok = send_mail($to, trim(str_replace('\n', ' ', t("mail.{$key}_subject", $vars))), $nl(t("mail.{$key}_body", $vars)), $GLOBALS['CONFIG']['notify_email'] ?? null);
    lang($prev);
    return $ok;
}

function send_verify_mail(array $c): void
{
    [$token, $hash] = new_token();
    q("UPDATE customers SET verify_hash = ? WHERE id = ?", [$hash, $c['id']]);
    $url = abs_url(route_url('account', $c['lang'])) . '?verify=' . $token . '&u=' . $c['id'];
    send_customer_mail($c['email'], $c['lang'], 'verify', ['url' => $url, 'name' => $c['name']]);
}
