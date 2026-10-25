# Dockerfile
FROM php:8.2-apache

# 1. Installation des dépendances système requises pour les extensions PHP
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# 2. Installation des extensions PHP
RUN docker-php-ext-install pdo pdo_mysql intl opcache zip

# 3. Configuration d'Apache pour Symfony
# Activation du module de réécriture
RUN a2enmod rewrite
# Modification du DocumentRoot pour pointer vers le dossier /public de Symfony
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Définition du répertoire de travail
WORKDIR /var/www/html

# 4. Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copie de tout le projet dans le conteneur
COPY . /var/www/html/

# 5. Installation des dépendances (sans les dépendances de dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 6. Configuration des permissions
# On crée les dossiers s'ils n'existent pas (au cas où) et on donne les droits à Apache
RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data /var/www/html/var /var/www/html/vendor