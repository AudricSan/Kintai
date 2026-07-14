<?php
/** @var string $mode                  'create'|'edit' */
/** @var array  $user                  Données de l'utilisateur */
/** @var array  $all_stores            Liste des magasins */
/** @var array  $assignable_roles      Rôles dynamiques assignables par store (table roles) */
/** @var int    $default_store_role_id Rôle pré-sélectionné par défaut */
$mode ??= 'create';
$assignable_roles      ??= [];
$default_store_role_id ??= 0;
$genderOptions = ['male' => __('male'), 'female' => __('female')];
$taxOptions = ['kou' => '甲', 'otsu' => '乙'];
?>
<div class="form-stack">
    <!-- ── Identité ─────────────────────────────── -->
    <div class="section-divider">
        <h4 class="section-title"><?= __('identity') ?></h4>

        <div class="form-group">
            <label class="form-label form-label--required"><?= __('display_name') ?></label>
            <input type="text" name="display_name" class="form-control"
                   value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('last_name') ?></label>
                <input type="text" name="last_name" class="form-control"
                       value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('first_name') ?></label>
                <input type="text" name="first_name" class="form-control"
                       value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('furigana_last_name') ?></label>
                <input type="text" name="furigana_last_name" class="form-control"
                       value="<?= htmlspecialchars($user['furigana_last_name'] ?? '') ?>"
                       placeholder="カタカナ">
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('furigana_first_name') ?></label>
                <input type="text" name="furigana_first_name" class="form-control"
                       value="<?= htmlspecialchars($user['furigana_first_name'] ?? '') ?>"
                       placeholder="カタカナ">
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
    </div>

    <!-- ── Coordonnées ──────────────────────────── -->
    <div class="section-divider">
        <h4 class="section-title"><?= __('contact') ?></h4>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label form-label--required"><?= __('email') ?></label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
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

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('postal_code') ?></label>
                <input type="text" name="postal_code" class="form-control"
                       value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>"
                       placeholder="123-4567">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><?= __('address') ?></label>
            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- ── Garant ───────────────────────────────── -->
    <div class="section-divider">
        <h4 class="section-title"><?= __('guarantor') ?></h4>

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
    </div>

    <!-- ── Employé ──────────────────────────────── -->
    <div class="section-divider">
        <h4 class="section-title"><?= __('employee_info') ?></h4>

        <div class="form-group">
            <label class="form-label"><?= __('employee_code') ?> <span class="text-hint">(<?= __('employee_code_hint') ?>)</span></label>
            <input type="text" name="employee_code" class="form-control input-code mw-200"
                   value="<?= htmlspecialchars($user['employee_code'] ?? '') ?>"
                   placeholder="ex : EMP001">
            <p class="form-hint"><?= __('employee_login_hint') ?></p>
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
            <form method="POST" action="<?= $BASE_URL ?>/admin/users/<?= (int) $user['id'] ?>/reset-password"
                  onsubmit="return confirm('<?= htmlspecialchars(__('reset_password_confirm'), ENT_QUOTES) ?>')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--warning btn--sm"><?= __('reset_password_btn') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Rôles & apparence ────────────────────── -->
    <div class="section-divider">
        <h4 class="section-title"><?= __('roles_appearance') ?></h4>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= __('identification_color') ?></label>
                <div class="input-group">
                    <input type="color" name="color" class="input-color"
                           value="<?= htmlspecialchars($user['color'] ?? '#3B82F6') ?>">
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
            <?php if ($mode === 'edit'): ?>
            <div class="form-group">
                <label class="form-label"><?= __('status') ?></label>
                <select name="is_active" class="form-control">
                    <option value="1" <?= !empty($user['is_active']) ? 'selected' : '' ?>><?= __('active') ?></option>
                    <option value="0" <?= empty($user['is_active']) ? 'selected' : '' ?>><?= __('inactive') ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Affectation store (création uniquement) ─ -->
    <?php if ($mode === 'create'): ?>
    <div class="section-divider">
        <h4 class="section-title"><?= __('assign_to_store') ?></h4>
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
    </div>
    <?php endif; ?>
</div>
