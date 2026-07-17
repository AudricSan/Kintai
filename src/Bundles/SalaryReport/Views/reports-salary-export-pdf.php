<?php
/**
 * Template HTML for mPDF — Salary reports list export (item 5).
 * Rendered standalone (no layout) : soit pour la génération PDF serveur,
 * soit directement comme aperçu navigateur (avec barre d'outils) quand
 * $downloadUrl est fourni.
 *
 * @var array       $reports
 * @var array       $store_names  Map store_id => nom
 * @var string      $generated_at
 * @var string|null $downloadUrl
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
<?php
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-preview.css');
?>
body { font-family: sans-serif; font-size: 8pt; color: #333; margin: 0; padding: 0; }
h1 { text-align: center; font-size: 15pt; margin-bottom: 4pt; }
.subtitle { text-align: center; font-size: 9pt; color: #666; margin-bottom: 16pt; }
table { width: 100%; border-collapse: collapse; }
th { background: #eaeaea; padding: 4pt 5pt; text-align: left; font-size: 7.5pt; font-weight: 700; border: 1px solid #ccc; }
td { padding: 4pt 5pt; border: 1px solid #ccc; font-size: 7.5pt; vertical-align: top; }
.td-right { text-align: right; }
.footer { text-align: center; font-size: 8pt; color: #999; margin-top: 16pt; border-top: 1px solid #ccc; padding-top: 8pt; }
</style>
</head>
<body>

<?php include __DIR__ . '/../../../UI/View/_partials/_pdf-preview-toolbar.php'; ?>
<div class="pdf-preview-page">

<h1><?= __('sr_title') ?></h1>
<div class="subtitle"><?= count($reports) ?> — <?= htmlspecialchars($generated_at) ?></div>

<table>
    <tr>
        <th><?= __('store') ?></th>
        <th><?= __('sr_target_month') ?></th>
        <th><?= __('sr_person_in_charge') ?></th>
        <th class="td-right"><?= __('sr_total_payment') ?></th>
        <th class="td-right"><?= __('sr_net_payment') ?></th>
        <th class="td-right"><?= __('sr_active_employees') ?></th>
    </tr>
    <?php foreach ($reports as $r): ?>
    <tr>
        <td><?= htmlspecialchars($store_names[(int) ($r['store_id'] ?? 0)] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['target_month'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['person_in_charge'] ?? '—') ?></td>
        <td class="td-right"><?= number_format((float) ($r['total_payment'] ?? 0), 0) ?></td>
        <td class="td-right"><?= number_format((float) ($r['net_payment'] ?? 0), 0) ?></td>
        <td class="td-right"><?= (int) ($r['active_employees'] ?? 0) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="footer">
    <?= __('pdf_generated_by') ?> Kintai — <?= htmlspecialchars($generated_at) ?>
</div>

</div>
</body>
</html>
