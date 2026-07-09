<?php
/** @var array  $languages ['fr'|'en'|'ja' => ['first_page', 'browsable', 'github_wiki_url']] */
/** @var string $locale    Locale courante de l'utilisateur */

$guides = [
    'fr' => ['flag' => '🇫🇷', 'label' => 'Français',  'desc' => __('docs_desc_fr')],
    'en' => ['flag' => '🇬🇧', 'label' => 'English',   'desc' => __('docs_desc_en')],
    'ja' => ['flag' => '🇯🇵', 'label' => '日本語',     'desc' => __('docs_desc_ja')],
];
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('docs_title') ?></h2>
    <p class="page-header__subtitle"><?= __('docs_subtitle') ?></p>
</div>

<div class="docs-grid">
    <?php foreach ($guides as $lang => $guide):
        $entry      = $languages[$lang] ?? [];
        $browsable  = !empty($entry['browsable']);
        $isCurrent  = ($lang === $locale);
    ?>
    <div class="card docs-card<?= $isCurrent ? ' docs-card--current' : '' ?>">
        <div class="card-body">
            <div class="docs-card__flag"><?= $guide['flag'] ?></div>
            <h3 class="docs-card__lang"><?= htmlspecialchars($guide['label']) ?></h3>
            <?php if ($isCurrent): ?>
                <span class="docs-card__badge"><?= __('docs_recommended') ?></span>
            <?php endif; ?>
            <p class="docs-card__desc"><?= htmlspecialchars($guide['desc']) ?></p>

            <?php if ($browsable): ?>
                <a href="<?= route_url('docs.show', ['lang' => $lang, 'page' => $entry['first_page']]) ?>"
                   class="btn btn--primary docs-card__btn">
                    <?= __('docs_browse_online') ?>
                </a>
            <?php else: ?>
                <p class="docs-card__unavailable"><?= __('docs_not_available') ?></p>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($entry['github_wiki_url']) ?>"
               class="btn btn--ghost docs-card__btn"
               target="_blank" rel="noopener noreferrer">
                <?= __('docs_view_on_github') ?> ↗
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
