<?php
/** @var array $user */
?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('profile') ?></h2>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert--success mb-sm">
        <?= __('save_success') ?? 'Paramètres enregistrés.' ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $BASE_URL ?>/profile">
            <div class="form-stack">
                <div class="form-group">
                    <label class="form-label"><?= __('name') ?></label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('email') ?></label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('language') ?></label>
                    <select name="language" class="form-control">
                        <option value="fr" <?= ($user['language'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
                        <option value="en" <?= ($user['language'] ?? 'fr') === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="ja" <?= ($user['language'] ?? 'fr') === 'ja' ? 'selected' : '' ?>>日本語</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
                    <a href="<?= $BASE_URL ?>/" class="btn btn--ghost"><?= __('close') ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
