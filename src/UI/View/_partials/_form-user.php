<?php
/** @var string $mode                  'create'|'edit' */
/** @var array  $user                  Données de l'utilisateur */
/** @var array  $all_stores            Liste des magasins */
/** @var array  $assignable_roles      Rôles dynamiques assignables par store (table roles) */
/** @var int    $default_store_role_id Rôle pré-sélectionné par défaut */
/** @var bool   $as_cards              true : chaque section devient une Card séparée (page d'édition complète) ;
 *                                     false/absent : rendu compact d'origine (modale de création rapide, voir shifts-import-preview.php). */
$mode ??= 'create';
$assignable_roles      ??= [];
$default_store_role_id ??= 0;
$as_cards ??= false;
$genderOptions = ['male' => __('male'), 'female' => __('female')];
$taxOptions = ['kou' => '甲', 'otsu' => '乙'];
$currentUserId = (int) ($user['id'] ?? 0);
$baseUrl = rtrim($BASE_URL ?? '', '/');

$section = function (string $titleKey, string $body, bool $full = false) use ($as_cards): void {
    if ($as_cards) {
        $card = \kintai\UI\Components\Card::make()->header(__($titleKey))->body($body);
        if ($full) {
            $card->attrs(['class' => 'card--full']);
        }
        echo $card->render();
        return;
    }
    echo '<div class="section-divider"><h4 class="section-title">' . htmlspecialchars(__($titleKey)) . '</h4>' . $body . '</div>';
};
?>
<div class="<?= $as_cards ? 'form-section-grid' : 'form-stack' ?>">
    <?php ob_start(); ?>
        <div class="form-group">
            <label class="form-label form-label--required"><?= __('display_name') ?></label>
            <input type="text" name="display_name" class="form-control"
                   value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('last_name') ?></label>
                <input type="text" name="last_name" class="form-control"
                       value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('first_name') ?></label>
                <input type="text" name="first_name" class="form-control"
                       value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('furigana_last_name') ?></label>
                <input type="text" name="furigana_last_name" class="form-control"
                       value="<?= htmlspecialchars($user['furigana_last_name'] ?? '') ?>"
                       placeholder="カタカナ" required>
            </div>
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('furigana_first_name') ?></label>
                <input type="text" name="furigana_first_name" class="form-control"
                       value="<?= htmlspecialchars($user['furigana_first_name'] ?? '') ?>"
                       placeholder="カタカナ" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('gender') ?></label>
                <select name="gender" class="form-control">
                    <option value="">—</option>
                    <?php foreach ($genderOptions as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($user['gender'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('tax_classification') ?> (甲乙)</label>
                <select name="tax_classification" class="form-control">
                    <option value="">—</option>
                    <?php foreach ($taxOptions as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($user['tax_classification'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('birth_date') ?></label>
                <input type="date" name="birth_date" class="form-control"
                       value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('education') ?></label>
                <input type="text" name="education" class="form-control"
                       value="<?= htmlspecialchars($user['education'] ?? '') ?>">
            </div>
        </div>
    <?php $section('identity', ob_get_clean(), full: true); ?>

    <?php ob_start(); ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('email') ?></label>
                <input type="email" name="email" class="form-control live-check-input"
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                       data-check-url="<?= htmlspecialchars($baseUrl . '/admin/users/check-email') ?>"
                       data-check-param="email"
                       data-exclude-id="<?= $currentUserId ?>"
                       data-original-value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                       required>
                <p class="form-error" data-check-error hidden><?= __('email_taken') ?></p>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('postal_code') ?></label>
                <input type="text" name="postal_code" class="form-control"
                       value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>"
                       placeholder="123-4567">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('phone') ?></label>
                <input type="tel" name="phone" class="form-control"
                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                       placeholder="+33 6 00 00 00 00">
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('mobile_phone') ?></label>
                <input type="tel" name="mobile_phone" class="form-control"
                       value="<?= htmlspecialchars($user['mobile_phone'] ?? '') ?>"
                       placeholder="090-XXXX-XXXX">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><?= __('address') ?></label>
            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
        </div>
    <?php $section('contact', ob_get_clean()); ?>

    <?php ob_start(); ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('guarantor_name') ?></label>
                <input type="text" name="guarantor_name" class="form-control"
                       value="<?= htmlspecialchars($user['guarantor_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('guarantor_phone') ?></label>
                <input type="tel" name="guarantor_phone" class="form-control"
                       value="<?= htmlspecialchars($user['guarantor_phone'] ?? '') ?>">
            </div>
        </div>
    <?php $section('guarantor', ob_get_clean()); ?>

    <?php ob_start(); ?>
        <div class="form-group">
            <label class="form-label"><?= __('employee_code') ?> <span class="text-hint">(<?= __('employee_code_hint') ?>)</span></label>
            <input type="text" name="employee_code" class="form-control input-code mw-200 live-check-input"
                   value="<?= htmlspecialchars($user['employee_code'] ?? '') ?>"
                   data-check-url="<?= htmlspecialchars($baseUrl . '/admin/users/check-employee-code') ?>"
                   data-check-param="code"
                   data-exclude-id="<?= $currentUserId ?>"
                   data-original-value="<?= htmlspecialchars($user['employee_code'] ?? '') ?>"
                   placeholder="ex : EMP001">
            <p class="form-hint"><?= __('employee_login_hint') ?></p>
            <p class="form-error" data-check-error hidden><?= __('employee_code_taken_hint') ?></p>
        </div>

        <?php if ($mode === 'create'): ?>
        <div class="form-group">
            <label class="form-label"><?= __('password') ?></label>
            <input type="password" name="password" class="form-control"
                   autocomplete="new-password"
                   placeholder="<?= __('password_auto_generated') ?>">
            <p class="form-hint"><?= __('password_auto_hint') ?></p>
        </div>
        <?php else: ?>
        <div class="form-group">
            <label class="form-label"><?= __('password') ?></label>
            <p class="form-hint"><?= __('password_edit_hint') ?></p>
            <!-- Bouton rattaché à #resetPasswordForm (attribut form=) plutôt qu'à un formulaire
                 imbriqué ici : un formulaire ne peut pas être imbriqué dans celui d'édition qui
                 englobe ce partiel (users-form.php) sans que le navigateur ne referme ce dernier
                 prématurément à la fermeture du formulaire interne — ce qui éjectait le bouton
                 "Enregistrer" hors de tout formulaire et le rendait inopérant. -->
            <button type="submit" form="resetPasswordForm" class="btn btn--warning btn--sm"
                    onclick="return confirm('<?= htmlspecialchars(__('reset_password_confirm'), ENT_QUOTES) ?>')">
                <?= __('reset_password_btn') ?>
            </button>
        </div>
        <?php endif; ?>
    <?php $section('employee_info', ob_get_clean()); ?>

    <?php ob_start(); ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('identification_color') ?></label>
                <?php
                $userColor = htmlspecialchars($user['color'] ?? '#3B82F6');
                $userInitials = htmlspecialchars(strtoupper(
                    mb_substr((string) ($user['last_name'] ?? ''), 0, 1) . mb_substr((string) ($user['first_name'] ?? ''), 0, 1)
                ) ?: '··');
                ?>
                <div class="input-group">
                    <span class="avatar-chip" id="userColorPreview" style="--chip-bg:<?= $userColor ?>"><?= $userInitials ?></span>
                    <input type="color" name="color" class="input-color" id="userColorInput"
                           value="<?= $userColor ?>"
                           oninput="document.getElementById('userColorPreview').style.setProperty('--chip-bg', this.value)">
                    <span class="text-sm-muted"><?= __('planning_visible_hint') ?></span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('global_role') ?></label>
                <select name="is_admin" class="form-control">
                    <option value="0" <?= empty($user['is_admin']) ? 'selected' : '' ?>><?= __('staff') ?></option>
                    <option value="1" <?= !empty($user['is_admin']) ? 'selected' : '' ?>><?= __('admin') ?></option>
                </select>
            </div>
        </div>
        <?php if ($mode === 'edit'): ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('status') ?></label>
                <select name="is_active" class="form-control">
                    <option value="1" <?= !empty($user['is_active']) ? 'selected' : '' ?>><?= __('active') ?></option>
                    <option value="0" <?= empty($user['is_active']) ? 'selected' : '' ?>><?= __('inactive') ?></option>
                </select>
            </div>
        </div>
        <?php endif; ?>
    <?php $section('roles_appearance', ob_get_clean()); ?>

    <?php if ($mode === 'create'): ?>
    <?php ob_start(); ?>
        <div class="form-row">
            <?php if (!empty($all_stores)): ?>
            <!-- Sélecteur de store affiché uniquement quand le formulaire propose un choix -->
            <!-- (ex. modale de création rapide depuis l'import : le store est déjà fixé -->
            <!-- via un champ caché du formulaire parent, sinon le nom "store_id" entrerait -->
            <!-- en collision avec ce select et écraserait la valeur transmise) -->
            <div class="form-group">
                <label class="form-label"><?= __('store') ?></label>
                <select name="store_id" class="form-control">
                    <option value="">— <?= __('none') ?> —</option>
                    <?php foreach ($all_stores as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name'] ?? '') ?> (<?= htmlspecialchars($s['code'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (!empty($assignable_roles)): ?>
            <div class="form-group">
                <label class="form-label"><?= __('store_role') ?></label>
                <select name="store_role_id" class="form-control">
                    <?php foreach ($assignable_roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === (int) $default_store_role_id ? 'selected' : '' ?>><?= htmlspecialchars($r['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    <?php $section('assign_to_store', ob_get_clean()); ?>
    <?php endif; ?>
</div>
