# دليل رفع مشروع بوابة نتائج المسابقات على سيرفر Ubuntu باستخدام Docker

نفس آلية نشر مشروع نظام التقييم (Evaluation): Docker Compose على السيرفر، و Nginx على المضيف أمامه مع SSL.

الخدمات:

| الخدمة | الوظيفة |
|---|---|
| `nginx` | يقدّم ملفات النتائج الثابتة وملفات الواجهة مباشرة، ويمرر الباقي إلى PHP. يستمع على `127.0.0.1:8060` |
| `app` | تطبيق Laravel (PHP 8.3-FPM) |
| `mysql` | قاعدة بيانات MySQL 8.4 (غير مكشوفة للخارج) |

وحدات التخزين (volumes): `mysql-data` (قاعدة البيانات)، `storage-data` (السجلات والجلسات)، `results-data` (ملفات النتائج الثابتة `public/data`).

```
الزائر ─► Nginx المضيف (443 + SSL) ─► حاوية nginx (8060) ─┬─► /data/*.json و /build/*  (ثابتة، بدون PHP)
                                                          └─► حاوية app (PHP-FPM) ─► mysql
```

---

## 1. المتطلبات

- Ubuntu Server 22.04 / 24.04.
- ذاكرة 2GB على الأقل (4GB مفضّل عند الضغط العالي).
- Docker Engine و Docker Compose plugin.
- Git وصلاحية قراءة المستودع `RatebAlsaour/Competition-Results`.
- (للإنتاج) دومين يشير إلى IP السيرفر لتفعيل HTTPS.

## 2. تثبيت Docker

```bash
sudo apt update
sudo apt install -y ca-certificates curl git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

sudo usermod -aG docker $USER
newgrp docker
docker --version
docker compose version
```

> إذا كان Docker مثبتاً مسبقاً لمشروع Evaluation على نفس السيرفر، تخطَّ هذه الخطوة.

## 3. تنزيل المشروع

```bash
cd /opt
sudo git clone git@github.com:RatebAlsaour/Competition-Results.git competition-results
sudo chown -R $USER:$USER /opt/competition-results
cd /opt/competition-results
```

إذا لم يكن مفتاح SSH للسيرفر مضافاً إلى GitHub، استخدم HTTPS:

```bash
sudo git clone https://github.com/RatebAlsaour/Competition-Results.git competition-results
```

وعند طلب كلمة المرور ضع **GitHub Personal Access Token** وليس كلمة مرور الحساب.

## 4. ملف البيئة `.env.docker`

لا يُرفع إلى Git لأنه يحتوي كلمات المرور:

```bash
cp .env.docker.example .env.docker
nano .env.docker
```

عدّل على الأقل:

```env
APP_URL=https://your-domain.com

DB_PASSWORD=كلمة_مرور_قوية
MYSQL_PASSWORD=نفس_كلمة_المرور_السابقة
MYSQL_ROOT_PASSWORD=كلمة_مرور_أخرى_قوية
```

- `DB_HOST` يبقى `mysql` (اسم خدمة MySQL داخل Docker).
- `DB_PASSWORD` يجب أن يطابق `MYSQL_PASSWORD`.
- لتوليد كلمات مرور قوية: `openssl rand -base64 24`
- إذا لم يكن لديك HTTPS بعد (تجربة عبر IP): اجعل `APP_URL=http://SERVER_IP` و `SESSION_SECURE_COOKIE=false`، وإلا لن يعمل تسجيل الدخول للوحة التحكم.

## 5. توليد APP_KEY

```bash
docker compose run --rm --no-deps app php artisan key:generate --show
```

انسخ الناتج (يبدأ بـ `base64:`) إلى `APP_KEY=` في `.env.docker`.

## 6. البناء والتشغيل

```bash
docker compose up -d --build
docker compose ps
```

يجب أن ترى `mysql` (healthy) و `app` و `nginx` بحالة `running`.

## 7. إنشاء الجداول والمستخدم الأول

```bash
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan db:seed --force      # (اختياري) ينشئ «مسابقة التوظيف 2026» كمسودة
docker compose exec -u www-data app php artisan admin:create          # مستخدم لوحة التحكم
```

> استخدم دائماً `-u www-data` مع أوامر artisan، حتى تبقى الملفات المولّدة (السجلات وملفات النتائج) قابلة للكتابة من التطبيق.

## 8. Nginx المضيف و HTTPS

```bash
sudo apt install -y nginx certbot python3-certbot-nginx
sudo cp /opt/competition-results/deploy/nginx.conf.example /etc/nginx/sites-available/competition-results
sudo nano /etc/nginx/sites-available/competition-results      # ضع الدومين بدل results.example.gov.sy
```

قبل الحصول على الشهادة، علّق مؤقتاً سطري `ssl_certificate` أو استخدم certbot مباشرة:

```bash
sudo ln -s /etc/nginx/sites-available/competition-results /etc/nginx/sites-enabled/
sudo certbot --nginx -d your-domain.com
sudo nginx -t && sudo systemctl reload nginx
```

- يعمل بجانب مشروع Evaluation على نفس السيرفر: ذاك على المنفذ `8050` وهذا على `8060`.
- لتغيير المنفذ: `APP_HTTP_PORT=8070 docker compose up -d` وعدّل `upstream` في إعداد Nginx المضيف.

## 9. التحقق

من داخل السيرفر:

```bash
curl -I http://127.0.0.1:8060/                        # 200
curl -I http://127.0.0.1:8060/admin                   # 200
curl -s http://127.0.0.1:8060/data/competitions.json  # [] قبل النشر
```

ثم من المتصفح:

- `https://your-domain.com/admin` ← سجّل الدخول، أنشئ مسابقة، استورد ملف Excel، اضغط «نشر على البوابة».
- `https://your-domain.com/` ← البوابة العامة.

## 10. استيراد ملف Excel من سطر الأوامر (بديل عن لوحة التحكم)

```bash
docker compose cp "results.xlsx" app:/tmp/results.xlsx
docker compose exec -u www-data app php artisan results:import recruitment-2026 /tmp/results.xlsx --dry-run
docker compose exec -u www-data app php artisan results:import recruitment-2026 /tmp/results.xlsx
```

## 11. تحديث المشروع بعد تعديلات جديدة

بعد رفع التعديلات إلى GitHub:

```bash
cd /opt/competition-results
bash scripts/deploy-docker-server.sh main
```

السكربت ينفّذ: `git pull` ← إعادة البناء ← `migrate` ← `optimize` ← `results:build-static` ← فحص صحة الروابط.

أو يدوياً:

```bash
git pull --ff-only origin main
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan results:build-static
```

## 12. النسخ الاحتياطي

```bash
bash scripts/backup-db.sh            # ينشئ backups/competition-results-YYYY-MM-DD-HHMMSS.sql.gz
```

جدولة يومية الساعة 3 صباحاً (`crontab -e`):

```cron
0 3 * * * cd /opt/competition-results && bash scripts/backup-db.sh >> backups/backup.log 2>&1
```

الاستعادة:

```bash
gunzip -c backups/FILE.sql.gz | docker compose exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
docker compose exec -u www-data app php artisan results:build-static
```

> ملفات النتائج الثابتة تُولّد من قاعدة البيانات، فلا حاجة لنسخها احتياطياً.

## 13. أوامر مفيدة

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f nginx
docker compose exec -u www-data app php artisan optimize
docker compose exec -u www-data app php artisan results:build-static
docker compose exec -u www-data app php artisan admin:create          # مستخدم جديد أو تغيير كلمة مرور
docker compose restart app nginx
docker compose down        # إيقاف (البيانات محفوظة في volumes)
docker compose up -d
```

## 14. مشاكل شائعة

### صفحة 500 أو `No application encryption key`
`APP_KEY` فارغ: الخطوة 5، ثم `docker compose up -d app`.

### تسجيل الدخول للوحة التحكم لا يعمل (يعود لصفحة الدخول أو خطأ 419)
- تفتح الموقع عبر `http` بينما `SESSION_SECURE_COOKIE=true` ← فعّل HTTPS أو اجعلها `false`.
- `APP_URL` لا يطابق الرابط الذي تفتحه.
- بعد أي تعديل على `.env.docker`: `docker compose up -d app` (إعادة إنشاء الحاوية لتقرأ القيم الجديدة).

### البوابة تعرض «لا توجد نتائج منشورة»
- تأكد أن المسابقة «منشورة» في لوحة التحكم.
- أعد توليد الملفات: `docker compose exec -u www-data app php artisan results:build-static`

### `Permission denied` عند الكتابة في storage أو public/data
نُفّذ أمر artisan بدون `-u www-data`. الإصلاح:

```bash
docker compose exec app chown -R www-data:www-data storage public/data
```

### فشل تنزيل صور Docker بسبب IPv6
```bash
sudo cp /etc/gai.conf /etc/gai.conf.bak
echo 'precedence ::ffff:0:0/96 100' | sudo tee -a /etc/gai.conf
sudo systemctl restart docker
```

### الموقع يعمل داخل السيرفر ولا يفتح من الخارج
```bash
sudo ss -ltnp | grep -E ':80|:443'
sudo ufw status              # إن كان مفعلاً: sudo ufw allow 'Nginx Full'
```

## 15. الأداء تحت الضغط

- نتائج الزوار ملفات JSON ثابتة (`/data/...`) يقدمها nginx مباشرة بدون PHP أو قاعدة بيانات، فيتحمل الموقع مئات الطلبات في الثانية.
- صفحة البوابة `/` بلا جلسة ولا قاعدة بيانات، وتُخزَّن 60 ثانية.
- `docker/php/www.conf`: عدد عمال PHP (`pm.max_children = 40`)؛ خفّضه إن كانت ذاكرة السيرفر أقل من 2GB.
- لتحمل أكبر: ضع الموقع خلف CDN مثل Cloudflare؛ كل ما يطلبه الزوار قابل للتخزين فيه.

## 16. ملاحظات أمنية

- لا ترفع `.env.docker` إلى Git.
- MySQL غير مكشوفة للخارج (داخل شبكة Docker فقط)، و nginx الحاوية على `127.0.0.1` فقط.
- كلمات مرور قوية لـ MySQL ولحسابات لوحة التحكم.
- فعّل HTTPS قبل استخدام لوحة التحكم.
- خذ نسخاً احتياطية دورية (الخطوة 12).
