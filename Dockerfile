FROM php:8.2-apache

# Cài đặt extension PDO MySQL, PDO SQLite
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Cấu hình Apache nhận DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html/

# Cấp quyền ghi cho thư mục uploads và database (chứa SQLite runtime nếu không dùng MySQL)
RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/database

# Script khởi động tự động bind port theo biến môi trường $PORT (tương thích Render / Railway / Local)
RUN echo '#!/bin/bash\n\
PORT="${PORT:-80}"\n\
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\n\
sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/*.conf\n\
exec apache2-foreground\n' > /usr/local/bin/start-app.sh \
    && chmod +x /usr/local/bin/start-app.sh

EXPOSE 80 10000

CMD ["/usr/local/bin/start-app.sh"]
