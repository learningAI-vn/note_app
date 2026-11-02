FROM php:8.2-apache

# Enable Apache mod_rewrite and install MySQL PDO & GD extensions
RUN a2enmod rewrite \
    && docker-php-ext-install pdo pdo_mysql mysqli

# Configure PHP upload limits for note attachments (images/videos/files)
RUN { \
    echo "upload_max_filesize=20M"; \
    echo "post_max_size=25M"; \
    echo "memory_limit=256M"; \
    echo "display_errors=Off"; \
    echo "log_errors=On"; \
} > /usr/local/etc/php/conf.d/note-app.ini

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/avatars /var/www/html/uploads/attachments /var/www/html/logs/mails \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs \
    && chmod -R 775 /var/www/html/uploads /var/www/html/logs

EXPOSE 80
