FROM php:8.2-apache

# --- Dépendances système et extensions PHP requises par Flarum ---
RUN apt-get update && apt-get install -y --no-install-recommends \
      git \
      unzip \
      libicu-dev \
      libzip-dev \
      libpng-dev \
      libjpeg-dev \
      libfreetype6-dev \
      libcurl4-openssl-dev \
      libonig-dev \
      default-mysql-client \
      nodejs \
      npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
      pdo_mysql \
      mysqli \
      gd \
      intl \
      zip \
      bcmath \
      curl \
      mbstring \
      exif \
      fileinfo \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Apache : mod_rewrite + vhost Flarum ---
RUN a2enmod rewrite headers
COPY apache-flarum.conf /etc/apache2/sites-available/000-default.conf

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Entrypoint : installe Flarum si /app est vide, sinon démarre juste Apache ---
COPY scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
