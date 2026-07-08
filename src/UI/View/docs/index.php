<?php
/** @var array  $pdfs         ['fr'|'en'|'ja' => ['filename', 'available', 'size_kb']] */
/** @var string $locale       Locale courante de l'utilisateur */
/** @var string $public_token Token dérivé de installed.lock */

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
        $pdf        = $pdfs[$lang] ?? [];
        $available  = !empty($pdf['available']);
        $sizeKb     = (int) ($pdf['size_kb'] ?? 0);
        $filename   = $pdf['filename'] ?? '';
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

            <?php if ($available): ?>
                <div class="docs-card__meta">
                    <?= __('docs_guide_user') ?> — <?= $sizeKb ?> Ko
                </div>
                <a href="<?= $BASE_URL ?>/pdf/<?= htmlspecialchars($filename) ?>"
                   class="btn btn--primary docs-card__btn"
                   download="<?= htmlspecialchars($filename) ?>">
                    ↓ <?= __('docs_download') ?>
                </a>
            <?php else: ?>
                <p class="docs-card__unavailable"><?= __('docs_not_generated') ?></p>
                <code class="docs-card__cmd">php scripts/pdf.php --lang=<?= $lang ?></code>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card card--mt">
    <div class="card-body docs-info">
        <h4><?= __('docs_info_title') ?></h4>
        <p><?= __('docs_info_body') ?></p>
        <?php if ($public_token !== ''): ?>
        <div class="docs-gen">
            <button id="btnGenerate" class="btn btn--primary docs-gen__btn" onclick="generatePdfs()">
                ↻ <?= __('docs_generate_btn') ?>
            </button>
            <span id="genStatus" class="docs-gen__status" aria-live="polite"></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($public_token !== ''): ?>
<?php
    $baseOrigin  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim($BASE_URL, '/');
    $generateUrl = $baseOrigin . '/api/v1/pdf/generate?token=' . $public_token;
?>
<div class="card card--mt docs-api">
    <div class="card-body">
        <h4><?= __('docs_api_title') ?></h4>
        <p class="docs-api__desc"><?= __('docs_api_desc') ?></p>

        <table class="docs-api__table">
            <thead>
                <tr><th><?= __('docs_api_action') ?></th><th>URL</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= __('docs_api_gen_all') ?></td>
                    <td><code class="docs-api__url" onclick="copyUrl(this)"><?= htmlspecialchars($generateUrl) ?></code></td>
                </tr>
            </tbody>
        </table>

        <p class="docs-api__hint"><?= __('docs_api_hint') ?></p>
    </div>
</div>
<?php endif; ?>

<script>
function copyUrl(el) {
    navigator.clipboard.writeText(el.textContent.trim()).then(function() {
        el.classList.add('copied');
        var prev = el.textContent;
        el.textContent = '✓ Copié';
        setTimeout(function() { el.classList.remove('copied'); el.textContent = prev; }, 1800);
    });
}

<?php if ($public_token !== ''): ?>
var _generateUrl = <?= json_encode($generateUrl) ?>;

function generatePdfs() {
    var btn    = document.getElementById('btnGenerate');
    var status = document.getElementById('genStatus');

    btn.disabled = true;
    btn.textContent = '⏳ <?= __('docs_generating') ?>…';
    status.className = 'docs-gen__status';
    status.textContent = '';

    fetch(_generateUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                var files = Object.entries(data.generated || {})
                    .map(function(e) { return e[0].toUpperCase() + ' (' + e[1] + ')'; })
                    .join(', ');
                status.className = 'docs-gen__status docs-gen__status--ok';
                status.textContent = '✓ <?= __('docs_generate_ok') ?>' + (files ? ' — ' + files : '');
                setTimeout(function() { location.reload(); }, 1200);
            } else {
                status.className = 'docs-gen__status docs-gen__status--err';
                status.textContent = '✗ ' + (data.error || '<?= __('docs_generate_err') ?>');
            }
        })
        .catch(function() {
            status.className = 'docs-gen__status docs-gen__status--err';
            status.textContent = '✗ <?= __('docs_generate_err') ?>';
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = '↻ <?= __('docs_generate_btn') ?>';
        });
}
<?php endif; ?>
</script>
