#!/usr/bin/env bash
# ZenTaoPMS 本地开发数据库初始化
#
# 用法：
#   ./dev-init-db.sh                    # 使用默认配置
#   DB_ROOT_PASSWORD=xxx ./dev-init-db.sh  # root 有密码时
#
# 环境变量：
#   DB_HOST          MySQL 主机（默认 127.0.0.1）
#   DB_PORT          MySQL 端口（默认 3306）
#   DB_NAME          数据库名（默认 zentao）
#   DB_USER          应用账号（默认 zentao）
#   DB_PASSWORD      应用密码（默认 zentao123456）
#   DB_PREFIX        表前缀（默认 zt_）
#   DB_ROOT_USER     root 账号（默认 root）
#   DB_ROOT_PASSWORD root 密码（默认空）
#   ADMIN_ACCOUNT    管理员账号（默认 admin）
#   ADMIN_PASSWORD   管理员密码（默认 Admin1234!）

set -euo pipefail

DB_HOST_WAS_SET="${DB_HOST+x}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-zentao}"
DB_USER="${DB_USER:-zentao}"
DB_PASSWORD="${DB_PASSWORD:-zentao123456}"
DB_PREFIX="${DB_PREFIX:-zt_}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"
ADMIN_ACCOUNT="${ADMIN_ACCOUNT:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Admin1234!}"

PROJECT_ROOT="$(dirname "$(readlink -f "$0")")"

discover_compose_db_host()
{
    [[ -z "$DB_HOST_WAS_SET" ]] || return 0
    command -v docker >/dev/null 2>&1 || return 0
    [[ -f "$PROJECT_ROOT/docker-compose.yaml" || -f "$PROJECT_ROOT/docker-compose.yml" ]] || return 0

    local container_id db_ip
    container_id="$(cd "$PROJECT_ROOT" && docker compose ps -q db 2>/dev/null || true)"
    if [[ -z "$container_id" ]]; then
        echo "→ 未发现运行中的 compose 数据库，启动 db 服务"
        (cd "$PROJECT_ROOT" && docker compose up -d db >/dev/null)
        container_id="$(cd "$PROJECT_ROOT" && docker compose ps -q db 2>/dev/null || true)"
    fi

    if [[ -n "$container_id" ]]; then
        db_ip="$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$container_id" 2>/dev/null || true)"
        if [[ -n "$db_ip" ]]; then
            DB_HOST="$db_ip"
            echo "→ 使用 Docker Compose 数据库: $DB_HOST:$DB_PORT"
        fi
    fi
}

discover_compose_db_host

[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'DB_NAME contains unsupported characters.' >&2; exit 2; }
[[ "$DB_PREFIX" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'DB_PREFIX contains unsupported characters.' >&2; exit 2; }
[[ "$ADMIN_ACCOUNT" =~ ^[A-Za-z0-9._-]+$ ]] || { echo 'ADMIN_ACCOUNT contains unsupported characters.' >&2; exit 2; }
(( ${#ADMIN_PASSWORD} >= 6 )) || { echo 'ADMIN_PASSWORD must be at least 6 characters.' >&2; exit 2; }

mysql_client="$(command -v mariadb || command -v mysql)"
mysql_ssl_opt=(--ssl=0)
if "$mysql_client" --no-defaults --help 2>&1 | grep -q -- '--ssl-mode'; then
    mysql_ssl_opt=(--ssl-mode=DISABLED)
fi
root_mysql=("$mysql_client" --protocol=TCP "${mysql_ssl_opt[@]}" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_ROOT_USER" --default-character-set=utf8mb4)
app_mysql=("$mysql_client" --protocol=TCP "${mysql_ssl_opt[@]}" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --default-character-set=utf8mb4 "$DB_NAME")

ensure_runtime_dirs()
{
    mkdir -p \
        "$PROJECT_ROOT/tmp" \
        "$PROJECT_ROOT/tmp/cache" \
        "$PROJECT_ROOT/tmp/log" \
        "$PROJECT_ROOT/tmp/logs" \
        "$PROJECT_ROOT/tmp/session" \
        "$PROJECT_ROOT/data/upload" \
        "$PROJECT_ROOT/www/data/upload"
    chmod -R u+rwX,go+rwX "$PROJECT_ROOT/tmp" "$PROJECT_ROOT/www/data" 2>/dev/null || true
    chmod -R u+rwX,go+rwX "$PROJECT_ROOT/data/upload" 2>/dev/null || true
}

ensure_config()
{
    CONFIG_FILE="$PROJECT_ROOT/config/my.php"
    mkdir -p "$PROJECT_ROOT/config"
    if [[ ! -f "$CONFIG_FILE" ]]; then
        echo "→ 生成 config/my.php"
        cat > "$CONFIG_FILE" <<PHP
<?php
\$config->installed   = true;
\$config->requestType = 'GET';
\$config->timezone    = 'Asia/Shanghai';
\$config->db->driver  = 'mysql';
\$config->db->host    = '$DB_HOST';
\$config->db->port    = '$DB_PORT';
\$config->db->name    = '$DB_NAME';
\$config->db->user    = '$DB_USER';
\$config->db->encoding = 'utf8mb4';
\$config->db->password = '$DB_PASSWORD';
\$config->db->prefix  = '$DB_PREFIX';
\$config->webRoot     = '/';
\$config->default->lang = 'zh-cn';
PHP
        echo "✓ 已生成 config/my.php"
    fi
    chmod u+rwx,go+rx "$PROJECT_ROOT/config" 2>/dev/null || true
    chmod u+rw,go+r "$CONFIG_FILE" 2>/dev/null || true
}

ensure_runtime_dirs

ensure_global_effort_privilege()
{
    export MYSQL_PWD="$DB_PASSWORD"
    local has_group_tables
    has_group_tables="$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name IN ('${DB_PREFIX}group', '${DB_PREFIX}grouppriv')" 2>/dev/null || true)"
    [[ "$has_group_tables" == 2 ]] || return 0

    echo "→ 补齐全局工时默认查看权限"
    "${app_mysql[@]}" <<SQL
REPLACE INTO \`${DB_PREFIX}grouppriv\` (\`group\`, module, method)
SELECT id, 'report', 'globalEffort' FROM \`${DB_PREFIX}group\`;
SQL
    echo "✓ 全局工时默认查看权限已就绪"
}

table_exists()
{
    export MYSQL_PWD="$DB_PASSWORD"
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='${DB_PREFIX}$1'")" == 1 ]]
}

column_exists()
{
    export MYSQL_PWD="$DB_PASSWORD"
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${DB_NAME}' AND table_name='${DB_PREFIX}$1' AND column_name='$2'")" == 1 ]]
}

index_exists()
{
    export MYSQL_PWD="$DB_PASSWORD"
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='${DB_NAME}' AND table_name='${DB_PREFIX}$1' AND index_name='$2'")" != 0 ]]
}

ensure_release_build_schema()
{
    export MYSQL_PWD="$DB_PASSWORD"

    echo "→ 补齐发布/构建应用 schema"
    "${app_mysql[@]}" <<SQL
CREATE TABLE IF NOT EXISTS \`${DB_PREFIX}system\` (
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
        column_exists release system   || "${app_mysql[@]}" -e "ALTER TABLE \`${DB_PREFIX}release\` ADD \`system\` int unsigned NOT NULL DEFAULT 0 AFTER \`name\`"
        column_exists release releases || "${app_mysql[@]}" -e "ALTER TABLE \`${DB_PREFIX}release\` ADD \`releases\` varchar(255) NOT NULL DEFAULT '' AFTER \`system\`"
        index_exists release idx_system || "${app_mysql[@]}" -e "CREATE INDEX \`idx_system\` ON \`${DB_PREFIX}release\`(\`system\`)"
    fi

    if table_exists build; then
        column_exists build system || "${app_mysql[@]}" -e "ALTER TABLE \`${DB_PREFIX}build\` ADD \`system\` int unsigned NOT NULL DEFAULT 0 AFTER \`name\`"
        index_exists build idx_system || "${app_mysql[@]}" -e "CREATE INDEX \`idx_system\` ON \`${DB_PREFIX}build\`(\`system\`)"
    fi

    index_exists system idx_product || "${app_mysql[@]}" -e "CREATE INDEX \`idx_product\` ON \`${DB_PREFIX}system\`(\`product\`)"
    index_exists system idx_status  || "${app_mysql[@]}" -e "CREATE INDEX \`idx_status\` ON \`${DB_PREFIX}system\`(\`status\`)"
    echo "✓ 发布/构建应用 schema 已就绪"
}

fast_ready()
{
    export MYSQL_PWD="$DB_PASSWORD"
    local table_counts
    table_counts="$("${app_mysql[@]}" -Nse "SELECT SUM(table_name='${DB_PREFIX}user'), SUM(table_name='${DB_PREFIX}grouppriv'), SUM(table_name='${DB_PREFIX}workflow_definition') FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name IN ('${DB_PREFIX}user', '${DB_PREFIX}grouppriv', '${DB_PREFIX}workflow_definition')" 2>/dev/null || true)"
    [[ "$table_counts" == $'1\t1\t1' ]] || return 1

    local data_counts
    data_counts="$("${app_mysql[@]}" -Nse "SELECT (SELECT COUNT(*) FROM \`${DB_PREFIX}user\` WHERE account='${ADMIN_ACCOUNT}'), (SELECT COUNT(*) FROM \`${DB_PREFIX}grouppriv\` WHERE module='statetransition')" 2>/dev/null || true)"
    [[ "$data_counts" == $'1\t5' ]]
}

app_can_connect()
{
    export MYSQL_PWD="$DB_PASSWORD"
    "${app_mysql[@]}" -e 'SELECT 1' >/dev/null 2>&1
}

try_root_connect()
{
    local candidate
    for candidate in "$@"; do
        export MYSQL_PWD="$candidate"
        if "${root_mysql[@]}" -e 'SELECT 1' >/dev/null 2>&1; then
            DB_ROOT_PASSWORD="$candidate"
            return 0
        fi
    done
    return 1
}

sql_escape()
{
    printf "%s" "$1" | sed "s/'/''/g"
}

# 等待 MySQL 就绪
echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
root_password_candidates=("$DB_ROOT_PASSWORD")
if [[ -z "$DB_ROOT_PASSWORD" && -n "$DB_PASSWORD" ]]; then
    root_password_candidates+=("$DB_PASSWORD")
fi

app_ready=false
root_ready=false
for attempt in $(seq 1 30); do
    if app_can_connect; then
        app_ready=true
        break
    fi
    if try_root_connect "${root_password_candidates[@]}"; then
        root_ready=true
        break
    fi
    if [[ "$attempt" == 30 ]]; then
        cat >&2 <<EOF
MySQL did not become ready with the configured credentials.
Checked app user '${DB_USER}' for database '${DB_NAME}' and root user '${DB_ROOT_USER}'.
If this environment reuses an existing MySQL data directory, DB_ROOT_PASSWORD must match
the password stored in that data directory, or the app user must already exist.
EOF
        exit 1
    fi
    sleep 2
done
echo "✓ MySQL 连接正常"

if fast_ready; then
    ensure_config
    ensure_global_effort_privilege
    ensure_release_build_schema
    echo "✓ 数据库已就绪，跳过初始化"
    exit 0
fi

render_sql()
{
    sed -e "s/__DATABASE__/${DB_NAME}/g" -e "s/\`zt_/\`${DB_PREFIX}/g" -e "s/\`ztv_/\`${DB_PREFIX}v_/g" "$1"
}

# 创建数据库和用户
if [[ "$app_ready" != true ]]; then
    if [[ "$root_ready" != true ]]; then
        cat >&2 <<EOF
数据库还不能使用应用账号 '${DB_USER}' 连接，且 root 登录失败。
这通常表示 MySQL 数据目录之前用另一个 root 密码初始化过。
请设置 DB_ROOT_PASSWORD 为已有数据目录里的真实 root 密码，或在需要全新数据库时重置数据目录。
EOF
        exit 1
    fi

    db_user_sql="$(sql_escape "$DB_USER")"
    db_password_sql="$(sql_escape "$DB_PASSWORD")"
    echo "→ 创建数据库和用户"
    export MYSQL_PWD="$DB_ROOT_PASSWORD"
    "${root_mysql[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_user_sql}'@'%' IDENTIFIED BY '${db_password_sql}';
CREATE USER IF NOT EXISTS '${db_user_sql}'@'localhost' IDENTIFIED BY '${db_password_sql}';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '${db_user_sql}'@'%';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '${db_user_sql}'@'localhost';
FLUSH PRIVILEGES;
SQL

    if ! app_can_connect; then
        echo "已创建数据库/用户，但应用账号 '${DB_USER}' 仍无法连接 '${DB_NAME}'。" >&2
        exit 1
    fi
fi

# 导入核心 schema
if ! table_exists user; then
    echo "  导入核心 schema..."
    export MYSQL_PWD="$DB_PASSWORD"
    render_sql "$PROJECT_ROOT/db/zentao.sql" | "${app_mysql[@]}"
fi

ensure_global_effort_privilege
ensure_release_build_schema

# 安装扩展
for extension in objecteffort workflowflowchart; do
    install_sql="$PROJECT_ROOT/extension/custom/$extension/db/install.sql"
    if [[ -f "$install_sql" ]] && ! table_exists "$extension"; then
        echo "  安装 $extension..."
        export MYSQL_PWD="$DB_PASSWORD"
        render_sql "$install_sql" | "${app_mysql[@]}"
    fi
done

# 安装 statetransition
if [[ -f "$PROJECT_ROOT/module/statetransition/db/install.sql" ]] && ! table_exists workflow_definition; then
    echo "  安装 statetransition..."
    export MYSQL_PWD="$DB_PASSWORD"
    render_sql "$PROJECT_ROOT/module/statetransition/db/install.sql" | "${app_mysql[@]}"
fi

admin_exists="$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM \`${DB_PREFIX}user\` WHERE account='${ADMIN_ACCOUNT}'")"
if [[ "$admin_exists" != 0 ]]; then
    echo "✓ 管理员已存在，跳过创建"
else
# 创建管理员
    echo "→ 创建管理员账号"
    password_hash=$(ADMIN_PASSWORD="$ADMIN_PASSWORD" php -r 'echo md5(getenv("ADMIN_PASSWORD"));')
    export MYSQL_PWD="$DB_PASSWORD"
    "${app_mysql[@]}" <<SQL
INSERT INTO \`${DB_PREFIX}company\` (name, admins) VALUES ('ZenTao', ',$ADMIN_ACCOUNT,');
INSERT INTO \`${DB_PREFIX}user\` (account, realname, password, gender, visions) VALUES ('$ADMIN_ACCOUNT', '$ADMIN_ACCOUNT', '$password_hash', 'f', 'rnd,lite');
REPLACE INTO \`${DB_PREFIX}config\` (vision, owner, module, section, \`key\`, value) VALUES
('', 'system', 'common', 'global', 'version', '22.2'),
('', 'system', 'common', 'global', 'flow', 'full'),
('', 'system', 'common', 'safe', 'mode', '1'),
('', 'system', 'common', 'safe', 'changeWeak', '0'),
('', 'system', 'common', 'safe', 'modifyPasswordFirstLogin', '0'),
('', 'system', 'common', 'global', 'cron', '1');
SQL
    echo "✓ 已创建管理员: $ADMIN_ACCOUNT / $ADMIN_PASSWORD"
fi

ensure_config

echo "✓ 初始化完成"
