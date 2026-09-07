<?php
/**
 * Liste des utilisateurs ayant un rôle, sous forme de bulles (puce
 * d'initiales colorée + nom + store) — inclus par roles-form.php.
 * @var array $holders ['user_name','initials','color','scope_label'][]
 */
?>
<?php if (empty($holders)): ?>
    <p class="form-hint"><?= __('none') ?></p>
<?php else: ?>
    <ul class="role-holders-list">
        <?php foreach ($holders as $h): ?>
            <li class="role-holder-chip">
                <span class="avatar-chip" style="--chip-bg:<?= htmlspecialchars($h['color']) ?>"><?= htmlspecialchars($h['initials']) ?></span>
                <span class="role-holder-chip__body">
                    <span class="role-holder-chip__name"><?= htmlspecialchars($h['user_name']) ?></span>
                    <span class="role-holder-chip__scope"><?= htmlspecialchars($h['scope_label']) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
