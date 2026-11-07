<?php
declare(strict_types=1);
/*
 * Smlouva o dílo – vytvoření z poptávky, uložení, tisk / uložení do PDF z prohlížeče.
 *   ?inquiry=ID      nová smlouva předvyplněná z poptávky (+ seznam dřívějších smluv k poptávce)
 *   ?id=ID           hotová smlouva k tisku
 *   ?id=ID&edit=1    úprava uložené smlouvy
 */
require __DIR__ . '/../includes/bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . url('admin/'), true, 303);
    exit;
}

$self    = url('admin/contract.php');
$id      = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$inqId   = (int) ($_GET['inquiry'] ?? $_POST['inquiry_id'] ?? 0);
$edit    = isset($_GET['edit']);
$errors  = [];

const CONTRACT_FIELDS = [
    'client_name', 'client_address', 'client_ico', 'client_dic', 'client_phone', 'client_email',
    'place', 'subject', 'area', 'start', 'end', 'price_net', 'vat_rate', 'deposit', 'deposit_days',
    'due_days', 'warranty', 'notes', 'date', 'sign_place',
];

function money(float $v): string { return number_format($v, 2, ',', "\u{00A0}") . "\u{00A0}Kč"; }
function num(string $v): float { return (float) str_replace([',', ' ', "\u{00A0}"], ['.', '', ''], $v); }
function cz_date(string $iso): string { return $iso !== '' && ($t = strtotime($iso)) ? date('j. n. Y', $t) : '……………'; }
/** Datum z poptávky („7. 11. 2026“ nebo ISO) → Y-m-d */
function iso_date(string $v): string
{
    foreach (['!j. n. Y', '!Y-m-d'] as $f) if ($d = DateTimeImmutable::createFromFormat($f, trim($v))) return $d->format('Y-m-d');
    return '';
}
function next_number(): string
{
    $y = date('Y');
    $n = 0;
    foreach (q("SELECT number FROM contracts WHERE number LIKE ?", ["SoD-$y-%"])->fetchAll() as $r) {
        $n = max($n, (int) substr($r['number'], strlen("SoD-$y-")));
    }
    return sprintf('SoD-%s-%03d', $y, $n + 1);
}

// ---------- Uložení ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = t('adm.contract.err_csrf');
    } else {
        $d = [];
        foreach (CONTRACT_FIELDS as $f) $d[$f] = post($f, $f === 'subject' || $f === 'notes' ? 5000 : 300);
        foreach (['start', 'end', 'date'] as $f) if ($d[$f] !== '' && iso_date($d[$f]) !== $d[$f]) $d[$f] = '';
        if (!in_array($d['vat_rate'], ['21', '12', '0'], true)) $d['vat_rate'] = '21';
        if ($d['client_name'] === '')    $errors[] = t('adm.contract.err_name');
        if ($d['client_address'] === '') $errors[] = t('adm.contract.err_address');
        if ($d['subject'] === '')        $errors[] = t('adm.contract.err_subject');
        if (num($d['price_net']) <= 0)   $errors[] = t('adm.contract.err_price');
        if ($d['start'] !== '' && $d['end'] !== '' && $d['end'] < $d['start']) $errors[] = t('adm.contract.err_dates');

        if (!$errors) {
            $json = json_encode($d, JSON_UNESCAPED_UNICODE);
            if ($id) {
                q("UPDATE contracts SET data = ?, updated_at = NOW() WHERE id = ?", [$json, $id]);
            } else {
                for ($try = 0; ; $try++) {                     // souběžné vytvoření → další číslo
                    try {
                        q("INSERT INTO contracts (inquiry_id, number, data) VALUES (?,?,?)", [$inqId ?: null, next_number(), $json]);
                        break;
                    } catch (PDOException $ex) {
                        if ($try >= 3 || (int) ($ex->errorInfo[1] ?? 0) !== 1062) throw $ex;
                    }
                }
                $id = (int) db()->lastInsertId();
            }
            header('Location: ' . $self . '?id=' . $id, true, 303);
            exit;
        }
    }
}

// ---------- Načtení ----------
$contract = $id ? (q("SELECT * FROM contracts WHERE id = ?", [$id])->fetch() ?: null) : null;
if ($id && !$contract) { http_response_code(404); exit(t('adm.contract.not_found')); }
if ($contract) $inqId = (int) $contract['inquiry_id'];
$inquiry = $inqId ? (q("SELECT * FROM inquiries WHERE id = ?", [$inqId])->fetch() ?: null) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [];
    foreach (CONTRACT_FIELDS as $f) $d[$f] = post($f, 5000);
} elseif ($contract) {
    $d = (array) json_decode($contract['data'], true) + array_fill_keys(CONTRACT_FIELDS, '');
} else {
    // nová smlouva: výchozí hodnoty + údaje z poptávky
    $iv = [];
    if ($inquiry) foreach ((array) json_decode((string) $inquiry['data'], true) as $k => $row) $iv[$k] = (string) ($row['value'] ?? '');
    $subject = trim(($iv['service'] ?? '') . (($iv['service'] ?? '') !== '' && ($iv['message'] ?? '') !== '' ? "\n" : '') . ($iv['message'] ?? $inquiry['message'] ?? ''));
    $d = array_fill_keys(CONTRACT_FIELDS, '');
    $d = array_merge($d, [
        'client_name'  => (string) ($inquiry['name'] ?? ''),
        'client_phone' => (string) ($inquiry['phone'] ?? ''),
        'client_email' => (string) ($inquiry['email'] ?? ''),
        'place'        => (string) ($inquiry['location'] ?? ''),
        'subject'      => $subject,
        'area'         => $iv['area'] ?? '',
        'start'        => iso_date($iv['term_from'] ?? ''),
        'end'          => iso_date($iv['term_to'] ?? ''),
        'vat_rate'     => '21',
        'deposit'      => '',
        'deposit_days' => '7',
        'due_days'     => (string) (props()['contract.due']['value_cs'] ?? '14'),
        'warranty'     => (string) (props()['contract.warranty']['value_cs'] ?? '24'),
        'date'         => date('Y-m-d'),
        'sign_place'   => (string) (props()['contract.place']['value_cs'] ?? ''),
    ]);
}

$net   = round(num($d['price_net']), 2);
$rate  = (int) $d['vat_rate'];
$vat   = round($net * $rate / 100, 2);
$gross = $net + $vat;
$dep   = round(num($d['deposit']), 2);

// =====================================================================
// Hotová smlouva k tisku
// =====================================================================
if ($contract && !$edit && !$errors):
    $terms = t('contract.terms', ['warranty' => e($d['warranty'] ?: '24'), 'privacy_url' => e(abs_url(privacy_url()))]);
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($contract['number']) ?> – Smlouva o dílo – <?= e($d['client_name']) ?></title>
<style>
  @page { size: A4; margin: 16mm 18mm; }
  :root { --ink: #1f1e1c; --muted: #5f5b55; --line: #d8d4ce; --accent: #b5643a; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #ebe8e3; color: var(--ink); font: 10.5pt/1.5 Georgia, "Times New Roman", serif; }
  .toolbar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: center; padding: .7rem 16px;
    background: var(--ink); font: 600 14px/1.2 system-ui, sans-serif; }
  .toolbar a, .toolbar button { color: #fff; background: transparent; border: 1px solid rgba(255,255,255,.35); border-radius: 4px; padding: .5rem .9rem; text-decoration: none; font: inherit; cursor: pointer; }
  .toolbar .primary { background: var(--accent); border-color: var(--accent); }
  .toolbar span { color: #cfcac2; font-weight: 400; margin-right: auto; }
  .sheet { width: 210mm; max-width: 100%; margin: 24px auto; padding: 16mm 18mm; background: #fff; box-shadow: 0 2px 18px rgba(0,0,0,.12); }
  h1 { font: 700 18pt/1.2 system-ui, sans-serif; letter-spacing: .02em; text-align: center; margin: 0; }
  .sub { text-align: center; color: var(--muted); margin: .3rem 0 1.4rem; }
  h2 { font: 700 10.5pt/1.3 system-ui, sans-serif; text-transform: uppercase; letter-spacing: .04em; margin: 1.3rem 0 .4rem; break-after: avoid; }
  ol { margin: 0; padding-left: 1.4rem; } li { margin: .2rem 0; } p { margin: .3rem 0; }
  .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.5rem; }
  .party { border: 1px solid var(--line); padding: .7rem .9rem; break-inside: avoid; }
  .party b.role { display: block; font: 700 9pt/1.2 system-ui, sans-serif; text-transform: uppercase; letter-spacing: .06em; color: var(--accent); margin-bottom: .3rem; }
  .party dl { display: grid; grid-template-columns: max-content 1fr; gap: 0 .7rem; margin: .3rem 0 0; font-size: 9.5pt; }
  .party dt { color: var(--muted); } .party dd { margin: 0; overflow-wrap: anywhere; }
  table.price { width: 100%; border-collapse: collapse; margin: .5rem 0; break-inside: avoid; }
  table.price td { padding: .3rem .5rem; border-bottom: 1px solid var(--line); }
  table.price td:last-child { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  table.price tr.total td { font-weight: 700; border-bottom: 2px solid var(--ink); }
  .pre { white-space: pre-line; }
  .signs { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-top: 2.2rem; break-inside: avoid; }
  .signs div { border-top: 1px solid var(--ink); padding-top: .3rem; text-align: center; font-size: 9.5pt; margin-top: 3.2rem; }
  .signs p { margin: 0 0 .2rem; }
  @media (max-width: 640px) { .sheet { padding: 20px 16px; } .parties, .signs { grid-template-columns: 1fr; } }
  @media print {
    body { background: #fff; } .toolbar { display: none; }
    .sheet { width: auto; margin: 0; padding: 0; box-shadow: none; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <span><?= e($contract['number']) ?></span>
  <a href="<?= e(url('admin/?tab=inquiries')) ?>"><?= te('adm.contract.back_admin') ?></a>
  <a href="<?= e($self . '?id=' . $id . '&edit=1') ?>"><?= te('adm.edit') ?></a>
  <?php if ($dep > 0): ?><a href="<?= e(url('admin/invoice.php?contract=' . $id . '&kind=deposit')) ?>"><?= te('adm.contract.invoice_deposit') ?></a><?php endif; ?>
  <a href="<?= e(url('admin/invoice.php?contract=' . $id)) ?>"><?= te('adm.contract.invoice') ?></a>
  <button type="button" class="primary" onclick="window.print()"><?= te('adm.contract.print') ?></button>
</div>

<article class="sheet">
  <h1>SMLOUVA O DÍLO</h1>
  <p class="sub">č. <?= e($contract['number']) ?> · uzavřená podle § 2586 a násl. zákona č. 89/2012 Sb., občanský zákoník</p>

  <div class="parties">
    <section class="party">
      <b class="role">Zhotovitel</b>
      <strong><?= e(site('owner')) ?></strong>
      <dl>
        <dt>Místo podnikání</dt><dd><?= e(site('address')) ?></dd>
        <dt>IČO</dt><dd><?= e(site('ico')) ?></dd>
        <?php if (site('dic') !== ''): ?><dt>DIČ</dt><dd><?= e(site('dic')) ?></dd><?php endif; ?>
        <dt>Telefon</dt><dd><?= e(site('phone')) ?></dd>
        <dt>E-mail</dt><dd><?= e(site('email')) ?></dd>
        <?php if (site('bank') !== ''): ?><dt>Účet</dt><dd><?= e(site('bank')) ?></dd><?php endif; ?>
      </dl>
      <p style="font-size:9pt;color:var(--muted);margin-top:.4rem">Fyzická osoba podnikající podle živnostenského zákona.</p>
    </section>
    <section class="party">
      <b class="role">Objednatel</b>
      <strong><?= e($d['client_name']) ?></strong>
      <dl>
        <dt>Adresa</dt><dd><?= e($d['client_address']) ?></dd>
        <?php if ($d['client_ico'] !== ''): ?><dt>IČO</dt><dd><?= e($d['client_ico']) ?></dd><?php endif; ?>
        <?php if ($d['client_dic'] !== ''): ?><dt>DIČ</dt><dd><?= e($d['client_dic']) ?></dd><?php endif; ?>
        <?php if ($d['client_phone'] !== ''): ?><dt>Telefon</dt><dd><?= e($d['client_phone']) ?></dd><?php endif; ?>
        <?php if ($d['client_email'] !== ''): ?><dt>E-mail</dt><dd><?= e($d['client_email']) ?></dd><?php endif; ?>
      </dl>
    </section>
  </div>

  <h2>I. Předmět díla</h2>
  <ol>
    <li>Zhotovitel se zavazuje provést pro objednatele na svůj náklad a nebezpečí toto dílo:
      <p class="pre"><strong><?= e($d['subject']) ?></strong></p>
      <?php if ($d['area'] !== ''): ?><p>Rozsah: přibližně <?= e($d['area']) ?> m², přesný rozsah podle skutečně provedené plochy.</p><?php endif; ?></li>
    <li>Místo provedení díla: <?= e($d['place'] !== '' ? $d['place'] : $d['client_address']) ?>.</li>
    <li>Objednatel se zavazuje dílo převzít a zaplatit zhotoviteli cenu podle článku III.</li>
  </ol>

  <h2>II. Doba plnění</h2>
  <ol>
    <li>Zahájení prací: <?= e(cz_date($d['start'])) ?>. Dokončení a předání díla: <?= e(cz_date($d['end'])) ?>.</li>
    <li>Doba plnění se přiměřeně prodlužuje o dobu, po kterou zhotovitel nemohl pracovat z důvodů na straně objednatele nebo kvůli nevhodným klimatickým podmínkám pro zpracování materiálu.</li>
  </ol>

  <h2>III. Cena díla</h2>
  <ol>
    <li>Cena díla je stanovena dohodou stran:
      <table class="price">
        <tr><td>Cena bez DPH</td><td><?= money($net) ?></td></tr>
        <tr><td>DPH <?= $rate ?> %</td><td><?= money($vat) ?></td></tr>
        <tr class="total"><td>Cena celkem včetně DPH</td><td><?= money($gross) ?></td></tr>
      </table></li>
    <li>Práce nad rámec předmětu díla (vícepráce) provede zhotovitel jen na základě písemné dohody s objednatelem o jejich rozsahu a ceně.</li>
  </ol>

  <h2>IV. Platební podmínky</h2>
  <ol>
    <?php if ($dep > 0): ?><li>Objednatel uhradí zálohu ve výši <?= money($dep) ?> do <?= (int) $d['deposit_days'] ?> dnů od podpisu smlouvy. Záloha se započte na cenu díla.</li><?php endif; ?>
    <li><?= $dep > 0 ? 'Doplatek ceny' : 'Cenu díla' ?> uhradí objednatel na základě daňového dokladu (faktury) vystaveného po předání díla, se splatností <?= (int) $d['due_days'] ?> dnů<?= site('bank') !== '' ? ', na účet zhotovitele č. ' . e(site('bank')) : '' ?>.</li>
    <li>Do úplného zaplacení ceny zůstávají dodané materiály ve vlastnictví zhotovitele, nebrání-li tomu povaha díla.</li>
  </ol>

  <?= $terms /* HTML z administrace: Texty CZ/EN → Smlouva o dílo */ ?>

  <?php if (trim($d['notes']) !== ''): ?>
    <h2>Zvláštní ujednání</h2>
    <p class="pre"><?= e($d['notes']) ?></p>
  <?php endif; ?>

  <p style="margin-top:1.6rem"><?= e($d['sign_place'] ?: '……………') ?>, <?= e(cz_date($d['date'])) ?></p>
  <div class="signs">
    <div><p><strong>Zhotovitel</strong></p><?= e(site('owner')) ?></div>
    <div><p><strong>Objednatel</strong></p><?= e($d['client_name']) ?></div>
  </div>
</article>
</body>
</html>
<?php exit; endif;

// =====================================================================
// Formulář (nová smlouva / úprava)
// =====================================================================
$others = $inqId ? q("SELECT id, number, created_at FROM contracts WHERE inquiry_id = ? ORDER BY id DESC", [$inqId])->fetchAll() : [];
$fld = function (string $name, string $label, string $type = 'text', string $extra = '') use ($d): string {
    return '<label>' . $label . '<input type="' . $type . '" name="' . $name . '" value="' . e((string) ($d[$name] ?? '')) . '"' . $extra . '></label>';
};
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $contract ? te('adm.contract.edit_title', ['number' => $contract['number']]) : te('adm.contract.new_title') ?> | <?= te('adm.title') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style>
  .contract-form { max-width: 920px; }
  .contract-form fieldset { border: 0; padding: 0; margin: 0 0 1.5rem; display: grid; gap: 1rem; }
  .contract-form legend { font-family: var(--font-head); font-weight: 800; font-size: 1.15rem; margin-bottom: .8rem; }
  .contract-sum { background: var(--concrete-50, #f7f6f4); padding: .8rem 1rem; font-variant-numeric: tabular-nums; }
  .hint { font-size: .82rem; font-weight: 400; color: var(--concrete-600); }
  @media (max-width: 760px) { .contract-form .form__grid { grid-template-columns: 1fr; } }
</style>
</head>
<body class="admin">
<main class="container admin-main contract-form">
  <p><a href="<?= e(url('admin/?tab=inquiries')) ?>"><?= te('adm.contract.back') ?></a></p>
  <h1><?= $contract ? te('adm.contract.heading', ['number' => $contract['number']]) : te('adm.contract.new_title') ?></h1>

  <?php if ($others && !$contract): ?>
    <div class="alert alert--info" style="margin-bottom:1rem"><?= te('adm.contract.existing') ?>
      <?php foreach ($others as $o): ?><a href="<?= e($self . '?id=' . $o['id']) ?>"><?= e($o['number']) ?></a> <?php endforeach; ?></div>
  <?php endif; ?>
  <?php if ($errors): ?><div class="alert alert--err" role="alert" style="margin-bottom:1rem"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php if (site('dic') === '' || site('bank') === ''): ?>
    <div class="alert alert--info" style="margin-bottom:1rem"><?= te('adm.contract.missing_settings') ?> <a href="<?= e(url('admin/?tab=settings')) ?>"><?= te('adm.contract.missing_link') ?></a>: <?= e(implode(', ', array_filter([site('dic') === '' ? 'DIČ' : '', site('bank') === '' ? t('adm.contract.bank') : '']))) ?>.</div>
  <?php endif; ?>

  <form method="post" class="form panel" action="<?= e($self . ($contract ? '?id=' . $id : '?inquiry=' . $inqId)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="inquiry_id" value="<?= (int) $inqId ?>">

    <fieldset>
      <legend><?= te('adm.contract.client') ?></legend>
      <div class="form__grid">
        <?= $fld('client_name', t('adm.contract.f_client_name')) ?>
        <?= $fld('client_address', t('adm.contract.f_client_address')) ?>
        <?= $fld('client_phone', t('adm.contract.f_phone'), 'tel') ?>
        <?= $fld('client_email', t('adm.contract.f_email'), 'email') ?>
        <?= $fld('client_ico', t('adm.contract.f_ico')) ?>
        <?= $fld('client_dic', t('adm.contract.f_dic')) ?>
      </div>
    </fieldset>

    <fieldset>
      <legend><?= te('adm.contract.work') ?></legend>
      <label><?= te('adm.contract.f_subject') ?><textarea name="subject" rows="4"><?= e($d['subject']) ?></textarea></label>
      <div class="form__grid">
        <?= $fld('place', t('adm.contract.f_place')) ?>
        <?= $fld('area', t('adm.contract.f_area'), 'text', ' inputmode="decimal"') ?>
        <?= $fld('start', t('adm.contract.f_start'), 'date') ?>
        <?= $fld('end', t('adm.contract.f_end'), 'date') ?>
      </div>
    </fieldset>

    <fieldset>
      <legend><?= te('adm.contract.payment') ?></legend>
      <div class="form__grid">
        <?= $fld('price_net', t('adm.contract.f_price'), 'text', ' inputmode="decimal" data-net') ?>
        <label><?= te('adm.contract.f_vat') ?>
          <select name="vat_rate" data-rate>
            <?php foreach (['21' => t('adm.contract.vat21'), '12' => t('adm.contract.vat12'), '0' => t('adm.contract.vat0')] as $k => $v): ?>
              <option value="<?= $k ?>"<?= (string) $d['vat_rate'] === (string) $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></label>
        <?= $fld('deposit', t('adm.contract.f_deposit'), 'text', ' inputmode="decimal"') ?>
        <?= $fld('deposit_days', t('adm.contract.f_deposit_days'), 'number', ' min="0"') ?>
        <?= $fld('due_days', t('adm.contract.f_due_days'), 'number', ' min="0"') ?>
        <?= $fld('warranty', t('adm.contract.f_warranty'), 'number', ' min="0"') ?>
      </div>
      <p class="contract-sum" data-sum aria-live="polite" data-t-net="<?= te('adm.contract.sum_net') ?>" data-t-total="<?= te('adm.contract.sum_total') ?>" data-t-empty="<?= te('adm.contract.sum_empty') ?>"></p>
    </fieldset>

    <fieldset>
      <legend><?= te('adm.contract.signing') ?></legend>
      <div class="form__grid">
        <?= $fld('sign_place', t('adm.contract.f_sign_place')) ?>
        <?= $fld('date', t('adm.contract.f_date'), 'date') ?>
      </div>
      <label><?= t('adm.contract.f_notes') /* HTML */ ?><textarea name="notes" rows="3"><?= e($d['notes']) ?></textarea></label>
      <p class="hint"><?= te('adm.contract.terms_hint') ?> <a href="<?= e(url('admin/?tab=texts&grp=contract')) ?>"><?= te('adm.contract.terms_link') ?></a>.</p>
    </fieldset>

    <button class="btn" type="submit"><?= te($contract ? 'adm.contract.save' : 'adm.contract.create') ?></button>
  </form>
</main>
<script>
(function () {
  var net = document.querySelector('[data-net]'), rate = document.querySelector('[data-rate]'), out = document.querySelector('[data-sum]');
  var fmt = new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' });
  function upd() {
    var n = parseFloat((net.value || '').replace(/\s/g, '').replace(',', '.')) || 0, r = parseInt(rate.value, 10) || 0, v = Math.round(n * r) / 100;
    out.textContent = n ? out.dataset.tNet + ' ' + fmt.format(n) + ' + DPH ' + r + ' % ' + fmt.format(v) + ' = ' + out.dataset.tTotal + ' ' + fmt.format(n + v) : out.dataset.tEmpty;
  }
  net.addEventListener('input', upd); rate.addEventListener('change', upd); upd();
})();
</script>
</body>
</html>
