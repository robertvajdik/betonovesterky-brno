<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Zákaznické účty: registrace, přihlášení, obnova hesla, e-maily
// ---------------------------------------------------------------

const PRIVATE_ROUTES = ['login', 'register', 'forgot', 'account'];

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
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', str_replace("\r", '', $body), $headers);
}

/** E-mail zákazníkovi v jeho jazyce; texty z vlastností mail.* */
function send_customer_mail(string $to, string $lang, string $key, array $vars): bool
{
    $prev = lang();
    lang($lang);
    $vars += ['site' => site('name')];
    $ok = send_mail($to, t("mail.{$key}_subject", $vars), t("mail.{$key}_body", $vars), $GLOBALS['CONFIG']['notify_email'] ?? null);
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
