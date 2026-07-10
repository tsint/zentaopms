#!/usr/bin/env bash
set -euo pipefail

mkdir -p /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data/upload
chown -R www-data:www-data /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data

export MYSQL_HOST="${MYSQL_HOST:-${ZT_DB_HOST:-db}}"
export MYSQL_PORT="${MYSQL_PORT:-${ZT_DB_PORT:-3306}}"
export MYSQL_DB="${MYSQL_DB:-${ZT_DB_NAME:-zentao}}"
export MYSQL_USER="${MYSQL_USER:-${ZT_DB_USER:-zentao}}"
export MYSQL_PASSWORD="${MYSQL_PASSWORD:-${ZT_DB_PASSWORD:-zentao}}"

export ZT_DB_DRIVER="${ZT_DB_DRIVER:-mysql}"
export ZT_DB_HOST="${ZT_DB_HOST:-$MYSQL_HOST}"
export ZT_DB_PORT="${ZT_DB_PORT:-$MYSQL_PORT}"
export ZT_DB_NAME="${ZT_DB_NAME:-$MYSQL_DB}"
export ZT_DB_USER="${ZT_DB_USER:-$MYSQL_USER}"
export ZT_DB_PREFIX="${ZT_DB_PREFIX:-zt_}"
export ZT_DB_ENCODING="${ZT_DB_ENCODING:-utf8mb4}"
export ZT_DEFAULT_LANG="${ZT_DEFAULT_LANG:-zh-cn}"
export ZT_TIMEZONE="${ZT_TIMEZONE:-Asia/Shanghai}"
export ZT_AUTO_INIT="${ZT_AUTO_INIT:-false}"
if [[ -z "${ZT_DB_PASSWORD+x}" ]]; then
    export ZT_DB_PASSWORD="$MYSQL_PASSWORD"
fi

image_version()
{
    sed -n "s/^\$config->version[[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" /var/www/html/config/config.php | head -n 1
}

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

query_installed_version()
{
    local prefix="${ZT_DB_PREFIX:-zt_}"
    local table="${prefix}config"
    local i=0

    export MYSQL_PWD="$MYSQL_PASSWORD"
    until mysql --connect-timeout=5 -h"$MYSQL_HOST" -P"$MYSQL_PORT" -u"$MYSQL_USER" "$MYSQL_DB" -sN -e "SELECT 1" >/dev/null 2>&1; do
        i=$((i+1))
        if [[ $i -ge 10 ]]; then
            unset MYSQL_PWD
            echo "[entrypoint] Cannot connect to MySQL database '${MYSQL_DB}' at ${MYSQL_HOST}:${MYSQL_PORT} after ${i} tries; assuming not installed." >&2
            return 1
        fi
        sleep 2
    done

    mysql --connect-timeout=5 -h"$MYSQL_HOST" -P"$MYSQL_PORT" -u"$MYSQL_USER" \
        "$MYSQL_DB" -sN -e \
        "SELECT \`value\` FROM \`${table}\` WHERE \`owner\`='system' AND \`module\`='common' AND \`section\`='global' AND \`key\`='version' LIMIT 1" \
        2>/dev/null
    local status=$?
    unset MYSQL_PWD
    return "$status"
}

secure_installed_runtime()
{
    local installed_version="${1:-}"
    local current_version

    rm -f /var/www/html/www/install.php

    current_version="$(image_version)"
    if [[ -n "$installed_version" && -n "$current_version" && "$installed_version" == "$current_version" ]]; then
        rm -f /var/www/html/www/upgrade.php
    else
        if [[ ! -f /var/www/html/www/upgrade.php && -f /var/www/html/www/upgrade.php.tmp ]]; then
            cp /var/www/html/www/upgrade.php.tmp /var/www/html/www/upgrade.php
        fi
        echo "[entrypoint] Detected ZenTao ${installed_version:-unknown}; image version is ${current_version:-unknown}. Keeping upgrade.php for upgrade flow."
    fi
}

prepare_fresh_install_runtime()
{
    export ZT_INSTALLED=false

    if [[ ! -f /var/www/html/www/install.php && -f /var/www/html/www/install.php.tmp ]]; then
        cp /var/www/html/www/install.php.tmp /var/www/html/www/install.php
    fi
    if [[ ! -f /var/www/html/www/upgrade.php && -f /var/www/html/www/upgrade.php.tmp ]]; then
        cp /var/www/html/www/upgrade.php.tmp /var/www/html/www/upgrade.php
    fi
}

if [[ "${ZT_AUTO_INIT:-false}" == "true" ]]; then
    export ZT_INSTALLED=true
    write_runtime_config
    /usr/local/bin/init-db.sh
    secure_installed_runtime "$(query_installed_version || true)"
elif [[ "${ZT_WRITE_CONFIG:-false}" == "true" ]]; then
    write_runtime_config
    if [[ "${ZT_INSTALLED:-false}" == "true" ]]; then
        secure_installed_runtime "$(query_installed_version || true)"
    fi
elif [[ -f /var/www/html/config/my.php ]]; then
    installed_version="$(query_installed_version || true)"
    chown www-data:www-data /var/www/html/config/my.php 2>/dev/null || true
    chmod 640 /var/www/html/config/my.php 2>/dev/null || true
    if [[ -n "$installed_version" ]]; then
        export ZT_INSTALLED=true
        secure_installed_runtime "$installed_version"
    else
        prepare_fresh_install_runtime
        echo "[entrypoint] config/my.php exists but no installed DB version was found; install.php workflow will run."
    fi
else
    installed_version="$(query_installed_version || true)"
    if [[ -n "$installed_version" ]]; then
        export ZT_INSTALLED=true
        write_runtime_config
        secure_installed_runtime "$installed_version"
        echo "[entrypoint] Detected existing install in DB; restored /var/www/html/config/my.php from environment."
    else
        prepare_fresh_install_runtime
        echo "[entrypoint] No prior install detected; install.php workflow will run."
    fi
fi

exec "$@"
