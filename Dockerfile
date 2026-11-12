# Production-ready, reproducible EduPulse PHP 8.2 Apache image
FROM php:8.2-apache

LABEL org.opencontainers.image.title="EduPulse Academic Portal" \
      org.opencontainers.image.description="Integrated Student Academic Performance Management Platform (PHP 8.2 / Apache)"

# Install mysqli + pdo_mysql + CA certificates for Azure Database for MySQL TLS verification
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
    && update-ca-certificates \
    && docker-php-ext-install -j"$(nproc)" mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Harden Apache headers and enable rewrite/headers
RUN a2enmod rewrite headers \
    && printf "ServerTokens Prod\nServerSignature Off\n" > /etc/apache2/conf-available/security-hardening.conf \
    && a2enconf security-hardening

# Configure PHP production defaults (never print errors to HTTP responses; log to container stderr)
RUN printf "display_errors = Off\nlog_errors = On\nerror_log = /proc/self/fd/2\nupload_max_filesize = 12M\npost_max_size = 15M\nexpose_php = Off\n" \
    > /usr/local/etc/php/conf.d/edupulse.ini

WORKDIR /var/www/html

# Copy application source (secrets and local uploads excluded via .dockerignore)
COPY --chown=www-data:www-data . /var/www/html/

# Ensure runtime upload directories exist and are writable by www-data
RUN mkdir -p /var/www/html/uploads/assignments \
             /var/www/html/uploads/profile_photos \
             /var/www/html/uploads/documents \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/backend/api/health.php") === false ? 1 : 0);'
