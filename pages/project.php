<?php
/** @var array $project */
$title    = tr($project, 'title');
$metaDesc = tr($project, 'summary') ?: $title;
$ogImage  = $project['cover_image'];
$images   = gallery_for('project', (int) $project['id']);
$others   = q("SELECT * FROM projects WHERE is_published = 1 AND id <> ? ORDER BY created_at DESC LIMIT 3", [$project['id']])->fetchAll();
$body     = tr($project, 'body');

$schema[] = array_filter([
    '@type'           => 'CreativeWork',
    '@id'             => abs_url(item_url($project)) . '#project',
    'name'            => $title,
    'description'     => tr($project, 'summary') ?: null,
    'url'             => abs_url(item_url($project)),
    'dateCreated'     => substr((string) $project['created_at'], 0, 10),
    'creator'         => ['@id' => business_id()],
    'locationCreated' => $project['location'] ? ['@type' => 'Place', 'name' => $project['location']] : null,
    'image'           => array_values(array_filter(array_map(fn($im) => abs_image($im['file']), array_slice($images, 0, 20)))) ?: abs_image($project['cover_image']),
    'inLanguage'      => lang(),
]);
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([[t('nav.references'), route_url('references')], [$title, null]]) ?>
    <h1><?= e($title) ?></h1>
    <?php if ($project['location']): ?><p class="tag"><?= e($project['location']) ?></p><?php endif; ?>
  </div>
</section>

<?php if (tr($project, 'summary') || $body): ?>
<section class="section section--tight">
  <div class="container split">
    <div class="prose">
      <?php if (tr($project, 'summary')): ?><p class="lead"><?= e(tr($project, 'summary')) ?></p><?php endif; ?>
      <?= $body ?>
    </div>
    <div><?= img($project['cover_image'], $title, 'split__img', true) ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($images): ?>
<section class="section section--tight">
  <div class="container"><?= gallery_html($images, $title) ?></div>
</section>
<?php endif; ?>

<?php if ($others): ?>
<section class="section section--tint">
  <div class="container">
    <div class="section__head"><h2><?= te('project.others_title') ?></h2></div>
    <div class="cards cards--wide">
      <?php foreach ($others as $p): ?>
        <a class="card" href="<?= e(item_url($p)) ?>">
          <?= img($p['cover_image'], tr($p, 'title'), 'card__img') ?>
          <div class="card__body"><h3><?= e(tr($p, 'title')) ?></h3><?php if (tr($p, 'summary')): ?><p><?= e(tr($p, 'summary')) ?></p><?php endif; ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require ROOT_DIR . '/includes/partials/cta.php'; ?>
