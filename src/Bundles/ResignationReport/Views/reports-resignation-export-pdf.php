<?php
/**
 * Template HTML for mPDF — Resignation reports list export (item 5).
 * Rendered standalone (no layout) for PDF generation.
 *
 * @var array  $reports
 * @var array  $store_names  Map store_id => nom
 * @var string $generated_at
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: sans-serif; font-size: 8pt; color: #333; margin: 0; padding: 0; }
h1 { text-align: center; font-size: 15pt; margin-bottom: 4pt; }
.subtitle { text-align: center; font-size: 9pt; color: #666; margin-bottom: 16pt; }
table { width: 100%; border-collapse: collapse; }
th { background: #eaeaea; padding: 4pt 5pt; text-align: left; font-size: 7.5pt; font-weight: 700; border: 1px solid #ccc; }
td { padding: 4pt 5pt; border: 1px solid #ccc; font-size: 7.5pt; vertical-align: top; }
.text-muted { color: #999; }
.footer { text-align: center; font-size: 8pt; color: #999; margin-top: 16pt; border-top: 1px solid #ccc; padding-top: 8pt; }
</style>
</head>
<body>

<h1><?= __('resignation_reports') ?></h1>
<div class="subtitle"><?= count($reports) ?> — <?= htmlspecialchars($generated_at) ?></div>

<table>
    <tr>
        <th><?= __('store') ?></th>
        <th><?= __('employee_number') ?></th>
        <th><?= __('employee_name') ?></th>
        <th><?= __('resignation_date') ?></th>
        <th><?= __('person_in_charge') ?></th>
    </tr>
    <?php foreach ($reports as $r): ?>
    <tr>
        <td><?= htmlspecialchars($store_names[(int) ($r['store_id'] ?? 0)] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['employee_number'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['employee_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['resignation_date'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['person_in_charge'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="footer">
    <?= __('pdf_generated_by') ?> Kintai — <?= htmlspecialchars($generated_at) ?>
</div>

</body>
</html>
