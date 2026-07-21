# 私有化部署镜像（PHP 8.2 + FPM；可选预装 headless Chrome 供 Panther）
# 对应评审议题 T3：一期是否启用 JS 渲染
FROM php:8.2-fpm

# 基础依赖 + PHP 扩展
RUN apt-get update && apt-get install -y \
        git unzip libzip-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql zip bcmath \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# （可选）headless Chrome + chromedriver —— 仅当 T3 决定启用 JS 渲染时开启
# RUN apt-get install -y chromium chromium-driver

WORKDIR /var/www

COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader || true

COPY backend ./

# 运行时配置（来自 .env：SANDBOX_* 危险函数禁用、内存/超时）
RUN chown -R www-data:www-data /var/www
EXPOSE 9000

CMD ["php-fpm"]
