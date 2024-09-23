# Utiliser l'image PHP avec FPM et Composer
FROM php:8.1-fpm

# Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql pdo_pgsql

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www

# Copier le fichier de configuration
COPY . .

# Installer les dépendances PHP
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Copier le fichier d'environnement
COPY .env.example .env

# Générer la clé d'application
RUN php artisan key:generate

# Changer les permissions du répertoire de stockage et du cache
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Exposer le port 9000 et démarrer le serveur PHP-FPM
EXPOSE 9000
CMD ["php-fpm"]
