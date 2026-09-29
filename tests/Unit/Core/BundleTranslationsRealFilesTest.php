<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Repositories\JsonTranslationRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

/**
 * Vérifie, sur les vrais fichiers lang/*.json (pas des fixtures synthétiques), que la
 * migration des clés bundle-exclusives hors du Core n'a laissé aucun trou : chaque
 * bundle migré résout bien ses propres clés, et le Core reste accessible en fallback
 * pour les clés partagées. Depuis que TeamDirectory (le dernier bundle du monorepo) a
 * été extrait vers son propre dépôt, plus aucun bundle n'est en legacy dans
 * src/Bundles/ : tous sont couverts par distributedBundleKeyProvider() (voir
 * docs/architecture.md "Modular Bundles"), dont tests/Fixtures/bundles/<slug>-<version>/
 * est une copie fidèle utilisée à la fois comme fixture de test et comme source ayant
 * servi à peupler le dépôt externe.
 */
final class BundleTranslationsRealFilesTest extends TestCase
{
    private JsonTranslationRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new JsonTranslationRepository(BASE_PATH . '/lang', BASE_PATH . '/src/Bundles');
    }

    /** @return array<string, array{0: string, 1: string}> bundle => [clé migrée, dossier sous tests/Fixtures/bundles/] */
    public static function distributedBundleKeyProvider(): array
    {
        return [
            'Feedback'          => ['feedback_deleted', 'feedback-1.0.0'],
            'DailyReport'       => ['bundle_daily_report', 'daily-report-1.0.0'],
            'Messaging'         => ['bundle_messaging', 'messaging-1.0.0'],
            'HiringReport'      => ['bundle_hiring_report', 'hiring-report-1.0.0'],
            'ResignationReport' => ['bundle_resignation_report', 'resignation-report-1.0.0'],
            'ShiftSwap'         => ['bundle_shift_swap', 'shift-swap-1.0.0'],
            'SalaryReport'      => ['sr_pdf', 'salary-report-1.0.0'],
            'ShiftClaim'        => ['bundle_shift_claim', 'shift-claim-1.0.0'],
            'StorePhoto'        => ['photo_upload', 'store-photos-1.0.0'],
            'Timeclock'         => ['bundle_timeclock', 'timeclock-1.0.0'],
            'TimeOff'           => ['bundle_timeoff', 'timeoff-1.0.0'],
            'TeamDirectory'     => ['bundle_team_directory', 'team-directory-1.0.0'],
        ];
    }

    #[DataProvider('distributedBundleKeyProvider')]
    public function testEachDistributedBundleOwnsItsKeyInEveryLocale(string $key, string $fixtureDir): void
    {
        $fixtureRoot = BASE_PATH . "/tests/Fixtures/bundles/{$fixtureDir}";
        $repo = new JsonTranslationRepository(BASE_PATH . '/lang', null, [$fixtureRoot]);

        foreach (['fr', 'en', 'ja'] as $locale) {
            $bundleFile = $fixtureRoot . "/lang/{$locale}.json";
            $this->assertFileExists($bundleFile, "Fichier de langue {$locale} manquant pour la fixture {$fixtureDir}");

            $bundleData = json_decode((string) file_get_contents($bundleFile), true);
            $this->assertArrayHasKey($key, $bundleData, "{$key} absent de {$bundleFile}");
            $this->assertSame($bundleData[$key], $repo->findValue($locale, $key));
        }
    }

    public function testCoreLangFilesNoLongerContainMigratedBundleKeys(): void
    {
        $allKeys = self::distributedBundleKeyProvider();

        foreach (['fr', 'en', 'ja'] as $locale) {
            $core = json_decode((string) file_get_contents(BASE_PATH . "/lang/{$locale}.json"), true);
            foreach ($allKeys as $bundleDir => [$key, $_dir]) {
                $this->assertArrayNotHasKey(
                    $key,
                    $core,
                    "{$key} devrait vivre dans le bundle {$bundleDir}, pas dans le Core"
                );
            }
        }
    }

    public function testSharedCoreStringsStayResolvableAlongsideBundleStrings(): void
    {
        // 'save' est une chaîne Core partagée par toute l'appli, y compris les bundles
        // (aucun ne la redéfinit) : elle doit rester accessible en fallback.
        $this->assertSame('Enregistrer', $this->repo->findValue('fr', 'save'));
        $this->assertSame('Save', $this->repo->findValue('en', 'save'));
    }
}
