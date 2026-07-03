#!/usr/bin/env bash
# ZenTaoPMS 一键启动开发服务器
#
# 用法：
#   ./dev.sh              # 默认 8080 端口
#   PORT=9000 ./dev.sh    # 自定义端口
#
# 功能：
#   - 自动杀掉占用端口的旧进程
#   - 启动 PHP 内置服务器（www 为 docroot）
#   - 实时打印请求日志
#   - Ctrl-C 干净退出
#
# 默认管理员账号：admin / 123456

set -euo pipefail

PORT="${PORT:-8080}"
HOST="${HOST:-127.0.0.1}"
DOCROOT="$(dirname "$(readlink -f "$0")")/www"
LOG_DIR="$(dirname "$(readlink -f "$0")")/tmp/logs"
LOG_FILE="$LOG_DIR/dev-server.log"
PID_FILE="$LOG_DIR/dev-server.pid"

mkdir -p "$LOG_DIR"

# 终止旧进程
if [[ -f "$PID_FILE" ]]; then
    OLD_PID="$(cat "$PID_FILE" 2>/dev/null || true)"
    if [[ -n "$OLD_PID" ]] && kill -0 "$OLD_PID" 2>/dev/null; then
        echo "→ 终止旧进程 PID=$OLD_PID"
        kill "$OLD_PID" 2>/dev/null || true
        sleep 1
    fi
fi

# 也按端口查一遍（兜底）
EXISTING_PID="$(lsof -ti ":$PORT" 2>/dev/null || true)"
if [[ -n "$EXISTING_PID" ]]; then
    echo "→ 端口 $PORT 被占用，终止 PID=$EXISTING_PID"
    kill -9 $EXISTING_PID 2>/dev/null || true
    sleep 1
fi

# 前置检查
echo "→ 检查环境"
command -v php >/dev/null || { echo "✗ 未找到 php，请先安装 PHP 8.1+"; exit 1; }
PHP_VER_OK="$(php -r 'echo PHP_VERSION_ID >= 80100 ? "yes" : "no";')"
[[ "$PHP_VER_OK" == "yes" ]] || { echo "✗ PHP 版本太低，需要 8.1+（当前 $(php -v | head -1)）"; exit 1; }

# 检查关键扩展
MISSING=()
for ext in pdo_mysql mbstring json curl gd iconv; do
    php -m 2>/dev/null | grep -qi "^$ext$" || MISSING+=("$ext")
done
if [[ ${#MISSING[@]} -gt 0 ]]; then
    echo "⚠ 缺少扩展: ${MISSING[*]}（建议安装：sudo apt install php8.3-{pdo_mysql,mbstring,curl,gd}）"
fi

# 检查 DB 连接
if command -v mysql >/dev/null; then
    if mysql -h 127.0.0.1 -u zentao -pzentao123456 -e "SELECT 1 FROM zentao.zt_user LIMIT 1" >/dev/null 2>&1; then
        echo "✓ MySQL 连接正常"
    else
        echo "⚠ MySQL 连接失败（参考 config/my.php 配置，预期 zentao/zentao123456/zentao）"
    fi
else
    echo "⚠ 未找到 mysql 客户端"
fi

# 启动服务器
echo "→ 启动 PHP 开发服务器 http://${HOST}:${PORT}/"
echo "  docroot: $DOCROOT"
echo "  log:     $LOG_FILE"
echo "  默认账号：admin / 123456"
echo "  按 Ctrl-C 停止"
echo ""

php -S "${HOST}:${PORT}" -t "$DOCROOT" >"$LOG_FILE" 2>&1 &
SERVER_PID=$!
echo "$SERVER_PID" > "$PID_FILE"

cleanup() {
    echo ""
    echo "→ 停止服务器 PID=$SERVER_PID"
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
    rm -f "$PID_FILE"
    echo "✓ 已退出"
}
trap cleanup EXIT INT TERM

# 等待服务器就绪
for i in {1..15}; do
    if curl -sI "http://${HOST}:${PORT}/" >/dev/null 2>&1; then
        echo "✓ 服务器就绪"
        break
    fi
    sleep 0.3
done

# 自动打开浏览器（可选）
if command -v xdg-open >/dev/null && [[ -z "${NO_OPEN_BROWSER:-}" ]]; then
    (sleep 1; xdg-open "http://${HOST}:${PORT}/" >/dev/null 2>&1) &
fi

# 实时打印日志
tail -f "$LOG_FILE"
