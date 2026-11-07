<?php
$title    = t('reviews.title');
$metaDesc = t('reviews.meta_desc');
$errors   = [];
$self     = route_url('reviews');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'review') {
    if (!empty($_POST['website'])) redirect($self);
    if (!csrf_ok()) $errors[] = t('form.err_csrf');
    $author = post('author', 100);
    $loc    = post('location', 100);
    $text   = post('text', 3000);
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    if ($author === '') $errors[] = t('reviews.err_name');
    if (mb_strlen($text) < 10) $errors[] = t('reviews.err_text');
    if (!$errors) {
        q("INSERT INTO reviews (author, location, rating, text, lang) VALUES (?,?,?,?,?)", [$author, $loc, $rating, $text, lang()]);
        flash('review', t('reviews.thanks'));
        redirect($self . '#napsat');
    }
}

$reviews = q("SELECT * FROM reviews WHERE is_approved = 1 ORDER BY created_at DESC")->fetchAll();
// Průměr jen z hodnocení s hvězdami (převzatá hodnocení bez hvězd mají rating NULL)
$rated = array_values(array_filter($reviews, fn($r) => $r['rating'] !== null));
$avg = $rated ? array_sum(array_column($rated, 'rating')) / count($rated) : 0;
$srcUrl = t('reviews.source_url');
$source = preg_match('~^https?://~', $srcUrl)
    ? str_replace('{link}', '<a href="' . e($srcUrl) . '" target="_blank" rel="noopener">' . te('reviews.source_name') . '</a>', te('reviews.source'))
    : '';
$avgTxt = number_format($avg, 1, lang() === 'en' ? '.' : ',', '');
$ok  = flash('review');
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([[t('nav.reviews'), null]]) ?>
    <h1><?= e($title) ?></h1>
    <?php if ($rated): ?>
      <p class="lead"><span class="stars" aria-hidden="true"><?= stars((int) round($avg)) ?></span>
      <?= te('reviews.summary', ['avg' => $avgTxt, 'count' => count($rated)]) ?></p>
    <?php endif; ?>
    <?php if ($source !== ''): ?><p class="reviews-source"><?= $source ?></p><?php endif; ?>
  </div>
</section>

<section class="section section--tight">
  <div class="container"<?= $rated ? md('itemscope itemtype="https://schema.org/HomeAndConstructionBusiness" itemid="' . e(business_id()) . '"') : '' ?>>
    <?php if ($reviews): ?>
      <?php if ($rated && schema_on('microdata')): ?>
        <meta itemprop="name" content="<?= e(site('name')) ?>">
        <meta itemprop="telephone" content="<?= e(site('phone')) ?>">
        <link itemprop="url" href="<?= e(abs_url(route_url('home'))) ?>">
        <div itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
          <meta itemprop="ratingValue" content="<?= number_format($avg, 1, '.', '') ?>">
          <meta itemprop="reviewCount" content="<?= count($rated) ?>">
          <meta itemprop="bestRating" content="5"><meta itemprop="worstRating" content="1">
        </div>
      <?php endif; ?>
      <div class="reviews">
        <?php foreach ($reviews as $r): $mdOn = $r['rating'] !== null; /* microdata jen u hodnocení s hvězdami */ ?>
          <blockquote class="review"<?= $mdOn ? md('itemprop="review" itemscope itemtype="https://schema.org/Review"') : '' ?>>
            <?php if ($mdOn): ?><div class="stars" aria-label="<?= (int) $r['rating'] ?> <?= te('reviews.of5') ?>"<?= md('itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating"') ?>><?= stars((int) $r['rating']) ?><?php if (schema_on('microdata')): ?><meta itemprop="ratingValue" content="<?= (int) $r['rating'] ?>"><meta itemprop="bestRating" content="5"><?php endif; ?></div><?php endif; ?>
            <?php if (!empty($r['title'])): ?><h3 class="review__title"<?= $mdOn ? md('itemprop="name"') : '' ?>><?= e($r['title']) ?></h3><?php endif; ?>
            <p<?= $mdOn ? md('itemprop="reviewBody"') : '' ?>><?= nl2br(e($r['text'])) ?></p>
            <footer><strong<?= $mdOn ? md('itemprop="author" itemscope itemtype="https://schema.org/Person"') : '' ?>><span<?= $mdOn ? md('itemprop="name"') : '' ?>><?= e($r['author'] !== '' ? $r['author'] : t('reviews.anonymous')) ?></span></strong><?php if ($r['location']): ?> · <?= e($r['location']) ?><?php endif; ?>
              · <time datetime="<?= e(substr((string) $r['created_at'], 0, 10)) ?>"><?= e(date(lang() === 'en' ? 'j M Y' : 'j. n. Y', strtotime((string) $r['created_at']))) ?></time>
              <?php if ($mdOn && schema_on('microdata')): ?><meta itemprop="datePublished" content="<?= e(substr((string) $r['created_at'], 0, 10)) ?>"><meta itemprop="inLanguage" content="<?= e($r['lang']) ?>"><?php endif; ?></footer>
          </blockquote>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted"><?= te('reviews.empty') ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="section section--tint" id="napsat">
  <div class="container narrow">
    <h2><?= te('reviews.form_title') ?></h2>
    <form method="post" class="form" novalidate>
      <?php if ($ok): ?><div class="alert alert--ok" role="status"><?= e($ok) ?></div><?php endif; ?>
      <?php if ($errors): ?><div class="alert alert--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="review">
      <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="form__grid">
        <label class="field"><?= te('reviews.f_name') ?><input type="text" name="author" required value="<?= e(post('author')) ?>" autocomplete="name"></label>
        <label class="field"><?= te('reviews.f_location') ?><input type="text" name="location" value="<?= e(post('location')) ?>"></label>
      </div>
      <fieldset class="rating">
        <legend><?= te('reviews.f_rating') ?></legend>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" name="rating" id="r<?= $i ?>" value="<?= $i ?>" <?= ((int) ($_POST['rating'] ?? 5)) === $i ? 'checked' : '' ?>>
          <label for="r<?= $i ?>" title="<?= $i ?> <?= te('reviews.of5') ?>">★</label>
        <?php endfor; ?>
      </fieldset>
      <label><?= te('reviews.f_text') ?><textarea name="text" rows="5" required><?= e(post('text')) ?></textarea></label>
      <button class="btn" type="submit"><?= te('reviews.submit') ?></button>
    </form>
  </div>
</section>
