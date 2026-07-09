<?php
/** @var string      $lang           Langue courante ('fr'|'en'|'ja') */
/** @var string      $page           Slug de la page courante (sans extension) */
/** @var array       $toc            [{label, items: [{slug, title, available, github_url}]}] */
/** @var array       $flatItems      [{slug, title, available, github_url}] à plat, dans l'ordre du sommaire */
/** @var int|null    $currentIndex   Index de $page dans $flatItems */
/** @var string      $contentHtml    Contenu HTML déjà rendu (Parsedown) */
/** @var array       $otherLanguages ['fr'|'en'|'ja' => bool] page courante disponible dans cette langue */
/** @var string      $githubWikiUrl  URL de la page courante sur le wiki GitHub */

$prev = ($currentIndex !== null && $currentIndex > 0) ? $flatItems[$currentIndex - 1] : null;
$next = ($currentIndex !== null && $currentIndex < count($flatItems) - 1) ? $flatItems[$currentIndex + 1] : null;

$langFlags = ['fr' => '🇫🇷', 'en' => '🇬🇧', 'ja' => '🇯🇵'];
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('docs_title') ?></h2>
    <p class="page-header__subtitle">
        <a href="<?= route_url('docs.index') ?>">&larr; <?= __('docs_back_to_list') ?></a>
        ·
        <a href="<?= htmlspecialchars($githubWikiUrl) ?>" target="_blank" rel="noopener noreferrer">
            <?= __('docs_view_on_github') ?> ↗
        </a>
    </p>
</div>

<?php if ($otherLanguages !== []): ?>
<div class="docs-wiki__lang-switch">
    <?php foreach ($otherLanguages as $l => $available): ?>
        <?php if ($available): ?>
            <a href="<?= route_url('docs.show', ['lang' => $l, 'page' => $page]) ?>" class="docs-wiki__lang-link">
                <?= $langFlags[$l] ?? $l ?> <?= strtoupper($l) ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="docs-wiki">
    <nav class="docs-wiki__sidebar" aria-label="<?= htmlspecialchars(__('docs_toc_title')) ?>">
        <?php foreach ($toc as $group): ?>
            <?php if ($group['label'] !== null): ?>
                <h4 class="docs-wiki__toc-title"><?= htmlspecialchars($group['label']) ?></h4>
            <?php endif; ?>
            <ul class="docs-wiki__toc-list">
                <?php foreach ($group['items'] as $entry): ?>
                    <li>
                        <?php if ($entry['available']): ?>
                            <a href="<?= route_url('docs.show', ['lang' => $lang, 'page' => $entry['slug']]) ?>"
                               class="docs-wiki__toc-link<?= $entry['slug'] === $page ? ' docs-wiki__toc-link--active' : '' ?>">
                                <?= htmlspecialchars($entry['title']) ?>
                            </a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($entry['github_url']) ?>"
                               class="docs-wiki__toc-link docs-wiki__toc-link--unavailable"
                               target="_blank" rel="noopener noreferrer"
                               title="<?= htmlspecialchars(__('docs_view_on_github')) ?>">
                                <?= htmlspecialchars($entry['title']) ?> ↗
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <div class="card docs-wiki__content-card">
        <div class="card-body docs-wiki__content">
            <?= $contentHtml ?>
        </div>

        <div class="docs-wiki__pager">
            <?php if ($prev): ?>
                <a href="<?= route_url('docs.show', ['lang' => $lang, 'page' => $prev['slug']]) ?>" class="btn btn--ghost docs-wiki__pager-link docs-wiki__pager-link--prev">
                    &larr; <?= htmlspecialchars($prev['title']) ?>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <?php if ($next): ?>
                <a href="<?= route_url('docs.show', ['lang' => $lang, 'page' => $next['slug']]) ?>" class="btn btn--ghost docs-wiki__pager-link docs-wiki__pager-link--next">
                    <?= htmlspecialchars($next['title']) ?> &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
