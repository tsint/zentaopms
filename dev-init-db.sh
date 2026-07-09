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
#   ADMIN_PASSWORD   管理员密码（默认 123456）

set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-zentao}"
DB_USER="${DB_USER:-zentao}"
DB_PASSWORD="${DB_PASSWORD:-zentao123456}"
DB_PREFIX="${DB_PREFIX:-zt_}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"
ADMIN_ACCOUNT="${ADMIN_ACCOUNT:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-123456}"

PROJECT_ROOT="$(dirname "$(readlink -f "$0")")"

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

# 等待 MySQL 就绪
echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
root_password_candidates=("$DB_ROOT_PASSWORD")
if [[ -z "$DB_ROOT_PASSWORD" && -n "$DB_PASSWORD" ]]; then
    root_password_candidates+=("$DB_PASSWORD")
fi

root_ready=false
for attempt in $(seq 1 30); do
    for candidate in "${root_password_candidates[@]}"; do
        export MYSQL_PWD="$candidate"
        if "${root_mysql[@]}" -e 'SELECT 1' >/dev/null 2>&1; then
            DB_ROOT_PASSWORD="$candidate"
            root_ready=true
            break 2
        fi
    done
    if [[ "$attempt" == 30 ]]; then echo 'MySQL did not become ready in time.' >&2; exit 1; fi
    sleep 2
done
[[ "$root_ready" == true ]] || { echo 'MySQL did not become ready in time.' >&2; exit 1; }
echo "✓ MySQL 连接正常"

if fast_ready; then
    ensure_config
    echo "✓ 数据库已就绪，跳过初始化"
    exit 0
fi

render_sql()
{
    sed -e "s/__DATABASE__/${DB_NAME}/g" -e "s/\`zt_/\`${DB_PREFIX}/g" -e "s/\`ztv_/\`${DB_PREFIX}v_/g" "$1"
}

table_exists()
{
    export MYSQL_PWD="$DB_PASSWORD"
    [[ "$("${app_mysql[@]}" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='${DB_PREFIX}$1'")" == 1 ]]
}

# 创建数据库和用户
echo "→ 创建数据库和用户"
export MYSQL_PWD="$DB_ROOT_PASSWORD"
"${root_mysql[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASSWORD';
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

# 导入核心 schema
if ! table_exists user; then
    echo "  导入核心 schema..."
    export MYSQL_PWD="$DB_PASSWORD"
    render_sql "$PROJECT_ROOT/db/zentao.sql" | "${app_mysql[@]}"
fi

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
('', 'system', 'common', 'safe', 'mode', '0'),
('', 'system', 'common', 'safe', 'changeWeak', '0'),
('', 'system', 'common', 'global', 'cron', '1');
SQL
    echo "✓ 已创建管理员: $ADMIN_ACCOUNT / $ADMIN_PASSWORD"
fi

ensure_config

echo "✓ 初始化完成"
