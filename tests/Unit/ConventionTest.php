<?php

declare(strict_types=1);

namespace kintai\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ConventionTest extends TestCase
{
    private const EXCEPTIONS = [
        'employee-payslip-pdf.php',   // mPDF requires embedded CSS
        'shifts-timeline-print.php',  // dynamic date in @page @bottom-center
        'reports-hiring-pdf.php',     // mPDF requires embedded CSS
        'reports-resignation-pdf.php',// mPDF requires embedded CSS
        'reports-salary-pdf.php',     // mPDF requires embedded CSS
    ];

    private const DOMAIN_DIRS = [
        'scheduling', 'staff', 'requests', 'analytics', 'system',
    ];

    public function testNoInlineCssInWebViews(): void
    {
        $base = dirname(__DIR__, 2) . '/src/UI/View';

        $violations = [];
        foreach (self::DOMAIN_DIRS as $dir) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator("$base/$dir", \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if (in_array($file->getBasename(), self::EXCEPTIONS, true)) {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                if (preg_match('/<style[^>]*>/i', $content)) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        self::assertEmpty($violations, implode("\n", array_map(
            fn($v) => str_replace('\\', '/', $v),
            $violations
        )));
    }
}
