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

mysql_client="$(command -v mariadb || command -v mysql)"
mysql_ssl_opt=(--ssl=0)
if "$mysql_client" --no-defaults --help 2>&1 | grep -q -- '--ssl-mode'; then
    mysql_ssl_opt=(--ssl-mode=DISABLED)
fi
root_mysql=("$mysql_client" --protocol=TCP "${mysql_ssl_opt[@]}" -h "$ZT_DB_HOST" -P "$ZT_DB_PORT" -u "$ZT_DB_ROOT_USER" --default-character-set=utf8mb4)
app_mysql=("$mysql_client" --protocol=TCP "${mysql_ssl_opt[@]}" -h "$ZT_DB_HOST" -P "$ZT_DB_PORT" -u "$ZT_DB_USER" --default-character-set=utf8mb4 "$ZT_DB_NAME")

ensure_runtime_dirs()
{
    mkdir -p \
        /var/www/html/config \
        /var/www/html/tmp \
        /var/www/html/tmp/cache \
        /var/www/html/tmp/log \
        /var/www/html/tmp/logs \
        /var/www/html/tmp/session \
        /var/www/html/data/upload \
        /var/www/html/www/data/upload
    chown -R www-data:www-data /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data 2>/dev/null || true
    chmod -R u+rwX,go+rwX /var/www/html/config /var/www/html/tmp /var/www/html/data/upload /var/www/html/www/data 2>/dev/null || true
    chmod u+rw,go+r /var/www/html/config/my.php 2>/dev/null || true
}

fast_ready()
{
    export MYSQL_PWD="$ZT_DB_PASSWORD"
    local table_counts
    table_counts="$("${app_mysql[@]}" -Nse "SELECT SUM(table_name='${ZT_DB_PREFIX}user'), SUM(table_name='${ZT_DB_PREFIX}grouppriv'), SUM(table_name='${ZT_DB_PREFIX}workflow_definition') FROM information_schema.tables WHERE table_schema='${ZT_DB_NAME}' AND table_name IN ('${ZT_DB_PREFIX}user', '${ZT_DB_PREFIX}grouppriv', '${ZT_DB_PREFIX}workflow_definition')" 2>/dev/null || true)"
    [[ "$table_counts" == $'1\t1\t1' ]] || return 1

    local data_counts
    data_counts="$("${app_mysql[@]}" -Nse "SELECT (SELECT COUNT(*) FROM \`${ZT_DB_PREFIX}user\` WHERE account='${ZT_ADMIN_ACCOUNT}'), (SELECT COUNT(*) FROM \`${ZT_DB_PREFIX}grouppriv\` WHERE module='statetransition')" 2>/dev/null || true)"
    [[ "$data_counts" == $'1\t5' ]]
}

app_can_connect()
{
    export MYSQL_PWD="$ZT_DB_PASSWORD"
    "${app_mysql[@]}" -e 'SELECT 1' >/dev/null 2>&1
}

try_root_connect()
{
    local candidate
    for candidate in "$@"; do
        export MYSQL_PWD="$candidate"
        if "${root_mysql[@]}" -e 'SELECT 1' >/dev/null 2>&1; then
            ZT_DB_ROOT_PASSWORD="$candidate"
            return 0
        fi
    done
    return 1
}

sql_escape()
{
    printf "%s" "$1" | sed "s/'/''/g"
}

ensure_runtime_dirs

ensure_global_effort_privilege()
{
    export MYSQL_PWD="$ZT_DB_PASSWORD"
    local has_group_tables
    has_group_tables="$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${ZT_DB_NAME}' AND table_name IN ('${ZT_DB_PREFIX}group', '${ZT_DB_PREFIX}grouppriv')" 2>/dev/null || true)"
    [[ "$has_group_tables" == 2 ]] || return 0

    echo 'Ensuring global effort default view privilege...'
    "${app_mysql[@]}" <<SQL
REPLACE INTO \`${ZT_DB_PREFIX}grouppriv\` (\`group\`, module, method)
SELECT id, 'report', 'globalEffort' FROM \`${ZT_DB_PREFIX}group\`;
SQL
    echo 'Global effort default view privilege is ready.'
}

table_exists()
{
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${ZT_DB_NAME}' AND table_name='${ZT_DB_PREFIX}$1'")" == 1 ]]
}

column_exists()
{
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${ZT_DB_NAME}' AND table_name='${ZT_DB_PREFIX}$1' AND column_name='$2'")" == 1 ]]
}

index_exists()
{
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='${ZT_DB_NAME}' AND table_name='${ZT_DB_PREFIX}$1' AND index_name='$2'")" != 0 ]]
}

ensure_release_build_schema()
{
    export MYSQL_PWD="$ZT_DB_PASSWORD"

    echo 'Ensuring release/build system schema...'
    "${app_mysql[@]}" <<SQL
CREATE TABLE IF NOT EXISTS \`${ZT_DB_PREFIX}system\` (
  \`id\` int unsigned NOT NULL AUTO_INCREMENT,
  \`name\` varchar(100) NOT NULL DEFAULT '',
  \`product\` int unsigned NOT NULL DEFAULT 0,
  \`integrated\` tinyint unsigned NOT NULL DEFAULT 0,
  \`latestRelease\` int unsigned NOT NULL DEFAULT 0,
  \`latestDate\` datetime DEFAULT NULL,
  \`children\` varchar(255) NOT NULL DEFAULT '',
  \`status\` varchar(10) NOT NULL DEFAULT 'active',
  \`desc\` mediumtext DEFAULT NULL,
  \`createdBy\` varchar(30) NOT NULL DEFAULT '',
  \`createdDate\` datetime DEFAULT NULL,
  \`editedBy\` varchar(30) NOT NULL DEFAULT '',
  \`editedDate\` datetime DEFAULT NULL,
  \`deleted\` tinyint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (\`id\`)
) ENGINE=InnoDB;
SQL

    if table_exists release; then
        column_exists release system   || "${app_mysql[@]}" -e "ALTER TABLE \`${ZT_DB_PREFIX}release\` ADD \`system\` int unsigned NOT NULL DEFAULT 0 AFTER \`name\`"
        column_exists release releases || "${app_mysql[@]}" -e "ALTER TABLE \`${ZT_DB_PREFIX}release\` ADD \`releases\` varchar(255) NOT NULL DEFAULT '' AFTER \`system\`"
        index_exists release idx_system || "${app_mysql[@]}" -e "CREATE INDEX \`idx_system\` ON \`${ZT_DB_PREFIX}release\`(\`system\`)"
    fi

    if table_exists build; then
        column_exists build system || "${app_mysql[@]}" -e "ALTER TABLE \`${ZT_DB_PREFIX}build\` ADD \`system\` int unsigned NOT NULL DEFAULT 0 AFTER \`name\`"
        index_exists build idx_system || "${app_mysql[@]}" -e "CREATE INDEX \`idx_system\` ON \`${ZT_DB_PREFIX}build\`(\`system\`)"
    fi

    index_exists system idx_product || "${app_mysql[@]}" -e "CREATE INDEX \`idx_product\` ON \`${ZT_DB_PREFIX}system\`(\`product\`)"
    index_exists system idx_status  || "${app_mysql[@]}" -e "CREATE INDEX \`idx_status\` ON \`${ZT_DB_PREFIX}system\`(\`status\`)"
    echo 'Release/build system schema is ready.'
}

echo "Waiting for MySQL at ${ZT_DB_HOST}:${ZT_DB_PORT}..."
root_password_candidates=("$ZT_DB_ROOT_PASSWORD")
if [[ -n "${MYSQL_ROOT_PASSWORD:-}" && "$MYSQL_ROOT_PASSWORD" != "$ZT_DB_ROOT_PASSWORD" ]]; then
    root_password_candidates+=("$MYSQL_ROOT_PASSWORD")
fi
if [[ -n "$ZT_DB_PASSWORD" && "$ZT_DB_PASSWORD" != "$ZT_DB_ROOT_PASSWORD" ]]; then
    root_password_candidates+=("$ZT_DB_PASSWORD")
fi

app_ready=false
root_ready=false
for attempt in $(seq 1 60); do
    if app_can_connect; then
        app_ready=true
        break
    fi
    if try_root_connect "${root_password_candidates[@]}"; then
        root_ready=true
        break
    fi
    if [[ "$attempt" == 60 ]]; then
        cat >&2 <<EOF
MySQL did not become ready with the configured credentials.
Checked app user '${ZT_DB_USER}' for database '${ZT_DB_NAME}' and root user '${ZT_DB_ROOT_USER}'.
If this deployment reuses an existing MySQL data directory, MYSQL_ROOT_PASSWORD/ ZT_DB_ROOT_PASSWORD
must match the password stored in that data directory, or the app user must already exist.
EOF
        exit 1
    fi
    sleep 2
done

if fast_ready; then
    ensure_global_effort_privilege
    ensure_release_build_schema
    echo 'Database already ready; skipping initialization.'
    exit 0
fi

if [[ "$app_ready" != true ]]; then
    if [[ "$root_ready" != true ]]; then
        cat >&2 <<EOF
Database is not initialized for app user '${ZT_DB_USER}', and root login failed.
This usually means the MySQL volume was initialized earlier with a different root password.
Fix by setting ZT_DB_ROOT_PASSWORD to the real existing root password, or by resetting/recreating
the MySQL data volume when a fresh database is intended.
EOF
        exit 1
    fi

    db_user_sql="$(sql_escape "$ZT_DB_USER")"
    db_password_sql="$(sql_escape "$ZT_DB_PASSWORD")"
    export MYSQL_PWD="$ZT_DB_ROOT_PASSWORD"
    "${root_mysql[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${ZT_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_user_sql}'@'%' IDENTIFIED BY '${db_password_sql}';
GRANT ALL PRIVILEGES ON \`${ZT_DB_NAME}\`.* TO '${db_user_sql}'@'%';
FLUSH PRIVILEGES;
SQL

    if ! app_can_connect; then
        echo "Created database/user, but app user '${ZT_DB_USER}' still cannot connect to '${ZT_DB_NAME}'." >&2
        exit 1
    fi
fi

export MYSQL_PWD="$ZT_DB_PASSWORD"

render_sql()
{
    sed -e "s/__DATABASE__/${ZT_DB_NAME}/g" -e "s/\`zt_/\`${ZT_DB_PREFIX}/g" -e "s/\`ztv_/\`${ZT_DB_PREFIX}v_/g" "$1"
}

if ! table_exists user; then
    echo 'Importing ZenTao core schema...'
    render_sql /var/www/html/db/zentao.sql | "${app_mysql[@]}"
fi

ensure_global_effort_privilege
ensure_release_build_schema

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
if [[ "$admin_exists" != 0 ]]; then
    ensure_global_effort_privilege
    echo "ZenTao administrator ${ZT_ADMIN_ACCOUNT} already exists; database schema checks completed."
    exit 0
fi

password_hash="$(php -r 'echo md5(getenv("ZT_ADMIN_PASSWORD"));')"
company_name="${ZT_COMPANY_NAME//\'/\'\'}"
"${app_mysql[@]}" <<SQL
INSERT INTO \`${ZT_DB_PREFIX}company\` (name, admins) VALUES ('${company_name}', ',${ZT_ADMIN_ACCOUNT},');
INSERT INTO \`${ZT_DB_PREFIX}user\` (account, realname, password, gender, visions) VALUES ('${ZT_ADMIN_ACCOUNT}', '${ZT_ADMIN_ACCOUNT}', '${password_hash}', 'f', 'rnd,lite');
REPLACE INTO \`${ZT_DB_PREFIX}config\` (vision, owner, module, section, \`key\`, value) VALUES
('', 'system', 'common', 'global', 'version', '22.2'),
('', 'system', 'common', 'global', 'flow', 'full'),
('', 'system', 'common', 'safe', 'mode', '1'),
('', 'system', 'common', 'safe', 'changeWeak', '0'),
('', 'system', 'common', 'safe', 'modifyPasswordFirstLogin', '0'),
('', 'system', 'common', 'global', 'cron', '1');
SQL
ensure_global_effort_privilege
echo "Created ZenTao administrator: ${ZT_ADMIN_ACCOUNT}"
