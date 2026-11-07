<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Odesílání e-mailů přes SMTP (bez knihoven). Nastavení v config.php → 'smtp'.
// Bez nastavení posílá send_mail() přes PHP mail() jako dřív.
// ---------------------------------------------------------------

function smtp_on(): bool
{
    global $CONFIG;
    return !empty($CONFIG['smtp']['host']);
}

/** Průběh posledního odeslání (pro test v administraci); heslo se nezapisuje */
function smtp_log(?string $line = null, bool $reset = false): array
{
    static $log = [];
    if ($reset) $log = [];
    if ($line !== null) $log[] = date('H:i:s') . '  ' . $line;
    return $log;
}

/** Poslední chyba SMTP (pro test v administraci) */
function smtp_error(?string $set = null): string
{
    static $err = '';
    if ($set !== null) $err = $set;
    return $err;
}

/**
 * Odešle textový e-mail přes SMTP. true = server zprávu převzal.
 * $cfg: host, port, secure ('tls' = STARTTLS, 'ssl' = SMTPS, '' = bez šifrování), user, pass
 */
/**
 * MIME tělo e-mailu: [hlavičky Content-Type…, obsah]. S HTML jako multipart/alternative (text + HTML), jinak jen text.
 */
function mail_mime_body(string $text, ?string $html = null): array
{
    $b64 = fn(string $s) => rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $s)), 76, "\r\n"));
    if ($html === null || $html === '') {
        return [['Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64'], $b64($text)];
    }
    $b = '=_bs_' . bin2hex(random_bytes(10));
    $content = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($text) . "\r\n"
             . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($html) . "\r\n"
             . "--$b--";
    return [['Content-Type: multipart/alternative; boundary="' . $b . '"'], $content];
}

function smtp_send(string $to, string $subject, string $body, string $from, string $fromName = '', ?string $replyTo = null, ?string $html = null): bool
{
    global $CONFIG;
    $c = (array) $CONFIG['smtp'];
    $host   = (string) $c['host'];
    $secure = strtolower((string) ($c['secure'] ?? 'tls'));
    $port   = (int) ($c['port'] ?? ($secure === 'ssl' ? 465 : 587));
    smtp_error('');
    smtp_log(null, true);
    smtp_log("připojuji se k $host:$port (" . ($secure ?: 'bez šifrování') . ')');

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return smtp_fail("připojení k $host:$port selhalo ($errstr)");
    stream_set_timeout($fp, 15);

    // odpověď serveru (i víceřádková „250-…“); vrací [kód, text]
    $read = function () use ($fp): array {
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return [(int) substr($text, 0, 3), trim($text)];
    };
    $cmd = function (string $line, array $ok, string $shown = '') use ($fp, $read): ?string {
        fwrite($fp, $line . "\r\n");
        smtp_log('> ' . ($shown !== '' ? $shown : (strlen($line) > 200 ? '(obsah zprávy, ' . strlen($line) . ' B)' : $line)));
        [$code, $text] = $read();
        smtp_log('< ' . str_replace("\n", ' | ', str_replace("\r", '', $text)));
        return in_array($code, $ok, true) ? null : (preg_replace('~^(AUTH \S+).*~', '$1 …', $line) . ' → ' . $text);
    };

    try {
        [$code, $text] = $read();
        smtp_log('< ' . $text);
        if ($code !== 220) throw new RuntimeException('server neodpověděl: ' . $text);
        $ehlo = 'EHLO ' . (preg_replace('~[^a-z0-9.-]~i', '', (string) ($_SERVER['SERVER_NAME'] ?? '')) ?: 'localhost');
        if ($e = $cmd($ehlo, [250])) throw new RuntimeException($e);
        if ($secure === 'tls') {
            if ($e = $cmd('STARTTLS', [220])) throw new RuntimeException($e);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('nepodařilo se zapnout šifrování TLS (certifikát serveru?)');
            }
            smtp_log('šifrování TLS zapnuto');
            if ($e = $cmd($ehlo, [250])) throw new RuntimeException($e);
        }
        if (!empty($c['user'])) {
            if ($e = $cmd('AUTH LOGIN', [334])) throw new RuntimeException($e);
            if ($e = $cmd(base64_encode((string) $c['user']), [334], '(uživatel ' . $c['user'] . ')')) throw new RuntimeException($e);
            if ($e = $cmd(base64_encode((string) ($c['pass'] ?? '')), [235], '(heslo skryto)')) throw new RuntimeException('přihlášení odmítnuto – zkontrolujte jméno a heslo');
        }
        if ($e = $cmd('MAIL FROM:<' . $from . '>', [250])) throw new RuntimeException($e);
        if ($e = $cmd('RCPT TO:<' . $to . '>', [250, 251])) throw new RuntimeException($e);
        if ($e = $cmd('DATA', [354])) throw new RuntimeException($e);

        $enc = fn(string $s) => preg_match('~[^\x20-\x7e]~', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . ($fromName !== '' ? $enc($fromName) . ' ' : '') . '<' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $enc($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
        ];
        if ($replyTo) $headers[] = 'Reply-To: <' . $replyTo . '>';
        // tělo: text, nebo text + HTML (multipart/alternative); base64 = žádné problémy s tečkou na začátku řádku ani s délkou řádků
        [$ctype, $content] = mail_mime_body($body, $html);
        $msg = implode("\r\n", array_merge($headers, $ctype)) . "\r\n\r\n" . $content;
        if ($e = $cmd($msg . "\r\n.", [250])) throw new RuntimeException($e);
        $cmd('QUIT', [221]);
        fclose($fp);
        smtp_log('hotovo – server zprávu převzal');
        return true;
    } catch (RuntimeException $ex) {
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return smtp_fail($ex->getMessage());
    }
}

function smtp_fail(string $msg): bool
{
    smtp_error($msg);
    smtp_log('CHYBA: ' . $msg);
    error_log('SMTP: ' . $msg);
    return false;
}
