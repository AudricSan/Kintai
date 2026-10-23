<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

/**
 * Mode mural du tableau de bord : « Quitter » n'existe qu'en mode mural, « Activer » est inutilisable sur téléphone.
 *
 * Régression d'origine : .btn fixe un display qui l'emporte sur le [hidden] de la feuille de style du navigateur, si
 * bien que « Quitter le mode mural » restait visible hors mode mural. Le comportement tient dans le CSS et le JS, sans
 * navigateur ici : on garde donc les règles qui le garantissent.
 */
final class DashboardWallModeAssetsTest extends TestCase
{
    private string $css;
    private string $js;
    private string $view;

    protected function setUp(): void
    {
        $root       = dirname(__DIR__, 3);
        $this->css  = (string) file_get_contents($root . '/public/assets/css/src/features/dashboard.css');
        $this->js   = (string) file_get_contents($root . '/public/assets/js/modules/dashboard-live.js');
        $this->view = (string) file_get_contents($root . '/src/UI/View/_partials/_dashboard-actions.php');
    }

    public function testTheExitButtonStartsHiddenAndDisabled(): void
    {
        $this->assertMatchesRegularExpression('/data-dash-wall-exit hidden disabled/', $this->view);
    }

    public function testTheHiddenAttributeReallyHidesTheExitButton(): void
    {
        // Sans cette règle, .btn { display: … } rend le bouton visible malgré hidden.
        $this->assertMatchesRegularExpression('/\.dash-wall-exit\[hidden\]\s*\{\s*display:\s*none;?\s*\}/', $this->css);
    }

    public function testTheWallButtonIsHiddenOnPhones(): void
    {
        $this->assertMatchesRegularExpression('/@media \(max-width: 768px\)\s*\{\s*\[data-dash-wall\]\s*\{\s*display:\s*none;?\s*\}/', $this->css);
    }

    public function testTheScriptRefusesWallModeOnPhones(): void
    {
        $this->assertStringContainsString("window.matchMedia('(max-width: 768px)')", $this->js);
        $this->assertMatchesRegularExpression('/function enterWall\(\) \{\s*if \(phone\.matches/', $this->js);
    }

    public function testResizingTheScreenDoesNotUnlockWallMode(): void
    {
        // Avant le mot de passe, un écran réduit quittait le mode mural : ce serait maintenant un moyen de contourner le verrou.
        $this->assertStringNotContainsString("classList.contains('dash-wall')) exitWall();", $this->js);
        $this->assertDoesNotMatchRegularExpression('/phone\.matches\s*&&[^;]*exitWall\(\)/', $this->js);
    }

    public function testEnablingWallModeAsksForAPasswordFirst(): void
    {
        $this->assertMatchesRegularExpression("/function enterWall\(\).*?askPassword\('set',\s*function \(\) \{\s*setWall\(true\);/s", $this->js);
        $this->assertStringContainsString('data-wall-lock-confirm', $this->view);
        $this->assertStringContainsString('<dialog class="wall-lock" data-wall-lock>', $this->view);
    }

    public function testEveryWayOutAsksForThePassword(): void
    {
        // Bouton « Quitter », Échap et sortie du plein écran passent tous par requestExit(), jamais par exitWall() directement.
        $this->assertMatchesRegularExpression("/data-dash-wall-exit\]'\)\) \{\s*requestExit\(\);/", $this->js);
        $this->assertMatchesRegularExpression("/e\.key === 'Escape' && inWall\(\) && !document\.fullscreenElement\) requestExit\(\);/", $this->js);
        $this->assertMatchesRegularExpression('/fullscreenchange.*?requestExit\(\);/s', $this->js);
        $this->assertStringContainsString("askPassword('unlock', exitWall, goFullscreen)", $this->js);
    }

    public function testThePasswordNeverLeavesThePage(): void
    {
        $code = (string) preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $this->js);

        foreach (['localStorage', 'sessionStorage', 'document.cookie', 'XMLHttpRequest'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, "Le mot de passe du mode mural ne doit pas passer par $forbidden.");
        }
        // Le seul fetch du module relit la page des widgets ; il n'embarque aucun corps de requête.
        $this->assertStringNotContainsString('body:', $code);
        $this->assertStringContainsString('var wallPassword = null;', $code);
    }

    public function testTheLockDialogStaysVisibleInWallMode(): void
    {
        // Les actions de l'en-tête sont masquées en mode mural, sauf « Quitter » et la boîte du mot de passe.
        $this->assertStringContainsString(':not(.dash-wall-exit):not(.wall-lock)', $this->css);
    }

    public function testEveryTextOfTheLockDialogIsTranslated(): void
    {
        $root = dirname(__DIR__, 3);
        preg_match_all("/__\('(dashboard_wall_[a-z_]+)'\)/", $this->view, $m);
        $this->assertNotEmpty($m[1]);

        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents($root . "/lang/$locale.json"), true);
            foreach (array_unique($m[1]) as $key) {
                $this->assertArrayHasKey($key, $lang, "Clé « $key » absente de lang/$locale.json");
            }
        }
    }

    public function testTheExitButtonIsOnlyEnabledInWallMode(): void
    {
        $this->assertStringContainsString('wallExit.hidden = !on; wallExit.disabled = !on;', $this->js);
    }
}
