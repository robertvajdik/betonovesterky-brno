<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin-nav.php';
require_once __DIR__ . '/../includes/admin-calendar.php';

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
        back('', t('adm.login.created'));
    }
    back('', t('adm.login.err_setup'));
}
if ($action === 'login' && csrf_ok()) {
    $row = q("SELECT * FROM admin_users WHERE username = ?", [post('username', 60)])->fetch();
    if ($row && password_verify((string) ($_POST['password'] ?? ''), $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $row['id'];
        back();
    }
    usleep(500000);
    back('', t('adm.login.err'));
}
if (($_GET['logout'] ?? '') === '1') {
    unset($_SESSION['admin_id']);
    back();
}

$logged = !empty($_SESSION['admin_id']);
$tab    = (string) ($_GET['tab'] ?? 'inquiries');
if ($logged && $tab === 'invoices') { header('Location: ' . url('admin/invoice.php?list=1'), true, 303); exit; }   // přehled faktur má vlastní stránku

// ---------- Akce po přihlášení ----------
if ($logged && $action !== '') {
    if (!csrf_ok()) back("tab=$tab", t('adm.err_csrf'));
    $id = (int) ($_POST['id'] ?? 0);

    switch ($action) {
        // Poptávky
        case 'inquiry_read':
            q("UPDATE inquiries SET is_read = 1 - is_read WHERE id = ?", [$id]); back('tab=inquiries');
        case 'inquiry_link':
            back('tab=inquiries#q' . $id, send_track_link($id) ? t('adm.inq.link_sent') : t('adm.inq.link_failed'));
        case 'inquiry_delete':
            inquiry_delete_photos($id);
            q("DELETE FROM inquiries WHERE id = ?", [$id]); back('tab=inquiries', t('adm.inq.deleted'));
        case 'inquiry_reply':
            // odpověď zákazníkovi (+ změna stavu); registrovaný ji uvidí v účtu, všem přijde e-mailem
            $inq = q("SELECT i.*, c.email AS c_email, c.lang AS c_lang FROM inquiries i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ?", [$id])->fetch();
            if (!$inq) back('tab=inquiries');
            $status = in_array($_POST['status'] ?? '', INQUIRY_STATUSES, true) ? $_POST['status'] : $inq['status'];
            $body   = post('body', 10000);
            q("UPDATE inquiries SET status = ?, is_read = 1, updated_at = NOW() WHERE id = ?", [$status, $id]);
            q("UPDATE inquiry_messages SET is_read = 1 WHERE inquiry_id = ? AND author = 'customer'", [$id]);
            $note = t('adm.inq.status_saved');
            if ($body !== '') {
                q("INSERT INTO inquiry_messages (inquiry_id, author, body) VALUES (?, 'admin', ?)", [$id, $body]);
                $to   = $inq['c_email'] ?: $inq['email'];
                $lang = $inq['c_lang'] ?: $inq['lang'];
                $vars = ['id' => $id, 'message' => $body, 'status' => '', 'url' => abs_url(route_url('account', $lang)) . '?id=' . $id];
                $sent = !empty($_POST['notify']) && send_customer_mail($to, $lang, $inq['customer_id'] ? 'reply' : 'reply_guest', $vars);
                $note = t($sent ? 'adm.inq.reply_sent' : (!empty($_POST['notify']) ? 'adm.inq.reply_mail_failed' : 'adm.inq.reply_saved'), ['email' => $to]);
            }
            back('tab=inquiries#q' . $id, $note);

        // Zákazníci
        case 'customer_toggle':
            q("UPDATE customers SET is_active = 1 - is_active WHERE id = ?", [$id]); back('tab=customers');
        case 'customer_delete':
            q("DELETE FROM customers WHERE id = ?", [$id]); back('tab=customers', t('adm.cust.deleted'));

        // Hodnocení
        case 'review_toggle':
            q("UPDATE reviews SET is_approved = 1 - is_approved WHERE id = ?", [$id]); back('tab=reviews');
        case 'review_delete':
            q("DELETE FROM reviews WHERE id = ?", [$id]); back('tab=reviews', t('adm.rev.deleted'));
        case 'review_save':
            $author = post('author', 100);
            $text   = post('text', 5000);
            $rating = (string) ($_POST['rating'] ?? '');
            $rating = in_array($rating, ['1', '2', '3', '4', '5'], true) ? (int) $rating : null;   // prázdné = bez hvězd
            $date   = DateTimeImmutable::createFromFormat('!Y-m-d', post('date', 10));
            $lang   = in_array($_POST['lang'] ?? '', LANGS, true) ? $_POST['lang'] : 'cs';
            if ($text === '') back("tab=reviews#r$id", t('adm.rev.err_text'));
            q("UPDATE reviews SET author = ?, title = ?, location = ?, rating = ?, text = ?, lang = ?, is_approved = ?"
              . ($date ? ", created_at = CONCAT(?, ' ', TIME(created_at))" : '') . " WHERE id = ?",
              array_merge([$author, nn(post('title', 160)), nn(post('location', 100)), $rating, $text, $lang, empty($_POST['is_approved']) ? 0 : 1],
                          $date ? [$date->format('Y-m-d')] : [], [$id]));
            back("tab=reviews#r$id", t('adm.rev.saved'));

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
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['target'] ?? '')), t('adm.gal.uploaded', ['count' => $count]));
        case 'image_save':
            q("UPDATE gallery_images SET caption = ?, caption_en = ?, sort = ?, in_references = ? WHERE id = ?",
              [nn(post('caption', 200)), nn(post('caption_en', 200)), (int) ($_POST['sort'] ?? 0), !empty($_POST['in_references']) ? 1 : 0, $id]);
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['f'] ?? '')), t('adm.saved'));
        case 'image_delete':
            $im = q("SELECT file FROM gallery_images WHERE id = ?", [$id])->fetch();
            if ($im) {
                $used = (int) q("SELECT (SELECT COUNT(*) FROM categories WHERE cover_image = ?) + (SELECT COUNT(*) FROM projects WHERE cover_image = ?)", [$im['file'], $im['file']])->fetchColumn();
                q("DELETE FROM gallery_images WHERE id = ?", [$id]);
                $path = UPLOAD_DIR . '/' . $im['file'];
                if (!$used && is_file($path) && str_starts_with(realpath($path) ?: '', realpath(UPLOAD_DIR))) @unlink($path);
            }
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['f'] ?? '')), t('adm.gal.deleted'));
        case 'image_cover':
            $im = q("SELECT * FROM gallery_images WHERE id = ?", [$id])->fetch();
            if ($im && $im['category_id']) q("UPDATE categories SET cover_image = ? WHERE id = ?", [$im['file'], $im['category_id']]);
            if ($im && $im['project_id'])  q("UPDATE projects SET cover_image = ? WHERE id = ?", [$im['file'], $im['project_id']]);
            back('tab=gallery&f=' . rawurlencode((string) ($_POST['f'] ?? '')), t('adm.gal.cover_set'));

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
            back("tab=pages&edit=$id", t('adm.saved'));

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
            back("tab=projects&edit=$id", t('adm.proj.saved'));
        case 'project_delete':
            q("DELETE FROM projects WHERE id = ?", [$id]); after_content_change(); back('tab=projects', t('adm.proj.deleted'));

        // Formulář
        case 'field_save':
            $types = ['text','email','tel','number','textarea','select','checkbox','date'];
            $type  = in_array($_POST['type'] ?? '', $types, true) ? $_POST['type'] : 'text';
            $old   = $id ? q("SELECT * FROM form_fields WHERE id = ?", [$id])->fetch() : null;
            if ($id && !$old) back('tab=form');
            $label = post('label_cs', 160);
            // základní pole (jméno, e-mail…) si drží klíč – ukládají se podle něj do přehledu poptávek
            $fkey  = $old && in_array($old['fkey'], FORM_SYSTEM_FIELDS, true) ? $old['fkey']
                   : str_replace('-', '_', slugify(post('fkey', 40) ?: ($old['fkey'] ?? '') ?: $label));
            if ($label === '' || $fkey === '') back('tab=form' . ($id ? "#f$id" : ''), t('adm.form.err_name'));
            if ($old && in_array($old['fkey'], ['name', 'email'], true)) $type = $old['type'];
            $hasPh  = !in_array($type, ['select', 'checkbox', 'date'], true);
            $hasOpt = $type === 'select';
            $vals = [$fkey, $type, $label, nn(post('label_en', 160)),
                     $hasPh ? nn(post('placeholder_cs', 200)) : null, $hasPh ? nn(post('placeholder_en', 200)) : null,
                     $hasOpt ? nn(post('options_cs', 5000)) : null, $hasOpt ? nn(post('options_en', 5000)) : null,
                     isset($_POST['is_required']) ? (!empty($_POST['is_required']) ? 1 : 0) : (int) ($old['is_required'] ?? 0),
                     (($_POST['is_wide'] ?? '') !== '') ? (!empty($_POST['is_wide']) ? 1 : 0) : (int) ($old['is_wide'] ?? ($type === 'textarea' ? 1 : 0))];
            try {
                if ($old) {
                    q("UPDATE form_fields SET fkey=?, type=?, label_cs=?, label_en=?, placeholder_cs=?, placeholder_en=?, options_cs=?, options_en=?, is_required=?, is_wide=? WHERE id=?", [...$vals, $id]);
                } else {
                    $sort = (int) q("SELECT COALESCE(MAX(sort), 0) + 10 FROM form_fields")->fetchColumn();
                    q("INSERT INTO form_fields (fkey, type, label_cs, label_en, placeholder_cs, placeholder_en, options_cs, options_en, is_required, is_wide, is_active, sort) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)", [...$vals, $sort]);
                    $id = (int) db()->lastInsertId();
                }
            } catch (PDOException $ex) {
                back('tab=form', t('adm.form.err_exists'));
            }
            back("tab=form#f$id", t('adm.form.saved'));
        case 'field_toggle':
            // rychlé přepínače v seznamu: povinné / zobrazeno (jméno a e-mail zůstávají vždy povinné a zobrazené)
            $f   = q("SELECT * FROM form_fields WHERE id = ?", [$id])->fetch();
            $col = ($_POST['what'] ?? '') === 'active' ? 'is_active' : 'is_required';
            if (!$f || in_array($f['fkey'], ['name', 'email'], true)) back("tab=form#f$id", t('adm.form.err_system'));
            q("UPDATE form_fields SET $col = 1 - $col WHERE id = ?", [$id]);
            back("tab=form#f$id");
        case 'field_move':
            // posun o jedno místo nahoru / dolů; pořadí se přečísluje po 10
            $ids = array_map('intval', q("SELECT id FROM form_fields ORDER BY sort, id")->fetchAll(PDO::FETCH_COLUMN));
            $pos = array_search($id, $ids, true);
            $to  = $pos === false ? false : $pos + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
            if ($to !== false && isset($ids[$to])) {
                [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
                foreach ($ids as $i => $fid) q("UPDATE form_fields SET sort = ? WHERE id = ?", [($i + 1) * 10, $fid]);
            }
            back("tab=form#f$id");
        case 'field_delete':
            $f = q("SELECT fkey FROM form_fields WHERE id = ?", [$id])->fetch();
            if ($f && in_array($f['fkey'], FORM_SYSTEM_FIELDS, true)) back("tab=form#f$id", t('adm.form.err_system'));
            q("DELETE FROM form_fields WHERE id = ?", [$id]); back('tab=form', t('adm.form.deleted'));

        // Nastavení / vlastnosti
        case 'props_save':
            $all = props();
            foreach ((array) ($_POST['p'] ?? []) as $key => $v) {
                if (!isset($all[$key]) || !is_array($v)) continue;
                $type = $all[$key]['type'];
                if ($type === 'image') continue;
                $cs = $type === 'bool' ? (!empty($v['cs']) ? '1' : '0') : mb_substr(trim((string) ($v['cs'] ?? '')), 0, 20000);
                if ($type !== 'bool' && array_key_exists('en', $v)) {
                    q("UPDATE properties SET value_cs = ?, value_en = ? WHERE pkey = ?", [$cs, mb_substr(trim((string) $v['en']), 0, 20000), $key]);
                } else {
                    q("UPDATE properties SET value_cs = ? WHERE pkey = ?", [$cs, $key]);   // pole bez EN verze: EN nechat
                }
            }
            // bool checkboxy zobrazené ve formuláři, které nepřišly = vypnuto
            foreach ((array) ($_POST['bools'] ?? []) as $key) {
                if (isset($all[$key]) && $all[$key]['type'] === 'bool' && !isset($_POST['p'][$key])) {
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
            back('tab=' . rawurlencode($tab) . (isset($_POST['grp']) ? '&grp=' . rawurlencode((string) $_POST['grp']) : ''), t('adm.settings.saved'));
        case 'prop_add':
            $key = strtolower(preg_replace('~[^a-z0-9._-]~i', '', post('pkey', 80)));
            if ($key !== '' && !isset(props()[$key])) {
                q("INSERT INTO properties (pkey, grp, type, value_cs, value_en) VALUES (?, ?, ?, ?, ?)",
                  [$key, explode('.', $key)[0], in_array($_POST['type'] ?? '', ['line','text','html'], true) ? $_POST['type'] : 'line', post('value_cs', 20000), post('value_en', 20000)]);
            }
            back('tab=texts', t('adm.texts.added'));
        case 'seo_generate':
            props(true);
            $r = write_seo_files();
            back('tab=settings&grp=robots', ($r['robots.txt'] && $r['sitemap.xml']) ? t('adm.seo.generated') : t('adm.seo.err_write'));
        case 'mail_test':
            $to = (string) ($CONFIG['notify_email'] ?? '') ?: site('email');
            $ok = send_mail($to, t('adm.mail.test_subject'), t('adm.mail.test_body', ['transport' => smtp_on() ? 'SMTP ' . $CONFIG['smtp']['host'] : 'PHP mail()']));
            back('tab=settings&grp=site#mail', $ok ? t('adm.mail.test_ok', ['email' => $to]) : t('adm.mail.test_fail', ['error' => smtp_error() ?: 'mail()']));
        case 'seo_remove':
            foreach (['robots.txt', 'sitemap.xml'] as $f) if (is_file(ROOT_DIR . "/$f")) @unlink(ROOT_DIR . "/$f");
            back('tab=settings&grp=robots', t('adm.seo.removed'));
    }
    back("tab=$tab");
}

$msg     = flash('admin');

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

?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= te('adm.title') ?> | <?= e(site('name')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<?php if (!$logged): ?>
  <div class="admin-login panel">
    <h1><?= te($hasUsers ? 'adm.login.title' : 'adm.login.setup_title') ?></h1>
    <?php if ($msg): ?><div class="alert alert--err"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $hasUsers ? 'login' : 'setup' ?>">
      <label><?= te('adm.login.username') ?><input type="text" name="username" required autocomplete="username"></label>
      <label><?= te('adm.login.password') ?><input type="password" name="password" required autocomplete="<?= $hasUsers ? 'current-password' : 'new-password' ?>"></label>
      <button class="btn"><?= te($hasUsers ? 'adm.login.btn' : 'adm.login.setup_btn') ?></button>
    </form>
  </div>
<?php else: ?>
  <?= admin_menu($tab) ?>
  <main class="container admin-main">
    <?php if ($msg): ?><div class="alert alert--ok"><?= e($msg) ?></div><?php endif; ?>

    <?php /* ================= POPTÁVKY ================= */ if ($tab === 'inquiries'): ?>
      <h1><?= te('adm.tab.inquiries') ?></h1>
      <?php
        $sf   = in_array($_GET['status'] ?? '', INQUIRY_STATUSES, true) ? $_GET['status'] : '';
        $rows = q("SELECT i.*, c.name AS c_name, c.id AS c_id FROM inquiries i LEFT JOIN customers c ON c.id = i.customer_id"
                . ($sf ? " WHERE i.status = ?" : '') . " ORDER BY i.is_read, COALESCE(i.updated_at, i.created_at) DESC LIMIT 300", $sf ? [$sf] : [])->fetchAll();
        $stLabels = []; foreach (INQUIRY_STATUSES as $st) $stLabels[$st] = t('adm.status.' . $st);
        $view = ($_GET['view'] ?? '') === 'calendar' ? 'calendar' : 'list';
        $vq   = $view === 'calendar' ? '&view=calendar' : '';
      ?>
      <nav class="subtabs">
        <a href="<?= e(admin_url('tab=inquiries' . $vq)) ?>" class="<?= $sf === '' ? 'is-active' : '' ?>"><?= te('adm.inq.all') ?></a>
        <?php foreach ($stLabels as $k => $v): ?><a href="<?= e(admin_url("tab=inquiries&status=$k" . $vq)) ?>" class="<?= $sf === $k ? 'is-active' : '' ?>"><?= e($v) ?></a><?php endforeach; ?>
      </nav>
      <nav class="view-switch" aria-label="<?= te('adm.inq.view') ?>">
        <a href="<?= e(admin_url('tab=inquiries' . ($sf ? "&status=$sf" : ''))) ?>"<?= $view === 'list' ? ' class="is-active" aria-current="page"' : '' ?>><?= te('adm.inq.view_list') ?></a>
        <a href="<?= e(admin_url('tab=inquiries&view=calendar' . ($sf ? "&status=$sf" : ''))) ?>"<?= $view === 'calendar' ? ' class="is-active" aria-current="page"' : '' ?>><?= te('adm.inq.view_cal') ?></a>
      </nav>
      <?php if ($view === 'calendar'): ?>
        <?= inquiry_calendar($rows, (string) ($_GET['m'] ?? ''), $sf) ?>
      <?php $rows = []; elseif (!$rows): ?><p class="muted"><?= te('adm.inq.empty') ?></p><?php endif; ?>
      <?php foreach ($rows as $r): $data = json_decode((string) $r['data'], true) ?: [];
            $msgs = q("SELECT * FROM inquiry_messages WHERE inquiry_id = ? ORDER BY created_at, id", [$r['id']])->fetchAll();
            // cena a záloha ze smluv, faktury k poptávce
            $deals = q("SELECT id, number, data FROM contracts WHERE inquiry_id = ? ORDER BY id DESC", [$r['id']])->fetchAll();
            $invs  = q("SELECT id, number, total, due, paid_at, data FROM invoices WHERE inquiry_id = ? ORDER BY id DESC", [$r['id']])->fetchAll();
            $pics  = inquiry_photos((int) $r['id']); ?>
        <article class="item <?= $r['is_read'] ? '' : 'item--new' ?>" id="q<?= (int) $r['id'] ?>">
          <div class="item__head">
            <strong>#<?= (int) $r['id'] ?> <?= e($r['name']) ?></strong>
            <span class="status status--<?= e($r['status']) ?>"><?= e($stLabels[$r['status']] ?? $r['status']) ?></span>
            <?php if ($r['c_id']): ?><a href="<?= e(admin_url('tab=customers#c' . $r['c_id'])) ?>" class="badge"><?= te('adm.inq.account') ?></a><?php endif; ?>
            <span class="muted"><?= e(date('j. n. Y H:i', strtotime($r['created_at']))) ?> · <?= e($r['source']) ?> · <?= strtoupper(e($r['lang'])) ?></span>
          </div>
          <p><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a>
            <?php if ($r['phone']): ?> · <a href="<?= e(tel_href($r['phone'])) ?>"><?= e($r['phone']) ?></a><?php endif; ?></p>
          <?php if ($deals || $invs): ?>
            <ul class="deal">
              <?php foreach ($deals as $cn): $cd = (array) json_decode($cn['data'], true);
                    $net = (float) str_replace([',', ' '], ['.', ''], (string) ($cd['price_net'] ?? '0'));
                    $gross = round($net * (1 + (int) ($cd['vat_rate'] ?? 0) / 100), 2);
                    $dep = (float) str_replace([',', ' '], ['.', ''], (string) ($cd['deposit'] ?? '0')); ?>
                <li><a href="<?= e(url('admin/contract.php?id=' . $cn['id'])) ?>"><strong><?= e($cn['number']) ?></strong></a> ·
                  <?= te('adm.inq.price') ?> <strong><?= e(number_format($gross, 0, ',', ' ')) ?> Kč</strong> <span class="muted"><?= te('adm.inq.incl_vat') ?></span>
                  · <?= te('adm.inq.deposit') ?> <strong><?= $dep > 0 ? e(number_format($dep, 0, ',', ' ')) . ' Kč' : '—' ?></strong></li>
              <?php endforeach; ?>
              <?php foreach ($invs as $iv): $over = !$iv['paid_at'] && $iv['due'] < date('Y-m-d'); ?>
                <li><a href="<?= e(url('admin/invoice.php?id=' . $iv['id'])) ?>"><strong><?= e($iv['number']) ?></strong></a>
                  <?= str_contains((string) $iv['data'], '"kind":"deposit"') ? '<span class="badge">' . te('adm.invoice.kind_deposit_short') . '</span>' : '' ?> ·
                  <strong><?= e(number_format((float) $iv['total'], 0, ',', ' ')) ?> Kč</strong> ·
                  <?= $iv['paid_at'] ? '<span class="badge">' . te('adm.invoice.paid_badge') . '</span>'
                      : '<span' . ($over ? ' class="deal__over"' : ' class="muted"') . '>' . te('adm.inq.due_on', ['date' => date('j. n. Y', strtotime((string) $iv['due']))]) . '</span>' ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($data): ?>
            <dl class="kv">
              <?php foreach ($data as $k => $d): if (in_array($k, ['name', 'email', 'phone'], true) || ($d['value'] ?? '') === '') continue; ?>
                <dt><?= e($d['label'] ?? $k) ?></dt><dd><?= nl2br(e((string) $d['value'])) ?></dd>
              <?php endforeach; ?>
            </dl>
          <?php else: ?>
            <p><?= nl2br(e($r['message'])) ?></p>
          <?php endif; ?>
          <?php if ($pics): ?>
            <ul class="inq-photos" aria-label="<?= te('adm.inq.photos') ?>">
              <?php foreach ($pics as $ph): $src = url('admin/file.php?id=' . $ph['id']); ?>
                <li><a href="<?= e($src) ?>" target="_blank" title="<?= e($ph['original']) ?>"><img src="<?= e($src) ?>" alt="<?= e($ph['original']) ?>" loading="lazy"></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($msgs): ?>
            <ol class="thread thread--admin">
              <?php foreach ($msgs as $m): ?>
                <li class="msg msg--<?= e($m['author']) ?><?= $m['author'] === 'customer' && !$m['is_read'] ? ' msg--unread' : '' ?>">
                  <div class="msg__head"><strong><?= $m['author'] === 'admin' ? te('adm.inq.you') : e($r['name']) ?></strong> <span class="muted"><?= e(date('j. n. Y H:i', strtotime($m['created_at']))) ?></span></div>
                  <p><?= nl2br(e($m['body'])) ?></p>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
          <details class="reply"<?= !$r['is_read'] && $msgs ? ' open' : '' ?>>
            <summary><?= te('adm.inq.reply_toggle') ?></summary>
            <form method="post" class="form">
              <?= csrf_field() ?><input type="hidden" name="action" value="inquiry_reply"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <label><?= te('adm.inq.status') ?>
                <select name="status"><?php foreach ($stLabels as $k => $v): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
              <label><?= te('adm.inq.message') ?><textarea name="body" rows="4"></textarea></label>
              <label class="check"><input type="checkbox" name="notify" value="1" checked><span><?= te('adm.inq.notify', ['email' => $r['email']]) ?><?= $r['c_id'] ? ' ' . te('adm.inq.notify_account') : '' ?></span></label>
              <button class="btn btn--small"><?= te('adm.save') ?></button>
            </form>
          </details>
          <div class="item__actions">
            <a href="<?= e(url('admin/contract.php?inquiry=' . (int) $r['id'])) ?>" class="btn btn--small"><?= te('adm.inq.contract') ?></a>
            <a href="<?= e(url('admin/invoice.php?' . ($deals ? 'contract=' . (int) $deals[0]['id'] : 'inquiry=' . (int) $r['id']))) ?>" class="btn btn--small"><?= te('adm.inq.invoice') ?></a>
            <?= btn_post('inquiry_link', (int) $r['id'], t('adm.inq.send_link')) ?>
            <?= btn_post('inquiry_read', (int) $r['id'], t($r['is_read'] ? 'adm.inq.mark_new' : 'adm.inq.mark_read')) ?>
            <?= btn_post('inquiry_delete', (int) $r['id'], t('adm.delete'), 'btn btn--small btn--danger', t('adm.confirm_delete')) ?>
          </div>
        </article>
      <?php endforeach; ?>

    <?php /* ================= ZÁKAZNÍCI ================= */ elseif ($tab === 'customers'): ?>
      <h1><?= te('adm.tab.customers') ?></h1>
      <p class="muted"><?= te('adm.cust.hint') ?></p>
      <?php $rows = q("SELECT c.*, (SELECT COUNT(*) FROM inquiries i WHERE i.customer_id = c.id) AS n FROM customers c ORDER BY c.created_at DESC")->fetchAll(); ?>
      <?php if (!$rows): ?><p class="muted"><?= te('adm.cust.empty') ?></p><?php else: ?>
        <div class="table-wrap"><table class="table">
          <tr><th><?= te('adm.cust.th_customer') ?></th><th><?= te('adm.cust.th_contact') ?></th><th><?= te('adm.cust.th_inquiries') ?></th><th><?= te('adm.cust.th_registered') ?></th><th><?= te('adm.cust.th_login') ?></th><th></th></tr>
          <?php foreach ($rows as $c): ?>
            <tr id="c<?= (int) $c['id'] ?>"<?= $c['is_active'] ? '' : ' class="item--off"' ?>>
              <td><strong><?= e($c['name']) ?></strong><?= $c['location'] ? '<br><span class="muted">' . e($c['location']) . '</span>' : '' ?></td>
              <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a> <?= $c['email_verified_at'] ? '<span class="badge">' . te('adm.cust.verified') . '</span>' : '<span class="muted">' . te('adm.cust.unverified') . '</span>' ?>
                <?= $c['phone'] ? '<br><a href="' . e(tel_href($c['phone'])) . '">' . e($c['phone']) . '</a>' : '' ?></td>
              <td><?= (int) $c['n'] ?></td>
              <td><?= e(date('j. n. Y', strtotime($c['created_at']))) ?> · <?= strtoupper(e($c['lang'])) ?></td>
              <td><?= $c['last_login'] ? e(date('j. n. Y H:i', strtotime($c['last_login']))) : '—' ?></td>
              <td class="item__actions">
                <?= btn_post('customer_toggle', (int) $c['id'], t($c['is_active'] ? 'adm.cust.block' : 'adm.cust.unblock')) ?>
                <?= btn_post('customer_delete', (int) $c['id'], t('adm.delete'), 'btn btn--small btn--danger', t('adm.cust.confirm_delete')) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>

    <?php /* ================= HODNOCENÍ ================= */ elseif ($tab === 'reviews'): ?>
      <h1><?= te('adm.tab.reviews') ?></h1>
      <?php $rows = q("SELECT * FROM reviews ORDER BY is_approved, created_at DESC")->fetchAll(); ?>
      <?php if (!$rows): ?><p class="muted"><?= te('adm.rev.empty') ?></p><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <article class="item <?= $r['is_approved'] ? '' : 'item--new' ?>" id="r<?= (int) $r['id'] ?>">
          <div class="item__head">
            <strong><?= e($r['author'] !== '' ? $r['author'] : t('adm.rev.no_name')) ?></strong> <?= $r['rating'] !== null ? '<span class="stars">' . stars((int) $r['rating']) . '</span>' : '<span class="muted">' . te('adm.rev.no_stars') . '</span>' ?>
            <span class="muted"><?= e($r['location']) ?> · <?= e(date('j. n. Y', strtotime($r['created_at']))) ?> · <?= strtoupper(e($r['lang'])) ?> · <?= te($r['is_approved'] ? 'adm.rev.published' : 'adm.rev.pending') ?></span>
          </div>
          <?php if (!empty($r['title'])): ?><p><strong><?= e($r['title']) ?></strong></p><?php endif; ?>
          <p><?= nl2br(e($r['text'])) ?></p>
          <details class="reply">
            <summary><?= te('adm.edit') ?></summary>
            <form method="post" class="form">
              <?= csrf_field() ?><input type="hidden" name="action" value="review_save"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <div class="form__grid">
                <label><?= te('adm.rev.f_author') ?> <span class="muted"><?= te('adm.rev.f_author_hint') ?></span><input type="text" name="author" maxlength="100" value="<?= e($r['author']) ?>"></label>
                <label><?= te('adm.rev.f_title') ?><input type="text" name="title" maxlength="160" value="<?= e((string) ($r['title'] ?? '')) ?>"></label>
                <label><?= te('adm.f_location') ?><input type="text" name="location" maxlength="100" value="<?= e((string) $r['location']) ?>"></label>
                <label><?= te('adm.rev.f_date') ?><input type="date" name="date" value="<?= e(substr((string) $r['created_at'], 0, 10)) ?>"></label>
                <label><?= te('adm.rev.f_stars') ?>
                  <select name="rating">
                    <option value=""<?= $r['rating'] === null ? ' selected' : '' ?>><?= te('adm.rev.no_stars') ?></option>
                    <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= $r['rating'] !== null && (int) $r['rating'] === $i ? ' selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option><?php endfor; ?>
                  </select></label>
                <label><?= te('adm.rev.f_lang') ?>
                  <select name="lang"><?php foreach (LANGS as $l): ?><option value="<?= $l ?>"<?= $r['lang'] === $l ? ' selected' : '' ?>><?= strtoupper($l) ?></option><?php endforeach; ?></select></label>
              </div>
              <label><?= te('adm.rev.f_text') ?><textarea name="text" rows="5" required><?= e($r['text']) ?></textarea></label>
              <label class="check"><input type="checkbox" name="is_approved" value="1"<?= $r['is_approved'] ? ' checked' : '' ?>><span><?= te('adm.rev.f_published') ?></span></label>
              <button class="btn btn--small"><?= te('adm.save') ?></button>
            </form>
          </details>
          <div class="item__actions">
            <?= btn_post('review_toggle', (int) $r['id'], t($r['is_approved'] ? 'adm.rev.hide' : 'adm.rev.approve'), $r['is_approved'] ? 'btn btn--small btn--ghost' : 'btn btn--small') ?>
            <?= btn_post('review_delete', (int) $r['id'], t('adm.delete'), 'btn btn--small btn--danger', t('adm.confirm_delete')) ?>
          </div>
        </article>
      <?php endforeach; ?>

    <?php /* ================= GALERIE ================= */ elseif ($tab === 'gallery'): ?>
      <h1><?= te('adm.tab.gallery') ?></h1>
      <?php
        $cats  = q("SELECT c.*, p.title AS parent_title FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY COALESCE(p.sort, c.sort), c.parent_id IS NOT NULL, c.sort")->fetchAll();
        $projs = q("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll();
        $filter = (string) ($_GET['f'] ?? '');
        $targetOptions = function (string $sel) use ($cats, $projs): string {
            $h = '<optgroup label="' . te('adm.tab.pages') . '">';
            foreach ($cats as $c) $h .= '<option value="c:' . $c['id'] . '"' . ($sel === 'c:' . $c['id'] ? ' selected' : '') . '>' . ($c['parent_title'] ? '— ' : '') . e($c['title']) . '</option>';
            $h .= '</optgroup><optgroup label="' . te('adm.tab.projects') . '">';
            foreach ($projs as $p) $h .= '<option value="p:' . $p['id'] . '"' . ($sel === 'p:' . $p['id'] ? ' selected' : '') . '>' . e($p['title']) . '</option>';
            return $h . '</optgroup>';
        };
      ?>
      <details class="panel" open>
        <summary><strong><?= te('adm.gal.upload_title') ?></strong></summary>
        <form method="post" enctype="multipart/form-data" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="upload">
          <div class="form__grid">
            <label class="field"><?= te('adm.gal.f_files') ?><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
            <label class="field"><?= te('adm.gal.f_target') ?><select name="target"><option value=""><?= te('adm.gal.only_refs') ?></option><?= $targetOptions($filter) ?></select></label>
            <label class="field"><?= te('adm.gal.caption_cs') ?><input type="text" name="caption"></label>
            <label class="field"><?= te('adm.gal.caption_en') ?><input type="text" name="caption_en"></label>
          </div>
          <label class="check"><input type="checkbox" name="in_references" value="1" checked><span><?= te('adm.gal.f_in_refs') ?></span></label>
          <button class="btn"><?= te('adm.gal.upload_btn') ?></button>
        </form>
      </details>

      <form method="get" class="filter">
        <input type="hidden" name="tab" value="gallery">
        <select name="f" onchange="this.form.submit()"><option value=""><?= te('adm.gal.all') ?></option><option value="none" <?= $filter === 'none' ? 'selected' : '' ?>><?= te('adm.gal.refs_only') ?></option><?= $targetOptions($filter) ?></select>
      </form>
      <?php
        $where = ''; $params = [];
        if (preg_match('~^([cp]):(\d+)$~', $filter, $m)) { $where = 'WHERE ' . ($m[1] === 'c' ? 'g.category_id' : 'g.project_id') . ' = ?'; $params[] = (int) $m[2]; }
        if ($filter === 'none') $where = 'WHERE g.category_id IS NULL AND g.project_id IS NULL';
        $imgs = q("SELECT g.*, c.title AS cat, p.title AS proj, c.cover_image AS ccover, p.cover_image AS pcover FROM gallery_images g LEFT JOIN categories c ON c.id = g.category_id LEFT JOIN projects p ON p.id = g.project_id $where ORDER BY g.category_id, g.project_id, g.sort, g.id LIMIT 1000", $params)->fetchAll();
      ?>
      <p class="muted"><?= te('adm.gal.count', ['count' => count($imgs)]) ?></p>
      <div class="admin-gallery">
        <?php foreach ($imgs as $im): $isCover = $im['file'] === $im['ccover'] || $im['file'] === $im['pcover']; ?>
          <figure class="<?= $isCover ? 'is-cover' : '' ?>">
            <a href="<?= e(image_url($im['file'])) ?>" target="_blank"><img src="<?= e(image_url($im['file'])) ?>" alt="" loading="lazy"></a>
            <figcaption><?= e($im['cat'] ?: ($im['proj'] ?: t('adm.gal.reference'))) ?><?= $isCover ? ' · <strong>' . te('adm.gal.cover') . '</strong>' : '' ?></figcaption>
            <form method="post" class="form form--mini">
              <?= csrf_field() ?><input type="hidden" name="action" value="image_save"><input type="hidden" name="id" value="<?= (int) $im['id'] ?>"><input type="hidden" name="f" value="<?= e($filter) ?>">
              <input type="text" name="caption" value="<?= e($im['caption']) ?>" placeholder="<?= te('adm.gal.caption_cs') ?>">
              <input type="text" name="caption_en" value="<?= e($im['caption_en']) ?>" placeholder="<?= te('adm.gal.caption_en') ?>">
              <div class="row"><input type="text" name="sort" value="<?= (int) $im['sort'] ?>" title="<?= te('adm.sort') ?>" class="w-num">
              <label class="check"><input type="checkbox" name="in_references" value="1" <?= $im['in_references'] ? 'checked' : '' ?>><span><?= te('adm.gal.ref_short') ?></span></label>
              <button class="btn btn--small btn--ghost"><?= te('adm.save') ?></button></div>
            </form>
            <div class="item__actions">
              <?php if (($im['category_id'] || $im['project_id']) && !$isCover): ?><?= btn_post('image_cover', (int) $im['id'], t('adm.gal.set_cover'), 'btn btn--small btn--ghost', '', ['f' => $filter]) ?><?php endif; ?>
              <?= btn_post('image_delete', (int) $im['id'], t('adm.delete'), 'btn btn--small btn--danger', t('adm.gal.confirm_delete'), ['f' => $filter]) ?>
            </div>
          </figure>
        <?php endforeach; ?>
      </div>

    <?php /* ================= STRÁNKY ================= */ elseif ($tab === 'pages'): ?>
      <?php $editId = (int) ($_GET['edit'] ?? 0); $c = $editId ? q("SELECT * FROM categories WHERE id = ?", [$editId])->fetch() : null; ?>
      <?php if ($c): ?>
        <p><a href="<?= e(admin_url('tab=pages')) ?>"><?= te('adm.back') ?></a> · <a href="<?= e(item_url($c, 'cs')) ?>" target="_blank">CZ ↗</a> · <a href="<?= e(item_url($c, 'en')) ?>" target="_blank">EN ↗</a> · <a href="<?= e(admin_url('tab=gallery&f=c:' . $c['id'])) ?>"><?= te('adm.pages.photos') ?></a></p>
        <h1><?= e($c['title']) ?></h1>
        <form method="post" class="form panel">
          <?= csrf_field() ?><input type="hidden" name="action" value="category_save"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <div class="bi"><div class="bi__label"><?= te('adm.pages.f_url') ?></div><label><span class="flag">CZ</span><input type="text" value="/<?= e($c['slug']) ?>/" disabled></label><label><span class="flag">EN</span><input type="text" name="slug_en" value="<?= e($c['slug_en']) ?>" placeholder="<?= te('adm.pages.slug_ph') ?>"></label></div>
          <?= bi(t('adm.pages.f_title'), 'title', $c) ?>
          <?= bi(t('adm.pages.f_menu'), 'menu_title', $c) ?>
          <?= bi(t('adm.pages.f_h1'), 'headline', $c) ?>
          <?= bi(t('adm.pages.f_intro'), 'intro', $c, 'text') ?>
          <?= bi(t('adm.pages.f_body'), 'body', $c, 'html', 10) ?>
          <?= bi(t('adm.pages.f_meta'), 'meta_desc', $c, 'text', 2) ?>
          <div class="form__grid">
            <label class="field"><?= te('adm.sort') ?><input type="text" name="sort" value="<?= (int) $c['sort'] ?>"></label>
            <label class="check field"><input type="checkbox" name="is_active" value="1" <?= $c['is_active'] ? 'checked' : '' ?>><span><?= te('adm.pages.f_active') ?></span></label>
          </div>
          <button class="btn"><?= te('adm.save') ?></button>
        </form>
      <?php else: ?>
        <h1><?= te('adm.tab.pages') ?></h1>
        <div class="table-wrap"><table class="table">
          <tr><th><?= te('adm.pages.th_page') ?></th><th>CZ URL</th><th>EN URL</th><th><?= te('adm.pages.th_photos') ?></th></tr>
          <?php foreach (q("SELECT c.*, p.title AS parent_title, (SELECT COUNT(*) FROM gallery_images g WHERE g.category_id = c.id) AS photos FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY COALESCE(p.sort, c.sort), c.parent_id IS NOT NULL, c.sort")->fetchAll() as $c): ?>
            <tr>
              <td><?= $c['parent_title'] ? '<span class="muted">— </span>' : '' ?><a href="<?= e(admin_url('tab=pages&edit=' . $c['id'])) ?>"><?= e($c['title']) ?></a><?= $c['is_active'] ? '' : ' <span class="muted">' . te('adm.hidden_paren') . '</span>' ?><?= $c['title_en'] ? '' : ' <span class="badge">' . te('adm.missing_en') . '</span>' ?></td>
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
        <p><a href="<?= e(admin_url('tab=projects')) ?>"><?= te('adm.back') ?></a><?php if ($p): ?> · <a href="<?= e(item_url($p, 'cs')) ?>" target="_blank">CZ ↗</a> · <a href="<?= e(item_url($p, 'en')) ?>" target="_blank">EN ↗</a><?php endif; ?></p>
        <h1><?= $p ? e($p['title']) : te('adm.proj.new') ?></h1>
        <form method="post" class="form panel">
          <?= csrf_field() ?><input type="hidden" name="action" value="project_save"><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
          <?= bi(t('adm.proj.f_title'), 'title', $p ?: null) ?>
          <div class="bi"><div class="bi__label"><?= te('adm.proj.f_url') ?></div><label><span class="flag">CZ</span><input type="text" name="slug" value="<?= e($p['slug'] ?? '') ?>"></label><label><span class="flag">EN</span><input type="text" name="slug_en" value="<?= e($p['slug_en'] ?? '') ?>"></label></div>
          <label><?= te('adm.f_location') ?><input type="text" name="location" value="<?= e($p['location'] ?? '') ?>"></label>
          <?= bi(t('adm.proj.f_summary'), 'summary', $p ?: null, 'text', 2) ?>
          <?= bi(t('adm.proj.f_body'), 'body', $p ?: null, 'html', 10) ?>
          <label class="check"><input type="checkbox" name="is_published" value="1" <?= ($p['is_published'] ?? 1) ? 'checked' : '' ?>><span><?= te('adm.proj.f_published') ?></span></label>
          <button class="btn"><?= te('adm.save') ?></button>
        </form>
        <?php if ($p): ?>
          <p><a href="<?= e(admin_url('tab=gallery&f=p:' . $p['id'])) ?>"><?= te('adm.proj.photos') ?></a></p>
          <?= btn_post('project_delete', (int) $p['id'], t('adm.proj.delete'), 'btn btn--small btn--danger', t('adm.proj.confirm_delete')) ?>
        <?php endif; ?>
      <?php else: ?>
        <h1><?= te('adm.tab.projects') ?> <a href="<?= e(admin_url('tab=projects&edit=0')) ?>" class="btn btn--small"><?= te('adm.proj.add') ?></a></h1>
        <div class="table-wrap"><table class="table">
          <?php foreach (q("SELECT p.*, (SELECT COUNT(*) FROM gallery_images g WHERE g.project_id = p.id) AS photos FROM projects p ORDER BY created_at DESC")->fetchAll() as $p): ?>
            <tr>
              <td><a href="<?= e(admin_url('tab=projects&edit=' . $p['id'])) ?>"><?= e($p['title']) ?></a><?= $p['is_published'] ? '' : ' <span class="muted">' . te('adm.proj.draft') . '</span>' ?><?= $p['title_en'] ? '' : ' <span class="badge">' . te('adm.missing_en') . '</span>' ?></td>
              <td class="muted"><?= e($p['location']) ?></td>
              <td class="muted"><?= te('adm.gal.count', ['count' => (int) $p['photos']]) ?></td>
            </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>

    <?php /* ================= FORMULÁŘ ================= */ elseif ($tab === 'form'): ?>
      <h1><?= te('adm.form.title') ?></h1>
      <p class="muted"><?= te('adm.form.intro') ?></p>
      <?php
        $fields = q("SELECT * FROM form_fields ORDER BY sort, id")->fetchAll();
        $types = []; foreach (['text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox', 'date'] as $ft) $types[$ft] = t('adm.ftype.' . $ft);
        // formulář pole: jen to podstatné; nápověda a možnosti se ukážou podle typu, klíč a šířka v „Pokročilé“
        $fieldForm = function (?array $f) use ($types): string {
            $sys = $f && in_array($f['fkey'], FORM_SYSTEM_FIELDS, true);
            $lockType = $f && in_array($f['fkey'], ['name', 'email'], true);
            ob_start(); ?>
            <form method="post" class="form ff-form" data-ff>
              <?= csrf_field() ?><input type="hidden" name="action" value="field_save"><input type="hidden" name="id" value="<?= (int) ($f['id'] ?? 0) ?>">
              <div class="form__grid">
                <label class="field"><span><span class="flag">CZ</span> <?= te('adm.form.f_label') ?> *</span><input type="text" name="label_cs" value="<?= e($f['label_cs'] ?? '') ?>" required></label>
                <label class="field"><span><span class="flag">EN</span> <?= te('adm.form.f_label') ?></span><input type="text" name="label_en" value="<?= e($f['label_en'] ?? '') ?>" placeholder="<?= te('adm.settings.same_as_cz') ?>"></label>
                <label class="field"><?= te('adm.f_type') ?>
                  <select name="type" data-ff-type<?= $lockType ? ' disabled' : '' ?>><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"<?= ($f['type'] ?? 'text') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
                <?php if (!$f): ?><label class="check field ff-check"><input type="checkbox" name="is_required" value="1"><span><?= te('adm.form.f_required') ?></span></label><?php endif; ?>
              </div>
              <div class="form__grid" data-ff-show="text email tel number textarea">
                <label class="field"><span><span class="flag">CZ</span> <?= te('adm.form.f_placeholder') ?></span><input type="text" name="placeholder_cs" value="<?= e($f['placeholder_cs'] ?? '') ?>"></label>
                <label class="field"><span><span class="flag">EN</span> <?= te('adm.form.f_placeholder') ?></span><input type="text" name="placeholder_en" value="<?= e($f['placeholder_en'] ?? '') ?>"></label>
              </div>
              <div class="form__grid" data-ff-show="select">
                <label class="field"><span><span class="flag">CZ</span> <?= te('adm.form.f_options') ?> <small class="hint"><?= te('adm.form.options_hint') ?></small></span><textarea name="options_cs" rows="4"><?= e($f['options_cs'] ?? '') ?></textarea></label>
                <label class="field"><span><span class="flag">EN</span> <?= te('adm.form.f_options') ?></span><textarea name="options_en" rows="4"><?= e($f['options_en'] ?? '') ?></textarea></label>
              </div>
              <details class="ff-adv">
                <summary><?= te('adm.form.advanced') ?></summary>
                <div class="form__grid">
                  <label class="field"><?= te('adm.form.f_key') ?>
                    <input type="text" name="fkey" value="<?= e($f['fkey'] ?? '') ?>" placeholder="<?= te('adm.form.f_key_ph') ?>"<?= $sys ? ' readonly' : '' ?>>
                    <?php if ($sys): ?><small class="hint"><?= te('adm.form.key_locked') ?></small><?php endif; ?></label>
                  <label class="check field ff-check"><input type="hidden" name="is_wide" value="<?= $f ? '0' : '' ?>"><input type="checkbox" name="is_wide" value="1"<?= !empty($f['is_wide']) ? ' checked' : '' ?>><span><?= te('adm.form.f_wide') ?></span></label>
                </div>
              </details>
              <button class="btn btn--small"><?= te($f ? 'adm.form.save' : 'adm.form.add') ?></button>
            </form>
            <?php return (string) ob_get_clean();
        };
      ?>
      <ol class="ff-list">
        <?php foreach ($fields as $i => $f): $sys = in_array($f['fkey'], FORM_SYSTEM_FIELDS, true); $locked = in_array($f['fkey'], ['name', 'email'], true); ?>
          <li class="ff<?= $f['is_active'] ? '' : ' ff--off' ?>" id="f<?= (int) $f['id'] ?>">
            <div class="ff__row">
              <div class="ff__name">
                <strong><?= e($f['label_cs']) ?></strong>
                <span class="muted"><?= e($types[$f['type']] ?? $f['type']) ?><?= $f['label_en'] ? ' · EN: ' . e($f['label_en']) : ' · <span class="badge">' . te('adm.missing_en') . '</span>' ?></span>
              </div>
              <div class="ff__toggles">
                <?php if ($locked): ?>
                  <span class="pill is-on is-locked" title="<?= te('adm.form.err_system') ?>"><?= te('adm.form.f_required') ?></span>
                  <span class="pill is-on is-locked" title="<?= te('adm.form.err_system') ?>"><?= te('adm.form.visible') ?></span>
                <?php else: ?>
                  <?= btn_post('field_toggle', (int) $f['id'], t('adm.form.f_required'), 'pill' . ($f['is_required'] ? ' is-on' : ''), '', ['what' => 'required']) ?>
                  <?= btn_post('field_toggle', (int) $f['id'], t($f['is_active'] ? 'adm.form.visible' : 'adm.form.hidden_state'), 'pill' . ($f['is_active'] ? ' is-on' : ''), '', ['what' => 'active']) ?>
                <?php endif; ?>
              </div>
              <div class="ff__order">
                <?= $i > 0 ? btn_post('field_move', (int) $f['id'], '↑', 'btn btn--small btn--ghost ff__move', '', ['dir' => 'up']) : '<span class="ff__move"></span>' ?>
                <?= $i < count($fields) - 1 ? btn_post('field_move', (int) $f['id'], '↓', 'btn btn--small btn--ghost ff__move', '', ['dir' => 'down']) : '<span class="ff__move"></span>' ?>
              </div>
            </div>
            <details class="ff__edit">
              <summary><?= te('adm.edit') ?></summary>
              <?= $fieldForm($f) ?>
              <?php if (!$sys): ?><div class="item__actions"><?= btn_post('field_delete', (int) $f['id'], t('adm.form.delete'), 'btn btn--small btn--danger', t('adm.form.confirm_delete')) ?></div>
              <?php else: ?><p class="hint"><?= te('adm.form.system_note') ?></p><?php endif; ?>
            </details>
          </li>
        <?php endforeach; ?>
      </ol>
      <details class="panel ff-new">
        <summary><strong><?= te('adm.form.add_title') ?></strong></summary>
        <?= $fieldForm(null) ?>
      </details>
      <script>
        // nápověda v poli jen u textových typů, možnosti jen u výběru ze seznamu
        document.querySelectorAll('[data-ff]').forEach(function (form) {
          var sel = form.querySelector('[data-ff-type]');
          var sync = function () {
            form.querySelectorAll('[data-ff-show]').forEach(function (box) {
              box.hidden = box.getAttribute('data-ff-show').split(' ').indexOf(sel.value) < 0;
            });
          };
          sel.addEventListener('change', sync); sync();
        });
      </script>

    <?php /* ================= TEXTY CZ/EN ================= */ elseif ($tab === 'texts'): ?>
      <?php
        $groups = [];
        foreach (['nav', 'home', 'cta', 'category', 'references', 'reviews', 'pricing', 'contact', 'privacy', 'contract', 'form', 'acc', 'status', 'mail', 'consent', 'misc'] as $g) $groups[$g] = t('adm.grp.' . $g);
        $all = props();
        foreach ($all as $r) if (!isset($groups[$r['grp']]) && !in_array($r['grp'], ['site', 'images', 'seo', 'sys', 'adm', 'ga', 'inv'], true)) $groups[$r['grp']] = $r['grp'];
        $grp = (string) ($_GET['grp'] ?? 'home');
        if (!isset($groups[$grp])) $grp = 'home';
      ?>
      <h1><?= te('adm.texts.title') ?></h1>
      <p class="muted"><?= props()['adm.texts.hint']['value_cs'] ?? 'adm.texts.hint' /* HTML, proměnné se nedosazují */ ?></p>
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
        <button class="btn"><?= te('adm.texts.save') ?></button>
      </form>
      <details class="panel">
        <summary><strong><?= te('adm.texts.add_title') ?></strong></summary>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="prop_add">
          <div class="form__grid">
            <label class="field"><?= te('adm.texts.f_key') ?><input type="text" name="pkey" required></label>
            <label class="field"><?= te('adm.f_type') ?><select name="type"><option value="line"><?= te('adm.texts.type_line') ?></option><option value="text"><?= te('adm.texts.type_text') ?></option><option value="html">HTML</option></select></label>
            <label class="field"><span><span class="flag">CZ</span> <?= te('adm.texts.f_value') ?></span><textarea name="value_cs" rows="2"></textarea></label>
            <label class="field"><span><span class="flag">EN</span> <?= te('adm.texts.f_value') ?></span><textarea name="value_en" rows="2"></textarea></label>
          </div>
          <p class="muted"><?= props()['adm.texts.usage']['value_cs'] ?? 'adm.texts.usage' /* HTML */ ?></p>
          <button class="btn btn--small"><?= te('adm.add') ?></button>
        </form>
      </details>

    <?php /* ================= NASTAVENÍ ================= */ elseif ($tab === 'settings'): ?>
      <?php
        $sgroups = ['site' => t('adm.sgrp.site'), 'images' => t('adm.sgrp.images'), 'seo' => t('adm.sgrp.seo'),
                    'robots' => t('adm.sgrp.robots'), 'ga' => t('adm.sgrp.ga'), 'inv' => t('adm.sgrp.inv')];
        $grp = (string) ($_GET['grp'] ?? 'site');
        if (!isset($sgroups[$grp])) $grp = 'site';
        $all = props();
        // rozložení záložek: sekce => klíče vlastností (nezařazené klíče skupiny se ukážou v „Ostatní“)
        $layout = [
            'site'   => ['adm.sec.company'  => ['site.name', 'site.tagline', 'site.owner', 'site.ico', 'site.dic', 'site.address', 'site.years'],
                         'adm.sec.contacts' => ['site.phone', 'site.email', 'site.whatsapp', 'site.hours'],
                         'adm.sec.social'   => ['site.facebook', 'site.instagram', 'site.fb_feed'],
                         'adm.sec.web'      => ['site.domain', 'site.geo', 'site.gdpr_url', 'site.accounts', 'site.email_protect']],
            'images' => ['adm.sec.images'   => array_keys(array_filter($all, fn($r) => $r['grp'] === 'images'))],
            'seo'    => ['adm.sec.meta'     => ['seo.keywords', 'seo.og_image'],
                         'adm.sec.seo'      => ['seo.indexing', 'seo.jsonld', 'seo.microdata', 'seo.rdfa']],
            'robots' => ['adm.sec.robots'   => ['seo.robots_extra', 'seo.sitemap_auto']],
            'ga'     => ['adm.sec.ga'       => ['ga.enabled', 'ga.id']],
            'inv'    => ['adm.sec.inv_bank' => ['site.bank', 'inv.iban'],
                         'adm.sec.inv_tax'  => ['inv.vat_payer', 'site.dic', 'inv.vat_rate', 'inv.registry'],
                         'adm.sec.inv_doc'  => ['inv.format', 'inv.due', 'inv.note']],
        ];
        $listed = [];
        foreach ($layout as $secs) foreach ($secs as $keys) $listed = array_merge($listed, $keys);
        $sections = $layout[$grp];
        $prefix = ['site' => 'site.', 'seo' => 'seo.', 'ga' => 'ga.', 'inv' => 'inv.'][$grp] ?? null;
        if ($prefix) {
            $rest = array_values(array_filter(array_keys($all), fn($k) => str_starts_with($k, $prefix) && !in_array($k, $listed, true)));
            if ($rest) $sections['adm.sec.other'] = $rest;
        }
        $bilingual = ['site.name', 'site.tagline', 'site.hours', 'seo.keywords'];
        $inputType = ['site.email' => 'email', 'site.phone' => 'tel', 'site.whatsapp' => 'tel', 'site.facebook' => 'url', 'site.instagram' => 'url',
                      'site.gdpr_url' => 'url', 'site.domain' => 'url', 'site.years' => 'number', 'inv.due' => 'number', 'inv.vat_rate' => 'number'];
        $placeholder = ['ga.id' => 'G-XXXXXXXXXX', 'site.domain' => 'https://www.betonovesterky-brno.cz', 'site.bank' => '123456789/0800', 'inv.format' => 'RRRR###'];
        // „Popisek (nápověda)“ → popisek + nápověda pod polem
        $split = function (array $r, string $key): array {
            $l = (string) ($r['label'] ?: $key);
            return preg_match('~^(.*?)\s*\((.+)\)$~u', $l, $m) ? [$m[1], $m[2]] : [$l, ''];
        };
        $field = function (string $key) use ($all, $bilingual, $inputType, $placeholder, $split): string {
            $r = $all[$key] ?? null;
            if (!$r) return '';
            [$label, $hint] = $split($r, $key);
            $n = 'p[' . e($key) . ']';
            $hintH = $hint !== '' ? '<small class="hint">' . e($hint) . '</small>' : '';
            switch ($r['type']) {
                case 'image':
                    $src = image_url($r['value_cs']);
                    return '<div class="img-prop field--wide"><div class="img-prop__preview">' . ($src ? '<img src="' . e($src) . '" alt="">' : '<span class="muted">' . te('adm.settings.no_image') . '</span>') . '</div>'
                         . '<div><strong>' . e($label) . '</strong>' . $hintH . '<input type="file" name="img[' . e($key) . ']" accept="image/*,.ico">'
                         . ($src ? '<label class="check"><input type="checkbox" name="img_remove[' . e($key) . ']" value="1"><span>' . te('adm.settings.remove') . '</span></label>' : '') . '</div></div>';
                case 'bool':
                    return '<input type="hidden" name="bools[]" value="' . e($key) . '"><label class="check field--wide"><input type="checkbox" name="' . $n . '[cs]" value="1"' . ($r['value_cs'] === '1' ? ' checked' : '') . '>'
                         . '<span>' . e($label) . ($hint !== '' ? ' <span class="muted">(' . e($hint) . ')</span>' : '') . '</span></label>';
                case 'text':
                case 'html':
                    return '<label class="field field--wide"><span class="field__label">' . e($label) . '</span>' . $hintH
                         . '<textarea name="' . $n . '[cs]" rows="4"' . ($key === 'seo.robots_extra' ? ' class="code"' : '') . '>' . e($r['value_cs']) . '</textarea></label>';
            }
            $type = $inputType[$key] ?? 'text';
            $ph = isset($placeholder[$key]) ? ' placeholder="' . e($placeholder[$key]) . '"' : '';
            if (in_array($key, $bilingual, true)) {
                return '<div class="bi field--wide"><div class="bi__label">' . e($label) . $hintH . '</div>'
                     . '<label><span class="flag">CZ</span><input type="' . $type . '" name="' . $n . '[cs]" value="' . e($r['value_cs']) . '"' . $ph . '></label>'
                     . '<label><span class="flag">EN</span><input type="' . $type . '" name="' . $n . '[en]" value="' . e($r['value_en']) . '" placeholder="' . te('adm.settings.same_as_cz') . '"></label></div>';
            }
            return '<label class="field"><span class="field__label">' . e($label) . '</span>'
                 . '<input type="' . $type . '" name="' . $n . '[cs]" value="' . e($r['value_cs']) . '"' . $ph . ($type === 'number' ? ' min="0"' : '') . '>' . $hintH . '</label>';
        };
      ?>
      <h1><?= te('adm.tab.settings') ?></h1>
      <nav class="subtabs"><?php foreach ($sgroups as $k => $v): ?><a href="<?= e(admin_url("tab=settings&grp=$k")) ?>" class="<?= $grp === $k ? 'is-active' : '' ?>"><?= e($v) ?></a><?php endforeach; ?></nav>

      <?php if ($grp === 'ga'): $gaId = trim((string) ($all['ga.id']['value_cs'] ?? '')); ?>
        <div class="alert alert--info settings-note"><?= t('adm.ga.info') /* HTML */ ?></div>
        <?php if (prop_bool('ga.enabled') && !preg_match('~^G-[A-Z0-9]{4,}$~', $gaId)): ?><div class="alert alert--err settings-note"><?= te('adm.ga.err_id') ?></div><?php endif; ?>
      <?php endif; ?>
      <?php if ($grp === 'inv'): $iban = invoice_iban(); ?>
        <div class="alert <?= $iban ? 'alert--ok' : 'alert--err' ?> settings-note"><?= $iban ? te('adm.inv.iban_ok', ['iban' => iban_format($iban)]) : te('adm.inv.iban_missing') ?></div>
      <?php endif; ?>

      <form method="post" class="form panel settings" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="props_save"><input type="hidden" name="grp" value="<?= e($grp) ?>">
        <?php foreach ($sections as $secKey => $keys): $html = implode('', array_map($field, $keys)); if ($html === '') continue; ?>
          <fieldset class="settings-sec">
            <legend><?= te($secKey) ?></legend>
            <div class="form__grid"><?= $html ?></div>
          </fieldset>
        <?php endforeach; ?>
        <button class="btn"><?= te('adm.save') ?></button>
      </form>

      <?php if ($grp === 'site'): ?>
        <div class="panel" id="mail">
          <h2><?= te('adm.mail.title') ?></h2>
          <p><?= smtp_on() ? te('adm.mail.via_smtp', ['host' => (string) $CONFIG['smtp']['host']]) : te('adm.mail.via_php') ?></p>
          <p class="muted"><?= te('adm.mail.recaptcha', ['state' => t(recaptcha_on() ? 'adm.mail.on' : 'adm.mail.off')]) ?></p>
          <?= btn_post('mail_test', 0, t('adm.mail.test_btn', ['email' => (string) ($CONFIG['notify_email'] ?? '') ?: site('email')]), 'btn btn--small') ?>
        </div>
      <?php endif; ?>

      <?php if ($grp === 'robots'): $hasFiles = is_file(ROOT_DIR . '/robots.txt') || is_file(ROOT_DIR . '/sitemap.xml'); ?>
        <div class="panel">
          <h2><?= te('adm.seo.title') ?></h2>
          <p><?= te('adm.seo.state') ?> <?= $hasFiles
              ? t('adm.seo.state_static', ['date' => e(date('j. n. Y H:i', (int) @filemtime(ROOT_DIR . '/sitemap.xml')))])
              : t('adm.seo.state_dynamic') ?>.
            <?= te('adm.seo.links') ?> <a href="<?= e(url('robots.txt')) ?>" target="_blank">robots.txt</a> · <a href="<?= e(url('sitemap.xml')) ?>" target="_blank">sitemap.xml</a></p>
          <div class="item__actions">
            <?= btn_post('seo_generate', 0, t($hasFiles ? 'adm.seo.regenerate' : 'adm.seo.generate'), 'btn btn--small') ?>
            <?php if ($hasFiles): ?><?= btn_post('seo_remove', 0, t('adm.seo.remove'), 'btn btn--small btn--ghost') ?><?php endif; ?>
          </div>
          <h3><?= te('adm.seo.robots_preview') ?></h3>
          <pre class="pre"><?= e(build_robots()) ?></pre>
          <h3><?= te('adm.seo.sitemap_count', ['count' => count(sitemap_urls()) * 2]) ?></h3>
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
