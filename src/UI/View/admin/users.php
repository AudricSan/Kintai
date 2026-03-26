<?php
/** @var array  $users */
/** @var array  $user_stats      id → [hours_month, hours_week, estimated_pay] */
/** @var array  $user_store_map  id → storeId (premier store accessible) */
/** @var string $month_label */
/** @var string $store_currency */
/** @var string $sort */
$store_currency  ??= 'JPY';
$sort            ??= 'name_asc';
$user_store_map  ??= [];

function userSortUrl(string $key, string $current): string {
    $next = ($current === $key . '_asc') ? $key . '_desc' : $key . '_asc';
    return '?sort=' . $next;
}
function userSortIcon(string $key, string $current): string {
    if (str_starts_with($current, $key . '_asc'))  return ' ↑';
    if (str_starts_with($current, $key . '_desc')) return ' ↓';
    return '';
}
function thSort(string $label, string $key, string $current): string {
    $icon = userSortIcon($key, $current);
    $url  = userSortUrl($key, $current);
    $active = $icon !== '' ? ' sort-link--active' : '';
    return '<th><a href="' . $url . '" class="sort-link' . $active . '">'
        . $label . '<span class="text-primary">' . $icon . '</span></a></th>';
}
?>

<?php if ($flash = ($_GET['success'] ?? '')): ?>
    <div class="alert alert--success">
        <?= match($flash) {
            'created' => __('operation_success'),
            'updated' => __('user_updated'),
            'deleted' => __('operation_success'),
            default   => __('operation_success'),
        } ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('users') ?> <span class="page-count">(<?= count($users) ?>)</span></h2>
    <div class="page-header__actions">
        <a href="<?= $BASE_URL ?>/admin/users/create" class="btn btn--primary">+ <?= __('new_user') ?></a>
    </div>
</div>

<div class="card">
    <?php if (empty($users)): ?>
        <div class="empty-state"><?= __('none') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <?= thSort(__('name'),             'name',   $sort) ?>
                        <?= thSort(__('email'),            'email',  $sort) ?>
                        <?= thSort(__('role'),             'role',   $sort) ?>
                        <?= thSort(__('status'),           'status', $sort) ?>
                        <?= thSort(__('hours') . '/mois',  'hours',  $sort) ?>
                        <th><?= __('avg_per_week_short') ?></th>
                        <th><?= __('estimated_pay') ?></th>
                        <th><?= __('date') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int) $user['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($user['display_name'] ?? '') ?></strong>
                                <div class="text-hint">
                                    <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($user['is_admin'])): ?>
                                    <span class="badge badge--admin">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge--staff">Staff</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($user['is_active']) && empty($user['deleted_at'])): ?>
                                    <span class="badge badge--active"><?= __('active') ?></span>
                                <?php else: ?>
                                    <span class="badge badge--inactive"><?= __('inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <?php
                            $uid   = (int) $user['id'];
                            $stats = ($user_stats ?? [])[$uid] ?? ['hours_month' => 0, 'hours_week' => 0, 'estimated_pay' => 0];
                            $hm    = $stats['hours_month'];
                            $hw    = $stats['hours_week'];
                            $pay   = $stats['estimated_pay'];
                            ?>
                            <td class="text-sm td-nowrap">
                                <?php if ($hm > 0): ?>
                                    <strong><?= number_format($hm, 1) ?></strong> h
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm td-nowrap">
                                <?php if ($hw > 0): ?>
                                    <?= number_format($hw, 1) ?> h
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm td-nowrap">
                                <?php if ($pay > 0): ?>
                                    <?= format_currency((float) $pay, $store_currency) ?>
                                <?php else: ?>
                                    <span class="text-muted" title="<?= __('no_rate_configured') ?>">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-date-muted"><?= htmlspecialchars(substr($user['created_at'] ?? '', 0, 10)) ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (isset($user_store_map[$uid])): ?>
                                        <?php $sId = $user_store_map[$uid]; ?>
                                        <a href="<?= $BASE_URL ?>/admin/stores/<?= $sId ?>/employee-report/<?= $uid ?>/stats"
                                           class="btn btn--ghost btn--sm" title="<?= __('employee_stats') ?>">📊 <?= __('view_stats') ?></a>
                                        <button type="button"
                                                class="btn btn--ghost btn--sm ps-period-trigger"
                                                data-url="<?= $BASE_URL ?>/admin/stores/<?= $sId ?>/employee-report/<?= $uid ?>/payslip?from=__FROM__&amp;to=__TO__"
                                                title="<?= __('payslip') ?>">🖨 <?= __('payslip') ?></button>
                                    <?php endif; ?>
                                    <a href="<?= $BASE_URL ?>/admin/users/<?= $uid ?>/edit" class="btn btn--ghost btn--sm"><?= __('edit') ?></a>
                                    <form method="POST" action="<?= $BASE_URL ?>/admin/users/<?= $uid ?>/delete" class="form-inline">
                                        <button type="submit" class="btn btn--danger btn--sm"
                                                onclick="return confirm('<?= __('confirm_delete_user') ?>')"><?= __('delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modale sélection de période — fiche de paie -->
<div id="ps-period-modal" class="ps-modal-overlay" hidden>
    <div class="ps-modal">
        <div class="ps-modal__header">
            <span>🖨 <?= __('payslip') ?> — <?= __('select_period') ?></span>
            <button type="button" class="ps-modal__close" onclick="psPeriodClose()">✕</button>
        </div>
        <div class="ps-modal__body">
            <!-- Sélecteur de mois rapide -->
            <div class="ps-modal__section-label"><?= __('quick_month') ?></div>
            <div class="ps-month-grid" id="ps-month-grid">
                <?php
                for ($i = 12; $i >= 0; $i--) {
                    $dt    = new \DateTime("first day of -$i months");
                    $from  = $dt->format('Y-m-01');
                    $to    = $dt->format('Y-m-t');
                    $label = $dt->format('M Y');
                    echo '<button type="button" class="ps-month-btn" data-from="' . $from . '" data-to="' . $to . '">'
                        . htmlspecialchars($label) . '</button>';
                }
                ?>
            </div>
            <!-- Dates personnalisées -->
            <div class="ps-modal__section-label"><?= __('custom_range') ?></div>
            <div class="ps-date-row">
                <label class="ps-date-label">
                    <?= __('from_date') ?>
                    <input type="date" id="ps-from" class="ps-date-input">
                </label>
                <span class="ps-date-sep">→</span>
                <label class="ps-date-label">
                    <?= __('to_date') ?>
                    <input type="date" id="ps-to" class="ps-date-input">
                </label>
            </div>
        </div>
        <div class="ps-modal__footer">
            <button type="button" class="btn btn--ghost btn--sm" onclick="psPeriodClose()"><?= __('cancel') ?></button>
            <button type="button" class="btn btn--primary btn--sm" onclick="psPeriodOpen()">🖨 <?= __('open') ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    let _pendingUrl = '';

    // Pré-remplir les inputs avec le mois en cours
    const now      = new Date();
    const y        = now.getFullYear();
    const m        = String(now.getMonth() + 1).padStart(2, '0');
    const lastDay  = new Date(y, now.getMonth() + 1, 0).getDate();
    document.getElementById('ps-from').value = y + '-' + m + '-01';
    document.getElementById('ps-to').value   = y + '-' + m + '-' + String(lastDay).padStart(2, '0');

    // Surligner le mois courant par défaut
    const todayFrom = y + '-' + m + '-01';
    document.querySelectorAll('.ps-month-btn').forEach(function (btn) {
        if (btn.dataset.from === todayFrom) btn.classList.add('active');
    });

    // Clic sur un mois rapide
    document.getElementById('ps-month-grid').addEventListener('click', function (e) {
        const btn = e.target.closest('.ps-month-btn');
        if (!btn) return;
        document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('ps-from').value = btn.dataset.from;
        document.getElementById('ps-to').value   = btn.dataset.to;
    });

    // Changement manuel → désélectionner les mois rapides
    ['ps-from', 'ps-to'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () {
            document.querySelectorAll('.ps-month-btn').forEach(function (b) { b.classList.remove('active'); });
        });
    });

    // Ouvrir la modale au clic sur un bouton trigger
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.ps-period-trigger');
        if (!btn) return;
        _pendingUrl = btn.dataset.url || '';
        document.getElementById('ps-period-modal').removeAttribute('hidden');
    });

    window.psPeriodClose = function () {
        document.getElementById('ps-period-modal').setAttribute('hidden', '');
        _pendingUrl = '';
    };

    window.psPeriodOpen = function () {
        const from = document.getElementById('ps-from').value;
        const to   = document.getElementById('ps-to').value;
        if (!from || !to || from > to) {
            alert('<?= addslashes(__('invalid_date_range') ?? 'Plage de dates invalide') ?>');
            return;
        }
        const url = _pendingUrl.replace('__FROM__', encodeURIComponent(from)).replace('__TO__', encodeURIComponent(to));
        window.open(url, '_blank');
        psPeriodClose();
    };

    document.getElementById('ps-period-modal').addEventListener('click', function (e) {
        if (e.target === this) psPeriodClose();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') psPeriodClose();
    });
}());
</script>
