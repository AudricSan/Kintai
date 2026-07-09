<?php
/** @var string      $lang         Langue courante ('fr'|'en'|'ja') */
/** @var string      $page         Nom du fichier .md courant */
/** @var array       $toc          [{file, title}] Table des matières de la langue */
/** @var int|null    $currentIndex Index de $page dans $toc */
/** @var string      $contentHtml  Contenu HTML déjà rendu (Parsedown) */

$prev = ($currentIndex !== null && $currentIndex > 0) ? $toc[$currentIndex - 1] : null;
$next = ($currentIndex !== null && $currentIndex < count($toc) - 1) ? $toc[$currentIndex + 1] : null;
$pdfFilename = 'Kintai-Guide-' . strtoupper($lang) . '.pdf';
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('docs_title') ?></h2>
    <p class="page-header__subtitle">
        <a href="<?= route_url('docs.index') ?>">&larr; <?= __('docs_back_to_list') ?></a>
    </p>
</div>

<div class="docs-wiki">
    <nav class="docs-wiki__sidebar" aria-label="<?= htmlspecialchars(__('docs_toc_title')) ?>">
        <h4 class="docs-wiki__toc-title"><?= __('docs_toc_title') ?></h4>
        <ul class="docs-wiki__toc-list">
            <?php foreach ($toc as $entry): ?>
                <li>
                    <a href="<?= route_url('docs.show', ['lang' => $lang, 'page' => $entry['file']]) ?>"
                       class="docs-wiki__toc-link<?= $entry['file'] === $page ? ' docs-wiki__toc-link--active' : '' ?>">
                        <?= htmlspecialchars($entry['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="<?= $BASE_URL ?>/pdf/<?= htmlspecialchars($pdfFilename) ?>"
           class="btn btn--ghost docs-wiki__pdf-link" download="<?= htmlspecialchars($pdfFilename) ?>">
            ↓ <?= __('docs_download') ?>
        </a>
    </nav>

    <div class="card docs-wiki__content-card">
        <div class="card-body docs-wiki__content">
            <?= $contentHtml ?>
        </div>

        <div class="docs-wiki__pager">
            <?php if ($prev): ?>
                <a href="<?= route_url('docs.show', ['lang' => $lang, 'page' => $prev['file']]) ?>" class="btn btn--ghost docs-wiki__pager-link docs-wiki__pager-link--prev">
                    &larr; <?= htmlspecialchars($prev['title']) ?>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <?php if ($next): ?>
                <a href="<?= route_url('docs.show', ['lang' => $lang, 'page' => $next['file']]) ?>" class="btn btn--ghost docs-wiki__pager-link docs-wiki__pager-link--next">
                    <?= htmlspecialchars($next['title']) ?> &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
