<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Fakturace: IBAN z českého čísla účtu, QR Platba (SPD), číslování
// ---------------------------------------------------------------

/** Zbytek po dělení 97 pro dlouhé číslo zapsané jako řetězec (IBAN) */
function mod97(string $digits): int
{
    $r = 0;
    foreach (str_split($digits, 7) as $chunk) $r = (int) (($r . $chunk) % 97);
    return $r;
}

/** Kontrola váženého součtu českého čísla účtu (předčíslí i číslo musí být dělitelné 11) */
function cz_account_valid(string $prefix, string $number): bool
{
    $check = function (string $n, array $w): bool {
        $n = str_pad($n, count($w), '0', STR_PAD_LEFT);
        $sum = 0;
        foreach ($w as $i => $wt) $sum += (int) $n[$i] * $wt;
        return $sum % 11 === 0;
    };
    return $check($prefix, [10, 5, 8, 4, 2, 1]) && $check($number, [6, 3, 7, 9, 10, 5, 8, 4, 2, 1]);
}

/** „19-123457/0100“ nebo „123457/0100“ → CZ IBAN (bez mezer), jinak null */
function cz_iban(string $account): ?string
{
    if (!preg_match('~^\s*(?:(\d{1,6})-)?(\d{2,10})\s*/\s*(\d{4})\s*$~', $account, $m)) return null;
    [$prefix, $number, $bank] = [$m[1] ?? '', $m[2], $m[3]];
    if (!cz_account_valid($prefix, $number)) return null;
    $bban = $bank . str_pad($prefix, 6, '0', STR_PAD_LEFT) . str_pad($number, 10, '0', STR_PAD_LEFT);
    $check = 98 - mod97($bban . '123500');                       // C=12, Z=35, 00
    return 'CZ' . str_pad((string) $check, 2, '0', STR_PAD_LEFT) . $bban;
}

function iban_valid(string $iban): bool
{
    $iban = strtoupper(preg_replace('~\s+~', '', $iban));
    if (!preg_match('~^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$~', $iban)) return false;
    $num = '';
    foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $ch) $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
    return mod97($num) === 1;
}

function iban_format(string $iban): string
{
    return trim(chunk_split($iban, 4, ' '));
}

/** IBAN pro faktury a QR platbu: ručně zadaný (Nastavení → Faktury), jinak spočítaný z čísla účtu */
function invoice_iban(): ?string
{
    $manual = strtoupper(preg_replace('~\s+~', '', (string) (props()['inv.iban']['value_cs'] ?? '')));
    if ($manual !== '' && iban_valid($manual)) return $manual;
    return cz_iban(site('bank'));
}

/** Řetězec QR Platby (formát SPD 1.0, standard České bankovní asociace) */
function spd_string(string $iban, float $amount, string $vs, string $message, ?string $due = null): string
{
    $clean = function (string $s, int $max): string {
        $s = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);          // banky spolehlivě čtou jen ASCII
        $s = str_replace(["'", '"', '^', '`', '~'], '', $s);                 // zbytky diakritiky z iconv (Windows: á → 'a)
        return mb_substr(trim(str_replace(['*', "\r", "\n"], [' ', ' ', ' '], $s)), 0, $max);
    };
    $parts = ['SPD', '1.0', 'ACC:' . $iban, 'AM:' . number_format($amount, 2, '.', ''), 'CC:CZK'];
    if ($vs !== '') $parts[] = 'X-VS:' . substr(preg_replace('~\D~', '', $vs), 0, 10);
    if ($due && preg_match('~^\d{4}-\d{2}-\d{2}$~', $due)) $parts[] = 'DT:' . str_replace('-', '', $due);
    if ($message !== '') $parts[] = 'MSG:' . $clean($message, 60);
    return implode('*', $parts);
}

/** Další číslo faktury podle masky (Nastavení → Faktury), např. RRRR### → 2026001 */
function next_invoice_number(): string
{
    $mask = (string) (props()['inv.format']['value_cs'] ?? '') ?: 'RRRR###';
    $mask = str_replace(['RRRR', 'YYYY', 'RR', 'YY'], [date('Y'), date('Y'), date('y'), date('y')], $mask);
    if (!preg_match('~^(.*?)(#+)(.*)$~', $mask, $m)) $m = [0, $mask, '###', ''];
    [$pre, $hashes, $post] = [$m[1], $m[2], $m[3]];
    $max = 0;
    foreach (q("SELECT number FROM invoices WHERE number LIKE ?", [$pre . '%' . $post])->fetchAll() as $r) {
        $mid = substr($r['number'], strlen($pre), strlen($r['number']) - strlen($pre) - strlen($post));
        if (ctype_digit($mid)) $max = max($max, (int) $mid);
    }
    return $pre . str_pad((string) ($max + 1), strlen($hashes), '0', STR_PAD_LEFT) . $post;
}
