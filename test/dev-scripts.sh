#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

cat > "$TMP/php" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$@"
EOF
chmod +x "$TMP/php"

output="$(PHP_BIN="$TMP/php" "$ROOT/bin/dev-test" sample.php first second)"
grep -Fx -- 'sample.php' <<<"$output" >/dev/null
grep -Fx -- 'first' <<<"$output" >/dev/null
grep -Fx -- 'second' <<<"$output" >/dev/null

if PHP_BIN="$TMP/php" env -u ZT_TEST_ACCOUNT -u ZT_TEST_PASSWORD -u ZT_TEST_BASE \
    "$ROOT/bin/dev-test" test/api/example.php >"$TMP/stdout" 2>"$TMP/stderr"
then
    echo 'API test unexpectedly accepted missing credentials.' >&2
    exit 1
fi

grep -F 'ZT_TEST_ACCOUNT' "$TMP/stderr" >/dev/null
grep -F 'ZT_TEST_PASSWORD' "$TMP/stderr" >/dev/null
grep -F 'ZT_TEST_BASE' "$TMP/stderr" >/dev/null

echo 'dev script tests passed'
