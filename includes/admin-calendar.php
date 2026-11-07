<?php
declare(strict_types=1);

/**
 * Kalendář poptávek (admin → Poptávky → Kalendář).
 *  - termín ze smlouvy o dílo (zahájení–dokončení) = plný pruh
 *  - jinak preferovaný termín zákazníka z formuláře (od–do) = čárkovaný
 * $rows = poptávky (už vyfiltrované podle stavu), $month = 'RRRR-MM'
 */
function inquiry_calendar(array $rows, string $month, string $statusFilter): string
{
    $month = preg_match('~^\d{4}-(0[1-9]|1[0-2])$~', $month) ? $month : date('Y-m');
    $first = new DateTimeImmutable($month . '-01');
    $last  = $first->modify('last day of this month');
    $gridStart = $first->modify('monday this week');
    $gridEnd   = $last->modify('sunday this week');

    // poslední smlouva ke každé poptávce
    $ids = array_map(fn($r) => (int) $r['id'], $rows);
    $contracts = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q("SELECT inquiry_id, number, data FROM contracts WHERE inquiry_id IN ($in) ORDER BY id", $ids)->fetchAll() as $c) {
            $contracts[(int) $c['inquiry_id']] = $c;
        }
    }
    $parse = function (string $v): ?DateTimeImmutable {
        foreach (['!Y-m-d', '!j. n. Y'] as $f) if ($v !== '' && ($d = DateTimeImmutable::createFromFormat($f, trim($v)))) return $d;
        return null;
    };

    $events = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if (isset($contracts[$id])) {
            $cd = (array) json_decode((string) $contracts[$id]['data'], true);
            $from = $parse((string) ($cd['start'] ?? '')); $to = $parse((string) ($cd['end'] ?? ''));
            $type = 'contract';
        } else {
            $data = (array) json_decode((string) $r['data'], true);
            $from = $parse((string) ($data['term_from']['value'] ?? '')); $to = $parse((string) ($data['term_to']['value'] ?? ''));
            $type = 'wish';
        }
        $from ??= $to;
        if (!$from) continue;
        $to = $to && $to >= $from ? $to : $from;
        if ($to < $gridStart || $from > $gridEnd) continue;
        $events[] = ['id' => $id, 'name' => (string) $r['name'], 'from' => $from, 'to' => $to, 'type' => $type,
                     'status' => (string) $r['status'], 'contract' => $contracts[$id]['number'] ?? ''];
    }
    usort($events, fn($a, $b) => [$a['from'], $a['id']] <=> [$b['from'], $b['id']]);

    $qs   = fn(array $p) => admin_url(http_build_query(array_filter(['tab' => 'inquiries', 'view' => 'calendar', 'status' => $statusFilter] + $p)));
    $link = fn(int $id) => admin_url(http_build_query(array_filter(['tab' => 'inquiries', 'status' => $statusFilter])) . '#q' . $id);
    $months   = array_map('trim', explode(',', t('adm.cal.months')));
    $weekdays = array_map('trim', explode(',', t('adm.cal.weekdays')));
    $title    = ($months[(int) $first->format('n') - 1] ?? $first->format('m')) . ' ' . $first->format('Y');
    $today    = date('Y-m-d');
    $chip = function (array $ev, bool $long = false) use ($link): string {
        $tip = '#' . $ev['id'] . ' ' . $ev['name'] . ' · ' . $ev['from']->format('j. n.') . ($ev['to'] != $ev['from'] ? '–' . $ev['to']->format('j. n.') : '')
             . ($ev['contract'] !== '' ? ' · ' . $ev['contract'] : ' · ' . t('adm.cal.legend_wish'));
        return '<a href="' . e($link($ev['id'])) . '" class="cal-ev cal-ev--' . $ev['type'] . ($ev['status'] === 'cancelled' ? ' cal-ev--cancelled' : '')
             . ($ev['status'] === 'done' ? ' cal-ev--done' : '') . '" title="' . e($tip) . '">'
             . '<b>#' . $ev['id'] . '</b> ' . e($ev['name']) . ($long && $ev['contract'] !== '' ? ' <span>' . e($ev['contract']) . '</span>' : '') . '</a>';
    };

    $h  = '<div class="cal">';
    $h .= '<div class="cal__bar"><h2>' . e($title) . '</h2><div class="cal__nav">'
        . '<a class="btn btn--small btn--ghost" href="' . e($qs(['m' => $first->modify('-1 month')->format('Y-m')])) . '" aria-label="' . te('adm.cal.prev') . '">‹</a>'
        . '<a class="btn btn--small btn--ghost" href="' . e($qs(['m' => date('Y-m')])) . '">' . te('adm.cal.today') . '</a>'
        . '<a class="btn btn--small btn--ghost" href="' . e($qs(['m' => $first->modify('+1 month')->format('Y-m')])) . '" aria-label="' . te('adm.cal.next') . '">›</a>'
        . '</div></div>';
    $h .= '<p class="cal__legend"><span class="cal-ev cal-ev--contract">' . te('adm.cal.legend_contract') . '</span> <span class="cal-ev cal-ev--wish">' . te('adm.cal.legend_wish') . '</span></p>';

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
            $h .= '<div class="' . $cls . '" role="cell"><span class="cal__num">' . $day->format('j') . '</span>';
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
    return $h . '</div>';
}
