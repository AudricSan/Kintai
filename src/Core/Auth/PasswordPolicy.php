<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

/**
 * Règle unique de longueur d'un mot de passe choisi par l'utilisateur (changement depuis le profil,
 * réinitialisation par e-mail). Avant, chaque parcours avait son propre seuil (8 à la réinitialisation,
 * 4 au profil) : changer son mot de passe depuis le profil permettait de choisir plus faible que ce que
 * la réinitialisation exigeait.
 *
 * Ne s'applique pas au mot de passe par défaut « 0000 » attribué à la création d'un compte : c'est une
 * décision assumée (l'utilisateur voit un avertissement tant qu'il ne l'a pas changé).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    public static function isLongEnough(string $password): bool
    {
        return mb_strlen($password) >= self::MIN_LENGTH;
    }
}
