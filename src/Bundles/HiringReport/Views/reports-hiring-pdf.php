<?php
/**
 * Template HTML for mPDF — Hiring Report (採用報告書)
 * Rendered standalone (no layout) for PDF generation, or directly as an
 * HTML preview (with a toolbar) when $downloadUrl is set — see
 * HasStaffReportCrud::reportPdf() vs reportPdfDownload().
 *
 * @var array       $report
 * @var array       $store
 * @var string|null $downloadUrl
 */
$genderLabel = match ($report['gender'] ?? null) {
    'male'   => __('male'),
    'female' => __('female'),
    default  => '—',
};
$taxLabel = match ($report['tax_classification'] ?? null) {
    'kou'  => __('tax_kou'),
    'otsu' => __('tax_otsu'),
    default => '—',
};
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
<?php
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(dirname(__DIR__, 4) . '/public/assets/css/pdf/pdf-preview.css');
?>
body { font-family: sans-serif; font-size: 10pt; color: #333; margin: 0; padding: 0; }
h1 { text-align: center; font-size: 16pt; margin-bottom: 4pt; }
.subtitle { text-align: center; font-size: 9pt; color: #666; margin-bottom: 20pt; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16pt; }
th { background: #eaeaea; padding: 6pt 8pt; text-align: left; font-size: 9pt; font-weight: 700; border: 1px solid #ccc; }
td { padding: 5pt 8pt; border: 1px solid #ccc; font-size: 9pt; }
.section-title { font-size: 11pt; font-weight: 700; margin-top: 18pt; margin-bottom: 6pt; }
.label-cell { width: 30%; font-weight: 600; background: #f7f7f7; }
.footer { text-align: center; font-size: 8pt; color: #999; margin-top: 24pt; border-top: 1px solid #ccc; padding-top: 8pt; }
</style>
</head>
<body>

<?php include __DIR__ . '/../../../UI/View/_partials/_pdf-preview-toolbar.php'; ?>
<div class="pdf-preview-page">

<h1><?= __('hiring_report') ?></h1>
<div class="subtitle"><?= htmlspecialchars($store['name'] ?? '') ?></div>

<table>
    <tr><th colspan="2"><?= __('section_identity') ?></th></tr>
    <tr><td class="label-cell"><?= __('employee_number') ?></td><td><?= htmlspecialchars($report['employee_number'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('employee_name') ?></td><td><?= htmlspecialchars($report['employee_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('furigana_last_name') ?></td><td><?= htmlspecialchars($report['furigana_last_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('furigana_first_name') ?></td><td><?= htmlspecialchars($report['furigana_first_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('gender') ?></td><td><?= htmlspecialchars($genderLabel) ?></td></tr>
    <tr><td class="label-cell"><?= __('tax_classification') ?></td><td><?= htmlspecialchars($taxLabel) ?></td></tr>
    <tr><td class="label-cell"><?= __('birth_date') ?></td><td><?= htmlspecialchars($report['birth_date'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('education') ?></td><td><?= htmlspecialchars($report['education'] ?? '') ?></td></tr>
</table>

<table>
    <tr><th colspan="2"><?= __('section_contact') ?></th></tr>
    <tr><td class="label-cell"><?= __('postal_code') ?></td><td><?= htmlspecialchars($report['postal_code'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('address') ?></td><td><?= nl2br(htmlspecialchars($report['address'] ?? '')) ?></td></tr>
    <tr><td class="label-cell"><?= __('phone') ?></td><td><?= htmlspecialchars($report['phone'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('mobile_phone') ?></td><td><?= htmlspecialchars($report['mobile_phone'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('email') ?></td><td><?= htmlspecialchars($report['email'] ?? '') ?></td></tr>
</table>

<table>
    <tr><th colspan="2"><?= __('section_guarantor') ?></th></tr>
    <tr><td class="label-cell"><?= __('guarantor_name') ?></td><td><?= htmlspecialchars($report['guarantor_name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('guarantor_phone') ?></td><td><?= htmlspecialchars($report['guarantor_phone'] ?? '') ?></td></tr>
</table>

<table>
    <tr><th colspan="2"><?= __('section_employment') ?></th></tr>
    <tr><td class="label-cell"><?= __('store_name') ?></td><td><?= htmlspecialchars($report['store_name'] ?? $store['name'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('hired_by') ?></td><td><?= htmlspecialchars($report['hired_by'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('hire_date') ?></td><td><?= htmlspecialchars($report['hire_date'] ?? '') ?></td></tr>
    <tr><td class="label-cell"><?= __('notes') ?></td><td><?= nl2br(htmlspecialchars($report['notes'] ?? '')) ?></td></tr>
</table>

<div class="footer">
    <?= __('pdf_generated_by') ?> Kintai — <?= date('Y-m-d H:i') ?>
</div>

</div>
</body>
</html>
