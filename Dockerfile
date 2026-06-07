# LiveZilla server - PHP live chat software
#
# Legacy codebase: requires PHP >= 5.6 and the mysqli extension.
# We pin to PHP 7.4 because newer PHP (8.2+) deprecates/removes functions the
# code relies on (e.g. utf8_encode), which breaks the app at runtime.
FROM php:7.4-apache

# --- System packages needed to build the PHP extensions ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libcurl4-openssl-dev \
        libldap2-dev \
        libonig-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions required by LiveZilla ---
#  mysqli  : database driver
#  gd      : dynamic image generation (with jpeg/freetype support)
#  curl    : PUSH messages / social media
#  mbstring: email parsing
#  ldap    : optional directory authentication
#  zip     : packaging / uploads
# iconv, phar, xml are bundled with the base image already.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap --with-libdir=lib/$(uname -m)-linux-gnu/ \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        gd \
        curl \
        mbstring \
        ldap \
        zip

# --- Apache configuration ---
# Enable mod_rewrite (used by the knowledge-base pretty URLs) and allow
# .htaccess overrides under the document root.
RUN a2enmod rewrite \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# --- Sensible PHP defaults for handling email attachments / uploads ---
RUN { \
        echo 'memory_limit = 256M'; \
        echo 'upload_max_filesize = 64M'; \
        echo 'post_max_size = 64M'; \
        echo 'max_execution_time = 120'; \
    } > /usr/local/etc/php/conf.d/livezilla.ini

# --- Application code ---
COPY . /var/www/html/

# LiveZilla ships its rewrite rules in a non-standard filename. Activate them.
RUN if [ -f /var/www/html/_htaccess_mod_rewrite ]; then \
        cp /var/www/html/_htaccess_mod_rewrite /var/www/html/.htaccess; \
    fi

# The web installer and the app need write access to these directories.
RUN mkdir -p \
        /var/www/html/_config \
        /var/www/html/_language \
        /var/www/html/uploads/internal \
        /var/www/html/uploads/external \
        /var/www/html/_log \
        /var/www/html/stats/day \
        /var/www/html/stats/month \
        /var/www/html/stats/year \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Auto-remove the install/ folder once installation is complete (see script).
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
