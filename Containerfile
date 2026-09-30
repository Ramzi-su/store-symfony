FROM php:8.4-apache

# 1. Installer les dépendances système
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install intl pdo_mysql zip opcache

# 2. Configurer Apache pour pointer vers public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 3. Activer le module rewrite (URL Rewriting de Symfony)
RUN a2enmod rewrite

# 4. Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copier les fichiers du projet
COPY . /var/www/html/

# 6. Donner les bons droits
RUN mkdir -p /var/www/html/var && chown -R www-data:www-data /var/www/html/var

# 7. Forcer l'environnement de production et donner une fausse URL de DB pour la compilation
ENV APP_ENV=prod
ENV DATABASE_URL="mysql://user:pass@127.0.0.1:3306/db?serverVersion=8.0.32&charset=utf8mb4"

# 8. Installer les dépendances (sans celles de dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 9. Démarrer le serveur : Lancer les migrations PUIS démarrer Apache
CMD php bin/console doctrine:migrations:migrate --no-interaction && apache2-foreground
