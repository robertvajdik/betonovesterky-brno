<?php
declare(strict_types=1);
/*
 * Soukromý iCal kalendář pro Google Kalendář / Outlook / Apple: admin/calendar.php?k=…
 * Obsahuje termíny ze smluv o dílo a vlastní události z kalendáře v administraci.
 * Klíč odkazu je odvozený z app_key (viz calendar_feed_token()); bez platného klíče 404.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-nav.php';
require_once __DIR__ . '/../includes/admin-calendar.php';

if (!hash_equals(calendar_feed_token(), (string) ($_GET['k'] ?? ''))) {
    usleep(300000);
    http_response_code(404);
    exit;
}

$rows  = q("SELECT * FROM inquiries WHERE status <> 'cancelled' ORDER BY id")->fetchAll();
$items = calendar_items($rows, false);                       // jen pevné termíny (smlouvy) a vlastní události
$host  = parse_url(abs_url('/'), PHP_URL_HOST) ?: 'localhost';

$esc  = fn(string $s) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $s);
$fold = function (string $line): string {                    // řádky max. 75 bajtů (RFC 5545), nedělit UTF-8 znaky
    $out = '';
    while (strlen($line) > 75) {
        $cut = 75;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--;
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
    }
    return $out . $line . "\r\n";
};

$ics  = $fold('BEGIN:VCALENDAR') . $fold('VERSION:2.0') . $fold('PRODID:-//' . $esc(site('name')) . '//Kalendar zakazek//CS')
      . $fold('CALSCALE:GREGORIAN') . $fold('METHOD:PUBLISH') . $fold('X-WR-CALNAME:' . $esc(site('name') . ' – zakázky'))
      . $fold('X-WR-TIMEZONE:Europe/Prague') . $fold('REFRESH-INTERVAL;VALUE=DURATION:PT1H') . $fold('X-PUBLISHED-TTL:PT1H');
$stamp = gmdate('Ymd\THis\Z');
foreach ($items as $it) {
    $ics .= $fold('BEGIN:VEVENT');
    $ics .= $fold('UID:' . $it['kind'] . '-' . $it['id'] . '@' . $host);
    $ics .= $fold('DTSTAMP:' . $stamp);
    if ($it['time_from']) {
        $ics .= $fold('DTSTART:' . $it['from']->format('Ymd') . 'T' . str_replace(':', '', $it['time_from']) . '00');
        $endT = $it['time_to'] ?: date('H:i', strtotime($it['time_from'] . ' +1 hour'));
        $ics .= $fold('DTEND:' . $it['to']->format('Ymd') . 'T' . str_replace(':', '', $endT) . '00');
    } else {
        $ics .= $fold('DTSTART;VALUE=DATE:' . $it['from']->format('Ymd'));
        $ics .= $fold('DTEND;VALUE=DATE:' . $it['to']->modify('+1 day')->format('Ymd'));   // celodenní: konec výlučný
    }
    $summary = $it['kind'] === 'contract' ? $it['title'] . ' (' . $it['contract'] . ')' : $it['title'];
    $ics .= $fold('SUMMARY:' . $esc($summary));
    if ($it['place'] !== '') $ics .= $fold('LOCATION:' . $esc($it['place']));
    $desc = $it['kind'] === 'event' ? ($it['note'] ?? '') : '';
    $inq  = $it['kind'] === 'event' ? (int) ($it['inquiry_id'] ?? 0) : (int) $it['id'];
    if ($inq) $desc = trim($desc . "\n" . abs_url(url('admin/')) . '?tab=inquiries#q' . $inq);
    if ($desc !== '') $ics .= $fold('DESCRIPTION:' . $esc($desc));
    $ics .= $fold('END:VEVENT');
}
$ics .= $fold('END:VCALENDAR');

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: inline; filename="kalendar.ics"');
header('Cache-Control: private, max-age=300');
header('X-Robots-Tag: noindex');
echo $ics;
