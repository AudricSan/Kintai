<?php
/** @var array  $languages      ['fr'|'en'|'ja' => ['first_page', 'browsable', 'github_wiki_url']] */
/** @var string $locale         Locale courante de l'utilisateur */
/** @var bool   $wikiConfigured Le dossier .wiki/ est-il présent sur le serveur */
/** @var bool   $isOwner        L'utilisateur courant est-il propriétaire (peut synchroniser le wiki) */

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

<?php if ($isOwner): ?>
<div class="card card--mt">
    <div class="card-body docs-sync">
        <h4><?= __('docs_sync_title') ?></h4>
        <p><?= __($wikiConfigured ? 'docs_sync_body_configured' : 'docs_sync_body_missing') ?></p>
        <div class="docs-sync__row">
            <button id="btnWikiSync" type="button" class="btn btn--primary docs-sync__btn" onclick="syncWiki()">
                ↻ <?= __($wikiConfigured ? 'docs_sync_btn_update' : 'docs_sync_btn_clone') ?>
            </button>
            <span id="wikiSyncStatus" class="docs-sync__status" aria-live="polite"></span>
        </div>
    </div>
</div>

<script>
var _wikiSyncUrl = <?= json_encode(route_url('docs.sync')) ?>;

function syncWiki() {
    var btn    = document.getElementById('btnWikiSync');
    var status = document.getElementById('wikiSyncStatus');

    btn.disabled = true;
    status.className = 'docs-sync__status';
    status.textContent = '<?= __('docs_sync_running') ?>…';

    fetch(_wikiSyncUrl, { method: 'POST' })
        .then(function(r) { return r.json().then(function(data) { return { status: r.status, data: data }; }); })
        .then(function(res) {
            var data = res.data;
            if (res.status === 200 && data.ok) {
                status.className = 'docs-sync__status docs-sync__status--ok';
                status.textContent = '✓ <?= __('docs_sync_ok') ?>'
                    + ' (' + '<?= __('docs_sync_added') ?>: ' + data.added
                    + ', ' + '<?= __('docs_sync_updated') ?>: ' + data.updated
                    + ', ' + '<?= __('docs_sync_unchanged') ?>: ' + data.unchanged + ')';
                setTimeout(function() { location.reload(); }, 1200);
            } else {
                status.className = 'docs-sync__status docs-sync__status--err';
                status.textContent = '✗ ' + ((data.errors && data.errors[0]) || '<?= __('docs_sync_err') ?>');
                btn.disabled = false;
            }
        })
        .catch(function() {
            status.className = 'docs-sync__status docs-sync__status--err';
            status.textContent = '✗ <?= __('docs_sync_err') ?>';
            btn.disabled = false;
        });
}
</script>
<?php endif; ?>
