<?php

/**
 * Configuration des notifications push mobiles (Firebase Cloud Messaging).
 *
 * FCM sert de relais unique vers Android, iOS (via APNs) et le web push — un
 * seul fournisseur à intégrer côté serveur au lieu de deux (FCM + APNs séparés).
 *
 * Pour l'activer :
 *   1. Créer un projet sur https://console.firebase.google.com (gratuit).
 *   2. Paramètres du projet → Comptes de service → "Générer une nouvelle clé
 *      privée" : télécharge un fichier JSON.
 *   3. Déposer ce fichier dans storage/ (déjà entièrement ignoré par git —
 *      voir .gitignore), jamais dans le dépôt.
 *   4. Renseigner dans .env (chemin relatif à storage/) :
 *
 *   PUSH_FCM_ENABLED=true
 *   PUSH_FCM_PROJECT_ID=votre-projet-id
 *   PUSH_FCM_CREDENTIALS_PATH=fcm-service-account.json
 *
 * Tant que PUSH_FCM_ENABLED n'est pas à true, PushNotificationService ne fait
 * rien (no-op silencieux) — aucune notification en base n'est bloquée par
 * l'absence de configuration push.
 */

declare(strict_types=1);

$credentialsFile = (string) env('PUSH_FCM_CREDENTIALS_PATH', '');

return [
    'fcm' => [
        'enabled'          => filter_var(env('PUSH_FCM_ENABLED', false), FILTER_VALIDATE_BOOL),
        'project_id'       => env('PUSH_FCM_PROJECT_ID', ''),
        'credentials_path' => $credentialsFile !== '' ? storage_path($credentialsFile) : '',
    ],
];
