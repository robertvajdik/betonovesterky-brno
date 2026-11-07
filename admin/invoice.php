<?php
declare(strict_types=1);
/*
 * Faktury – vystavení z poptávky nebo smlouvy, uložení, tisk / PDF z prohlížeče, QR platba.
 *   ?inquiry=ID | ?contract=ID [&kind=deposit]   nová faktura (předvyplněná)
 *   ?id=ID                                        hotová faktura k tisku
 *   ?id=ID&edit=1                                 úprava
 *   ?list=1                                       přehled faktur
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-nav.php';

// PUBLIC_DOC = zobrazení pro zákazníka přes soukromý odkaz (pages/track.php ověřil token): jen čtení, bez tlačítek administrace
if (!defined('PUBLIC_DOC') && empty($_SESSION['admin_id'])) {
    header('Location: ' . url('admin/'), true, 303);
    exit;
}

$self   = url('admin/invoice.php');
$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$inqId  = (int) ($_GET['inquiry'] ?? $_POST['inquiry_id'] ?? 0);
$conId  = (int) ($_GET['contract'] ?? $_POST['contract_id'] ?? 0);
$edit   = isset($_GET['edit']);
$errors = [];
$vatPayer = prop_bool('inv.vat_payer');

const INVOICE_FIELDS = ['kind', 'client_name', 'client_address', 'client_ico', 'client_dic', 'client_email',
                        'issued', 'taxable', 'due', 'paid_deposit', 'note'];

function inv_money(float $v): string { return number_format($v, 2, ',', "\u{00A0}") . "\u{00A0}Kč"; }
function inv_num(string $v): float { return (float) str_replace([',', ' ', "\u{00A0}"], ['.', '', ''], $v); }
function inv_date(string $iso): string { return $iso !== '' && ($t = strtotime($iso)) ? date('j. n. Y', $t) : ''; }
function inv_iso(string $v): string { $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim($v)); return $d ? $d->format('Y-m-d') : ''; }

/** Součty: řádky → základ / DPH podle sazeb, celkem, k úhradě */
function inv_totals(array $items, float $paidDeposit, bool $vatPayer): array
{
    $byRate = [];
    $total  = 0.0;
    foreach ($items as $it) {
        $base = round((float) $it['qty'] * (float) $it['price'], 2);
        $rate = $vatPayer ? (int) $it['vat'] : 0;
        $byRate[$rate] ??= ['base' => 0.0, 'vat' => 0.0];
        $byRate[$rate]['base'] += $base;
    }
    foreach ($byRate as $rate => &$r) {
        $r['vat'] = round($r['base'] * $rate / 100, 2);
        $total += $r['base'] + $r['vat'];
    }
    unset($r);
    ksort($byRate);
    $total = round($total, 2);
    return ['rates' => $byRate, 'total' => $total, 'due' => max(0, round($total - $paidDeposit, 2))];
}

// ---------- Akce ----------
if (!defined('PUBLIC_DOC') && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && ($_POST['action'] ?? '') === 'send' && $id) {
    // faktura e-mailem: platební údaje + odkaz na fakturu (přes soukromý odkaz poptávky, bez přihlášení)
    $iv  = q("SELECT i.*, COALESCE(i.inquiry_id, c.inquiry_id) AS inq FROM invoices i LEFT JOIN contracts c ON c.id = i.contract_id WHERE i.id = ?", [$id])->fetch();
    $ivd = $iv ? (array) json_decode($iv['data'], true) : [];
    $inqRow = $iv && $iv['inq'] ? q("SELECT email, lang FROM inquiries WHERE id = ?", [(int) $iv['inq']])->fetch() : null;
    $to = filter_var((string) ($ivd['client_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: (string) ($inqRow['email'] ?? '');
    if (!$iv || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('invoice', t('adm.invoice.no_email'));
    } else {
        $lang = in_array($inqRow['lang'] ?? 'cs', LANGS, true) ? $inqRow['lang'] : 'cs';
        $ok = send_customer_mail($to, $lang, ($ivd['kind'] ?? '') === 'deposit' ? 'invoice_deposit' : 'invoice', [
            'number'  => $iv['number'],
            'amount'  => number_format((float) $iv['total'], 2, ',', ' ') . ' Kč',
            'due'     => date('j. n. Y', strtotime((string) $iv['due'])),
            'vs'      => preg_replace('~\D~', '', $iv['number']),
            'account' => site('bank'),
            'url'     => $iv['inq'] ? track_link((int) $iv['inq'], $lang, '&invoice=' . $id) : '',
        ]);
        if ($ok) {
            $ivd['sent_at'] = date('Y-m-d H:i');
            $ivd['sent_to'] = $to;
            q("UPDATE invoices SET data = ?, updated_at = NOW() WHERE id = ?", [json_encode($ivd, JSON_UNESCAPED_UNICODE), $id]);
        }
        flash('invoice', $ok ? t('adm.invoice.sent', ['email' => $to]) : t('adm.invoice.send_fail', ['error' => smtp_error() ?: 'mail()']));
    }
    header('Location: ' . $self . '?id=' . $id, true, 303);
    exit;
}
if (!defined('PUBLIC_DOC') && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && ($_POST['action'] ?? '') === 'paid' && $id) {
    q("UPDATE invoices SET paid_at = IF(paid_at IS NULL, CURDATE(), NULL), updated_at = NOW() WHERE id = ?", [$id]);
    header('Location: ' . $self . '?id=' . $id, true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_ok()) {
        $errors[] = t('adm.invoice.err_csrf');
    } else {
        $d = [];
        foreach (INVOICE_FIELDS as $f) $d[$f] = post($f, $f === 'note' || $f === 'client_address' ? 2000 : 300);
        $d['kind'] = $d['kind'] === 'deposit' ? 'deposit' : 'invoice';
        foreach (['issued', 'taxable', 'due'] as $f) $d[$f] = inv_iso($d[$f]);
        $items = [];
        foreach ((array) ($_POST['items'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $desc = mb_substr(trim((string) ($it['desc'] ?? '')), 0, 500);
            $price = inv_num((string) ($it['price'] ?? ''));
            if ($desc === '' && $price == 0) continue;
            $vat = (string) ($it['vat'] ?? '21');
            $items[] = ['desc' => $desc, 'qty' => max(0, inv_num((string) ($it['qty'] ?? '1')) ?: 1), 'unit' => mb_substr(trim((string) ($it['unit'] ?? '')), 0, 20),
                        'price' => $price, 'vat' => in_array($vat, ['0', '12', '21'], true) ? (int) $vat : 21];
        }
        $d['items'] = $items;
        if ($d['client_name'] === '')    $errors[] = t('adm.invoice.err_name');
        if ($d['client_address'] === '') $errors[] = t('adm.invoice.err_address');
        if (!$items)                     $errors[] = t('adm.invoice.err_items');
        if ($d['issued'] === '' || $d['due'] === '') $errors[] = t('adm.invoice.err_dates');
        if (!invoice_iban())             $errors[] = t('adm.invoice.err_bank');

        if (!$errors) {
            $tot  = inv_totals($items, inv_num($d['paid_deposit']), $vatPayer && $d['kind'] !== 'deposit');
            $json = json_encode($d, JSON_UNESCAPED_UNICODE);
            if ($id) {
                q("UPDATE invoices SET data = ?, total = ?, issued = ?, due = ?, updated_at = NOW() WHERE id = ?", [$json, $tot['due'], $d['issued'], $d['due'], $id]);
            } else {
                for ($try = 0; ; $try++) {
                    try {
                        q("INSERT INTO invoices (number, inquiry_id, contract_id, data, total, issued, due) VALUES (?,?,?,?,?,?,?)",
                          [next_invoice_number(), $inqId ?: null, $conId ?: null, $json, $tot['due'], $d['issued'], $d['due']]);
                        break;
                    } catch (PDOException $ex) {
                        if ($try >= 3 || (int) ($ex->errorInfo[1] ?? 0) !== 1062) throw $ex;   // souběh → další číslo
                    }
                }
                $id = (int) db()->lastInsertId();
            }
            header('Location: ' . $self . '?id=' . $id, true, 303);
            exit;
        }
    }
}

// ---------- Přehled faktur ----------
if (isset($_GET['list'])):
    $rows = q("SELECT * FROM invoices ORDER BY id DESC LIMIT 500")->fetchAll();
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= te('adm.invoice.list_title') ?> | <?= te('adm.title') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head><body class="admin">
<?= admin_menu('invoices') ?><main class="container admin-main">
  <p><a href="<?= e(url('admin/?tab=inquiries')) ?>"><?= te('adm.invoice.back') ?></a></p>
  <h1><?= te('adm.invoice.list_title') ?></h1>
  <?php if (!$rows): ?><p class="muted"><?= te('adm.invoice.list_empty') ?></p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <tr><th><?= te('adm.invoice.th_number') ?></th><th><?= te('adm.invoice.th_client') ?></th><th><?= te('adm.invoice.th_issued') ?></th><th><?= te('adm.invoice.th_due') ?></th><th><?= te('adm.invoice.th_amount') ?></th><th></th></tr>
    <?php foreach ($rows as $r): $rd = (array) json_decode($r['data'], true); $over = !$r['paid_at'] && $r['due'] < date('Y-m-d'); ?>
      <tr>
        <td><a href="<?= e($self . '?id=' . $r['id']) ?>"><strong><?= e($r['number']) ?></strong></a><?= ($rd['kind'] ?? '') === 'deposit' ? ' <span class="badge">' . te('adm.invoice.kind_deposit_short') . '</span>' : '' ?></td>
        <td><?= e($rd['client_name'] ?? '') ?></td>
        <td><?= e(inv_date((string) $r['issued'])) ?></td>
        <td<?= $over ? ' style="color:#a3321f;font-weight:700"' : '' ?>><?= e(inv_date((string) $r['due'])) ?></td>
        <td style="white-space:nowrap"><?= inv_money((float) $r['total']) ?></td>
        <td><?= $r['paid_at'] ? '<span class="badge">' . te('adm.invoice.paid_badge') . '</span>' : ($over ? '<span class="muted">' . te('adm.invoice.overdue') . '</span>' : '') ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</main></body></html>
<?php exit; endif;

// ---------- Načtení ----------
$invoice = $id ? (q("SELECT * FROM invoices WHERE id = ?", [$id])->fetch() ?: null) : null;
if ($id && !$invoice) { http_response_code(404); exit(t('adm.invoice.not_found')); }
if ($invoice) { $inqId = (int) $invoice['inquiry_id']; $conId = (int) $invoice['contract_id']; }
$contract = $conId ? (q("SELECT * FROM contracts WHERE id = ?", [$conId])->fetch() ?: null) : null;
if ($contract && !$inqId) $inqId = (int) $contract['inquiry_id'];
$inquiry = $inqId ? (q("SELECT * FROM inquiries WHERE id = ?", [$inqId])->fetch() ?: null) : null;
$defaultVat = (int) (props()['inv.vat_rate']['value_cs'] ?? 21);
$dueDays    = max(0, (int) (props()['inv.due']['value_cs'] ?? 14));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // po chybě: vrátit, co admin vyplnil
    $d = [];
    foreach (INVOICE_FIELDS as $f) $d[$f] = post($f, 2000);
    $d['items'] = array_values(array_filter((array) ($_POST['items'] ?? []), 'is_array'));
} elseif ($invoice) {
    $d = (array) json_decode($invoice['data'], true) + array_fill_keys(INVOICE_FIELDS, '') + ['items' => []];
} else {
    $cd = $contract ? (array) json_decode($contract['data'], true) : [];
    $kind = ($_GET['kind'] ?? '') === 'deposit' ? 'deposit' : 'invoice';
    $d = array_fill_keys(INVOICE_FIELDS, '');
    $d = array_merge($d, [
        'kind'           => $kind,
        'client_name'    => (string) ($cd['client_name'] ?? $inquiry['name'] ?? ''),
        'client_address' => (string) ($cd['client_address'] ?? ''),
        'client_ico'     => (string) ($cd['client_ico'] ?? ''),
        'client_dic'     => (string) ($cd['client_dic'] ?? ''),
        'client_email'   => (string) ($cd['client_email'] ?? $inquiry['email'] ?? ''),
        'issued'         => date('Y-m-d'),
        'taxable'        => date('Y-m-d'),
        'due'            => date('Y-m-d', strtotime("+$dueDays days")),
        'note'           => (string) (props()['inv.note']['value_cs'] ?? ''),
    ]);
    $subject = trim(strtok((string) ($cd['subject'] ?? ''), "\n") ?: '');
    if ($contract && $kind === 'deposit') {
        // zálohová faktura = výzva k platbě zálohy ze smlouvy (částka včetně DPH, bez rozpisu DPH)
        $d['items'] = [['desc' => t('adm.invoice.deposit_item', ['number' => $contract['number']]), 'qty' => '1', 'unit' => '',
                        'price' => (string) inv_num((string) ($cd['deposit'] ?? '0')), 'vat' => '0']];
    } elseif ($contract) {
        $d['items'] = [['desc' => ($subject !== '' ? $subject : t('adm.invoice.work_item')) . ' – ' . t('adm.invoice.per_contract', ['number' => $contract['number']]),
                        'qty' => '1', 'unit' => '', 'price' => (string) inv_num((string) ($cd['price_net'] ?? '0')), 'vat' => (string) ($cd['vat_rate'] ?? $defaultVat)]];
        // zaplacená záloha (zálohová faktura ke stejné smlouvě označená jako zaplacená)
        $paidDep = q("SELECT SUM(total) FROM invoices WHERE contract_id = ? AND paid_at IS NOT NULL AND data LIKE ?", [$conId, '%"kind":"deposit"%'])->fetchColumn();
        if ($paidDep) $d['paid_deposit'] = (string) (float) $paidDep;
    } else {
        $iv = [];
        if ($inquiry) foreach ((array) json_decode((string) $inquiry['data'], true) as $k => $row) $iv[$k] = (string) ($row['value'] ?? '');
        $d['items'] = [['desc' => ($iv['service'] ?? '') ?: t('adm.invoice.work_item'), 'qty' => (string) ((float) str_replace(',', '.', $iv['area'] ?? '') ?: 1),
                        'unit' => ($iv['area'] ?? '') !== '' ? 'm²' : '', 'price' => '', 'vat' => (string) $defaultVat]];
    }
}
$items = array_map(fn($it) => ['desc' => (string) ($it['desc'] ?? ''), 'qty' => (string) ($it['qty'] ?? '1'), 'unit' => (string) ($it['unit'] ?? ''),
                                'price' => (string) ($it['price'] ?? ''), 'vat' => (string) ($it['vat'] ?? $defaultVat)], (array) ($d['items'] ?? []));
$isDeposit = ($d['kind'] ?? '') === 'deposit';

// =====================================================================
// Hotová faktura k tisku
// =====================================================================
if ($invoice && !$edit && !$errors):
    $numItems = array_map(fn($it) => ['desc' => $it['desc'], 'qty' => inv_num($it['qty']), 'unit' => $it['unit'], 'price' => inv_num($it['price']), 'vat' => (int) $it['vat']], $items);
    $showVat  = $vatPayer && !$isDeposit;
    $tot  = inv_totals($numItems, inv_num((string) $d['paid_deposit']), $showVat);
    $iban = invoice_iban();
    $vs   = preg_replace('~\D~', '', $invoice['number']);
    $spd  = $iban && $tot['due'] > 0 ? spd_string($iban, $tot['due'], $vs, t('adm.invoice.qr_msg', ['number' => $invoice['number']]), (string) $invoice['due']) : '';
    $title = $isDeposit ? t('adm.invoice.title_deposit') : ($vatPayer ? t('adm.invoice.title_tax') : t('adm.invoice.title'));
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($invoice['number']) ?> – <?= e($title) ?> – <?= e($d['client_name']) ?></title>
<style>
  @page { size: A4; margin: 14mm 16mm; }
  :root { --ink: #1f1e1c; --muted: #5f5b55; --line: #d8d4ce; --accent: #b5643a; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #ebe8e3; color: var(--ink); font: 10pt/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; }
  .toolbar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; padding: .7rem 16px; background: var(--ink); font: 600 14px/1.2 system-ui, sans-serif; }
  .toolbar a, .toolbar button { color: #fff; background: transparent; border: 1px solid rgba(255,255,255,.35); border-radius: 4px; padding: .5rem .9rem; text-decoration: none; font: inherit; cursor: pointer; }
  .toolbar .primary { background: var(--accent); border-color: var(--accent); }
  .toolbar span { color: #cfcac2; font-weight: 400; margin-right: auto; }
  .toolbar form { margin: 0; }
  .flash { max-width: 210mm; margin: 16px auto 0; padding: .7rem 1rem; background: #fff; border-left: 0; border: 1px solid var(--line); font: 600 14px/1.4 system-ui, sans-serif; }
  @media print { .flash { display: none; } }
  .sheet { width: 210mm; max-width: 100%; margin: 24px auto; padding: 14mm 16mm; background: #fff; box-shadow: 0 2px 18px rgba(0,0,0,.12); }
  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; border-bottom: 2px solid var(--ink); padding-bottom: .8rem; margin-bottom: 1.2rem; }
  h1 { font-size: 17pt; margin: 0; letter-spacing: .01em; }
  .head .no { text-align: right; } .head .no strong { font-size: 14pt; display: block; }
  .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.2rem; }
  .party b.role { display: block; font-size: 8pt; text-transform: uppercase; letter-spacing: .08em; color: var(--accent); margin-bottom: .3rem; }
  .party p { margin: 0; } .party .muted { color: var(--muted); font-size: 9pt; }
  .meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem 1rem; border: 1px solid var(--line); padding: .7rem .9rem; margin-bottom: 1.2rem; }
  .meta div small { display: block; color: var(--muted); font-size: 8pt; text-transform: uppercase; letter-spacing: .05em; }
  .meta div strong { font-variant-numeric: tabular-nums; }
  table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
  th { text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); border-bottom: 1px solid var(--ink); padding: .35rem .4rem; }
  td { padding: .45rem .4rem; border-bottom: 1px solid var(--line); vertical-align: top; }
  .r { text-align: right; white-space: nowrap; }
  .sum { display: grid; grid-template-columns: 1fr auto; gap: 1.5rem; margin-top: 1rem; align-items: start; }
  .sum table { width: auto; margin-left: auto; min-width: 60%; }
  .sum tr.total td { font-weight: 700; font-size: 12pt; border-bottom: 2px solid var(--ink); }
  .qr { text-align: center; font-size: 8pt; color: var(--muted); }
  .qr svg, .qr img { width: 34mm; height: 34mm; display: block; margin: 0 auto .3rem; }
  .note { margin-top: 1.2rem; color: var(--muted); font-size: 9pt; white-space: pre-line; }
  .paid-stamp { display: inline-block; border: 2px solid #23592a; color: #23592a; font-weight: 800; padding: .2rem .6rem; transform: rotate(-4deg); }
  @media (max-width: 640px) { .sheet { padding: 20px 16px; } .parties, .sum { grid-template-columns: 1fr; } .meta { grid-template-columns: 1fr 1fr; } }
  @media print { body { background: #fff; } .toolbar { display: none; } .sheet { width: auto; margin: 0; padding: 0; box-shadow: none; } }
</style>
</head>
<body>
<div class="toolbar">
  <span><?= e($invoice['number']) ?><?= $invoice['paid_at'] ? ' · ' . te('adm.invoice.paid_on', ['date' => inv_date((string) $invoice['paid_at'])]) : '' ?><?= !defined('PUBLIC_DOC') && !empty($d['sent_at']) ? ' · ' . te('adm.invoice.sent_on', ['date' => date('j. n. H:i', strtotime((string) $d['sent_at'])), 'email' => (string) ($d['sent_to'] ?? '')]) : '' ?></span>
  <?php if (!defined('PUBLIC_DOC')): ?>
  <a href="<?= e($self . '?list=1') ?>"><?= te('adm.invoice.list_title') ?></a>
  <a href="<?= e($self . '?id=' . $id . '&edit=1') ?>"><?= te('adm.edit') ?></a>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="paid"><input type="hidden" name="id" value="<?= (int) $id ?>">
    <button type="submit"><?= te($invoice['paid_at'] ? 'adm.invoice.mark_unpaid' : 'adm.invoice.mark_paid') ?></button></form>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send"><input type="hidden" name="id" value="<?= (int) $id ?>">
    <button type="submit"><?= te(empty($d['sent_at']) ? 'adm.invoice.send_btn' : 'adm.invoice.resend_btn') ?></button></form>
  <?php else: ?><a href="<?= e(PUBLIC_DOC) ?>"><?= te('track.back') ?></a><?php endif; ?>
  <button type="button" class="primary" onclick="window.print()"><?= te('adm.contract.print') ?></button>
</div>

<?php if (!defined('PUBLIC_DOC') && ($flash = flash('invoice'))): ?><div class="flash" role="status"><?= e($flash) ?></div><?php endif; ?>
<article class="sheet">
  <div class="head">
    <div><h1><?= e($title) ?></h1><?php if ($isDeposit): ?><div style="color:var(--muted)"><?= te('adm.invoice.not_tax_doc') ?></div><?php endif; ?></div>
    <div class="no"><?= te('adm.invoice.number') ?><strong><?= e($invoice['number']) ?></strong><?php if ($invoice['paid_at']): ?><span class="paid-stamp"><?= te('adm.invoice.paid_stamp') ?></span><?php endif; ?></div>
  </div>

  <div class="parties">
    <div class="party">
      <b class="role"><?= te('adm.invoice.supplier') ?></b>
      <p><strong><?= e(site('owner')) ?></strong></p>
      <p><?= e(site('address')) ?></p>
      <p><?= te('footer.ico') ?> <?= e(site('ico')) ?><?= site('dic') !== '' ? ' · DIČ ' . e(site('dic')) : '' ?></p>
      <p class="muted"><?= e((string) (props()['inv.registry']['value_cs'] ?? '')) ?><?= !$vatPayer ? ' ' . te('adm.invoice.not_vat_payer') : '' ?></p>
      <p class="muted"><?= e(site('phone')) ?> · <?= e(site('email')) ?></p>
    </div>
    <div class="party">
      <b class="role"><?= te('adm.invoice.customer') ?></b>
      <p><strong><?= e($d['client_name']) ?></strong></p>
      <p style="white-space:pre-line"><?= e($d['client_address']) ?></p>
      <?php if ($d['client_ico'] !== '' || $d['client_dic'] !== ''): ?><p><?= $d['client_ico'] !== '' ? te('footer.ico') . ' ' . e($d['client_ico']) : '' ?><?= $d['client_dic'] !== '' ? ' · DIČ ' . e($d['client_dic']) : '' ?></p><?php endif; ?>
    </div>
  </div>

  <div class="meta">
    <div><small><?= te('adm.invoice.issued') ?></small><strong><?= e(inv_date((string) $d['issued'])) ?></strong></div>
    <?php if ($showVat): ?><div><small><?= te('adm.invoice.taxable') ?></small><strong><?= e(inv_date((string) $d['taxable'])) ?></strong></div><?php endif; ?>
    <div><small><?= te('adm.invoice.due') ?></small><strong><?= e(inv_date((string) $d['due'])) ?></strong></div>
    <div><small><?= te('adm.invoice.vs') ?></small><strong><?= e($vs) ?></strong></div>
    <div><small><?= te('adm.invoice.method') ?></small><strong><?= te('adm.invoice.transfer') ?></strong></div>
    <div style="grid-column: span 2"><small><?= te('adm.invoice.account') ?></small><strong><?= e(site('bank')) ?></strong><?= $iban ? '<br><span style="font-size:8.5pt;color:var(--muted)">IBAN ' . e(iban_format($iban)) . '</span>' : '' ?></div>
    <?php if ($contract): ?><div><small><?= te('adm.invoice.contract') ?></small><strong><?= e($contract['number']) ?></strong></div><?php endif; ?>
  </div>

  <table>
    <tr><th><?= te('adm.invoice.th_desc') ?></th><th class="r"><?= te('adm.invoice.th_qty') ?></th><th class="r"><?= te($showVat ? 'adm.invoice.th_price_net' : 'adm.invoice.th_price') ?></th>
      <?php if ($showVat): ?><th class="r"><?= te('adm.invoice.th_vat') ?></th><?php endif; ?><th class="r"><?= te($showVat ? 'adm.invoice.th_total_net' : 'adm.invoice.th_total') ?></th></tr>
    <?php foreach ($numItems as $it): ?>
      <tr><td><?= nl2br(e($it['desc'])) ?></td>
        <td class="r"><?= e(rtrim(rtrim(number_format($it['qty'], 2, ',', ' '), '0'), ',')) ?> <?= e($it['unit']) ?></td>
        <td class="r"><?= inv_money($it['price']) ?></td>
        <?php if ($showVat): ?><td class="r"><?= (int) $it['vat'] ?> %</td><?php endif; ?>
        <td class="r"><?= inv_money(round($it['qty'] * $it['price'], 2)) ?></td></tr>
    <?php endforeach; ?>
  </table>

  <div class="sum">
    <div class="qr">
      <?php if ($spd !== ''): ?>
        <div id="qr" data-spd="<?= e($spd) ?>" role="img" aria-label="QR Platba"></div><?= te('adm.invoice.qr_label') ?>
      <?php endif; ?>
    </div>
    <table>
      <?php if ($showVat): foreach ($tot['rates'] as $rate => $r): ?>
        <tr><td><?= te('adm.invoice.base', ['rate' => $rate]) ?></td><td class="r"><?= inv_money($r['base']) ?></td></tr>
        <tr><td><?= te('adm.invoice.vat', ['rate' => $rate]) ?></td><td class="r"><?= inv_money($r['vat']) ?></td></tr>
      <?php endforeach; endif; ?>
      <tr><td><?= te('adm.invoice.total') ?></td><td class="r"><?= inv_money($tot['total']) ?></td></tr>
      <?php if (inv_num((string) $d['paid_deposit']) > 0): ?><tr><td><?= te('adm.invoice.paid_deposit') ?></td><td class="r">−<?= inv_money(inv_num((string) $d['paid_deposit'])) ?></td></tr><?php endif; ?>
      <tr class="total"><td><?= te('adm.invoice.to_pay') ?></td><td class="r"><?= inv_money($tot['due']) ?></td></tr>
    </table>
  </div>

  <?php if (trim((string) $d['note']) !== ''): ?><p class="note"><?= e($d['note']) ?></p><?php endif; ?>
</article>
<?php if ($spd !== ''): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
  (function () {
    var box = document.getElementById('qr');
    if (!box || typeof qrcode === 'undefined') return;
    var qr = qrcode(0, 'M');
    qr.addData(box.getAttribute('data-spd'));
    qr.make();
    box.innerHTML = qr.createSvgTag(4, 0);
  })();
</script>
<?php endif; ?>
</body>
</html>
<?php exit; endif;

// =====================================================================
// Formulář
// =====================================================================
$fld = function (string $name, string $label, string $type = 'text', string $extra = '') use ($d): string {
    return '<label>' . $label . '<input type="' . $type . '" name="' . $name . '" value="' . e((string) ($d[$name] ?? '')) . '"' . $extra . '></label>';
};
$others = $conId ? q("SELECT id, number FROM invoices WHERE contract_id = ? ORDER BY id DESC", [$conId])->fetchAll()
        : ($inqId ? q("SELECT id, number FROM invoices WHERE inquiry_id = ? ORDER BY id DESC", [$inqId])->fetchAll() : []);
if (!$items) $items = [['desc' => '', 'qty' => '1', 'unit' => '', 'price' => '', 'vat' => (string) $defaultVat]];
$query = $invoice ? '?id=' . $id . '&edit=1' : ($conId ? '?contract=' . $conId : '?inquiry=' . $inqId);
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $invoice ? te('adm.invoice.edit_title', ['number' => $invoice['number']]) : te('adm.invoice.new_title') ?> | <?= te('adm.title') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<?= admin_menu('invoices') ?>
<main class="container admin-main contract-form">
  <p><a href="<?= e(url('admin/?tab=inquiries')) ?>"><?= te('adm.invoice.back') ?></a> · <a href="<?= e($self . '?list=1') ?>"><?= te('adm.invoice.list_title') ?></a></p>
  <h1><?= $invoice ? te('adm.invoice.edit_title', ['number' => $invoice['number']]) : te('adm.invoice.new_title') ?></h1>

  <?php if ($others && !$invoice): ?>
    <div class="alert alert--info settings-note"><?= te('adm.invoice.existing') ?> <?php foreach ($others as $o): ?><a href="<?= e($self . '?id=' . $o['id']) ?>"><?= e($o['number']) ?></a> <?php endforeach; ?></div>
  <?php endif; ?>
  <?php if ($errors): ?><div class="alert alert--err settings-note" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php if (!invoice_iban()): ?><div class="alert alert--err settings-note"><?= te('adm.inv.iban_missing') ?> <a href="<?= e(url('admin/?tab=settings&grp=inv')) ?>"><?= te('adm.sgrp.inv') ?></a></div><?php endif; ?>

  <form method="post" class="form panel" action="<?= e($self . $query) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="inquiry_id" value="<?= (int) $inqId ?>">
    <input type="hidden" name="contract_id" value="<?= (int) $conId ?>">

    <fieldset class="settings-sec">
      <legend><?= te('adm.invoice.kind') ?></legend>
      <div class="form__grid">
        <label class="check"><input type="radio" name="kind" value="invoice"<?= !$isDeposit ? ' checked' : '' ?>><span><?= te($vatPayer ? 'adm.invoice.title_tax' : 'adm.invoice.title') ?></span></label>
        <label class="check"><input type="radio" name="kind" value="deposit"<?= $isDeposit ? ' checked' : '' ?>><span><?= te('adm.invoice.title_deposit') ?> <span class="muted">(<?= te('adm.invoice.not_tax_doc') ?>)</span></span></label>
      </div>
    </fieldset>

    <fieldset class="settings-sec">
      <legend><?= te('adm.invoice.customer') ?></legend>
      <div class="form__grid">
        <?= $fld('client_name', te('adm.contract.f_client_name')) ?>
        <?= $fld('client_email', te('adm.contract.f_email'), 'email') ?>
        <label class="field--wide"><?= te('adm.contract.f_client_address') ?><textarea name="client_address" rows="2"><?= e((string) $d['client_address']) ?></textarea></label>
        <?= $fld('client_ico', t('adm.contract.f_ico')) ?>
        <?= $fld('client_dic', t('adm.contract.f_dic')) ?>
      </div>
    </fieldset>

    <fieldset class="settings-sec">
      <legend><?= te('adm.invoice.dates') ?></legend>
      <div class="form__grid form__grid--3">
        <?= $fld('issued', te('adm.invoice.issued'), 'date') ?>
        <?= $fld('taxable', te('adm.invoice.taxable'), 'date') ?>
        <?= $fld('due', te('adm.invoice.due'), 'date') ?>
      </div>
    </fieldset>

    <fieldset class="settings-sec">
      <legend><?= te('adm.invoice.items') ?></legend>
      <div class="inv-items" data-items>
        <div class="inv-row inv-row--head" aria-hidden="true"><span><?= te('adm.invoice.th_desc') ?></span><span><?= te('adm.invoice.th_qty') ?></span><span><?= te('adm.invoice.th_unit') ?></span><span><?= te($vatPayer ? 'adm.invoice.th_price_net' : 'adm.invoice.th_price') ?></span><?php if ($vatPayer): ?><span><?= te('adm.invoice.th_vat') ?></span><?php endif; ?><span></span></div>
        <?php foreach ($items as $i => $it): ?>
          <div class="inv-row" data-row>
            <label><span class="inv-label"><?= te('adm.invoice.th_desc') ?></span><textarea name="items[<?= $i ?>][desc]" rows="1"><?= e($it['desc']) ?></textarea></label>
            <label><span class="inv-label"><?= te('adm.invoice.th_qty') ?></span><input type="text" inputmode="decimal" name="items[<?= $i ?>][qty]" value="<?= e($it['qty']) ?>"></label>
            <label><span class="inv-label"><?= te('adm.invoice.th_unit') ?></span><input type="text" name="items[<?= $i ?>][unit]" value="<?= e($it['unit']) ?>" placeholder="m²"></label>
            <label><span class="inv-label"><?= te($vatPayer ? 'adm.invoice.th_price_net' : 'adm.invoice.th_price') ?></span><input type="text" inputmode="decimal" name="items[<?= $i ?>][price]" value="<?= e($it['price']) ?>"></label>
            <?php if ($vatPayer): ?><label><span class="inv-label"><?= te('adm.invoice.th_vat') ?></span><select name="items[<?= $i ?>][vat]"><?php foreach (['21', '12', '0'] as $v): ?><option value="<?= $v ?>"<?= (string) $it['vat'] === $v ? ' selected' : '' ?>><?= $v ?> %</option><?php endforeach; ?></select></label>
            <?php else: ?><input type="hidden" name="items[<?= $i ?>][vat]" value="0"><?php endif; ?>
            <button type="button" class="btn btn--small btn--ghost" data-remove aria-label="<?= te('adm.delete') ?>">×</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn--small btn--ghost" data-add><?= te('adm.invoice.add_item') ?></button>
      <p class="contract-sum" data-sum aria-live="polite"></p>
      <div class="form__grid">
        <?= $fld('paid_deposit', te('adm.invoice.paid_deposit_f'), 'text', ' inputmode="decimal" data-deposit') ?>
      </div>
    </fieldset>

    <label><?= te('adm.invoice.note_f') ?><textarea name="note" rows="2"><?= e((string) $d['note']) ?></textarea></label>
    <button class="btn" type="submit"><?= te($invoice ? 'adm.contract.save' : 'adm.invoice.create') ?></button>
  </form>
</main>
<script>
(function () {
  var box = document.querySelector('[data-items]'), out = document.querySelector('[data-sum]'), dep = document.querySelector('[data-deposit]');
  var vatPayer = <?= $vatPayer ? 'true' : 'false' ?>;
  var fmt = new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' });
  var num = function (v) { return parseFloat(String(v || '').replace(/\s/g, '').replace(',', '.')) || 0; };
  function rows() { return Array.prototype.slice.call(box.querySelectorAll('[data-row]')); }
  function renumber() {
    rows().forEach(function (row, i) {
      row.querySelectorAll('[name^="items["]').forEach(function (el) { el.name = el.name.replace(/^items\[\d+\]/, 'items[' + i + ']'); });
    });
  }
  function sum() {
    var total = 0;
    rows().forEach(function (row) {
      var q = num(row.querySelector('[name$="[qty]"]').value) || 1, p = num(row.querySelector('[name$="[price]"]').value);
      var v = vatPayer && !document.querySelector('[name=kind][value=deposit]').checked ? num(row.querySelector('[name$="[vat]"]').value) : 0;
      total += Math.round(q * p * (1 + v / 100) * 100) / 100;
    });
    var d = num(dep && dep.value);
    out.textContent = <?= json_encode(t('adm.invoice.total') . ': ') ?> + fmt.format(total) + (d ? ' − ' + fmt.format(d) + ' = ' + <?= json_encode(t('adm.invoice.to_pay') . ' ') ?> + fmt.format(Math.max(0, total - d)) : '');
  }
  document.querySelector('[data-add]').addEventListener('click', function () {
    var last = rows().pop(), row = last.cloneNode(true);
    row.querySelectorAll('textarea, input[type=text]').forEach(function (el) { el.value = el.name.indexOf('[qty]') > -1 ? '1' : ''; });
    box.appendChild(row); renumber(); row.querySelector('textarea').focus(); sum();
  });
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-remove]');
    if (!b || rows().length < 2) return;
    b.closest('[data-row]').remove(); renumber(); sum();
  });
  document.querySelector('form').addEventListener('input', sum);
  document.querySelector('form').addEventListener('change', sum);
  sum();
})();
</script>
</body>
</html>
