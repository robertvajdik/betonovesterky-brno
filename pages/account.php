<?php
if (!accounts_on()) redirect(route_url('contact'));
private_page();
$self = route_url('account');

// Potvrzení e-mailu z odkazu (funguje i bez přihlášení)
if (isset($_GET['verify'], $_GET['u'])) {
    $r = q("SELECT * FROM customers WHERE id = ? AND verify_hash IS NOT NULL", [(int) $_GET['u']])->fetch();
    if ($r && hash_equals($r['verify_hash'], hash('sha256', (string) $_GET['verify']))) {
        q("UPDATE customers SET email_verified_at = NOW(), verify_hash = NULL WHERE id = ?", [$r['id']]);
        // dřívější poptávky odeslané bez účtu z ověřeného e-mailu připojíme k účtu
        $n = q("UPDATE inquiries SET customer_id = ? WHERE customer_id IS NULL AND email = ?", [$r['id'], $r['email']])->rowCount();
        flash('acc', t('acc.verified', ['count' => $n]));
    } else {
        flash('acc', t('acc.reset_invalid'));
    }
    redirect(customer() ? $self : route_url('login'));
}

$c    = require_customer();
$view = (string) ($_GET['view'] ?? '');
$id   = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = t('form.err_csrf');
    } else {
        switch ($_POST['do'] ?? '') {
            case 'logout':
                customer_logout();
                redirect(route_url('home'));

            case 'message':
                $iid  = (int) ($_POST['inquiry'] ?? 0);
                $body = post('body', 5000);
                $own  = q("SELECT id FROM inquiries WHERE id = ? AND customer_id = ?", [$iid, $c['id']])->fetch();
                if ($own && $body !== '') {
                    q("INSERT INTO inquiry_messages (inquiry_id, author, body) VALUES (?, 'customer', ?)", [$iid, $body]);
                    q("UPDATE inquiries SET is_read = 0, updated_at = NOW() WHERE id = ?", [$iid]);
                    global $CONFIG;
                    if (!empty($CONFIG['notify_email'])) {
                        send_mail($CONFIG['notify_email'], "Nová zpráva k poptávce č. {$iid}",
                            "{$c['name']} <{$c['email']}> napsal(a):\n\n{$body}\n\nAdministrace: " . abs_url(url('admin/')), $c['email']);
                    }
                    flash('acc', t('acc.msg_sent'));
                }
                redirect($self . '?id=' . $iid . '#zpravy');

            case 'profile':
                $name  = post('name', 120);
                $email = mb_strtolower(post('email', 160));
                if ($name === '') $errors[] = t('acc.err_name');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('acc.err_email');
                if (!$errors && $email !== $c['email'] && q("SELECT id FROM customers WHERE email = ? AND id <> ?", [$email, $c['id']])->fetch()) {
                    $errors[] = t('acc.err_exists');
                }
                if (!$errors) {
                    $changed = $email !== $c['email'];
                    q("UPDATE customers SET name = ?, email = ?, phone = ?, location = ?, lang = ?" . ($changed ? ", email_verified_at = NULL" : '') . " WHERE id = ?",
                      [$name, $email, (post('phone', 40) ?: null), (post('location', 160) ?: null), in_array($_POST['lang'] ?? '', LANGS, true) ? $_POST['lang'] : lang(), $c['id']]);
                    if ($changed) send_verify_mail(customer(true));
                    flash('acc', t('acc.saved'));
                    redirect($self . '?view=profile');
                }
                $view = 'profile';
                break;

            case 'password':
                if (!password_verify((string) ($_POST['current'] ?? ''), $c['password_hash'])) {
                    $errors[] = t('acc.err_current');
                } elseif (mb_strlen((string) ($_POST['password'] ?? '')) < 8) {
                    $errors[] = t('acc.err_password');
                } else {
                    q("UPDATE customers SET password_hash = ? WHERE id = ?", [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $c['id']]);
                    session_regenerate_id(true);
                    flash('acc', t('acc.password_changed'));
                    redirect($self . '?view=profile');
                }
                $view = 'profile';
                break;

            case 'resend':
                send_verify_mail($c);
                flash('acc', t('acc.verify_sent'));
                redirect($self);

            case 'delete':
                if (!password_verify((string) ($_POST['current'] ?? ''), $c['password_hash'])) {
                    $errors[] = t('acc.err_current');
                    $view = 'profile';
                    break;
                }
                q("DELETE FROM customers WHERE id = ?", [$c['id']]);       // poptávky zůstanou (customer_id = NULL)
                customer_logout();
                flash('acc', t('acc.deleted'));
                redirect(route_url('login'));
        }
    }
}


$title = t('acc.title');
$ok    = flash('acc');
$inq   = null;
if ($id) {
    $inq = q("SELECT * FROM inquiries WHERE id = ? AND customer_id = ?", [$id, $c['id']])->fetch() ?: null;
    if ($inq) {
        q("UPDATE inquiry_messages SET is_read = 1 WHERE inquiry_id = ? AND author = 'admin'", [$id]);
        $title = t('acc.request', ['id' => $id]);
    }
}
$fmt  = fn(string $d) => date(lang() === 'en' ? 'j M Y, H:i' : 'j. n. Y H:i', strtotime($d));
$tabs = ['' => t('acc.tab_requests'), 'profile' => t('acc.tab_profile')];
$crumbs = $inq ? [[t('acc.title'), $self], [$title, null]] : [[$title, null]];
?>
<section class="page-head page-head--account">
  <div class="container">
    <?= breadcrumbs($crumbs) ?>
    <div class="account-head">
      <div>
        <h1><?= e($title) ?></h1>
        <p class="lead"><?= te('acc.hello', ['name' => $c['name']]) ?></p>
      </div>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="logout">
        <button class="btn btn--ghost btn--small" type="submit"><?= icon('logout', 16) ?> <?= te('acc.logout') ?></button></form>
    </div>
    <nav class="acc-tabs" aria-label="<?= te('acc.title') ?>">
      <?php foreach ($tabs as $k => $label): $active = !$inq && $view === $k; ?>
        <a href="<?= e($self . ($k ? '?view=' . $k : '')) ?>"<?= $active ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(route_url('contact')) ?>#formular" class="acc-tabs__new"><?= icon('plus', 16) ?> <?= te('acc.new_request') ?></a>
    </nav>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <?php if ($ok): ?><div class="alert alert--ok" role="status"><?= e($ok) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <?php if (!$c['email_verified_at']): ?>
      <div class="alert alert--info">
        <?= te('acc.verify_notice') ?>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="resend">
          <button class="link-btn" type="submit"><?= te('acc.verify_resend') ?></button></form>
      </div>
    <?php endif; ?>

    <?php if ($inq): /* ---------- Detail poptávky ---------- */
        $data = json_decode((string) $inq['data'], true) ?: [];
        $msgs = q("SELECT * FROM inquiry_messages WHERE inquiry_id = ? ORDER BY created_at, id", [$inq['id']])->fetchAll(); ?>
      <div class="split split--top request">
        <div class="panel">
          <p class="request__meta"><span class="status status--<?= e($inq['status']) ?>"><?= e(status_label($inq['status'])) ?></span>
            <span class="muted"><?= te('acc.sent', ['date' => $fmt($inq['created_at'])]) ?></span></p>
          <dl class="kv">
            <?php foreach ($data as $k => $d): if (($d['value'] ?? '') === '') continue;
                $f = array_values(array_filter(form_fields(), fn($x) => $x['fkey'] === $k))[0] ?? null; ?>
              <dt><?= e($f ? tr($f, 'label') : ($d['label'] ?? $k)) ?></dt><dd><?= nl2br(e((string) $d['value'])) ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
        <div id="zpravy">
          <h2><?= te('acc.thread') ?></h2>
          <?php if (!$msgs): ?><p class="muted"><?= te('acc.no_messages') ?></p><?php endif; ?>
          <ol class="thread">
            <?php foreach ($msgs as $m): ?>
              <li class="msg msg--<?= e($m['author']) ?>">
                <div class="msg__head"><strong><?= $m['author'] === 'admin' ? e(site('owner') ?: site('name')) : te('acc.you') ?></strong>
                  <time datetime="<?= e(date('c', strtotime($m['created_at']))) ?>"><?= e($fmt($m['created_at'])) ?></time></div>
                <p><?= nl2br(e($m['body'])) ?></p>
              </li>
            <?php endforeach; ?>
          </ol>
          <?php if (!in_array($inq['status'], ['done', 'cancelled'], true)): ?>
            <form method="post" class="form">
              <?= csrf_field() ?><input type="hidden" name="do" value="message"><input type="hidden" name="inquiry" value="<?= (int) $inq['id'] ?>">
              <label><span class="field__label"><?= te('acc.msg_label') ?></span><textarea name="body" rows="4" required maxlength="5000"></textarea></label>
              <button class="btn" type="submit"><?= te('acc.msg_btn') ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <p><a href="<?= e($self) ?>" class="link-arrow"><?= te('acc.back') ?></a></p>

    <?php elseif ($view === 'profile'): /* ---------- Profil ---------- */ ?>
      <div class="split split--top">
        <form method="post" class="form panel">
          <h2><?= te('acc.profile_title') ?></h2>
          <?= csrf_field() ?><input type="hidden" name="do" value="profile">
          <label><span class="field__label"><?= te('acc.name') ?></span><input type="text" name="name" required autocomplete="name" value="<?= e($c['name']) ?>"></label>
          <label><span class="field__label"><?= te('acc.email') ?></span><input type="email" name="email" required autocomplete="email" value="<?= e($c['email']) ?>"></label>
          <label><span class="field__label"><?= te('acc.phone') ?></span><input type="tel" name="phone" autocomplete="tel" value="<?= e($c['phone']) ?>"></label>
          <label><span class="field__label"><?= te('acc.location') ?></span><input type="text" name="location" autocomplete="street-address" value="<?= e($c['location']) ?>"></label>
          <label><span class="field__label"><?= te('acc.lang') ?></span>
            <select name="lang"><option value="cs"<?= $c['lang'] === 'cs' ? ' selected' : '' ?>>Čeština</option><option value="en"<?= $c['lang'] === 'en' ? ' selected' : '' ?>>English</option></select></label>
          <button class="btn" type="submit"><?= te('acc.save') ?></button>
        </form>
        <div>
          <form method="post" class="form panel">
            <h2><?= te('acc.password_title') ?></h2>
            <?= csrf_field() ?><input type="hidden" name="do" value="password">
            <input type="email" value="<?= e($c['email']) ?>" autocomplete="username" hidden>
            <label><span class="field__label"><?= te('acc.password_current') ?></span><input type="password" name="current" required autocomplete="current-password"></label>
            <label><span class="field__label"><?= te('acc.password_new') ?></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <button class="btn" type="submit"><?= te('acc.save') ?></button>
          </form>
          <details class="panel danger-zone">
            <summary><?= te('acc.delete_title') ?></summary>
            <form method="post" class="form">
              <p class="muted"><?= te('acc.delete_text') ?></p>
              <?= csrf_field() ?><input type="hidden" name="do" value="delete">
              <label><span class="field__label"><?= te('acc.password_current') ?></span><input type="password" name="current" required autocomplete="current-password"></label>
              <button class="btn btn--danger" type="submit"><?= te('acc.delete_btn') ?></button>
            </form>
          </details>
        </div>
      </div>

    <?php else: /* ---------- Seznam poptávek ---------- */
        $rows = q("SELECT i.*, (SELECT COUNT(*) FROM inquiry_messages m WHERE m.inquiry_id = i.id AND m.author = 'admin' AND m.is_read = 0) AS unread,
                          (SELECT COUNT(*) FROM inquiry_messages m WHERE m.inquiry_id = i.id) AS msgs
                   FROM inquiries i WHERE i.customer_id = ? ORDER BY COALESCE(i.updated_at, i.created_at) DESC", [$c['id']])->fetchAll(); ?>
      <?php if (!$rows): ?>
        <div class="panel empty">
          <p><?= te('acc.requests_empty') ?></p>
          <a href="<?= e(route_url('contact')) ?>#formular" class="btn"><?= te('acc.new_request') ?></a>
        </div>
      <?php else: ?>
        <ul class="req-list">
          <?php foreach ($rows as $r): $data = json_decode((string) $r['data'], true) ?: []; $svc = $data['service']['value'] ?? ''; ?>
            <li>
              <a href="<?= e($self . '?id=' . $r['id']) ?>" class="req-card<?= $r['unread'] ? ' req-card--unread' : '' ?>">
                <span class="req-card__main">
                  <strong><?= te('acc.request', ['id' => $r['id']]) ?><?= $svc !== '' ? ' · ' . e($svc) : '' ?></strong>
                  <span class="muted"><?= e(mb_strimwidth((string) ($r['message'] ?? ''), 0, 120, '…')) ?></span>
                </span>
                <span class="req-card__side">
                  <span class="status status--<?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span>
                  <span class="muted"><?= e($fmt($r['created_at'])) ?><?= $r['msgs'] ? ' · ' . (int) $r['msgs'] . ' ✉' : '' ?></span>
                  <?php if ($r['unread']): ?><span class="badge-new"><?= te('acc.unread') ?></span><?php endif; ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
