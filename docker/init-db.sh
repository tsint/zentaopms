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

ensure_bi_builtin_data()
{
    export MYSQL_PWD="$ZT_DB_PASSWORD"
    local has_bi_tables
    has_bi_tables="$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${ZT_DB_NAME}' AND table_name IN ('${ZT_DB_PREFIX}dimension', '${ZT_DB_PREFIX}screen')" 2>/dev/null || true)"
    [[ "$has_bi_tables" == 2 ]] || return 0

    echo 'Ensuring BI built-in data...'
    "${app_mysql[@]}" <<SQL
REPLACE INTO \`${ZT_DB_PREFIX}dimension\` (id, name, code, \`desc\`, createdBy, createdDate, editedBy, editedDate, deleted) VALUES
(1, '宏观管理维度', 'macro', '', 'system', '2023-04-27 20:22:16', '', NULL, '0'),
(2, '效能管理维度', 'efficiency', '', 'system', '2023-04-27 20:22:16', '', NULL, '0'),
(3, '质量管理维度', 'quality', '', 'system', '2023-04-27 20:22:16', '', NULL, '0');
REPLACE INTO \`${ZT_DB_PREFIX}grouppriv\` (\`group\`, module, method)
SELECT id, 'report', 'globalEffort' FROM \`${ZT_DB_PREFIX}group\`;
SQL

    PROJECT_ROOT="/var/www/html" DB_PREFIX="$ZT_DB_PREFIX" php <<'PHP' | "${app_mysql[@]}"
<?php
$root   = getenv('PROJECT_ROOT');
$prefix = getenv('DB_PREFIX');
$ids    = array(1, 2, 3, 4, 5, 6, 7, 8, 1001);

$quote = function($value)
{
    if($value === null) return 'NULL';
    return "'" . str_replace("'", "''", (string)$value) . "'";
};

foreach($ids as $id)
{
    $file = $root . "/module/bi/json/screen{$id}.json";
    if(!is_file($file)) continue;

    $screen = json_decode(file_get_contents($file));
    if(!$screen) continue;

    $scheme = isset($screen->scheme) ? json_encode($screen->scheme, JSON_UNESCAPED_UNICODE) : null;
    $values = array(
        (int)$screen->id,
        (int)$screen->dimension,
        $quote($screen->name ?? ''),
        $quote($screen->desc ?? ''),
        $quote($screen->acl ?? 'open'),
        isset($screen->whitelist) ? $quote($screen->whitelist) : 'NULL',
        $quote($screen->cover ?? ''),
        $quote($scheme),
        "'published'",
        (int)($screen->builtin ?? 1),
        "'system'",
        'NOW()',
        "''",
        'NULL',
        '0'
    );

    echo "REPLACE INTO `{$prefix}screen` (`id`, `dimension`, `name`, `desc`, `acl`, `whitelist`, `cover`, `scheme`, `status`, `builtin`, `createdBy`, `createdDate`, `editedBy`, `editedDate`, `deleted`) VALUES (" . implode(', ', $values) . ");\n";
}
PHP

    echo 'BI built-in data is ready.'
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
    ensure_bi_builtin_data
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

table_exists()
{
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${ZT_DB_NAME}' AND table_name='${ZT_DB_PREFIX}$1'")" == 1 ]]
}

if ! table_exists user; then
    echo 'Importing ZenTao core schema...'
    render_sql /var/www/html/db/zentao.sql | "${app_mysql[@]}"
fi

ensure_bi_builtin_data

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
    ensure_bi_builtin_data
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
ensure_bi_builtin_data
echo "Created ZenTao administrator: ${ZT_ADMIN_ACCOUNT}"
