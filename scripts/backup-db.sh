#!/usr/bin/env bash
# نسخة احتياطية من قاعدة البيانات إلى backups/ (تُحذف النسخ الأقدم من 30 يوماً).
# الاستخدام: cd /opt/competition-results && bash scripts/backup-db.sh
# للجدولة اليومية (crontab -e):
#   0 3 * * * cd /opt/competition-results && bash scripts/backup-db.sh >> backups/backup.log 2>&1

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
mkdir -p backups

FILE="backups/competition-results-$(date +%F-%H%M%S).sql.gz"
docker compose exec -T mysql sh -c 'mysqldump --single-transaction -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' | gzip > "$FILE"
echo "Backup: $FILE ($(du -h "$FILE" | cut -f1))"

find backups -name 'competition-results-*.sql.gz' -mtime +30 -delete
