<?php
/** @var array  $entries       [{date, staff_name, start_time, end_time, hours, user_id,
 *                               duplicate_count, db_exact_match, db_overlap, db_overlap_shifts}] */
/** @var int    $store_id */
/** @var array  $all_users */
/** @var string $import_month  YYYY-MM si l'import couvre un seul mois, '' sinon */
/** @var array  $store         informations du store courant */

$matched         = count(array_filter($entries, fn($e) => ($e['user_id'] ?? 0) > 0));
$unmatched       = count($entries) - $matched;
$excelDuplicates = count(array_filter($entries, fn($e) => ($e['duplicate_count'] ?? 0) > 0));
$exactMatches    = count(array_filter($entries, fn($e) => !empty($e['db_exact_match'])));
$overlaps        = count(array_filter($entries, fn($e) => !empty($e['db_overlap'])));
$okCount         = count(array_filter($entries, fn($e) =>
    ((int)($e['user_id'] ?? 0)) > 0 &&
    empty($e['db_exact_match']) &&
    empty($e['db_overlap']) &&
    (($e['duplicate_count'] ?? 0) === 0)
));
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('preview') ?>
        <span class="page-count">(<?= count($entries) ?> <?= __('shifts') ?>)</span>
    </h2>
    <a href="<?= $BASE_URL ?>/admin/shifts/import" class="btn btn--ghost">← <?= __('start_over') ?></a>
</div>

<?php if ($unmatched > 0): ?>
    <div class="alert alert--warning mb-sm">
        <?= __('unmatched_users_warning', ['n' => $unmatched]) ?>
    </div>
<?php endif; ?>

<?php if ($excelDuplicates > 0): ?>
    <div class="alert alert--warning mb-sm">
        ⚠️ <?= __('excel_duplicates_warning', ['n' => $excelDuplicates]) ?>
    </div>
<?php endif; ?>

<?php if ($exactMatches > 0): ?>
    <div class="alert alert--info mb-sm">
        ✅ <?= __('db_exact_match_warning', ['n' => $exactMatches]) ?>
    </div>
<?php endif; ?>

<?php if ($overlaps > 0): ?>
    <div class="alert alert--info mb-sm">
        🔄 <?= __('import_overlap_auto', ['n' => $overlaps]) ?>
    </div>
<?php endif; ?>

<?php
/* Barre de filtres — n'afficher que les filtres pour lesquels il y a des lignes */
$filterTypes = [];
if ($okCount > 0)         $filterTypes[] = ['key' => 'ok',         'icon' => '✓',  'label' => __('import_filter_ok'),          'count' => $okCount];
if ($exactMatches > 0)    $filterTypes[] = ['key' => 'exact',      'icon' => '✅', 'label' => __('import_status_identical'),    'count' => $exactMatches];
if ($overlaps > 0)        $filterTypes[] = ['key' => 'overlap',    'icon' => '🔄', 'label' => __('import_status_overlap'),      'count' => $overlaps];
if ($excelDuplicates > 0) $filterTypes[] = ['key' => 'duplicate',  'icon' => '⚠️', 'label' => __('duplicate_excel'),            'count' => $excelDuplicates];
if ($unmatched > 0)       $filterTypes[] = ['key' => 'unassigned', 'icon' => '👤', 'label' => __('not_assigned'),               'count' => $unmatched];
?>
<?php if (count($filterTypes) > 1): ?>
<div class="import-filter-bar" id="import-filter-bar">
    <span class="import-filter-label"><?= __('import_filter_label') ?></span>
    <?php foreach ($filterTypes as $ft): ?>
    <button type="button"
            class="import-filter-btn active"
            data-filter="<?= $ft['key'] ?>">
        <?= $ft['icon'] ?> <?= htmlspecialchars($ft['label']) ?>
        <span class="import-filter-badge"><?= $ft['count'] ?></span>
    </button>
    <?php endforeach; ?>
    <span id="import-filter-count" class="import-filter-count">
        <?= __('import_filter_count', ['n' => count($entries)]) ?>
    </span>
</div>
<?php endif; ?>

<form id="import-form" method="POST" action="<?= $BASE_URL ?>/admin/shifts/import/confirm">
    <input type="hidden" name="store_id"    value="<?= (int) $store_id ?>">
    <input type="hidden" name="shifts_json" id="shifts_json" value="">

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('excel_name') ?></th>
                        <th><?= __('start') ?></th>
                        <th><?= __('end') ?></th>
                        <th><?= __('duration') ?></th>
                        <th><?= __('user') ?></th>
                        <th><?= __('status') ?></th>
                        <th class="td-center"><?= __('ignore') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $i => $e): ?>
                    <?php
                        $noUser       = ((int) ($e['user_id'] ?? 0)) <= 0;
                        $isDupExcel   = ($e['duplicate_count'] ?? 0) > 0;
                        $isExactMatch = !empty($e['db_exact_match']);
                        $isOverlap    = !empty($e['db_overlap']);

                        // Pré-cocher "Ignorer" uniquement pour les doublons sans valeur ajoutée
                        $preSkip = $isDupExcel || $isExactMatch;

                        $rowClass = '';
                        if ($isExactMatch) $rowClass = 'tr-muted';
                        elseif ($isOverlap) $rowClass = 'tr-warn-dup';
                        elseif ($isDupExcel) $rowClass = 'tr-warn-dup';
                        elseif ($noUser)    $rowClass = 'tr-warn';

                        // Type de conflit pour les filtres JS
                        if ($isExactMatch)  $conflictType = 'exact';
                        elseif ($isOverlap) $conflictType = 'overlap';
                        elseif ($isDupExcel) $conflictType = 'duplicate';
                        elseif ($noUser)    $conflictType = 'unassigned';
                        else                $conflictType = 'ok';
                    ?>
                    <tr data-idx="<?= $i ?>"
                        data-date="<?= htmlspecialchars($e['date']) ?>"
                        data-staff="<?= htmlspecialchars($e['staff_name']) ?>"
                        data-conflict="<?= $conflictType ?>"
                        class="<?= $rowClass ?>">
                        <td class="td-nowrap"><?= htmlspecialchars($e['date']) ?></td>
                        <td><?= htmlspecialchars($e['staff_name']) ?></td>
                        <td>
                            <input type="time"
                                   data-field="start_time"
                                   value="<?= htmlspecialchars($e['start_time'] ?? '00:00') ?>"
                                   class="form-control import-time-input">
                        </td>
                        <td>
                            <input type="time"
                                   data-field="end_time"
                                   value="<?= htmlspecialchars($e['end_time'] ?? '00:00') ?>"
                                   class="form-control import-time-input">
                        </td>
                        <td class="td-nowrap td-muted td-sm">
                            <?= number_format((float) ($e['hours'] ?? 0), 1) ?> h
                        </td>
                        <td class="td-user-cell">
                            <select data-field="user_id"
                                    class="form-control import-select">
                                <option value="0">— <?= __('not_assigned') ?> —</option>
                                <?php foreach ($all_users as $u): ?>
                                    <?php
                                    $uid  = (int) $u['id'];
                                    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                    if ($name === '') $name = $u['display_name'] ?? ('User #' . $uid);
                                    ?>
                                    <option value="<?= $uid ?>"
                                        <?= ((int) ($e['user_id'] ?? 0)) === $uid ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($noUser): ?>
                            <button type="button"
                                    class="btn btn--ghost btn--xs qc-open-btn"
                                    data-staff="<?= htmlspecialchars($e['staff_name']) ?>"
                                    title="<?= htmlspecialchars(__('quick_create_title')) ?>">
                                <?= __('quick_create_staff') ?>
                            </button>
                            <?php endif; ?>
                        </td>
                        <td class="td-nowrap td-sm">
                            <?php if ($isExactMatch): ?>
                                <span class="badge badge--ok">✅ <?= __('import_status_identical') ?></span>
                            <?php elseif ($isOverlap): ?>
                                <span class="badge badge--warning">🔄 <?= __('import_status_overlap') ?></span>
                                <?php foreach ($e['db_overlap_shifts'] as $ox): ?>
                                <div class="td-sm td-muted" style="margin-top:2px">
                                    <?= htmlspecialchars($ox['start_time']) ?>–<?= htmlspecialchars($ox['end_time']) ?>
                                </div>
                                <?php endforeach; ?>
                            <?php elseif ($isDupExcel): ?>
                                <span class="badge badge--warning">⚠️ <?= __('duplicate_excel') ?></span>
                            <?php else: ?>
                                <span class="badge badge--ok">✓ OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-center">
                            <input type="checkbox" data-field="skip" value="1"
                                   class="import-checkbox"
                                   <?= $preSkip ? 'checked' : '' ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="import-preview-actions">
        <button type="submit" class="btn btn--primary">
            <?= __('confirm_import', ['count' => $matched]) ?>
        </button>
        <a href="<?= $BASE_URL ?>/admin/shifts/import" class="btn btn--ghost"><?= __('cancel') ?></a>
        <?php if ($unmatched > 0): ?>
            <span class="text-sm-muted">
                <?= __('unassigned_ignored_hint') ?>
            </span>
        <?php endif; ?>
    </div>
</form>

<!-- Modale création rapide de personnel -->
<div id="qc-overlay" class="fb-overlay" onclick="qcClose()" role="dialog" aria-modal="true" aria-labelledby="qc-modal-title">
    <div class="fb-modal" onclick="event.stopPropagation()" style="max-width:420px">
        <div class="fb-modal-header">
            <strong id="qc-modal-title"><?= __('quick_create_title') ?></strong>
            <button type="button" class="fb-modal-close" onclick="qcClose()" aria-label="<?= __('close') ?>">×</button>
        </div>
        <div class="fb-modal-body">
            <p id="qc-intro" class="text-sm mb-sm" style="margin-bottom:.75rem"></p>

            <div id="qc-alert" class="alert mb-sm" style="display:none"></div>

            <div class="form-stack">
                <div class="form-row" style="display:flex;gap:.75rem">
                    <div class="form-group" style="flex:1">
                        <label class="form-label form-label--required" for="qc-first-name">
                            <?= __('first_name') ?>
                        </label>
                        <input type="text" id="qc-first-name" class="form-control" required maxlength="80">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label" for="qc-last-name">
                            <?= __('last_name') ?>
                        </label>
                        <input type="text" id="qc-last-name" class="form-control" maxlength="80">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="qc-employee-code">
                        <?= __('employee_code') ?>
                    </label>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <input type="text" id="qc-employee-code" class="form-control"
                               maxlength="32" style="text-transform:uppercase;flex:1"
                               placeholder="ex: EMP001"
                               autocomplete="off">
                        <span id="qc-code-status" style="font-size:1rem;min-width:1.2rem;text-align:center" aria-live="polite"></span>
                    </div>
                    <span class="form-hint"><?= __('employee_code_hint') ?></span>
                    <span id="qc-code-error" class="form-hint" style="color:var(--color-danger,#ef4444);display:none">
                        <?= __('employee_code_taken') ?>
                    </span>
                </div>
                <p class="form-hint" style="margin-top:-.25rem;margin-bottom:.75rem">
                    <?= __('quick_create_email_hint') ?>
                </p>
                <div class="form-actions">
                    <button type="button" id="qc-submit" class="btn btn--primary" onclick="qcSubmit()">
                        <?= __('create') ?>
                    </button>
                    <button type="button" class="btn btn--ghost" onclick="qcClose()">
                        <?= __('cancel') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var BASE_URL    = <?= json_encode($BASE_URL) ?>;
    var STORE_ID    = <?= (int) $store_id ?>;
    var introTpl    = <?= json_encode(__('quick_create_intro')) ?>;
    var msgSuccess  = <?= json_encode(__('quick_create_success')) ?>;
    var msgError    = <?= json_encode(__('quick_create_error')) ?>;

    // Référence de la ligne en cours d'édition
    var _currentRow = null;

    // --- Vérification live du code employé ---
    var _codeCheckTimer = null;
    var _codeAvailable  = true; // null = en cours, true = dispo, false = pris

    function resetCodeStatus() {
        clearTimeout(_codeCheckTimer);
        _codeAvailable = true;
        document.getElementById('qc-code-status').textContent = '';
        document.getElementById('qc-code-error').style.display = 'none';
    }

    document.getElementById('qc-employee-code').addEventListener('input', function () {
        var code = this.value.trim().toUpperCase();
        resetCodeStatus();

        if (code === '') return;

        var statusEl = document.getElementById('qc-code-status');
        statusEl.textContent = '⏳';
        _codeAvailable = null;

        _codeCheckTimer = setTimeout(function () {
            fetch(BASE_URL + '/admin/users/check-employee-code?code=' + encodeURIComponent(code), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                _codeAvailable = data.available;
                statusEl.textContent = data.available ? '✅' : '❌';
                document.getElementById('qc-code-error').style.display =
                    data.available ? 'none' : '';
            })
            .catch(function () {
                statusEl.textContent = '';
                _codeAvailable = true; // En cas d'erreur réseau, on laisse passer
            });
        }, 350); // debounce 350 ms
    });

    window.qcOpen = function (btn) {
        _currentRow = btn.closest('tr');
        var staffName = btn.dataset.staff || '';

        // Pré-remplir prénom / nom depuis le nom du fichier Excel
        var parts = staffName.split(/\s+/);
        document.getElementById('qc-first-name').value = parts[0] || staffName;
        document.getElementById('qc-last-name').value  = parts.slice(1).join(' ');

        // Intro
        document.getElementById('qc-intro').innerHTML =
            introTpl.replace(':name', escHtml(staffName));

        // Réinitialiser le code employé et les erreurs
        document.getElementById('qc-employee-code').value = '';
        resetCodeStatus();

        // Masquer alerte précédente
        var alertEl = document.getElementById('qc-alert');
        alertEl.style.display = 'none';
        alertEl.className = 'alert mb-sm';

        document.getElementById('qc-overlay').classList.add('open');
        document.getElementById('qc-first-name').focus();
    };

    window.qcClose = function () {
        document.getElementById('qc-overlay').classList.remove('open');
        resetCodeStatus();
        _currentRow = null;
    };

    window.qcSubmit = function () {
        var firstName = document.getElementById('qc-first-name').value.trim();
        var lastName  = document.getElementById('qc-last-name').value.trim();
        var empCode   = document.getElementById('qc-employee-code').value.trim().toUpperCase();

        if (!firstName) {
            document.getElementById('qc-first-name').focus();
            return;
        }

        document.getElementById('qc-code-error').style.display = 'none';

        // Bloquer si le code est déjà pris ou si la vérification est en cours
        if (_codeAvailable === false) {
            document.getElementById('qc-code-error').style.display = '';
            document.getElementById('qc-employee-code').focus();
            return;
        }
        if (_codeAvailable === null) {
            // Vérification encore en cours — on attend
            return;
        }

        var btn = document.getElementById('qc-submit');
        btn.disabled = true;

        var body = new URLSearchParams();
        body.append('first_name',    firstName);
        body.append('last_name',     lastName);
        body.append('store_id',      STORE_ID);
        body.append('employee_code', empCode);

        fetch(BASE_URL + '/admin/users/quick-create', {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded',
                      'X-Requested-With': 'XMLHttpRequest'},
            body:    body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (!data.success) {
                if (data.error === 'employee_code_taken') {
                    document.getElementById('qc-code-error').style.display = '';
                    document.getElementById('qc-employee-code').focus();
                } else {
                    showAlert('alert--error', msgError + (data.error ? ' — ' + data.error : ''));
                }
                return;
            }
            var newUser = data.user; // {id, name}

            // Ajouter l'option dans tous les selects de la page
            document.querySelectorAll('.import-select').forEach(function (sel) {
                var opt = document.createElement('option');
                opt.value       = newUser.id;
                opt.textContent = newUser.name;
                sel.appendChild(opt);
            });

            // Sélectionner le nouvel utilisateur sur la ligne courante + toutes les lignes
            // ayant le même staff_name
            var staffName = _currentRow ? _currentRow.dataset.staff : '';
            document.querySelectorAll('tbody tr[data-idx]').forEach(function (row) {
                if (row.dataset.staff === staffName) {
                    var sel = row.querySelector('[data-field="user_id"]');
                    if (sel) sel.value = newUser.id;
                    // Masquer le bouton "+ Créer" sur ces lignes
                    var createBtn = row.querySelector('.qc-open-btn');
                    if (createBtn) createBtn.style.display = 'none';
                    // Retirer la classe d'avertissement orange
                    row.classList.remove('tr-warn');
                }
            });

            showAlert('alert--success', msgSuccess);
            setTimeout(qcClose, 1200);
        })
        .catch(function () {
            btn.disabled = false;
            showAlert('alert--error', msgError);
        });
    };

    function showAlert(cls, msg) {
        var el = document.getElementById('qc-alert');
        el.className = 'alert ' + cls + ' mb-sm';
        el.textContent = msg;
        el.style.display = '';
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Délégation d'événement sur tous les boutons ".qc-open-btn"
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.qc-open-btn');
        if (btn) qcOpen(btn);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') qcClose();
    });
}());

// --- Filtres par type de conflit ---
(function () {
    var filterTpl = <?= json_encode(__('import_filter_count')) ?>;

    function updateCount() {
        var visible = document.querySelectorAll('tbody tr[data-idx]:not([style*="display: none"])').length;
        var el = document.getElementById('import-filter-count');
        if (el) el.textContent = filterTpl.replace(':n', visible);
    }

    function applyFilters() {
        var active = {};
        document.querySelectorAll('.import-filter-btn').forEach(function (btn) {
            active[btn.dataset.filter] = btn.classList.contains('active');
        });
        document.querySelectorAll('tbody tr[data-idx]').forEach(function (row) {
            var type = row.dataset.conflict || 'ok';
            row.style.display = active[type] === false ? 'none' : '';
        });
        updateCount();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.import-filter-btn');
        if (!btn) return;
        btn.classList.toggle('active');
        applyFilters();
    });

    updateCount();
}());

document.getElementById('import-form').addEventListener('submit', function (e) {
    e.preventDefault();

    var shifts = [];
    document.querySelectorAll('tbody tr[data-idx]').forEach(function (row) {
        shifts.push({
            date:       row.dataset.date,
            staff_name: row.dataset.staff,
            start_time: row.querySelector('[data-field="start_time"]').value,
            end_time:   row.querySelector('[data-field="end_time"]').value,
            user_id:    row.querySelector('[data-field="user_id"]').value,
            skip:       row.querySelector('[data-field="skip"]').checked ? '1' : '0'
        });
    });

    document.getElementById('shifts_json').value = JSON.stringify(shifts);
    this.submit();
});
</script>
