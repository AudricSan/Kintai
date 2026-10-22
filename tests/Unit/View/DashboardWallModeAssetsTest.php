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

    public function testTheScriptRefusesWallModeOnPhonesAndLeavesItWhenTheScreenShrinks(): void
    {
        $this->assertStringContainsString("window.matchMedia('(max-width: 768px)')", $this->js);
        $this->assertMatchesRegularExpression('/function enterWall\(\) \{\s*if \(phone\.matches\) return;/', $this->js);
        $this->assertStringContainsString('if (phone.matches && document.documentElement.classList.contains(\'dash-wall\')) exitWall();', $this->js);
    }

    public function testTheExitButtonIsOnlyEnabledInWallMode(): void
    {
        $this->assertStringContainsString('wallExit.hidden = !on; wallExit.disabled = !on;', $this->js);
    }
}
