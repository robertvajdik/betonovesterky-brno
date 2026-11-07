<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

function admin_url(string $q = ''): string { return url('admin/') . ($q !== '' ? '?' . $q : ''); }
function back(string $q = '', ?string $msg = null): never
{
    if ($msg) flash('admin', $msg);
    header('Location: ' . admin_url($q), true, 303);
    exit;
}
/** Uloží nahraný obrázek do /uploads, vrátí název souboru nebo null */
function store_upload(string $tmp, int $size, int $error, bool $allowIco = false): ?string
{
    if ($error !== UPLOAD_ERR_OK || $size > 15 * 1024 * 1024 || !is_uploaded_file($tmp)) return null;
    $info = @getimagesize($tmp);
    $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif', IMAGETYPE_ICO => 'ico'][$info[2] ?? 0] ?? null;
    if ($ext === null && $allowIco && str_starts_with((string) file_get_contents($tmp, false, null, 0, 4), "\0\0\1\0")) $ext = 'ico';
    if ($ext === null) return null;
    $fn = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    return move_uploaded_file($tmp, UPLOAD_DIR . '/' . $fn) ? $fn : null;
}
function after_content_change(): void
{
    props(true);
    if (prop_bool('seo.sitemap_auto') && is_file(ROOT_DIR . '/sitemap.xml')) write_seo_files();
}
function nn(string $v): ?string { return $v === '' ? null : $v; }

$hasUsers = (int) q("SELECT COUNT(*) FROM admin_users")->fetchColumn() > 0;
$action   = $_POST['action'] ?? '';

// ---------- Přihlášení / první účet ----------
if ($action === 'setup' && !$hasUsers && csrf_ok()) {
    $u = post('username', 60); $p = (string) ($_POST['password'] ?? '');
    if ($u !== '' && strlen($p) >= 8) {
        q("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)", [$u, password_hash($p, PASSWORD_DEFAULT)]);
        back('', 'Účet vytvořen, přihlaste se.');
    }
    back('', 'Zadejte jméno a heslo (min. 8 znaků).');
}
if ($action === 'login' && csrf_ok()) {
    $row = q("SELECT * FROM admin_users WHERE username = ?", [post('username', 60)])->fetch();
    if ($row && password_verify((string) ($_POST['password'] ?? ''), $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $row['id'];
        back();
    }
    usleep(500000);
    back('', 'Nesprávné jméno nebo heslo.');
}
if (($_GET['logout'] ?? '') === '1') {
    unset($_SESSION['admin_id']);
    back();
}

$logged = !empty($_SESSION['admin_id']);
$tab    = (string) ($_GET['tab'] ?? 'inquiries');

// ---------- Akce po přihlášení ----------
if ($logged && $action !== '') {
    if (!csrf_ok()) back("tab=$tab", 'Platnost formuláře vypršela.');
    $id = (int) ($_POST['id'] ?? 0);

    switch ($action) {
        // Poptávky
        case 'inquiry_read':
            q("UPDATE inquiries SET is_read = 1 - is_read WHERE id = ?", [$id]); back('tab=inquiries');
        case 'inquiry_delete':
            q("DELETE FROM inquiries WHERE id = ?", [$id]); back('tab=inquiries', 'Poptávka smazána.');
        case 'inquiry_reply':
            // odpověď zákazníkovi (+ změna stavu); registrovaný ji uvidí v účtu, všem přijde e-mailem
            $inq = q("SELECT i.*, c.email AS c_email, c.lang AS c_lang FROM inquiries i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ?", [$id])->fetch();
            if (!$inq) back('tab=inquiries');
            $status = in_array($_POST['status'] ?? '', INQUIRY_STATUSES, true) ? $_POST['status'] : $inq['status'];
            $body   = post('body', 10000);
            q("UPDATE inquiries SET status = ?, is_read = 1, updated_at = NOW() WHERE id = ?", [$status, $id]);
            q("UPDATE inquiry_messages SET is_read = 1 WHERE inquiry_id = ? AND author = 'customer'", [$id]);
            $note = 'Stav uložen.';
            if ($body !== '') {
                q("INSERT INTO inquiry_messages (inquiry_id, author, body) VALUES (?, 'admin', ?)", [$id, $body]);
                $to   = $inq['c_email'] ?: $inq['email'];
                $lang = $inq['c_lang'] ?: $inq['lang'];
                $vars = ['id' => $id, 'message' => $body, 'status' => '', 'url' => abs_url(route_url('account', $lang)) . '?id=' . $id];
                $sent = !empty($_POST['notify']) && send_customer_mail($to, $lang, $inq['customer_id'] ? 'reply' : 'reply_guest', $vars);
                $note = 'Odpověď uložena' . ($sent ? ' a odeslána e-mailem na ' . $to : (!empty($_POST['notify']) ? ' – e-mail se nepodařilo odeslat' : '')) . '.';
            }
            back('tab=inquiries#q' . $id, $note);

        // Zákazníci
        case 'customer_toggle':
            q("UPDATE customers SET is_active = 1 - is_active WHERE id = ?", [$id]); back('tab=customers');
        case 'customer_delete':
            q("DELETE FROM customers WHERE id = ?", [$id]); back('tab=customers', 'Účet smazán, poptávky zůstaly zachovány.');

        // Hodnocení
        case 'review_toggle':
            q("UPDATE reviews SET is_approved = 1 - is_approved WHERE id = ?", [$id]); back('tab=reviews');
        case 'review_delete':
            q("DELETE FROM reviews WHERE id = ?", [$id]); back('tab=reviews', 'Hodnocení smazáno.');

        // Galerie
        case 'upload':
            [$kind, $tid] = array_pad(explode(':', (string) ($_POST['target'] ?? ''), 2), 2, '0');
            $catId  = $kind === 'c' ? (int) $tid : null;
            $projId = $kind === 'p' ? (int) $tid : null;
            $count  = 0;
            $files  = $_FILES['images'] ?? null;
            if ($files && is_array($files['name'])) {
                foreach ($files['name'] as $i => $_) {
                    $fn = store_upload($files['tmp_name'][$i], (int) $files['size'][$i], (int) $files['error'][$i]);
                    if (!$fn) continue;
                    q("INSERT INTO gallery_images (category_id, project_id, file, caption, caption_en, in_references) VALUES (?,?,?,?,?,?)",
                      [$catId, $projId, $fn, nn(post('caption', 200)), nn(post('caption_en', 200)), !empty($_POST['in_references']) ? 1 : 0]);
                    $count++;
                }
            }
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['target'] ?? '')), "Nahráno fotek: $count");
        case 'image_save':
            q("UPDATE gallery_images SET caption = ?, caption_en = ?, sort = ?, in_references = ? WHERE id = ?",
              [nn(post('caption', 200)), nn(post('caption_en', 200)), (int) ($_POST['sort'] ?? 0), !empty($_POST['in_references']) ? 1 : 0, $id]);
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['f'] ?? '')), 'Uloženo.');
        case 'image_delete':
            $im = q("SELECT file FROM gallery_images WHERE id = ?", [$id])->fetch();
            if ($im) {
                $used = (int) q("SELECT (SELECT COUNT(*) FROM categories WHERE cover_image = ?) + (SELECT COUNT(*) FROM projects WHERE cover_image = ?)", [$im['file'], $im['file']])->fetchColumn();
                q("DELETE FROM gallery_images WHERE id = ?", [$id]);
                $path = UPLOAD_DIR . '/' . $im['file'];
                if (!$used && is_file($path) && str_starts_with(realpath($path) ?: '', realpath(UPLOAD_DIR))) @unlink($path);
            }
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['f'] ?? '')), 'Fotka smazána.');
        case 'image_cover':
            $im = q("SELECT * FROM gallery_images WHERE id = ?", [$id])->fetch();
            if ($im && $im['category_id']) q("UPDATE categories SET cover_image = ? WHERE id = ?", [$im['file'], $im['category_id']]);
            if ($im && $im['project_id'])  q("UPDATE projects SET cover_image = ? WHERE id = ?", [$im['file'], $im['project_id']]);
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['f'] ?? '')), 'Úvodní fotka nastavena.');

        // Stránky
        case 'category_save':
            $slugEn = slugify(post('slug_en', 120));
            q("UPDATE categories SET slug_en=?, title=?, title_en=?, menu_title=?, menu_title_en=?, headline=?, headline_en=?, intro=?, intro_en=?, body=?, body_en=?, meta_desc=?, meta_desc_en=?, sort=?, is_active=? WHERE id=?", [
                nn($slugEn), post('title', 160), nn(post('title_en', 160)), nn(post('menu_title', 80)), nn(post('menu_title_en', 80)),
                nn(post('headline', 200)), nn(post('headline_en', 200)), nn(post('intro', 2000)), nn(post('intro_en', 2000)),
                nn(post('body', 100000)), nn(post('body_en', 100000)), nn(post('meta_desc', 300)), nn(post('meta_desc_en', 300)),
                (int) ($_POST['sort'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, $id,
            ]);
            after_content_change();
            back("tab=pages&edit=$id", 'Uloženo.');

        // Realizace
        case 'project_save':
            $slug   = slugify(post('slug', 160) ?: post('title', 200));
            $slugEn = slugify(post('slug_en', 160) ?: post('title_en', 200));
            $data = [$slug, nn($slugEn), post('title', 200), nn(post('title_en', 200)), nn(post('location', 120)),
                     nn(post('summary', 400)), nn(post('summary_en', 400)), nn(post('body', 100000)), nn(post('body_en', 100000)),
                     !empty($_POST['is_published']) ? 1 : 0];
            if ($id) {
                q("UPDATE projects SET slug=?, slug_en=?, title=?, title_en=?, location=?, summary=?, summary_en=?, body=?, body_en=?, is_published=? WHERE id=?", [...$data, $id]);
            } else {
                q("INSERT INTO projects (slug, slug_en, title, title_en, location, summary, summary_en, body, body_en, is_published) VALUES (?,?,?,?,?,?,?,?,?,?)", $data);
                $id = (int) db()->lastInsertId();
            }
            after_content_change();
            back("tab=projects&edit=$id", 'Realizace uložena.');
        case 'project_delete':
            q("DELETE FROM projects WHERE id = ?", [$id]); after_content_change(); back('tab=projects', 'Realizace smazána.');

        // Formulář
        case 'field_save':
            $types = ['text','email','tel','number','textarea','select','checkbox','date'];
            $type  = in_array($_POST['type'] ?? '', $types, true) ? $_POST['type'] : 'text';
            $fkey  = str_replace('-', '_', slugify(post('fkey', 40) ?: post('label_cs', 160)));
            if ($fkey === '') back('tab=form', 'Zadejte název pole.');
            $vals = [$fkey, $type, post('label_cs', 160), nn(post('label_en', 160)), nn(post('placeholder_cs', 200)), nn(post('placeholder_en', 200)),
                     nn(post('options_cs', 5000)), nn(post('options_en', 5000)), !empty($_POST['is_required']) ? 1 : 0,
                     !empty($_POST['is_wide']) ? 1 : 0, !empty($_POST['is_active']) ? 1 : 0, (int) ($_POST['sort'] ?? 0)];
            try {
                if ($id) {
                    q("UPDATE form_fields SET fkey=?, type=?, label_cs=?, label_en=?, placeholder_cs=?, placeholder_en=?, options_cs=?, options_en=?, is_required=?, is_wide=?, is_active=?, sort=? WHERE id=?", [...$vals, $id]);
                } else {
                    q("INSERT INTO form_fields (fkey, type, label_cs, label_en, placeholder_cs, placeholder_en, options_cs, options_en, is_required, is_wide, is_active, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", $vals);
                }
            } catch (PDOException $ex) {
                back('tab=form', 'Pole s tímto klíčem už existuje.');
            }
            back('tab=form', 'Pole uloženo.');
        case 'field_delete':
            q("DELETE FROM form_fields WHERE id = ?", [$id]); back('tab=form', 'Pole smazáno.');

        // Nastavení / vlastnosti
        case 'props_save':
            $all = props();
            foreach ((array) ($_POST['p'] ?? []) as $key => $v) {
                if (!isset($all[$key]) || !is_array($v)) continue;
                $type = $all[$key]['type'];
                if ($type === 'image') continue;
                $cs = $type === 'bool' ? (!empty($v['cs']) ? '1' : '0') : mb_substr((string) ($v['cs'] ?? ''), 0, 20000);
                $en = $type === 'bool' ? '' : mb_substr((string) ($v['en'] ?? ''), 0, 20000);
                q("UPDATE properties SET value_cs = ?, value_en = ? WHERE pkey = ?", [$cs, $en, $key]);
            }
            // bool checkboxy, které nepřišly = vypnuto
            foreach ($all as $key => $r) {
                if ($r['type'] === 'bool' && str_starts_with($key, (string) ($_POST['grp_prefix'] ?? '~')) && !isset($_POST['p'][$key])) {
                    q("UPDATE properties SET value_cs = '0' WHERE pkey = ?", [$key]);
                }
            }
            // nahrané obrázky
            foreach ($_FILES['img']['name'] ?? [] as $key => $_) {
                if (!isset($all[$key]) || $all[$key]['type'] !== 'image') continue;
                $fn = store_upload($_FILES['img']['tmp_name'][$key], (int) $_FILES['img']['size'][$key], (int) $_FILES['img']['error'][$key], true);
                if ($fn) q("UPDATE properties SET value_cs = ? WHERE pkey = ?", [$fn, $key]);
            }
            foreach ((array) ($_POST['img_remove'] ?? []) as $key => $on) {
                if ($on && isset($all[$key])) q("UPDATE properties SET value_cs = '' WHERE pkey = ?", [$key]);
            }
            after_content_change();
            back('tab=' . rawurlencode($tab) . (isset($_POST['grp']) ? '&grp=' . rawurlencode((string) $_POST['grp']) : ''), 'Nastavení uloženo.');
        case 'prop_add':
            $key = strtolower(preg_replace('~[^a-z0-9._-]~i', '', post('pkey', 80)));
            if ($key !== '' && !isset(props()[$key])) {
                q("INSERT INTO properties (pkey, grp, type, value_cs, value_en) VALUES (?, ?, ?, ?, ?)",
                  [$key, explode('.', $key)[0], in_array($_POST['type'] ?? '', ['line','text','html'], true) ? $_POST['type'] : 'line', post('value_cs', 20000), post('value_en', 20000)]);
            }
            back('tab=texts', 'Vlastnost přidána.');
        case 'seo_generate':
            props(true);
            $r = write_seo_files();
            back('tab=settings&grp=seo', ($r['robots.txt'] && $r['sitemap.xml']) ? 'robots.txt a sitemap.xml byly vygenerovány.' : 'Soubory se nepodařilo zapsat – zkontrolujte práva ke kořenové složce.');
        case 'seo_remove':
            foreach (['robots.txt', 'sitemap.xml'] as $f) if (is_file(ROOT_DIR . "/$f")) @unlink(ROOT_DIR . "/$f");
            back('tab=settings&grp=seo', 'Statické soubory odstraněny – web je nyní generuje dynamicky.');
    }
    back("tab=$tab");
}

$msg     = flash('admin');
$unread  = $logged ? (int) q("SELECT COUNT(*) FROM inquiries WHERE is_read = 0")->fetchColumn() : 0;
$pending = $logged ? (int) q("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn() : 0;

function btn_post(string $action, int $id, string $label, string $class = 'btn btn--small btn--ghost', string $confirm = '', array $extra = []): string
{
    $h = '<form method="post" class="inline"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>'
       . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">';
    foreach ($extra as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h . '<button class="' . e($class) . '">' . e($label) . '</button></form>';
}
/** Dvojice polí CZ / EN */
function bi(string $label, string $name, ?array $row, string $type = 'line', int $rows = 3): string
{
    $cs = (string) ($row[$name] ?? ''); $en = (string) ($row[$name . '_en'] ?? '');
    $in = function (string $n, string $v) use ($type, $rows) {
        return $type === 'line'
            ? '<input type="text" name="' . $n . '" value="' . e($v) . '">'
            : '<textarea name="' . $n . '" rows="' . $rows . '"' . ($type === 'html' ? ' class="code"' : '') . '>' . e($v) . '</textarea>';
    };
    return '<div class="bi"><div class="bi__label">' . e($label) . '</div>'
         . '<label><span class="flag">CZ</span>' . $in($name, $cs) . '</label>'
         . '<label><span class="flag">EN</span>' . $in($name . '_en', $en) . '</label></div>';
}

$tabs = [
    'inquiries' => 'Poptávky' . ($unread ? " ($unread)" : ''),
    'customers' => 'Zákazníci',
    'reviews'   => 'Hodnocení' . ($pending ? " ($pending)" : ''),
    'gallery'   => 'Galerie',
    'pages'     => 'Stránky',
    'projects'  => 'Realizace',
    'form'      => 'Formulář',
    'texts'     => 'Texty CZ/EN',
    'settings'  => 'Nastavení',
];
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Administrace | <?= e(site('name')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<?php if (!$logged): ?>
  <div class="admin-login panel">
    <h1><?= $hasUsers ? 'Přihlášení' : 'Vytvořit účet' ?></h1>
    <?php if ($msg): ?><div class="alert alert--err"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $hasUsers ? 'login' : 'setup' ?>">
      <label>Uživatelské jméno<input type="text" name="username" required autocomplete="username"></label>
      <label>Heslo<input type="password" name="password" required autocomplete="<?= $hasUsers ? 'current-password' : 'new-password' ?>"></label>
      <button class="btn"><?= $hasUsers ? 'Přihlásit' : 'Vytvořit' ?></button>
    </form>
  </div>
<?php else: ?>
  <header class="admin-bar">
    <strong><?= e(site('name')) ?></strong>
    <nav>
      <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= e(admin_url("tab=$k")) ?>" class="<?= $tab === $k ? 'is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url()) ?>" target="_blank">Web ↗</a>
      <a href="<?= e(admin_url('logout=1')) ?>">Odhlásit</a>
    </nav>
  </header>
  <main class="container admin-main">
    <?php if ($msg): ?><div class="alert alert--ok"><?= e($msg) ?></div><?php endif; ?>

    <?php /* ================= POPTÁVKY ================= */ if ($tab === 'inquiries'): ?>
      <h1>Poptávky</h1>
      <?php
        $sf   = in_array($_GET['status'] ?? '', INQUIRY_STATUSES, true) ? $_GET['status'] : '';
        $rows = q("SELECT i.*, c.name AS c_name, c.id AS c_id FROM inquiries i LEFT JOIN customers c ON c.id = i.customer_id"
                . ($sf ? " WHERE i.status = ?" : '') . " ORDER BY i.is_read, COALESCE(i.updated_at, i.created_at) DESC LIMIT 300", $sf ? [$sf] : [])->fetchAll();
        $stLabels = ['new' => 'Nová', 'in_progress' => 'Řešíme', 'quoted' => 'Nabídka odeslána', 'done' => 'Dokončeno', 'cancelled' => 'Zrušeno'];
      ?>
      <nav class="subtabs">
        <a href="<?= e(admin_url('tab=inquiries')) ?>" class="<?= $sf === '' ? 'is-active' : '' ?>">Vše</a>
        <?php foreach ($stLabels as $k => $v): ?><a href="<?= e(admin_url("tab=inquiries&status=$k")) ?>" class="<?= $sf === $k ? 'is-active' : '' ?>"><?= e($v) ?></a><?php endforeach; ?>
      </nav>
      <?php if (!$rows): ?><p class="muted">Zatím žádné poptávky.</p><?php endif; ?>
      <?php foreach ($rows as $r): $data = json_decode((string) $r['data'], true) ?: [];
            $msgs = q("SELECT * FROM inquiry_messages WHERE inquiry_id = ? ORDER BY created_at, id", [$r['id']])->fetchAll(); ?>
        <article class="item <?= $r['is_read'] ? '' : 'item--new' ?>" id="q<?= (int) $r['id'] ?>">
          <div class="item__head">
            <strong>#<?= (int) $r['id'] ?> <?= e($r['name']) ?></strong>
            <span class="status status--<?= e($r['status']) ?>"><?= e($stLabels[$r['status']] ?? $r['status']) ?></span>
            <?php if ($r['c_id']): ?><a href="<?= e(admin_url('tab=customers#c' . $r['c_id'])) ?>" class="badge">účet</a><?php endif; ?>
            <span class="muted"><?= e(date('j. n. Y H:i', strtotime($r['created_at']))) ?> · <?= e($r['source']) ?> · <?= strtoupper(e($r['lang'])) ?></span>
          </div>
          <p><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a>
            <?php if ($r['phone']): ?> · <a href="<?= e(tel_href($r['phone'])) ?>"><?= e($r['phone']) ?></a><?php endif; ?></p>
          <?php if ($data): ?>
            <dl class="kv">
              <?php foreach ($data as $k => $d): if (in_array($k, ['name', 'email', 'phone'], true) || ($d['value'] ?? '') === '') continue; ?>
                <dt><?= e($d['label'] ?? $k) ?></dt><dd><?= nl2br(e((string) $d['value'])) ?></dd>
              <?php endforeach; ?>
            </dl>
          <?php else: ?>
            <p><?= nl2br(e($r['message'])) ?></p>
          <?php endif; ?>
          <?php if ($msgs): ?>
            <ol class="thread thread--admin">
              <?php foreach ($msgs as $m): ?>
                <li class="msg msg--<?= e($m['author']) ?><?= $m['author'] === 'customer' && !$m['is_read'] ? ' msg--unread' : '' ?>">
                  <div class="msg__head"><strong><?= $m['author'] === 'admin' ? 'Vy' : e($r['name']) ?></strong> <span class="muted"><?= e(date('j. n. Y H:i', strtotime($m['created_at']))) ?></span></div>
                  <p><?= nl2br(e($m['body'])) ?></p>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
          <details class="reply"<?= !$r['is_read'] && $msgs ? ' open' : '' ?>>
            <summary>Odpovědět / změnit stav</summary>
            <form method="post" class="form">
              <?= csrf_field() ?><input type="hidden" name="action" value="inquiry_reply"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <label>Stav
                <select name="status"><?php foreach ($stLabels as $k => $v): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
              <label>Zpráva zákazníkovi (nepovinné)<textarea name="body" rows="4"></textarea></label>
              <label class="check"><input type="checkbox" name="notify" value="1" checked><span>Poslat zprávu také e-mailem na <?= e($r['email']) ?><?= $r['c_id'] ? ' (s odkazem do účtu)' : '' ?></span></label>
              <button class="btn btn--small">Uložit</button>
            </form>
          </details>
          <div class="item__actions">
            <?= btn_post('inquiry_read', (int) $r['id'], $r['is_read'] ? 'Označit jako nové' : 'Označit jako přečtené') ?>
            <?= btn_post('inquiry_delete', (int) $r['id'], 'Smazat', 'btn btn--small btn--danger', 'Opravdu smazat?') ?>
          </div>
        </article>
      <?php endforeach; ?>

    <?php /* ================= ZÁKAZNÍCI ================= */ elseif ($tab === 'customers'): ?>
      <h1>Zákazníci</h1>
      <p class="muted">Registrace zapnete či vypnete v Nastavení → Firma a kontakty.</p>
      <?php $rows = q("SELECT c.*, (SELECT COUNT(*) FROM inquiries i WHERE i.customer_id = c.id) AS n FROM customers c ORDER BY c.created_at DESC")->fetchAll(); ?>
      <?php if (!$rows): ?><p class="muted">Zatím se nikdo nezaregistroval.</p><?php else: ?>
        <div class="table-wrap"><table class="table">
          <tr><th>Zákazník</th><th>Kontakt</th><th>Poptávky</th><th>Registrace</th><th>Poslední přihlášení</th><th></th></tr>
          <?php foreach ($rows as $c): ?>
            <tr id="c<?= (int) $c['id'] ?>"<?= $c['is_active'] ? '' : ' class="item--off"' ?>>
              <td><strong><?= e($c['name']) ?></strong><?= $c['location'] ? '<br><span class="muted">' . e($c['location']) . '</span>' : '' ?></td>
              <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a> <?= $c['email_verified_at'] ? '<span class="badge">ověřen</span>' : '<span class="muted">neověřen</span>' ?>
                <?= $c['phone'] ? '<br><a href="' . e(tel_href($c['phone'])) . '">' . e($c['phone']) . '</a>' : '' ?></td>
              <td><?= (int) $c['n'] ?></td>
              <td><?= e(date('j. n. Y', strtotime($c['created_at']))) ?> · <?= strtoupper(e($c['lang'])) ?></td>
              <td><?= $c['last_login'] ? e(date('j. n. Y H:i', strtotime($c['last_login']))) : '—' ?></td>
              <td class="item__actions">
                <?= btn_post('customer_toggle', (int) $c['id'], $c['is_active'] ? 'Zablokovat' : 'Povolit') ?>
                <?= btn_post('customer_delete', (int) $c['id'], 'Smazat', 'btn btn--small btn--danger', 'Smazat účet? Poptávky zůstanou zachovány.') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>

    <?php /* ================= HODNOCENÍ ================= */ elseif ($tab === 'reviews'): ?>
      <h1>Hodnocení</h1>
      <?php $rows = q("SELECT * FROM reviews ORDER BY is_approved, created_at DESC")->fetchAll(); ?>
      <?php if (!$rows): ?><p class="muted">Zatím žádná hodnocení.</p><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <article class="item <?= $r['is_approved'] ? '' : 'item--new' ?>">
          <div class="item__head">
            <strong><?= e($r['author']) ?></strong> <span class="stars"><?= stars((int) $r['rating']) ?></span>
            <span class="muted"><?= e($r['location']) ?> · <?= e(date('j. n. Y', strtotime($r['created_at']))) ?> · <?= strtoupper(e($r['lang'])) ?> · <?= $r['is_approved'] ? 'zveřejněno' : 'čeká na schválení' ?></span>
          </div>
          <p><?= nl2br(e($r['text'])) ?></p>
          <div class="item__actions">
            <?= btn_post('review_toggle', (int) $r['id'], $r['is_approved'] ? 'Skrýt' : 'Schválit', $r['is_approved'] ? 'btn btn--small btn--ghost' : 'btn btn--small') ?>
            <?= btn_post('review_delete', (int) $r['id'], 'Smazat', 'btn btn--small btn--danger', 'Opravdu smazat?') ?>
          </div>
        </article>
      <?php endforeach; ?>

    <?php /* ================= GALERIE ================= */ elseif ($tab === 'gallery'): ?>
      <h1>Galerie</h1>
      <?php
        $cats  = q("SELECT c.*, p.title AS parent_title FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY COALESCE(p.sort, c.sort), c.parent_id IS NOT NULL, c.sort")->fetchAll();
        $projs = q("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll();
        $filter = (string) ($_GET['f'] ?? '');
        $targetOptions = function (string $sel) use ($cats, $projs): string {
            $h = '<optgroup label="Stránky">';
            foreach ($cats as $c) $h .= '<option value="c:' . $c['id'] . '"' . ($sel === 'c:' . $c['id'] ? ' selected' : '') . '>' . ($c['parent_title'] ? '— ' : '') . e($c['title']) . '</option>';
            $h .= '</optgroup><optgroup label="Realizace">';
            foreach ($projs as $p) $h .= '<option value="p:' . $p['id'] . '"' . ($sel === 'p:' . $p['id'] ? ' selected' : '') . '>' . e($p['title']) . '</option>';
            return $h . '</optgroup>';
        };
      ?>
      <details class="panel" open>
        <summary><strong>Nahrát fotky</strong></summary>
        <form method="post" enctype="multipart/form-data" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="upload">
          <div class="form__grid">
            <label class="field">Fotky (JPG, PNG, WebP, lze vybrat více)<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
            <label class="field">Zařadit do<select name="target"><option value="">— jen do referencí —</option><?= $targetOptions($filter) ?></select></label>
            <label class="field">Popisek CZ<input type="text" name="caption"></label>
            <label class="field">Popisek EN<input type="text" name="caption_en"></label>
          </div>
          <label class="check"><input type="checkbox" name="in_references" value="1" checked><span>Zobrazit i na stránce Reference</span></label>
          <button class="btn">Nahrát</button>
        </form>
      </details>

      <form method="get" class="filter">
        <input type="hidden" name="tab" value="gallery">
        <select name="f" onchange="this.form.submit()"><option value="">Všechny fotky</option><option value="none" <?= $filter === 'none' ? 'selected' : '' ?>>Jen reference (bez stránky)</option><?= $targetOptions($filter) ?></select>
      </form>
      <?php
        $where = ''; $params = [];
        if (preg_match('~^([cp]):(\d+)$~', $filter, $m)) { $where = 'WHERE ' . ($m[1] === 'c' ? 'g.category_id' : 'g.project_id') . ' = ?'; $params[] = (int) $m[2]; }
        if ($filter === 'none') $where = 'WHERE g.category_id IS NULL AND g.project_id IS NULL';
        $imgs = q("SELECT g.*, c.title AS cat, p.title AS proj, c.cover_image AS ccover, p.cover_image AS pcover FROM gallery_images g LEFT JOIN categories c ON c.id = g.category_id LEFT JOIN projects p ON p.id = g.project_id $where ORDER BY g.category_id, g.project_id, g.sort, g.id LIMIT 1000", $params)->fetchAll();
      ?>
      <p class="muted"><?= count($imgs) ?> fotek</p>
      <div class="admin-gallery">
        <?php foreach ($imgs as $im): $isCover = $im['file'] === $im['ccover'] || $im['file'] === $im['pcover']; ?>
          <figure class="<?= $isCover ? 'is-cover' : '' ?>">
            <a href="<?= e(image_url($im['file'])) ?>" target="_blank"><img src="<?= e(image_url($im['file'])) ?>" alt="" loading="lazy"></a>
            <figcaption><?= e($im['cat'] ?: ($im['proj'] ?: 'Reference')) ?><?= $isCover ? ' · <strong>úvodní</strong>' : '' ?></figcaption>
            <form method="post" class="form form--mini">
              <?= csrf_field() ?><input type="hidden" name="action" value="image_save"><input type="hidden" name="id" value="<?= (int) $im['id'] ?>"><input type="hidden" name="f" value="<?= e($filter) ?>">
              <input type="text" name="caption" value="<?= e($im['caption']) ?>" placeholder="Popisek CZ">
              <input type="text" name="caption_en" value="<?= e($im['caption_en']) ?>" placeholder="Popisek EN">
              <div class="row"><input type="text" name="sort" value="<?= (int) $im['sort'] ?>" title="Pořadí" class="w-num">
              <label class="check"><input type="checkbox" name="in_references" value="1" <?= $im['in_references'] ? 'checked' : '' ?>><span>Ref.</span></label>
              <button class="btn btn--small btn--ghost">Uložit</button></div>
            </form>
            <div class="item__actions">
              <?php if (($im['category_id'] || $im['project_id']) && !$isCover): ?><?= btn_post('image_cover', (int) $im['id'], 'Nastavit jako úvodní', 'btn btn--small btn--ghost', '', ['f' => $filter]) ?><?php endif; ?>
              <?= btn_post('image_delete', (int) $im['id'], 'Smazat', 'btn btn--small btn--danger', 'Smazat fotku?', ['f' => $filter]) ?>
            </div>
          </figure>
        <?php endforeach; ?>
      </div>

    <?php /* ================= STRÁNKY ================= */ elseif ($tab === 'pages'): ?>
      <?php $editId = (int) ($_GET['edit'] ?? 0); $c = $editId ? q("SELECT * FROM categories WHERE id = ?", [$editId])->fetch() : null; ?>
      <?php if ($c): ?>
        <p><a href="<?= e(admin_url('tab=pages')) ?>">← Zpět</a> · <a href="<?= e(item_url($c, 'cs')) ?>" target="_blank">CZ ↗</a> · <a href="<?= e(item_url($c, 'en')) ?>" target="_blank">EN ↗</a> · <a href="<?= e(admin_url('tab=gallery&f=c:' . $c['id'])) ?>">Fotky stránky</a></p>
        <h1><?= e($c['title']) ?></h1>
        <form method="post" class="form panel">
          <?= csrf_field() ?><input type="hidden" name="action" value="category_save"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <div class="bi"><div class="bi__label">URL</div><label><span class="flag">CZ</span><input type="text" value="/<?= e($c['slug']) ?>/" disabled></label><label><span class="flag">EN</span><input type="text" name="slug_en" value="<?= e($c['slug_en']) ?>" placeholder="např. kitchen"></label></div>
          <?= bi('Název stránky', 'title', $c) ?>
          <?= bi('Název v menu', 'menu_title', $c) ?>
          <?= bi('Nadpis H1', 'headline', $c) ?>
          <?= bi('Úvodní text (i na kartě)', 'intro', $c, 'text') ?>
          <?= bi('Obsah stránky (HTML)', 'body', $c, 'html', 10) ?>
          <?= bi('Meta popis pro vyhledávače', 'meta_desc', $c, 'text', 2) ?>
          <div class="form__grid">
            <label class="field">Pořadí<input type="text" name="sort" value="<?= (int) $c['sort'] ?>"></label>
            <label class="check field"><input type="checkbox" name="is_active" value="1" <?= $c['is_active'] ? 'checked' : '' ?>><span>Stránka je aktivní</span></label>
          </div>
          <button class="btn">Uložit</button>
        </form>
      <?php else: ?>
        <h1>Stránky</h1>
        <div class="table-wrap"><table class="table">
          <tr><th>Stránka</th><th>CZ URL</th><th>EN URL</th><th>Fotky</th></tr>
          <?php foreach (q("SELECT c.*, p.title AS parent_title, (SELECT COUNT(*) FROM gallery_images g WHERE g.category_id = c.id) AS photos FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY COALESCE(p.sort, c.sort), c.parent_id IS NOT NULL, c.sort")->fetchAll() as $c): ?>
            <tr>
              <td><?= $c['parent_title'] ? '<span class="muted">— </span>' : '' ?><a href="<?= e(admin_url('tab=pages&edit=' . $c['id'])) ?>"><?= e($c['title']) ?></a><?= $c['is_active'] ? '' : ' <span class="muted">(skryto)</span>' ?><?= $c['title_en'] ? '' : ' <span class="badge">chybí EN</span>' ?></td>
              <td class="muted">/<?= e($c['slug']) ?></td>
              <td class="muted">/en/<?= e($c['slug_en'] ?: $c['slug']) ?></td>
              <td class="muted"><?= (int) $c['photos'] ?></td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>

    <?php /* ================= REALIZACE ================= */ elseif ($tab === 'projects'): ?>
      <?php $editId = $_GET['edit'] ?? null; $p = $editId ? q("SELECT * FROM projects WHERE id = ?", [(int) $editId])->fetch() : null; ?>
      <?php if ($editId !== null): ?>
        <p><a href="<?= e(admin_url('tab=projects')) ?>">← Zpět</a><?php if ($p): ?> · <a href="<?= e(item_url($p, 'cs')) ?>" target="_blank">CZ ↗</a> · <a href="<?= e(item_url($p, 'en')) ?>" target="_blank">EN ↗</a><?php endif; ?></p>
        <h1><?= $p ? e($p['title']) : 'Nová realizace' ?></h1>
        <form method="post" class="form panel">
          <?= csrf_field() ?><input type="hidden" name="action" value="project_save"><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
          <?= bi('Název', 'title', $p ?: null) ?>
          <div class="bi"><div class="bi__label">URL (prázdné = z názvu)</div><label><span class="flag">CZ</span><input type="text" name="slug" value="<?= e($p['slug'] ?? '') ?>"></label><label><span class="flag">EN</span><input type="text" name="slug_en" value="<?= e($p['slug_en'] ?? '') ?>"></label></div>
          <label>Lokalita<input type="text" name="location" value="<?= e($p['location'] ?? '') ?>"></label>
          <?= bi('Krátké shrnutí', 'summary', $p ?: null, 'text', 2) ?>
          <?= bi('Popis (HTML)', 'body', $p ?: null, 'html', 10) ?>
          <label class="check"><input type="checkbox" name="is_published" value="1" <?= ($p['is_published'] ?? 1) ? 'checked' : '' ?>><span>Zveřejněno</span></label>
          <button class="btn">Uložit</button>
        </form>
        <?php if ($p): ?>
          <p><a href="<?= e(admin_url('tab=gallery&f=p:' . $p['id'])) ?>">Spravovat fotky této realizace →</a></p>
          <?= btn_post('project_delete', (int) $p['id'], 'Smazat realizaci', 'btn btn--small btn--danger', 'Smazat realizaci včetně fotek?') ?>
        <?php endif; ?>
      <?php else: ?>
        <h1>Realizace <a href="<?= e(admin_url('tab=projects&edit=0')) ?>" class="btn btn--small">+ Přidat</a></h1>
        <div class="table-wrap"><table class="table">
          <?php foreach (q("SELECT p.*, (SELECT COUNT(*) FROM gallery_images g WHERE g.project_id = p.id) AS photos FROM projects p ORDER BY created_at DESC")->fetchAll() as $p): ?>
            <tr>
              <td><a href="<?= e(admin_url('tab=projects&edit=' . $p['id'])) ?>"><?= e($p['title']) ?></a><?= $p['is_published'] ? '' : ' <span class="muted">(koncept)</span>' ?><?= $p['title_en'] ? '' : ' <span class="badge">chybí EN</span>' ?></td>
              <td class="muted"><?= e($p['location']) ?></td>
              <td class="muted"><?= (int) $p['photos'] ?> fotek</td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>

    <?php /* ================= FORMULÁŘ ================= */ elseif ($tab === 'form'): ?>
      <h1>Poptávkový formulář</h1>
      <p class="muted">Pole se zobrazují na stránkách Kontakt a Ceník. Klíče <code>name</code>, <code>email</code>, <code>phone</code>, <code>location</code> a <code>message</code> se ukládají i do přehledu poptávek. U výběru (select) zadejte jednu možnost na řádek.</p>
      <?php
        $fields = q("SELECT * FROM form_fields ORDER BY sort, id")->fetchAll();
        $types = ['text' => 'Text', 'email' => 'E-mail', 'tel' => 'Telefon', 'number' => 'Číslo', 'textarea' => 'Víceřádkový text', 'select' => 'Výběr ze seznamu', 'checkbox' => 'Zaškrtávací pole', 'date' => 'Datum'];
        $fieldForm = function (?array $f) use ($types): string {
            ob_start(); ?>
            <form method="post" class="form">
              <?= csrf_field() ?><input type="hidden" name="action" value="field_save"><input type="hidden" name="id" value="<?= (int) ($f['id'] ?? 0) ?>">
              <div class="form__grid form__grid--4">
                <label class="field">Klíč<input type="text" name="fkey" value="<?= e($f['fkey'] ?? '') ?>" placeholder="automaticky"></label>
                <label class="field">Typ<select name="type"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= ($f['type'] ?? 'text') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
                <label class="field">Pořadí<input type="text" name="sort" value="<?= (int) ($f['sort'] ?? 100) ?>"></label>
                <div class="field checks">
                  <label class="check"><input type="checkbox" name="is_required" value="1" <?= !empty($f['is_required']) ? 'checked' : '' ?>><span>Povinné</span></label>
                  <label class="check"><input type="checkbox" name="is_wide" value="1" <?= !empty($f['is_wide']) ? 'checked' : '' ?>><span>Celá šířka</span></label>
                  <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($f['is_active'] ?? 1) ? 'checked' : '' ?>><span>Aktivní</span></label>
                </div>
              </div>
              <div class="form__grid">
                <label class="field"><span><span class="flag">CZ</span> Popisek *</span><input type="text" name="label_cs" value="<?= e($f['label_cs'] ?? '') ?>" required></label>
                <label class="field"><span><span class="flag">EN</span> Popisek</span><input type="text" name="label_en" value="<?= e($f['label_en'] ?? '') ?>"></label>
                <label class="field"><span><span class="flag">CZ</span> Nápověda v poli</span><input type="text" name="placeholder_cs" value="<?= e($f['placeholder_cs'] ?? '') ?>"></label>
                <label class="field"><span><span class="flag">EN</span> Nápověda v poli</span><input type="text" name="placeholder_en" value="<?= e($f['placeholder_en'] ?? '') ?>"></label>
                <label class="field"><span><span class="flag">CZ</span> Možnosti (select)</span><textarea name="options_cs" rows="3"><?= e($f['options_cs'] ?? '') ?></textarea></label>
                <label class="field"><span><span class="flag">EN</span> Možnosti (select)</span><textarea name="options_en" rows="3"><?= e($f['options_en'] ?? '') ?></textarea></label>
              </div>
              <button class="btn btn--small"><?= $f ? 'Uložit pole' : 'Přidat pole' ?></button>
            </form>
            <?php return (string) ob_get_clean();
        };
      ?>
      <?php foreach ($fields as $f): ?>
        <details class="item <?= $f['is_active'] ? '' : 'item--off' ?>">
          <summary class="item__head">
            <strong><?= e($f['label_cs']) ?><?= $f['is_required'] ? ' *' : '' ?></strong>
            <span class="muted"><?= e($types[$f['type']] ?? $f['type']) ?> · <code><?= e($f['fkey']) ?></code> · <?= e($f['label_en'] ?: '—') ?><?= $f['is_active'] ? '' : ' · skryto' ?></span>
          </summary>
          <?= $fieldForm($f) ?>
          <div class="item__actions"><?= btn_post('field_delete', (int) $f['id'], 'Smazat pole', 'btn btn--small btn--danger', 'Smazat pole?') ?></div>
        </details>
      <?php endforeach; ?>
      <div class="panel"><h2>Nové pole</h2><?= $fieldForm(null) ?></div>

    <?php /* ================= TEXTY CZ/EN ================= */ elseif ($tab === 'texts'): ?>
      <?php
        $groups = ['nav' => 'Menu a patička', 'home' => 'Úvodní stránka', 'cta' => 'Výzva „Kontaktujte nás“', 'category' => 'Stránky služeb', 'references' => 'Reference', 'reviews' => 'Hodnocení', 'pricing' => 'Ceník', 'contact' => 'Kontakt', 'privacy' => 'Ochrana osobních údajů (GDPR)', 'form' => 'Formulář – hlášky', 'acc' => 'Zákaznický účet', 'status' => 'Stavy poptávek', 'mail' => 'E-maily zákazníkům', 'misc' => 'Ostatní'];
        $all = props();
        foreach ($all as $r) if (!isset($groups[$r['grp']]) && !in_array($r['grp'], ['site', 'images', 'seo', 'sys'], true)) $groups[$r['grp']] = $r['grp'];
        $grp = (string) ($_GET['grp'] ?? 'home');
        if (!isset($groups[$grp])) $grp = 'home';
      ?>
      <h1>Texty CZ / EN</h1>
      <p class="muted">Prázdné EN pole = zobrazí se česká verze. Proměnné: <code>{years}</code> (roky praxe), u hodnocení <code>{avg}</code>, <code>{count}</code>, u chyb formuláře <code>{field}</code>.</p>
      <nav class="subtabs"><?php foreach ($groups as $k => $v): ?><a href="<?= e(admin_url("tab=texts&grp=$k")) ?>" class="<?= $grp === $k ? 'is-active' : '' ?>"><?= e($v) ?></a><?php endforeach; ?></nav>
      <form method="post" class="form panel">
        <?= csrf_field() ?><input type="hidden" name="action" value="props_save"><input type="hidden" name="grp" value="<?= e($grp) ?>">
        <?php foreach ($all as $key => $r): if ($r['grp'] !== $grp) continue;
              $rows = $r['type'] === 'line' ? 0 : ($r['type'] === 'html' ? 6 : 3); ?>
          <div class="bi">
            <div class="bi__label"><code><?= e($key) ?></code><?= $r['label'] ? ' – ' . e($r['label']) : '' ?><?= $r['type'] === 'html' ? ' <span class="badge">HTML</span>' : '' ?></div>
            <?php foreach (['cs' => 'CZ', 'en' => 'EN'] as $l => $flag): $v = (string) $r['value_' . $l]; ?>
              <label><span class="flag"><?= $flag ?></span>
                <?php if ($rows): ?><textarea name="p[<?= e($key) ?>][<?= $l ?>]" rows="<?= $rows ?>"<?= $r['type'] === 'html' ? ' class="code"' : '' ?>><?= e($v) ?></textarea>
                <?php else: ?><input type="text" name="p[<?= e($key) ?>][<?= $l ?>]" value="<?= e($v) ?>"><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <button class="btn">Uložit texty</button>
      </form>
      <details class="panel">
        <summary><strong>Přidat vlastní text</strong></summary>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="prop_add">
          <div class="form__grid">
            <label class="field">Klíč (např. home.novy_text)<input type="text" name="pkey" required></label>
            <label class="field">Typ<select name="type"><option value="line">Řádek</option><option value="text">Odstavec</option><option value="html">HTML</option></select></label>
            <label class="field"><span><span class="flag">CZ</span> Hodnota</span><textarea name="value_cs" rows="2"></textarea></label>
            <label class="field"><span><span class="flag">EN</span> Hodnota</span><textarea name="value_en" rows="2"></textarea></label>
          </div>
          <p class="muted">V šabloně se použije jako <code>&lt;?= te('home.novy_text') ?&gt;</code>.</p>
          <button class="btn btn--small">Přidat</button>
        </form>
      </details>

    <?php /* ================= NASTAVENÍ ================= */ elseif ($tab === 'settings'): ?>
      <?php
        $grp = (string) ($_GET['grp'] ?? 'site');
        $sgroups = ['site' => 'Firma a kontakty', 'images' => 'Grafika', 'seo' => 'SEO, robots.txt a sitemap'];
        if (!isset($sgroups[$grp])) $grp = 'site';
        $all = props();
      ?>
      <h1>Nastavení</h1>
      <nav class="subtabs"><?php foreach ($sgroups as $k => $v): ?><a href="<?= e(admin_url("tab=settings&grp=$k")) ?>" class="<?= $grp === $k ? 'is-active' : '' ?>"><?= e($v) ?></a><?php endforeach; ?></nav>

      <form method="post" class="form panel" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="props_save"><input type="hidden" name="grp" value="<?= e($grp) ?>"><input type="hidden" name="grp_prefix" value="<?= e($grp) ?>.">
        <?php foreach ($all as $key => $r): if ($r['grp'] !== $grp) continue; ?>
          <?php if ($r['type'] === 'image'): $src = image_url($r['value_cs']); ?>
            <div class="img-prop">
              <div class="img-prop__preview"><?= $src ? '<img src="' . e($src) . '" alt="">' : '<span class="muted">bez obrázku</span>' ?></div>
              <div>
                <strong><?= e($r['label'] ?: $key) ?></strong>
                <input type="file" name="img[<?= e($key) ?>]" accept="image/*,.ico">
                <?php if ($src): ?><label class="check"><input type="checkbox" name="img_remove[<?= e($key) ?>]" value="1"><span>Odebrat</span></label><?php endif; ?>
              </div>
            </div>
          <?php elseif ($r['type'] === 'bool'): ?>
            <label class="check"><input type="checkbox" name="p[<?= e($key) ?>][cs]" value="1" <?= $r['value_cs'] === '1' ? 'checked' : '' ?>><span><?= e($r['label'] ?: $key) ?></span></label>
          <?php elseif ($grp === 'seo'): ?>
            <label><?= e($r['label'] ?: $key) ?><textarea name="p[<?= e($key) ?>][cs]" rows="4" class="code"><?= e($r['value_cs']) ?></textarea></label>
          <?php else: ?>
            <div class="bi">
              <div class="bi__label"><?= e($r['label'] ?: $key) ?></div>
              <label><span class="flag">CZ</span><input type="text" name="p[<?= e($key) ?>][cs]" value="<?= e($r['value_cs']) ?>"></label>
              <label><span class="flag">EN</span><input type="text" name="p[<?= e($key) ?>][en]" value="<?= e($r['value_en']) ?>" placeholder="stejné jako CZ"></label>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
        <button class="btn">Uložit</button>
      </form>

      <?php if ($grp === 'seo'): $hasFiles = is_file(ROOT_DIR . '/robots.txt') || is_file(ROOT_DIR . '/sitemap.xml'); ?>
        <div class="panel">
          <h2>Generování robots.txt a sitemap.xml</h2>
          <p>Stav: <?= $hasFiles
              ? '<strong>statické soubory</strong> v kořeni webu (vygenerováno ' . e(date('j. n. Y H:i', (int) @filemtime(ROOT_DIR . '/sitemap.xml'))) . ')'
              : '<strong>dynamicky</strong> – web je generuje při každém požadavku' ?>.
            Odkazy: <a href="<?= e(url('robots.txt')) ?>" target="_blank">robots.txt</a> · <a href="<?= e(url('sitemap.xml')) ?>" target="_blank">sitemap.xml</a></p>
          <div class="item__actions">
            <?= btn_post('seo_generate', 0, $hasFiles ? 'Přegenerovat soubory' : 'Vygenerovat soubory', 'btn btn--small') ?>
            <?php if ($hasFiles): ?><?= btn_post('seo_remove', 0, 'Odstranit (přepnout na dynamické)', 'btn btn--small btn--ghost') ?><?php endif; ?>
          </div>
          <h3>Náhled robots.txt</h3>
          <pre class="pre"><?= e(build_robots()) ?></pre>
          <h3>Sitemap – <?= count(sitemap_urls()) * 2 ?> adres</h3>
          <div class="table-wrap"><table class="table">
            <?php foreach (sitemap_urls() as $u): ?><tr><td><?= e($u['cs']) ?></td><td><?= e($u['en']) ?></td><td class="muted"><?= e($u['prio']) ?></td></tr><?php endforeach; ?>
          </table></div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
<?php endif; ?>
</body>
</html>
