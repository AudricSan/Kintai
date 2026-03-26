FROM php:8.2-apache

# Extensions système requises
RUN apt-get update && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libxml2-dev \
        libicu-dev \
        zip \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        mbstring \
        gd \
        zip \
        xml \
        dom \
        intl \
        bcmath \
        fileinfo \
    && rm -rf /var/lib/apt/lists/*

# Activer mod_rewrite pour les pretty URLs
RUN a2enmod rewrite

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuration Apache : DocumentRoot → public/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copier l'application
COPY . .

# Installer les dépendances PHP (sans dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Préparer le dossier storage avec les bonnes permissions
RUN mkdir -p storage/app storage/logs \
    && chown -R www-data:www-data storage config \
    && chmod -R 775 storage

# Script de démarrage
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
