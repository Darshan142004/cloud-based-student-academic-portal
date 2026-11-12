# Azure Database for MySQL Flexible Server Migration Guide

This guide covers safe migration of the EduPulse MySQL 8.0 schema and existing academic data from local Docker MySQL to Azure Database for MySQL Flexible Server while preserving local development support.

## 1. Compatibility & Engine Verification
- All EduPulse tables use **InnoDB** and **utf8mb4** collation (`utf8mb4_unicode_ci` / `utf8mb4_0900_ai_ci`), which are fully supported on Azure Database for MySQL Flexible Server 8.0.
- Avoid `DEFINER` clauses on triggers/views when exporting (`--set-gtid-purged=OFF`) because Azure MySQL Flexible Server restricts `SUPER` privileges on non-admin users.

## 2. Environment-Driven TLS Configuration (`backend/config/database.php`)
| Variable | Local Docker Default | Azure MySQL Production Value |
| :--- | :--- | :--- |
| `DB_HOST` | `mysql` (or `localhost`) | `<server>.mysql.database.azure.com` |
| `DB_PORT` | `3306` | `3306` |
| `DB_NAME` | `student_portal` | `student_portal` |
| `DB_USER` | `root` | `<admin_user>` |
| `DB_PASS` | *(env var)* | `<password_secret>` |
| `DB_SSL_MODE` | `disabled` | `verify_ca` |
| `DB_SSL_CA` | *(empty)* | `/etc/ssl/certs/ca-certificates.crt` |

## 3. Safe Backup & Export from Local Docker MySQL
Always capture a full transactional backup before any migration or import:

```bash
mkdir -p backups
docker exec student-portal-mysql mysqldump \
  -u root -p"$MYSQL_ROOT_PASSWORD" \
  --single-transaction --routines --triggers \
  --set-gtid-purged=OFF \
  student_portal > "backups/edupulse_backup_$(date +%F_%H%M%S).sql"
```

## 4. Import into Azure Database for MySQL Flexible Server (TLS Enforced)
```bash
export DB_HOST="<server>.mysql.database.azure.com"
export DB_USER="<admin_user>"
export MYSQL_PWD="<password>"
export CA_CERT="/etc/ssl/certs/ca-certificates.crt"

# 1. Create database if needed
mysql -h "$DB_HOST" -P 3306 -u "$DB_USER" --ssl-mode=VERIFY_CA --ssl-ca="$CA_CERT" \
  -e "CREATE DATABASE IF NOT EXISTS student_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import exported dump
mysql -h "$DB_HOST" -P 3306 -u "$DB_USER" --ssl-mode=VERIFY_CA --ssl-ca="$CA_CERT" \
  student_portal < backups/edupulse_backup_YYYY-MM-DD_HHMMSS.sql

# 3. Apply repeatable migrations safely
DB_PASS="$MYSQL_PWD" DB_SSL_CA="$CA_CERT" ./scripts/migrate.sh
```

## 5. Post-Migration Integrity Verification
Run after import to confirm all tables, foreign keys, indexes, and record counts match:

```sql
SELECT table_name, table_rows
FROM information_schema.tables
WHERE table_schema = 'student_portal'
ORDER BY table_name;

SELECT table_name, constraint_name, referenced_table_name
FROM information_schema.referential_constraints
WHERE constraint_schema = 'student_portal'
ORDER BY table_name, constraint_name;
```

## 6. Rollback Procedure
1. Enable Azure Automated Backups (point-in-time recovery) before altering production tables.
2. Retain the pre-migration `.sql` snapshot created in Step 3.
3. If an unexpected schema issue occurs, point `DB_HOST` back to the pre-migration replica/snapshot or restore into a clean database name (`student_portal_restore`) and verify row counts before switching application secrets.

