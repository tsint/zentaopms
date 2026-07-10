#!/usr/bin/env bash
set -euo pipefail

mkdir -p /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data/upload
chown -R www-data:www-data /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data

export MYSQL_HOST="${MYSQL_HOST:-${ZT_DB_HOST:-db}}"
export MYSQL_PORT="${MYSQL_PORT:-${ZT_DB_PORT:-3306}}"
export MYSQL_DB="${MYSQL_DB:-${ZT_DB_NAME:-zentao}}"
export MYSQL_USER="${MYSQL_USER:-${ZT_DB_USER:-zentao}}"
export MYSQL_PASSWORD="${MYSQL_PASSWORD:-${ZT_DB_PASSWORD:-zentao}}"

write_runtime_config()
{
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
}

if [[ "${ZT_AUTO_INIT:-false}" == "true" ]]; then
    export ZT_INSTALLED=true
    write_runtime_config
    /usr/local/bin/init-db.sh
elif [[ "${ZT_WRITE_CONFIG:-false}" == "true" ]]; then
    write_runtime_config
elif [[ -f /var/www/html/config/my.php ]]; then
    chown www-data:www-data /var/www/html/config/my.php 2>/dev/null || true
    chmod 640 /var/www/html/config/my.php 2>/dev/null || true
fi

exec "$@"
