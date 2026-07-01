FROM php:8.2-apache-bookworm

ENV ZT_DB_HOST=db \
    ZT_DB_PORT=3306 \
    ZT_DB_NAME=zentao \
    ZT_DB_USER=zentao \
    ZT_DB_PASSWORD=zentao \
    ZT_DB_PREFIX=zt_ \
    ZT_DB_ENCODING=utf8mb4 \
    ZT_DEFAULT_LANG=zh-cn \
    ZT_TIMEZONE=Asia/Shanghai \
    ZT_INSTALLED=true \
    ZT_AUTO_INIT=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client libcurl4-openssl-dev libfreetype6-dev libicu-dev \
        libjpeg62-turbo-dev libonig-dev libpng-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" curl dom gd intl mbstring mysqli pdo_mysql simplexml zip \
    && a2enmod rewrite headers expires \
    && sed -ri 's!/var/www/html!/var/www/html/www!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && printf 'upload_max_filesize=50M\npost_max_size=50M\nmemory_limit=256M\nmax_execution_time=120\ndate.timezone=Asia/Shanghai\n' > /usr/local/etc/php/conf.d/zentao.ini \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/entrypoint.sh docker/init-db.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/init-db.sh \
    && mkdir -p tmp data/upload config \
    && chown -R www-data:www-data tmp data/upload config \
    && chmod -R ug+rwX tmp data/upload config

VOLUME ["/var/www/html/data", "/var/www/html/tmp"]
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 CMD curl -fsS http://127.0.0.1/ || exit 1
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
