<?php
if (!accounts_on()) redirect(route_url('contact'));
private_page();
if (customer()) redirect(safe_next($_GET['next'] ?? null));

$title  = t('acc.login_title');
$errors = [];
$next   = (string) ($_POST['next'] ?? $_GET['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = t('form.err_csrf');
    } elseif (login_throttled()) {
        $errors[] = t('acc.err_throttle');
    } elseif (!recaptcha_ok('login')) {
        $errors[] = t('form.err_captcha');
    } else {
        $c = q("SELECT * FROM customers WHERE email = ? AND is_active = 1", [mb_strtolower(post('email', 160))])->fetch();
        if ($c && password_verify((string) ($_POST['password'] ?? ''), $c['password_hash'])) {
            if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
                q("UPDATE customers SET password_hash = ? WHERE id = ?", [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $c['id']]);
            }
            customer_login((int) $c['id']);
            redirect(safe_next($next));
        }
        login_failed();
        $errors[] = t('acc.err_login');
    }
}
$ok = flash('acc');
?>
<section class="page-head">
  <div class="container"><?= breadcrumbs([[$title, null]]) ?><h1><?= e($title) ?></h1><p class="lead"><?= te('acc.login_lead') ?></p></div>
</section>

<section class="section section--tight">
  <div class="container auth">
    <form method="post" class="form panel"<?= recaptcha_attrs('login') ?>>
      <?php if ($ok): ?><div class="alert alert--ok" role="status"><?= e($ok) ?></div><?php endif; ?>
      <?php if ($errors): ?><div class="alert alert--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <label><span class="field__label"><?= te('acc.email') ?></span><input type="email" name="email" required autocomplete="email" value="<?= e(post('email')) ?>"></label>
      <label><span class="field__label"><?= te('acc.password') ?></span><input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn" type="submit"><?= te('acc.login_btn') ?></button>
      <?= recaptcha_note() ?>
      <p class="auth__links">
        <a href="<?= e(route_url('forgot')) ?>"><?= te('acc.forgot_link') ?></a><br>
        <?= te('acc.no_account') ?> <a href="<?= e(route_url('register') . ($next !== '' ? '?next=' . rawurlencode($next) : '')) ?>"><?= te('acc.register_link') ?></a>
      </p>
    </form>
  </div>
</section>
