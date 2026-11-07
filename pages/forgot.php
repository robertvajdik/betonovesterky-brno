<?php
if (!accounts_on()) redirect(route_url('contact'));
private_page();

$token  = (string) ($_GET['token'] ?? '');
$uid    = (int) ($_GET['u'] ?? 0);
$errors = [];
$sent   = false;

// Krok 2: odkaz z e-mailu → nastavení nového hesla
$resetRow = null;
if ($token !== '' && $uid) {
    $r = q("SELECT * FROM customers WHERE id = ? AND is_active = 1 AND reset_hash IS NOT NULL AND reset_expires > NOW()", [$uid])->fetch();
    if ($r && hash_equals($r['reset_hash'], hash('sha256', $token))) $resetRow = $r;
}
$title = t($token !== '' ? 'acc.reset_title' : 'acc.forgot_title');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = t('form.err_csrf');
    } elseif ($resetRow) {
        $pass = (string) ($_POST['password'] ?? '');
        if (mb_strlen($pass) < 8) {
            $errors[] = t('acc.err_password');
        } else {
            q("UPDATE customers SET password_hash = ?, reset_hash = NULL, reset_expires = NULL, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?",
              [password_hash($pass, PASSWORD_DEFAULT), $resetRow['id']]);
            customer_login((int) $resetRow['id']);
            flash('acc', t('acc.reset_done'));
            redirect(route_url('account'));
        }
    } elseif ($token === '') {
        // Krok 1: žádost o odkaz. Odpověď je vždy stejná – neprozradí, zda účet existuje.
        $email = mb_strtolower(post('email', 160));
        // max. 1 e-mail za minutu (čas počítá databáze, ať nevadí jiné časové pásmo PHP)
        $c = filter_var($email, FILTER_VALIDATE_EMAIL) ? q("SELECT * FROM customers WHERE email = ? AND is_active = 1
              AND (reset_expires IS NULL OR reset_expires < DATE_ADD(NOW(), INTERVAL 59 MINUTE))", [$email])->fetch() : null;
        if ($c) {
            [$t, $hash] = new_token();
            q("UPDATE customers SET reset_hash = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?", [$hash, $c['id']]);
            $url = abs_url(route_url('forgot', $c['lang'])) . '?token=' . $t . '&u=' . $c['id'];
            send_customer_mail($c['email'], $c['lang'], 'reset', ['url' => $url, 'name' => $c['name']]);
        }
        usleep(300000);
        $sent = true;
    }
}
?>
<section class="page-head">
  <div class="container"><?= breadcrumbs([[t('acc.login_title'), route_url('login')], [$title, null]]) ?><h1><?= e($title) ?></h1>
    <?php if ($token === ''): ?><p class="lead"><?= te('acc.forgot_lead') ?></p><?php endif; ?></div>
</section>

<section class="section section--tight">
  <div class="container auth">
    <form method="post" class="form panel">
      <?php if ($errors): ?><div class="alert alert--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <?php if ($token !== '' && !$resetRow): ?>
        <div class="alert alert--err" role="alert"><?= te('acc.reset_invalid') ?></div>
        <a href="<?= e(route_url('forgot')) ?>" class="btn"><?= te('acc.forgot_btn') ?></a>
      <?php elseif ($resetRow): ?>
        <input type="email" name="email" value="<?= e($resetRow['email']) ?>" autocomplete="username" hidden>
        <label><span class="field__label"><?= te('acc.password_new') ?></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <button class="btn" type="submit"><?= te('acc.reset_btn') ?></button>
      <?php elseif ($sent): ?>
        <div class="alert alert--ok" role="status"><?= te('acc.forgot_sent') ?></div>
        <p class="auth__links"><a href="<?= e(route_url('login')) ?>"><?= te('acc.login_link') ?></a></p>
      <?php else: ?>
        <label><span class="field__label"><?= te('acc.email') ?></span><input type="email" name="email" required autocomplete="email"></label>
        <button class="btn" type="submit"><?= te('acc.forgot_btn') ?></button>
        <p class="auth__links"><a href="<?= e(route_url('login')) ?>"><?= te('acc.login_link') ?></a></p>
      <?php endif; ?>
    </form>
  </div>
</section>
