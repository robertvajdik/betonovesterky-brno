<?php
/** @var array $category */
$title    = tr($category, 'title');
$metaDesc = tr($category, 'meta_desc') ?: tr($category, 'intro');
$ogImage  = $category['cover_image'];
$images   = gallery_for('category', (int) $category['id']);
$parent   = $category['parent_id'] ? q("SELECT * FROM categories WHERE id = ?", [$category['parent_id']])->fetch() : null;
$children = subcategories((int) $category['id']);
$siblings = $parent ? subcategories((int) $parent['id']) : [];
$body     = tr($category, 'body');

$schema[] = array_filter([
    '@type'       => 'Service',
    '@id'         => abs_url(item_url($category)) . '#service',
    'name'        => $title,
    'serviceType' => $parent ? tr($parent, 'title') : $title,
    'description' => $metaDesc,
    'url'         => abs_url(item_url($category)),
    'image'       => array_values(array_filter(array_map(fn($im) => abs_image($im['file']), array_slice($images, 0, 10)))) ?: abs_image($category['cover_image']),
    'provider'    => ['@id' => business_id()],
    'areaServed'  => address_parts()['city'] ?: null,
]);
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs(array_merge($parent ? [[tr($parent, 'title'), item_url($parent)]] : [], [[tr($category, 'menu_title') ?: $title, null]])) ?>
    <h1><?= e(tr($category, 'headline') ?: $title) ?></h1>
    <?php if (tr($category, 'intro')): ?><p class="lead"><?= e(tr($category, 'intro')) ?></p><?php endif; ?>
  </div>
</section>

<section class="section section--tight">
  <div class="container split">
    <div class="prose">
      <?= $body !== '' ? $body /* HTML z administrace */ : '<p>' . te('cat.default_body') . '</p>' ?>
      <a href="<?= e(route_url('contact')) ?>" class="btn"><?= te('cat.cta_button') ?></a>
    </div>
    <div><?= img($category['cover_image'], $title, 'split__img', true) ?></div>
  </div>
</section>

<?php if (!empty($category['before_image']) && !empty($category['after_image'])
          && image_url($category['before_image']) && image_url($category['after_image'])): ?>
<section class="section section--tint">
  <div class="container">
    <div class="section__head"><h2><?= te('cat.compare_title') ?></h2><p class="muted"><?= te('cat.compare_hint') ?></p></div>
    <figure class="compare" data-compare style="--pos: 50%">
      <?= img($category['after_image'], t('cat.compare_after') . ' – ' . $title, 'compare__img') ?>
      <div class="compare__before">
        <?= img($category['before_image'], t('cat.compare_before') . ' – ' . $title, 'compare__img') ?>
      </div>
      <span class="compare__label compare__label--before"><?= te('cat.compare_before') ?></span>
      <span class="compare__label compare__label--after"><?= te('cat.compare_after') ?></span>
      <span class="compare__handle" aria-hidden="true"></span>
      <input type="range" class="compare__range" min="0" max="100" value="50" step="1" aria-label="<?= te('cat.compare_hint') ?>">
    </figure>
  </div>
</section>
<?php endif; ?>

<?php if ($children): ?>
<section class="section section--tint">
  <div class="container">
    <div class="section__head"><h2><?= te('cat.children_title') ?></h2></div>
    <div class="cards">
      <?php foreach ($children as $c): ?>
        <a class="card" href="<?= e(item_url($c)) ?>">
          <?= img($c['cover_image'], tr($c, 'title'), 'card__img') ?>
          <div class="card__body"><h3><?= e(tr($c, 'menu_title') ?: tr($c, 'title')) ?></h3><p><?= e(tr($c, 'intro')) ?></p></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="section__head"><h2><?= te('cat.gallery_title') ?></h2></div>
    <?= $images ? gallery_html($images, $title) : '<p class="muted">' . te('cat.gallery_empty') . '</p>' ?>
  </div>
</section>

<?php if ($siblings): ?>
<section class="section section--tight">
  <div class="container chips">
    <?php foreach ($siblings as $s): ?>
      <a href="<?= e(item_url($s)) ?>" class="chip <?= $s['id'] == $category['id'] ? 'is-active' : '' ?>"><?= e(tr($s, 'menu_title') ?: tr($s, 'title')) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require ROOT_DIR . '/includes/partials/cta.php'; ?>
