<?php
declare(strict_types=1);

/*
 * Kalendář poptávek (admin → Poptávky → Kalendář)
 *  - termín ze smlouvy o dílo (zahájení–dokončení) = plný pruh
 *  - preferovaný termín zákazníka z formuláře (od–do) = čárkovaný
 *  - vlastní události (zaměření, schůzka…) z tabulky calendar_events = oranžové
 *  - propojení s Google Kalendářem: soukromý iCal odkaz (admin/calendar.php?k=…) + „Přidat do Google Kalendáře“
 */

/** Tajný klíč iCal odkazu (z app_key; nový app_key = nový odkaz) */
function calendar_feed_token(): string
{
    return substr(hash_hmac('sha256', 'calendar-feed', app_key()), 0, 40);
}

function calendar_feed_url(): string
{
    return abs_url(url('admin/calendar.php')) . '?k=' . calendar_feed_token();
}

/** Odkaz „Přidat do Google Kalendáře“ (bez přihlášení k API – otevře předvyplněnou událost) */
function google_calendar_link(string $title, string $from, ?string $to, ?string $tFrom, ?string $tTo, string $place = '', string $note = ''): string
{
    if ($tFrom) {
        $start = date('Ymd\THis', strtotime("$from $tFrom"));
        $end   = date('Ymd\THis', strtotime(($to ?: $from) . ' ' . ($tTo ?: date('H:i', strtotime("$tFrom +1 hour")))));
    } else {
        $start = date('Ymd', strtotime($from));
        $end   = date('Ymd', strtotime(($to ?: $from) . ' +1 day'));       // celodenní: konec je výlučný
    }
    return 'https://calendar.google.com/calendar/render?' . http_build_query(array_filter([
        'action' => 'TEMPLATE', 'text' => $title, 'dates' => "$start/$end", 'ctz' => 'Europe/Prague', 'location' => $place, 'details' => $note,
    ], 'strlen'));
}

/** Všechny termíny pro kalendář i iCal: smlouvy, přání zákazníků (volitelně) a vlastní události */
function calendar_items(array $rows, bool $withWishes = true): array
{
    $parse = function (string $v): ?DateTimeImmutable {
        foreach (['!Y-m-d', '!j. n. Y'] as $f) if ($v !== '' && ($d = DateTimeImmutable::createFromFormat($f, trim($v)))) return $d;
        return null;
    };
    $ids = array_map(fn($r) => (int) $r['id'], $rows);
    $contracts = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q("SELECT id, inquiry_id, number, data FROM contracts WHERE inquiry_id IN ($in) ORDER BY id", $ids)->fetchAll() as $c) {
            $contracts[(int) $c['inquiry_id']] = $c;
        }
    }
    $items = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if (isset($contracts[$id])) {
            $cd = (array) json_decode((string) $contracts[$id]['data'], true);
            $from = $parse((string) ($cd['start'] ?? '')); $to = $parse((string) ($cd['end'] ?? ''));
            $type = 'contract';
            $place = (string) ($cd['place'] ?? '') ?: (string) ($cd['client_address'] ?? '');
        } else {
            if (!$withWishes) continue;
            $data = (array) json_decode((string) $r['data'], true);
            $from = $parse((string) ($data['term_from']['value'] ?? '')); $to = $parse((string) ($data['term_to']['value'] ?? ''));
            $type = 'wish';
            $place = (string) ($r['location'] ?? '');
        }
        $from ??= $to;
        if (!$from) continue;
        $to = $to && $to >= $from ? $to : $from;
        $items[] = ['kind' => $type, 'id' => $id, 'name' => (string) $r['name'], 'title' => '#' . $id . ' ' . $r['name'],
                    'from' => $from, 'to' => $to, 'time_from' => null, 'time_to' => null, 'place' => $place,
                    'status' => (string) $r['status'], 'contract' => $contracts[$id]['number'] ?? '', 'contract_id' => (int) ($contracts[$id]['id'] ?? 0)];
    }
    foreach (q("SELECT * FROM calendar_events ORDER BY starts, time_from, id")->fetchAll() as $ev) {
        $from = $parse((string) $ev['starts']);
        if (!$from) continue;
        $to = $parse((string) ($ev['ends'] ?? '')) ?? $from;
        $items[] = ['kind' => 'event', 'id' => (int) $ev['id'], 'inquiry_id' => (int) ($ev['inquiry_id'] ?? 0), 'name' => (string) $ev['title'],
                    'title' => (string) $ev['title'], 'from' => $from, 'to' => $to < $from ? $from : $to,
                    'time_from' => $ev['time_from'] ? substr((string) $ev['time_from'], 0, 5) : null,
                    'time_to' => $ev['time_to'] ? substr((string) $ev['time_to'], 0, 5) : null,
                    'place' => (string) ($ev['place'] ?? ''), 'note' => (string) ($ev['note'] ?? ''), 'status' => '', 'contract' => ''];
    }
    usort($items, fn($a, $b) => [$a['from'], $a['time_from'] ?? '', $a['id']] <=> [$b['from'], $b['time_from'] ?? '', $b['id']]);
    return $items;
}

/** Formulář vlastní události (nová / úprava) */
function calendar_event_form(?array $ev, string $date, string $back): string
{
    $v = fn(string $k, string $d = '') => e((string) ($ev[$k] ?? $d));
    $inqs = q("SELECT id, name FROM inquiries ORDER BY id DESC LIMIT 100")->fetchAll();
    $h  = '<div class="panel cal-form" id="udalost"><h2>' . te($ev ? 'adm.cal.edit_event' : 'adm.cal.new_event') . '</h2>';
    $h .= '<form method="post" class="form">' . csrf_field()
        . '<input type="hidden" name="action" value="event_save"><input type="hidden" name="id" value="' . (int) ($ev['id'] ?? 0) . '">'
        . '<input type="hidden" name="back" value="' . e($back) . '">';
    $h .= '<div class="form__grid">'
        . '<label class="field field--wide">' . te('adm.cal.f_title') . ' *<input type="text" name="title" required maxlength="200" value="' . $v('title') . '" placeholder="' . te('adm.cal.f_title_ph') . '"></label>'
        . '<label class="field">' . te('adm.cal.f_from') . ' *<input type="date" name="starts" required value="' . $v('starts', $date) . '"></label>'
        . '<label class="field">' . te('adm.cal.f_to') . '<input type="date" name="ends" value="' . $v('ends') . '"></label>'
        . '<label class="field">' . te('adm.cal.f_time_from') . '<input type="time" name="time_from" value="' . e(substr((string) ($ev['time_from'] ?? ''), 0, 5)) . '"></label>'
        . '<label class="field">' . te('adm.cal.f_time_to') . '<input type="time" name="time_to" value="' . e(substr((string) ($ev['time_to'] ?? ''), 0, 5)) . '"></label>'
        . '<label class="field">' . te('adm.cal.f_place') . '<input type="text" name="place" maxlength="200" value="' . $v('place') . '"></label>'
        . '<label class="field">' . te('adm.cal.f_inquiry') . '<select name="inquiry_id"><option value="">—</option>';
    foreach ($inqs as $i) {
        $h .= '<option value="' . (int) $i['id'] . '"' . ((int) ($ev['inquiry_id'] ?? ($_GET['inquiry'] ?? 0)) === (int) $i['id'] ? ' selected' : '') . '>#' . (int) $i['id'] . ' ' . e($i['name']) . '</option>';
    }
    $h .= '</select></label>'
        . '<label class="field field--wide">' . te('adm.cal.f_note') . '<textarea name="note" rows="2">' . $v('note') . '</textarea></label></div>';
    $h .= '<div class="item__actions"><button class="btn btn--small">' . te('adm.save') . '</button>'
        . '<a class="btn btn--small btn--ghost" href="' . e($back) . '">' . te('adm.cal.cancel') . '</a>';
    if ($ev) {
        $h .= '<a class="btn btn--small btn--ghost" target="_blank" rel="noopener" href="' . e(google_calendar_link((string) $ev['title'], (string) $ev['starts'], $ev['ends'] ?: null,
                $ev['time_from'] ? substr((string) $ev['time_from'], 0, 5) : null, $ev['time_to'] ? substr((string) $ev['time_to'], 0, 5) : null, (string) $ev['place'], (string) $ev['note'])) . '">'
            . te('adm.cal.to_google') . '</a>';
    }
    $h .= '</div></form>';
    if ($ev) $h .= btn_post('event_delete', (int) $ev['id'], t('adm.delete'), 'btn btn--small btn--danger', t('adm.confirm_delete'), ['back' => $back]);
    return $h . '</div>';
}

/** $rows = poptávky (už vyfiltrované podle stavu), $month = 'RRRR-MM' */
function inquiry_calendar(array $rows, string $month, string $statusFilter): string
{
    $month = preg_match('~^\d{4}-(0[1-9]|1[0-2])$~', $month) ? $month : date('Y-m');
    $first = new DateTimeImmutable($month . '-01');
    $last  = $first->modify('last day of this month');
    $gridStart = $first->modify('monday this week');
    $gridEnd   = $last->modify('sunday this week');
    $events = array_values(array_filter(calendar_items($rows), fn($ev) => $ev['to'] >= $gridStart && $ev['from'] <= $gridEnd));

    $qs   = fn(array $p) => admin_url(http_build_query(array_filter(['tab' => 'inquiries', 'view' => 'calendar', 'status' => $statusFilter, 'm' => $month] + $p)));
    $link = fn(array $ev) => $ev['kind'] === 'event'
        ? $qs(['edit_event' => $ev['id']]) . '#udalost'
        : admin_url(http_build_query(array_filter(['tab' => 'inquiries', 'status' => $statusFilter])) . '#q' . $ev['id']);
    $months   = array_map('trim', explode(',', t('adm.cal.months')));
    $weekdays = array_map('trim', explode(',', t('adm.cal.weekdays')));
    $title    = ($months[(int) $first->format('n') - 1] ?? $first->format('m')) . ' ' . $first->format('Y');
    $today    = date('Y-m-d');
    $chip = function (array $ev, bool $long = false) use ($link): string {
        $label = $ev['kind'] === 'event'
            ? ($ev['time_from'] ? '<b>' . e($ev['time_from']) . '</b> ' : '') . e($ev['title'])
            : '<b>#' . $ev['id'] . '</b> ' . e($ev['name']);
        $tip = strip_tags($label) . ' · ' . $ev['from']->format('j. n.') . ($ev['to'] != $ev['from'] ? '–' . $ev['to']->format('j. n.') : '')
             . ($ev['place'] !== '' ? ' · ' . $ev['place'] : '')
             . ($ev['kind'] === 'contract' ? ' · ' . $ev['contract'] : ($ev['kind'] === 'wish' ? ' · ' . t('adm.cal.legend_wish') : ''));
        return '<a href="' . e($link($ev)) . '" class="cal-ev cal-ev--' . $ev['kind'] . ($ev['status'] === 'cancelled' ? ' cal-ev--cancelled' : '')
             . ($ev['status'] === 'done' ? ' cal-ev--done' : '') . '" title="' . e($tip) . '">' . $label
             . ($long && $ev['contract'] !== '' ? ' <span>' . e($ev['contract']) . '</span>' : '')
             . ($long && $ev['place'] !== '' ? ' <span>· ' . e($ev['place']) . '</span>' : '') . '</a>';
    };

    $h = '<div class="cal">';
    // formulář vlastní události (?add=datum nebo ?edit_event=ID)
    $back = $qs([]);
    if (!empty($_GET['edit_event'])) {
        $ev = q("SELECT * FROM calendar_events WHERE id = ?", [(int) $_GET['edit_event']])->fetch();
        if ($ev) $h .= calendar_event_form($ev, '', $back);
    } elseif (isset($_GET['add'])) {
        $d = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $_GET['add']) ? (string) $_GET['add'] : date('Y-m-d');
        $h .= calendar_event_form(null, $d, $back);
    }

    $h .= '<div class="cal__bar"><h2>' . e($title) . '</h2><div class="cal__nav">'
        . '<a class="btn btn--small" href="' . e($qs(['add' => date('Y-m') === $month ? $today : $first->format('Y-m-d')]) . '#udalost') . '">' . te('adm.cal.add') . '</a>'
        . '<a class="btn btn--small btn--ghost" href="' . e($qs(['m' => $first->modify('-1 month')->format('Y-m')])) . '" aria-label="' . te('adm.cal.prev') . '">‹</a>'
        . '<a class="btn btn--small btn--ghost" href="' . e($qs(['m' => date('Y-m')])) . '">' . te('adm.cal.today') . '</a>'
        . '<a class="btn btn--small btn--ghost" href="' . e($qs(['m' => $first->modify('+1 month')->format('Y-m')])) . '" aria-label="' . te('adm.cal.next') . '">›</a>'
        . '</div></div>';
    $h .= '<p class="cal__legend"><span class="cal-ev cal-ev--contract">' . te('adm.cal.legend_contract') . '</span> <span class="cal-ev cal-ev--wish">'
        . te('adm.cal.legend_wish') . '</span> <span class="cal-ev cal-ev--event">' . te('adm.cal.legend_event') . '</span></p>';

    // mřížka (desktop)
    $h .= '<div class="cal__grid" role="table" aria-label="' . e($title) . '"><div class="cal__row cal__row--head" role="row">';
    foreach ($weekdays as $wd) $h .= '<div role="columnheader">' . e($wd) . '</div>';
    $h .= '</div>';
    for ($wk = $gridStart; $wk <= $gridEnd; $wk = $wk->modify('+7 days')) {
        $h .= '<div class="cal__row" role="row">';
        for ($i = 0; $i < 7; $i++) {
            $day = $wk->modify("+$i days");
            $dayEv = array_values(array_filter($events, fn($ev) => $ev['from'] <= $day && $ev['to'] >= $day));
            $cls = 'cal__day' . ($day->format('Y-m') !== $month ? ' is-out' : '') . ($day->format('Y-m-d') === $today ? ' is-today' : '') . ((int) $day->format('N') >= 6 ? ' is-weekend' : '');
            $h .= '<div class="' . $cls . '" role="cell"><div class="cal__head"><a class="cal__add" href="' . e($qs(['add' => $day->format('Y-m-d')]) . '#udalost') . '" title="'
                . te('adm.cal.add_on', ['date' => $day->format('j. n.')]) . '" aria-label="' . te('adm.cal.add_on', ['date' => $day->format('j. n.')]) . '">+</a>'
                . '<span class="cal__num">' . $day->format('j') . '</span></div>';
            foreach (array_slice($dayEv, 0, 3) as $ev) $h .= $chip($ev);
            if (count($dayEv) > 3) $h .= '<span class="cal__more">+' . (count($dayEv) - 3) . '</span>';
            $h .= '</div>';
        }
        $h .= '</div>';
    }
    $h .= '</div>';

    // přehled po dnech (mobil)
    $h .= '<ol class="cal__agenda">';
    $any = false;
    for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
        $dayEv = array_filter($events, fn($ev) => $ev['from'] <= $day && $ev['to'] >= $day);
        if (!$dayEv) continue;
        $any = true;
        $h .= '<li' . ($day->format('Y-m-d') === $today ? ' class="is-today"' : '') . '><span class="cal__date">' . e(($weekdays[(int) $day->format('N') - 1] ?? '') . ' ' . $day->format('j. n.')) . '</span><div>';
        foreach ($dayEv as $ev) $h .= $chip($ev, true);
        $h .= '</div></li>';
    }
    $h .= '</ol>';
    if (!$events) $h .= '<p class="muted">' . te('adm.cal.empty') . '</p>';
    elseif (!$any) $h .= '<p class="muted cal__agenda-empty">' . te('adm.cal.empty') . '</p>';

    // propojení s Google Kalendářem
    $feed = calendar_feed_url();
    $h .= '<details class="panel cal-sync"><summary><strong>' . te('adm.cal.sync_title') . '</strong></summary>'
        . '<p>' . te('adm.cal.sync_intro') . '</p>'
        . '<div class="cal-sync__url"><input type="text" readonly value="' . e($feed) . '" onclick="this.select()" aria-label="iCal URL">'
        . '<button type="button" class="btn btn--small" onclick="var i=this.previousElementSibling;i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.textContent=' . e(json_encode(t('adm.cal.copied'))) . '">' . te('adm.cal.copy') . '</button></div>'
        . '<ol class="cal-sync__steps"><li>' . t('adm.cal.sync_step1') . '</li><li>' . te('adm.cal.sync_step2') . '</li><li>' . te('adm.cal.sync_step3') . '</li></ol>'
        . '<p class="hint">' . te('adm.cal.sync_note') . '</p></details>';
    return $h . '</div>';
}
