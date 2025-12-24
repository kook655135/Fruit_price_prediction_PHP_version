FROM php:8.1-apache

# 設定時區為台北
ENV TZ=Asia/Taipei

# 1. 安裝系統依賴與 PHP 擴充
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

# 安裝 Laravel 必需的 PHP 擴充功能
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 2. 啟用 Apache Rewrite 模組 (Laravel 路由必需)
RUN a2enmod rewrite

# 3. 安裝最新版 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. 設定工作目錄 (建議之後 Laravel 放在 backend 資料夾)
WORKDIR /var/www/html

# 修改 Apache 配置，將 DocumentRoot 指向 Laravel 的 public 資料夾
ENV APACHE_DOCUMENT_ROOT /var/www/html/backend/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 5. 權限調整 (確保 Apache 有權限讀寫 Laravel 的 storage)
RUN chown -R www-data:www-data /var/www/html