FROM php:8.3-fpm-alpine

ENV IS_CONTAINER=true \
    ZT_DB_DRIVER=mysql \
    ZT_DB_HOST=db \
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

RUN apk add --no-cache \
        bash curl freetype icu-libs libjpeg-turbo libpng libxml2 libzip \
        mysql-client nginx supervisor tzdata \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS freetype-dev icu-dev libjpeg-turbo-dev libpng-dev \
        libzip-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath gd intl pdo_mysql sockets zip \
    && apk del .build-deps \
    && mkdir -p /run/nginx /var/log/supervisor \
    && { \
        echo '[www]'; \
        echo 'listen = 127.0.0.1:9000'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 30'; \
        echo 'pm.start_servers = 3'; \
        echo 'pm.min_spare_servers = 2'; \
        echo 'pm.max_spare_servers = 5'; \
    } > /usr/local/etc/php-fpm.d/zz-zentaopms.conf \
    && printf 'upload_max_filesize=50M\npost_max_size=50M\nmemory_limit=256M\nmax_execution_time=120\ndate.timezone=Asia/Shanghai\nopcache.enable=1\nopcache.validate_timestamps=0\n' \
        > /usr/local/etc/php/conf.d/zentao.ini

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/entrypoint.sh docker/init-db.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/init-db.sh \
    && mkdir -p tmp data/upload www/data/upload config /etc/nginx/http.d \
    && rm -f config/my.php config/db.php www/install.php www/upgrade.php \
    && chown -R www-data:www-data tmp data/upload www/data config \
    && chmod -R ug+rwX tmp data/upload www/data config

VOLUME ["/var/www/html/www/data", "/var/www/html/tmp"]
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/ || exit 1
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
