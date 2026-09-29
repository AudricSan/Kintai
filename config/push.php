<?php

/**
 * Configuration des notifications push mobiles (Firebase Cloud Messaging).
 *
 * FCM sert de relais unique vers Android, iOS (via APNs) et le web push — un
 * seul fournisseur à intégrer côté serveur au lieu de deux (FCM + APNs séparés).
 *
 * Pour l'activer côté serveur (envoi) :
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
 *
 * Pour activer en plus la réception dans le navigateur (PWA/web push), qui a
 * besoin d'informations publiques distinctes du compte de service ci-dessus :
 *   5. Dans la même console Firebase → Paramètres du projet → onglet
 *      "Général" → "Ajouter une application" → Web (</>) si ce n'est pas déjà
 *      fait : récupère `apiKey` et `appId` dans la config générée.
 *   6. Paramètres du projet → Cloud Messaging → "Certificats Web Push" →
 *      "Générer une paire de clés" : récupère la clé publique VAPID.
 *   7. Renseigner dans .env — ces valeurs sont publiques par nature (envoyées
 *      au navigateur), contrairement au fichier de compte de service :
 *
 *   PUSH_FCM_WEB_API_KEY=...
 *   PUSH_FCM_WEB_APP_ID=...
 *   PUSH_FCM_WEB_VAPID_KEY=...
 *
 * Tant que PUSH_FCM_WEB_VAPID_KEY n'est pas renseignée, rien n'est chargé
 * côté navigateur (pas de script, pas d'écouteur) — voir NotificationMiddleware.
 */

declare(strict_types=1);

$credentialsFile = (string) env('PUSH_FCM_CREDENTIALS_PATH', '');

return [
    'fcm' => [
        'enabled'          => filter_var(env('PUSH_FCM_ENABLED', false), FILTER_VALIDATE_BOOL),
        'project_id'       => env('PUSH_FCM_PROJECT_ID', ''),
        'credentials_path' => $credentialsFile !== '' ? storage_path($credentialsFile) : '',
        'web'              => [
            'api_key'   => env('PUSH_FCM_WEB_API_KEY', ''),
            'app_id'    => env('PUSH_FCM_WEB_APP_ID', ''),
            'vapid_key' => env('PUSH_FCM_WEB_VAPID_KEY', ''),
        ],
    ],
];
