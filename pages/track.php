<?php
/*
 * Soukromý odkaz zákazníka na poptávku: /poptavka/?id=…&t=…  (bez přihlášení)
 * Stav, co zákazník napsal, odpovědi, smlouva o dílo a faktury. Odkaz chodí e-mailem po odeslání poptávky.
 *   &contract=ID / &invoice=ID  → zobrazí dokument k tisku (jen pokud patří k této poptávce)
 */
private_page();
$title  = t('track.title_generic');
$id     = (int) ($_GET['id'] ?? 0);
$token  = (string) ($_GET['t'] ?? '');
$valid  = $id && track_token_ok($id, $token);
$inq    = $valid ? q("SELECT * FROM inquiries WHERE id = ?", [$id])->fetch() : null;
$valid  = (bool) $inq;
if (!$valid) usleep(300000);                                       // zpomalit hádání odkazů
$self   = route_url('track') . '?id=' . $id . '&t=' . rawurlencode($token);

if ($valid && isset($_GET['photo'])) {
    // fotka, kterou zákazník k poptávce přiložil
    $ph = q("SELECT * FROM inquiry_files WHERE id = ? AND inquiry_id = ?", [(int) $_GET['photo'], $id])->fetch();
    if ($ph) inquiry_send_photo($ph);
    http_response_code(404);
    exit;
}

if ($valid && (isset($_GET['contract']) || isset($_GET['invoice']))) {
    // dokument k tisku – jen smlouva/faktura patřící k této poptávce
    $isContract = isset($_GET['contract']);
    $docId = (int) ($isContract ? $_GET['contract'] : $_GET['invoice']);
    $owner = $isContract
        ? q("SELECT inquiry_id FROM contracts WHERE id = ?", [$docId])->fetchColumn()
        : q("SELECT COALESCE(i.inquiry_id, c.inquiry_id) FROM invoices i LEFT JOIN contracts c ON c.id = i.contract_id WHERE i.id = ?", [$docId])->fetchColumn();
    if ((int) $owner === $id) {
        ob_end_clean();                                            // celá stránka dokumentu místo šablony webu
        define('PUBLIC_DOC', $self);
        $_GET = ['id' => (string) $docId];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        require ROOT_DIR . '/admin/' . ($isContract ? 'contract.php' : 'invoice.php');
        exit;
    }
}

if ($valid) {
    $title     = t('track.title', ['id' => $id]);
    $data      = json_decode((string) $inq['data'], true) ?: [];
    $msgs      = q("SELECT * FROM inquiry_messages WHERE inquiry_id = ? ORDER BY created_at, id", [$id])->fetchAll();
    $pics      = inquiry_photos($id);
    $contracts = q("SELECT id, number, created_at FROM contracts WHERE inquiry_id = ? ORDER BY id", [$id])->fetchAll();
    $invoices  = q("SELECT i.id, i.number, i.total, i.due, i.paid_at, i.data FROM invoices i LEFT JOIN contracts c ON c.id = i.contract_id
                    WHERE i.inquiry_id = ? OR c.inquiry_id = ? ORDER BY i.id", [$id, $id])->fetchAll();
    $steps     = ['new', 'in_progress', 'quoted', 'done'];
    $stepIdx   = array_search($inq['status'], $steps, true);
}
$fmtDate = fn(string $d) => date(lang() === 'en' ? 'j M Y' : 'j. n. Y', strtotime($d));
?>
<section class="page-head">
  <div class="container">
    <h1><?= e($title) ?></h1>
    <?php if ($valid): ?><p class="lead"><?= te('track.lead', ['date' => $fmtDate((string) $inq['created_at'])]) ?></p><?php endif; ?>
  </div>
</section>

<section class="section section--tight">
  <div class="container track">
  <?php if (!$valid): ?>
    <div class="alert alert--err" role="alert"><?= te('track.invalid') ?></div>
    <p><a href="<?= e(route_url('contact')) ?>" class="btn"><?= te('nav.contact') ?></a></p>
  <?php else: ?>

    <div class="panel track__status">
      <h2><?= te('track.status') ?></h2>
      <?php if ($inq['status'] === 'cancelled'): ?>
        <p class="status status--cancelled"><?= e(status_label('cancelled')) ?></p>
      <?php else: ?>
        <ol class="track-steps">
          <?php foreach ($steps as $i => $st): $state = $stepIdx === false ? '' : ($i < $stepIdx ? 'is-done' : ($i === $stepIdx ? 'is-current' : '')); ?>
            <li class="<?= $state ?>"<?= $state === 'is-current' ? ' aria-current="step"' : '' ?>><span><?= e(status_label($st)) ?></span></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>

    <div class="split split--top">
      <div class="panel">
        <h2><?= te('track.documents') ?></h2>
        <?php if (!$contracts && !$invoices): ?><p class="muted"><?= te('track.no_documents') ?></p><?php endif; ?>
        <ul class="track-docs">
          <?php foreach ($contracts as $c): ?>
            <li><span><strong><?= te('track.contract') ?></strong> <?= e($c['number']) ?></span>
              <a href="<?= e($self . '&contract=' . $c['id']) ?>" class="link-arrow"><?= te('track.open') ?></a></li>
          <?php endforeach; ?>
          <?php foreach ($invoices as $iv): $dep = str_contains((string) $iv['data'], '"kind":"deposit"'); ?>
            <li><span><strong><?= te($dep ? 'track.invoice_deposit' : 'track.invoice') ?></strong> <?= e($iv['number']) ?>
                · <?= e(number_format((float) $iv['total'], 2, ',', ' ')) ?> Kč
                · <?= $iv['paid_at'] ? '<span class="badge">' . te('track.paid') . '</span>' : te('track.due', ['date' => $fmtDate((string) $iv['due'])]) ?></span>
              <a href="<?= e($self . '&invoice=' . $iv['id']) ?>" class="link-arrow"><?= te('track.open') ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="panel">
        <h2><?= te('track.details') ?></h2>
        <dl class="kv">
          <?php foreach ($data as $k => $d): if (($d['value'] ?? '') === '') continue; ?>
            <dt><?= e((string) ($d['label'] ?? $k)) ?></dt><dd><?= nl2br(e((string) $d['value'])) ?></dd>
          <?php endforeach; ?>
        </dl>
        <?php if ($pics): ?>
          <h3 class="track__sub"><?= te('track.photos') ?></h3>
          <ul class="track-photos">
            <?php foreach ($pics as $ph): $src = $self . '&photo=' . $ph['id']; ?>
              <li><a href="<?= e($src) ?>" target="_blank"><img src="<?= e($src) ?>" alt="<?= e($ph['original']) ?>" loading="lazy"></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($msgs): ?>
      <div class="panel">
        <h2><?= te('track.messages') ?></h2>
        <ol class="thread">
          <?php foreach ($msgs as $m): ?>
            <li class="msg msg--<?= e($m['author']) ?>">
              <div class="msg__head"><strong><?= $m['author'] === 'admin' ? e(site('owner')) : e($inq['name']) ?></strong> <span class="muted"><?= e($fmtDate((string) $m['created_at'])) ?></span></div>
              <p><?= nl2br(e($m['body'])) ?></p>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endif; ?>

    <p class="muted track__help"><?= te('track.help') ?> <a href="<?= e(tel_href(site('phone'))) ?>"><?= e(site('phone')) ?></a></p>
  <?php endif; ?>
  </div>
</section>
