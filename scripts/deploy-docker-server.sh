#!/usr/bin/env bash
# تحديث المشروع على السيرفر: سحب آخر نسخة، إعادة البناء، الترحيل، توليد ملفات النتائج.
# الاستخدام: cd /opt/competition-results && bash scripts/deploy-docker-server.sh [branch]

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BRANCH="${1:-$(git branch --show-current)}"

if [[ ! -f .env.docker ]]; then
    echo "ERROR: .env.docker غير موجود. انسخه من .env.docker.example وعدّل القيم." >&2
    exit 1
fi
if grep -qE '^APP_KEY=\s*$' .env.docker; then
    echo "ERROR: APP_KEY فارغ في .env.docker. ولّده بـ: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

echo "==> git pull ($BRANCH)"
git fetch origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Build & start"
export APP_BUILD_BRANCH="$BRANCH"
export APP_BUILD_COMMIT="$(git rev-parse --short HEAD)"
export APP_BUILD_COMMIT_DATE="$(git log -1 --format=%cI HEAD)"
docker compose up -d --build

echo "==> Wait for app"
for i in $(seq 1 30); do
    if docker compose exec -T app php artisan --version >/dev/null 2>&1; then break; fi
    sleep 2
done

echo "==> Migrate"
docker compose exec -T -u www-data app php artisan migrate --force --no-interaction

echo "==> Cache & static results"
docker compose exec -T -u www-data app php artisan optimize --no-interaction
docker compose exec -T -u www-data app php artisan results:build-static --no-interaction

echo "==> Status"
docker compose ps

PORT="${APP_HTTP_PORT:-8060}"
echo "==> Health check (http://127.0.0.1:${PORT})"
curl -fsS -o /dev/null -w "  /                         -> %{http_code}\n" "http://127.0.0.1:${PORT}/" || true
curl -fsS -o /dev/null -w "  /data/competitions.json   -> %{http_code}\n" "http://127.0.0.1:${PORT}/data/competitions.json" || true
curl -fsS -o /dev/null -w "  /admin                    -> %{http_code}\n" "http://127.0.0.1:${PORT}/admin" || true

echo "==> Done ($APP_BUILD_COMMIT)"
