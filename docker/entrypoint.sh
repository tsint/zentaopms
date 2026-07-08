#!/usr/bin/env bash
set -euo pipefail

mkdir -p /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data/upload
chown -R www-data:www-data /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data

cat > /var/www/html/config/my.php <<'PHP'
<?php
$config->installed     = getEnvData('ZT_INSTALLED', true, 'bool');
$config->debug         = getEnvData('ZT_DEBUG', 0, 'int');
$config->requestType   = getEnvData('ZT_REQUEST_TYPE', 'GET');
$config->timezone      = getEnvData('ZT_TIMEZONE', 'Asia/Shanghai');
$config->db->driver    = getEnvData('ZT_DB_DRIVER', 'mysql');
$config->db->host      = getEnvData('ZT_DB_HOST', 'db');
$config->db->port      = getEnvData('ZT_DB_PORT', '3306');
$config->db->name      = getEnvData('ZT_DB_NAME', 'zentao');
$config->db->user      = getEnvData('ZT_DB_USER', 'zentao');
$config->db->encoding  = getEnvData('ZT_DB_ENCODING', 'utf8mb4');
$config->db->password  = getEnvData('ZT_DB_PASSWORD', 'zentao');
$config->db->prefix    = getEnvData('ZT_DB_PREFIX', 'zt_');
$config->webRoot       = getWebRoot();
$config->default->lang = getEnvData('ZT_DEFAULT_LANG', 'zh-cn');
$config->customSession = true;
PHP
chown www-data:www-data /var/www/html/config/my.php
chmod 640 /var/www/html/config/my.php

if [[ "${ZT_AUTO_INIT:-false}" == "true" ]]; then
    /usr/local/bin/init-db.sh
fi

exec "$@"
