<?php
/**
 * Template HTML optimisé mPDF — 退職報告書 (Resignation Report)
 * Rendu sans layout : soit pour la génération PDF serveur, soit directement
 * comme aperçu navigateur (avec barre d'outils) quand $downloadUrl est
 * fourni — voir HasStaffReportCrud::reportPdf() vs reportPdfDownload().
 *
 * @var array       $report
 * @var array       $store
 * @var string|null $downloadUrl
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
<?php
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-base.css');
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-preview.css');
?>
body { font-family: sans-serif; font-size: 12pt; color: #333; }
h1 { text-align: center; font-size: 18pt; margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
th { background: #f5f5f5; font-weight: bold; width: 30%; }
.footer { text-align: center; font-size: 9pt; color: #999; margin-top: 30px; }
</style>
</head>
<body>

<?php include __DIR__ . '/../../../UI/View/_partials/_pdf-preview-toolbar.php'; ?>
<div class="pdf-preview-page">

<h1><?= __('resignation_report_pdf_title') ?></h1>

<p style="text-align:right;font-size:10pt;color:#666;">
    <?= __('date') ?>: <?= date('Y-m-d') ?>
</p>

<table>
    <tr>
        <th><?= __('employee_number') ?></th>
        <td><?= htmlspecialchars($report['employee_number'] ?? '—') ?></td>
    </tr>
    <tr>
        <th><?= __('employee_name') ?></th>
        <td><?= htmlspecialchars($report['employee_name'] ?? '—') ?></td>
    </tr>
    <tr>
        <th><?= __('resignation_date') ?></th>
        <td><?= htmlspecialchars($report['resignation_date'] ?? '—') ?></td>
    </tr>
    <tr>
        <th><?= __('reason') ?></th>
        <td><?= nl2br(htmlspecialchars($report['reason'] ?? '—')) ?></td>
    </tr>
    <tr>
        <th><?= __('resignation_notice') ?></th>
        <td><?= nl2br(htmlspecialchars($report['resignation_notice'] ?? '—')) ?></td>
    </tr>
    <tr>
        <th><?= __('notes') ?></th>
        <td><?= nl2br(htmlspecialchars($report['notes'] ?? '—')) ?></td>
    </tr>
    <tr>
        <th><?= __('person_in_charge') ?></th>
        <td><?= htmlspecialchars($report['person_in_charge'] ?? '—') ?></td>
    </tr>
</table>

<div class="footer">
    <?= htmlspecialchars($store['name'] ?? '') ?> — <?= date('Y-m-d H:i') ?>
</div>

</div>
</body>
</html>
