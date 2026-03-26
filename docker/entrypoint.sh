#!/bin/bash
set -e

# Render expose le port via $PORT (défaut : 80 si non défini)
export PORT="${PORT:-80}"

# Configurer Apache pour écouter sur le bon port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/\${PORT}/${PORT}/" /etc/apache2/sites-available/000-default.conf

# Créer les dossiers storage si absents (filesystem éphémère sur Render free)
mkdir -p /var/www/html/storage/app /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage /var/www/html/config
chmod -R 775 /var/www/html/storage

# Auto-installation au premier démarrage (ou après un redémarrage éphémère)
if [ ! -f "/var/www/html/storage/installed.lock" ]; then
    echo "[Kintai] Lancement de l'installation automatique..."
    php /var/www/html/scripts/docker-setup.php
    echo "[Kintai] Installation terminée."
fi

exec "$@"
