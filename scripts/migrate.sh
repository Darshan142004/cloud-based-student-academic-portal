#!/usr/bin/env bash
set -euo pipefail

# Repeatable, non-destructive EduPulse MySQL migration runner.
# Supports both local Docker MySQL and remote Azure Database for MySQL Flexible Server (with TLS).

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MIGRATIONS_DIR="${ROOT_DIR}/database/migrations"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-student_portal}"
DB_SSL_CA="${DB_SSL_CA:-}"

MYSQL_ARGS=(-h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}")
if [[ -n "${DB_PASS}" ]]; then
    export MYSQL_PWD="${DB_PASS}"
fi
if [[ -n "${DB_SSL_CA}" && -f "${DB_SSL_CA}" ]]; then
    MYSQL_ARGS+=(--ssl-mode=VERIFY_CA --ssl-ca="${DB_SSL_CA}")
fi

echo "==> Checking database connectivity (${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME})..."
mysql "${MYSQL_ARGS[@]}" "${DB_NAME}" -e "SELECT 1;" >/dev/null

echo "==> Ensuring schema_migrations tracking table exists..."
mysql "${MYSQL_ARGS[@]}" "${DB_NAME}" <<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_id INT NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (migration_id),
    UNIQUE KEY uq_schema_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL

shopt -s nullglob
for file in "${MIGRATIONS_DIR}"/*.sql; do
    fname="$(basename "${file}")"
    already=$(mysql "${MYSQL_ARGS[@]}" "${DB_NAME}" -Nse "SELECT COUNT(*) FROM schema_migrations WHERE filename = '${fname}';")
    if [[ "${already}" -gt 0 ]]; then
        echo "  [skip] ${fname} (already recorded)"
        continue
    fi
    echo "  [apply] ${fname}..."
    mysql "${MYSQL_ARGS[@]}" "${DB_NAME}" < "${file}"
    mysql "${MYSQL_ARGS[@]}" "${DB_NAME}" -e "INSERT INTO schema_migrations (filename) VALUES ('${fname}');"
    echo "  [done]  ${fname}"
done

echo "==> Migration check complete."

