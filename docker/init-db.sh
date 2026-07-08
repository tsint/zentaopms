#!/usr/bin/env bash
set -euo pipefail

: "${ZT_DB_HOST:=db}"
: "${ZT_DB_PORT:=3306}"
: "${ZT_DB_NAME:=zentao}"
: "${ZT_DB_USER:=zentao}"
: "${ZT_DB_PASSWORD:=zentao}"
: "${ZT_DB_PREFIX:=zt_}"
: "${ZT_ADMIN_ACCOUNT:=admin}"
: "${ZT_ADMIN_PASSWORD:?ZT_ADMIN_PASSWORD is required when ZT_AUTO_INIT=true}"
: "${ZT_COMPANY_NAME:=ZenTao}"
: "${ZT_DB_ROOT_USER:=root}"
: "${ZT_DB_ROOT_PASSWORD:=${MYSQL_ROOT_PASSWORD:-}}"

[[ "$ZT_DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'ZT_DB_NAME contains unsupported characters.' >&2; exit 2; }
[[ "$ZT_DB_PREFIX" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'ZT_DB_PREFIX contains unsupported characters.' >&2; exit 2; }
[[ "$ZT_ADMIN_ACCOUNT" =~ ^[A-Za-z0-9._-]+$ ]] || { echo 'ZT_ADMIN_ACCOUNT contains unsupported characters.' >&2; exit 2; }
(( ${#ZT_ADMIN_PASSWORD} >= 6 )) || { echo 'ZT_ADMIN_PASSWORD must contain at least 6 characters.' >&2; exit 2; }

export MYSQL_PWD="$ZT_DB_ROOT_PASSWORD"
mysql_client="$(command -v mariadb || command -v mysql)"
root_mysql=("$mysql_client" --protocol=TCP --ssl=0 -h "$ZT_DB_HOST" -P "$ZT_DB_PORT" -u "$ZT_DB_ROOT_USER" --default-character-set=utf8mb4)

echo "Waiting for MySQL at ${ZT_DB_HOST}:${ZT_DB_PORT}..."
for attempt in $(seq 1 60); do
    if "${root_mysql[@]}" -e 'SELECT 1' >/dev/null 2>&1; then break; fi
    if [[ "$attempt" == 60 ]]; then echo 'MySQL did not become ready in time.' >&2; exit 1; fi
    sleep 2
done

"${root_mysql[@]}" -e "CREATE DATABASE IF NOT EXISTS \`${ZT_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '${ZT_DB_USER}'@'%' IDENTIFIED BY '${ZT_DB_PASSWORD//\'/\'\'}'; GRANT ALL PRIVILEGES ON \`${ZT_DB_NAME}\`.* TO '${ZT_DB_USER}'@'%'; FLUSH PRIVILEGES;"

export MYSQL_PWD="$ZT_DB_PASSWORD"
app_mysql=("$mysql_client" --protocol=TCP --ssl=0 -h "$ZT_DB_HOST" -P "$ZT_DB_PORT" -u "$ZT_DB_USER" --default-character-set=utf8mb4 "$ZT_DB_NAME")

render_sql()
{
    sed -e "s/__DATABASE__/${ZT_DB_NAME}/g" -e "s/\`zt_/\`${ZT_DB_PREFIX}/g" -e "s/\`ztv_/\`${ZT_DB_PREFIX}v_/g" "$1"
}

table_exists()
{
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${ZT_DB_NAME}' AND table_name='${ZT_DB_PREFIX}$1'")" == 1 ]]
}

if ! table_exists user; then
    echo 'Importing ZenTao core schema...'
    render_sql /var/www/html/db/zentao.sql | "${app_mysql[@]}"
fi

for extension in objecteffort workflowflowchart; do
    install_sql="/var/www/html/extension/custom/${extension}/db/install.sql"
    if [[ -f "$install_sql" ]] && ! table_exists "$extension"; then
        echo "Installing ${extension} schema..."
        render_sql "$install_sql" | "${app_mysql[@]}"
    fi
done

if [[ -f /var/www/html/module/statetransition/db/install.sql ]] && ! table_exists workflow_definition; then
    echo 'Installing statetransition schema...'
    render_sql /var/www/html/module/statetransition/db/install.sql | "${app_mysql[@]}"
fi

admin_exists="$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM \`${ZT_DB_PREFIX}user\` WHERE account='${ZT_ADMIN_ACCOUNT}'")"
if [[ "$admin_exists" == 0 ]]; then
    password_hash="$(php -r 'echo md5(getenv("ZT_ADMIN_PASSWORD"));')"
    company_name="${ZT_COMPANY_NAME//\'/\'\'}"
    "${app_mysql[@]}" <<SQL
INSERT INTO \`${ZT_DB_PREFIX}company\` (name, admins) VALUES ('${company_name}', ',${ZT_ADMIN_ACCOUNT},');
INSERT INTO \`${ZT_DB_PREFIX}user\` (account, realname, password, gender, visions) VALUES ('${ZT_ADMIN_ACCOUNT}', '${ZT_ADMIN_ACCOUNT}', '${password_hash}', 'f', 'rnd,lite');
REPLACE INTO \`${ZT_DB_PREFIX}config\` (vision, owner, module, section, \`key\`, value) VALUES
('', 'system', 'common', 'global', 'version', '22.2'),
('', 'system', 'common', 'global', 'flow', 'full'),
('', 'system', 'common', 'safe', 'mode', '0'),
('', 'system', 'common', 'safe', 'changeWeak', '0'),
('', 'system', 'common', 'global', 'cron', '1');
SQL
    echo "Created ZenTao administrator: ${ZT_ADMIN_ACCOUNT}"
else
    echo "ZenTao administrator ${ZT_ADMIN_ACCOUNT} already exists; database data was left unchanged."
fi
