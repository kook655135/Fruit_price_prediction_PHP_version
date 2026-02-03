FROM php:8.1-apache

# 設定時區
ENV TZ=Asia/Taipei

# 1. 安裝系統依賴
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 安裝 PHP 擴充
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 2. 啟用 Apache Rewrite 模組
RUN a2enmod rewrite

# 3. 安裝最新版 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. 設定工作目錄
WORKDIR /var/www/html

# 修改 Apache 配置
ENV APACHE_DOCUMENT_ROOT /var/www/html/backend/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

# 修正 Apache 權限設定 (解決 Permission Denied 問題)
RUN echo "<Directory \${APACHE_DOCUMENT_ROOT}>" > /etc/apache2/conf-available/laravel.conf && \
    echo "    Options Indexes FollowSymLinks" >> /etc/apache2/conf-available/laravel.conf && \
    echo "    AllowOverride All" >> /etc/apache2/conf-available/laravel.conf && \
    echo "    Require all granted" >> /etc/apache2/conf-available/laravel.conf && \
    echo "</Directory>" >> /etc/apache2/conf-available/laravel.conf && \
    a2enconf laravel

# 5. 設定 Composer 與 Git 環境 (解決你剛才遇到的所有報錯)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer config -g audit.abandoned ignore && \
    composer config -g audit.ignore PKSA-fwvh-pm3c-1m7b && \
    git config --global --add safe.directory /var/www/html/backend

# 6. 權限調整
# 確保 www-data 擁有權限
RUN chown -R www-data:www-data /var/www/html