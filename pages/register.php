<?php
if (!accounts_on()) redirect(route_url('contact'));
private_page();
if (customer()) redirect(route_url('account'));

$title  = t('acc.register_title');
$errors = [];
$next   = (string) ($_POST['next'] ?? $_GET['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['website'])) redirect(route_url('home'));            // honeypot
    if (!csrf_ok()) $errors[] = t('form.err_csrf');
    elseif (!recaptcha_ok('register')) $errors[] = t('form.err_captcha');
    $name  = post('name', 120);
    $email = mb_strtolower(post('email', 160));
    $phone = post('phone', 40);
    $pass  = (string) ($_POST['password'] ?? '');
    if ($name === '') $errors[] = t('acc.err_name');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('acc.err_email');
    if (mb_strlen($pass) < 8) $errors[] = t('acc.err_password');
    if (empty($_POST['gdpr'])) $errors[] = t('form.err_gdpr');
    if (!$errors && q("SELECT id FROM customers WHERE email = ?", [$email])->fetch()) $errors[] = t('acc.err_exists');

    if (!$errors) {
        q("INSERT INTO customers (email, password_hash, name, phone, lang) VALUES (?,?,?,?,?)",
          [$email, password_hash($pass, PASSWORD_DEFAULT), $name, $phone ?: null, lang()]);
        $id = (int) db()->lastInsertId();
        customer_login($id);
        send_verify_mail(q("SELECT * FROM customers WHERE id = ?", [$id])->fetch());
        flash('acc', t('acc.welcome'));
        redirect(safe_next($next));
    }
}
?>
<section class="page-head">
  <div class="container"><?= breadcrumbs([[$title, null]]) ?><h1><?= e($title) ?></h1><p class="lead"><?= te('acc.register_lead') ?></p></div>
</section>

<section class="section section--tight">
  <div class="container auth">
    <form method="post" class="form panel"<?= recaptcha_attrs('register') ?>>
      <?php if ($errors): ?><div class="alert alert--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="hp" aria-hidden="true"><label>Web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <label><span class="field__label"><?= te('acc.name') ?> <span class="req" aria-hidden="true">*</span></span><input type="text" name="name" required autocomplete="name" value="<?= e(post('name')) ?>"></label>
      <label><span class="field__label"><?= te('acc.email') ?> <span class="req" aria-hidden="true">*</span></span><input type="email" name="email" required autocomplete="email" value="<?= e(post('email')) ?>"></label>
      <label><span class="field__label"><?= te('acc.phone') ?></span><input type="tel" name="phone" autocomplete="tel" value="<?= e(post('phone')) ?>"></label>
      <label><span class="field__label"><?= te('acc.password_new') ?> <span class="req" aria-hidden="true">*</span></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label class="check">
        <input type="checkbox" name="gdpr" value="1" required <?= !empty($_POST['gdpr']) ? 'checked' : '' ?>>
        <span><?= te('acc.gdpr') ?> (<a href="<?= e(privacy_url()) ?>" target="_blank" rel="noopener"><?= te('form.gdpr_more') ?></a>).</span>
      </label>
      <button class="btn" type="submit"><?= te('acc.register_btn') ?></button>
      <?= recaptcha_note() ?>
      <p class="auth__links"><?= te('acc.have_account') ?> <a href="<?= e(route_url('login')) ?>"><?= te('acc.login_link') ?></a></p>
    </form>
  </div>
</section>
