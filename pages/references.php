<?php
$title    = t('ref.title');
$metaDesc = t('ref.meta_desc');
$projects = q("SELECT * FROM projects WHERE is_published = 1 ORDER BY created_at DESC")->fetchAll();
$images   = q("SELECT * FROM gallery_images WHERE in_references = 1 ORDER BY created_at DESC, id DESC")->fetchAll();
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([[$title, null]]) ?>
    <h1><?= e($title) ?></h1>
    <p class="lead"><?= te('ref.lead') ?></p>
  </div>
</section>

<?php if ($projects): ?>
<section class="section section--tight">
  <div class="container">
    <div class="section__head"><h2><?= te('ref.projects_title') ?></h2></div>
    <div class="cards cards--wide">
      <?php foreach ($projects as $p): ?>
        <a class="card" href="<?= e(item_url($p)) ?>">
          <?= img($p['cover_image'], tr($p, 'title'), 'card__img') ?>
          <div class="card__body">
            <?php if ($p['location']): ?><span class="tag"><?= e($p['location']) ?></span><?php endif; ?>
            <h3><?= e(tr($p, 'title')) ?></h3>
            <?php if (tr($p, 'summary')): ?><p><?= e(tr($p, 'summary')) ?></p><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="section__head"><h2><?= te('ref.gallery_title') ?></h2></div>
    <?= $images ? gallery_html($images, $title) : '<p class="muted">' . te('ref.gallery_empty') . '</p>' ?>
  </div>
</section>

<?php require ROOT_DIR . '/includes/partials/cta.php'; ?>
