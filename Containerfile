FROM php:8.4-apache

# 1. Installer les dépendances système
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install intl pdo_mysql pdo_pgsql zip opcache

# 2. Configurer Apache pour pointer vers public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 3. Activer le module rewrite et configurer le routage Symfony (FallbackResource)
RUN a2enmod rewrite
RUN echo "FallbackResource /index.php" >> /etc/apache2/apache2.conf

# 4. Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copier les fichiers du projet
COPY . /var/www/html/

# 6. Forcer l'environnement de production et donner une fausse URL de DB pour la compilation
ENV APP_ENV=prod
ENV DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/db?serverVersion=16&charset=utf8"

# 7. Installer les dépendances (sans celles de dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 8. Pré-chauffer le cache prod et compiler les assets (évite les erreurs de permissions à l'exécution)
RUN php bin/console cache:warmup --env=prod
RUN php bin/console asset-map:compile --env=prod

# 9. Donner les bons droits sur var/ APRES la génération du cache
RUN chown -R www-data:www-data /var/www/html/var

# 10. Démarrer le serveur : Lancer les migrations PUIS démarrer Apache
CMD php bin/console doctrine:migrations:migrate --no-interaction && apache2-foreground
