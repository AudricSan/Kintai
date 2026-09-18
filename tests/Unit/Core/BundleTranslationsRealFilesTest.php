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
 * Vérifie, sur les vrais fichiers lang/*.json et src/Bundles/<Name>/lang/*.json du
 * dépôt (pas des fixtures synthétiques), que la migration des clés bundle-exclusives
 * hors du Core n'a laissé aucun trou : chaque bundle migré résout bien ses propres
 * clés, et le Core reste accessible en fallback pour les clés partagées.
 */
final class BundleTranslationsRealFilesTest extends TestCase
{
    private JsonTranslationRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new JsonTranslationRepository(BASE_PATH . '/lang', BASE_PATH . '/src/Bundles');
    }

    /**
     * @return array<string, array{0: string, 1: string}> bundle => [clé migrée, sous-répertoire]
     *
     * "Feedback" n'y figure plus : c'est le bundle pilote distribué hors monorepo
     * (voir docs/architecture.md "Modular Bundles"), ses traductions ne vivent plus
     * sous src/Bundles/ mais dans tests/Fixtures/bundles/feedback-1.0.0/lang/ (copie
     * du dépôt externe) et, une fois installé, storage/bundles/feedback/<version>/lang/.
     */
    public static function bundleKeyProvider(): array
    {
        return [
            'DailyReport'       => ['bundle_daily_report', 'DailyReport'],
            'HiringReport'      => ['bundle_hiring_report', 'HiringReport'],
            'Messaging'         => ['bundle_messaging', 'Messaging'],
            'ResignationReport' => ['bundle_resignation_report', 'ResignationReport'],
            'SalaryReport'      => ['sr_pdf', 'SalaryReport'],
            'ShiftClaim'        => ['bundle_shift_claim', 'ShiftClaim'],
            'ShiftSwap'         => ['bundle_shift_swap', 'ShiftSwap'],
            'StorePhoto'        => ['photo_upload', 'StorePhoto'],
            'TimeOff'           => ['bundle_timeoff', 'TimeOff'],
            'Timeclock'         => ['bundle_timeclock', 'Timeclock'],
        ];
    }

    #[DataProvider('bundleKeyProvider')]
    public function testEachBundleOwnsItsMigratedKeyInEveryLocale(string $key, string $bundleDir): void
    {
        foreach (['fr', 'en', 'ja'] as $locale) {
            $bundleFile = BASE_PATH . "/src/Bundles/{$bundleDir}/lang/{$locale}.json";
            $this->assertFileExists($bundleFile, "Fichier de langue {$locale} manquant pour {$bundleDir}");

            $bundleData = json_decode((string) file_get_contents($bundleFile), true);
            $this->assertArrayHasKey($key, $bundleData, "{$key} absent de {$bundleFile}");
            $this->assertNotSame('', $bundleData[$key]);

            // Et la clé doit rester résolvable via le dépôt agrégé (Core + bundles).
            $this->assertSame($bundleData[$key], $this->repo->findValue($locale, $key));
        }
    }

    public function testCoreLangFilesNoLongerContainMigratedBundleKeys(): void
    {
        foreach (['fr', 'en', 'ja'] as $locale) {
            $core = json_decode((string) file_get_contents(BASE_PATH . "/lang/{$locale}.json"), true);
            foreach (self::bundleKeyProvider() as [$key, $bundleDir]) {
                $this->assertArrayNotHasKey(
                    $key,
                    $core,
                    "{$key} devrait vivre dans src/Bundles/{$bundleDir}/lang/{$locale}.json, pas dans le Core"
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

    /**
     * Équivalent, pour le bundle pilote Feedback, de testEachBundleOwnsItsMigratedKeyInEveryLocale() :
     * ses traductions vivent désormais dans la fixture reflétant le dépôt externe, agrégées
     * par JsonTranslationRepository via installedBundleRoots (voir RepositoryServiceProvider).
     */
    public function testFeedbackFixtureOwnsItsKeyInEveryLocale(): void
    {
        $fixtureRoot = BASE_PATH . '/tests/Fixtures/bundles/feedback-1.0.0';
        $repo = new JsonTranslationRepository(BASE_PATH . '/lang', null, [$fixtureRoot]);

        foreach (['fr', 'en', 'ja'] as $locale) {
            $bundleFile = $fixtureRoot . "/lang/{$locale}.json";
            $this->assertFileExists($bundleFile, "Fichier de langue {$locale} manquant pour la fixture Feedback");

            $bundleData = json_decode((string) file_get_contents($bundleFile), true);
            $this->assertArrayHasKey('feedback_deleted', $bundleData);
            $this->assertSame($bundleData['feedback_deleted'], $repo->findValue($locale, 'feedback_deleted'));
        }
    }
}
