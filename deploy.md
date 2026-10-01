# دليل رفع مشروع بوابة نتائج المسابقات على سيرفر Ubuntu باستخدام Docker

نفس آلية نشر مشروع نظام التقييم (Evaluation): Docker Compose على السيرفر، و Nginx على المضيف أمامه مع SSL.

الخدمات:

| الخدمة | الوظيفة |
|---|---|
| `nginx` | يقدّم ملفات النتائج الثابتة وملفات الواجهة مباشرة، ويمرر الباقي إلى PHP. يستمع على `127.0.0.1:8060` |
| `app` | تطبيق Laravel (PHP 8.3-FPM) |
| `mysql` | قاعدة بيانات MySQL 8.4 (غير مكشوفة للخارج) |
| `redis` | (اختياري) كاش وجلسات في الذاكرة — انظر القسم 15 |

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

## 11.1 التحديث عندما لا يصل السيرفر إلى الإنترنت

إذا كانت شبكة السيرفر تمنع الإنترنت (`git pull` يفشل بـ `timed out` أو `Could not resolve`)، أرسل الكود
مباشرة من جهازك إلى السيرفر عبر الشبكة الداخلية. البناء يستخدم ما نزّله السيرفر سابقاً، فلا يحتاج إنترنت
(ما لم تتغير ملفات `composer.json` أو `package.json` أو قسم التثبيت في `Dockerfile`).

**مرة واحدة على السيرفر** (يسمح باستقبال الكود مباشرة):

```bash
cd /opt/competition-results && git config receive.denyCurrentBranch updateInstead
```

**مرة واحدة على جهازك** (PowerShell أو Git Bash داخل مجلد المشروع):

```bash
git remote add server ssh://hr@192.168.1.52/opt/competition-results
```

**كل تحديث:**

```bash
# على جهازك
git push origin main      # GitHub (نسخة احتياطية)
git push server main      # السيرفر مباشرة — يطلب كلمة مرور hr

# على السيرفر
cd /opt/competition-results && bash scripts/deploy-docker-server.sh main
```

السكربت يكتشف أن GitHub غير متاح ويكمل بالنسخة التي وصلت عبر `git push server`.

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

## 15. الأداء تحت الضغط (الكاش)

الكاش على أربع طبقات، كل طبقة تمنع الطلب من الوصول لما بعدها:

| الطبقة | ماذا يُخزَّن | المدة | الأثر |
|---|---|---|---|
| متصفح الزائر | ملفات الواجهة `/build` والنتائج `/data/{نسخة}` | سنة (تتغير أسماؤها عند كل تحديث) | الزائر العائد لا يطلبها مجدداً |
| nginx — ملفات ثابتة | كل النتائج JSON + ملفات الواجهة | دائم | بدون PHP ولا قاعدة بيانات إطلاقاً |
| nginx — صفحة البوابة `/` | نسخة HTML جاهزة | 60 ثانية | PHP مرة في الدقيقة بدل كل زائر؛ آلاف الزوار معاً = طلب واحد لـ PHP |
| كاش Laravel (قاعدة البيانات، أو Redis إن فُعّل) | إحصائيات لوحة التحكم، الجلسات، عدادات الحماية | حتى أي تعديل | لا إعادة حساب مع كل فتح للوحة |

- كل تعديل (استيراد، نشر، تعديل اسم أو متابعة) يمسح الكاش المتعلق به تلقائياً — لا حاجة لأي تدخل.
- **Redis اختياري:** يجعل الجلسات وعدادات الحماية في الذاكرة بدل قاعدة البيانات. يحتاج إنترنت **مرة واحدة** لتنزيله، ثم:
  ```bash
  sed -i 's/^CACHE_STORE=.*/CACHE_STORE=redis/; s/^SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env.docker
  INSTALL_REDIS=true COMPOSE_PROFILES=redis bash scripts/deploy-docker-server.sh main
  ```
  ولتشغيله دائماً أضف إلى `~/.bashrc`: `export INSTALL_REDIS=true COMPOSE_PROFILES=redis`
- `docker/php/www.conf`: عدد عمال PHP (`pm.max_children = 40`).
- لتحمل أكبر: CDN مثل Cloudflare أمام الموقع — كل ما يطلبه الزوار قابل للتخزين فيه.

التحقق من عمل الكاش:

```bash
# الطلب الثاني يجب أن يكون HIT (من الكاش)
curl -s -o /dev/null -D - http://127.0.0.1:8060/ | grep -i x-cache-status
curl -s -o /dev/null -D - http://127.0.0.1:8060/ | grep -i x-cache-status
# (إن فُعّل Redis)
docker compose exec redis redis-cli ping
```

## 16. الحماية من الاختراق والتخريب

الحماية على عدة طبقات، إذا تجاوز المهاجم طبقة تبقى التي بعدها:

| الطبقة | ماذا تمنع | أين |
|---|---|---|
| جدار ناري (ufw) | الوصول لأي منفذ غير SSH والموقع | `scripts/harden-server.sh` |
| fail2ban | تخمين كلمة مرور SSH (حظر ساعة، ثم أسبوع للمتكرر) | `scripts/harden-server.sh` |
| تحديثات أمان تلقائية | ثغرات النظام المعروفة | `scripts/harden-server.sh` |
| حد الطلبات في nginx | الإغراق (20 طلب PHP/ثانية لكل IP)، و 10 محاولات دخول/دقيقة | `docker/nginx/default.conf` |
| قفل الحساب | تخمين كلمة مرور لوحة التحكم حتى من عناوين متعددة (5 محاولات ← قفل 15 دقيقة) | `AuthService` |
| ترويسات الأمان (CSP…) | حقن JavaScript (XSS)، تضمين الموقع في صفحة مزيفة | `SecurityHeaders` |
| تنفيذ `index.php` فقط | تشغيل ملف PHP مزروع | `docker/nginx/default.conf` |
| حجب الملفات الحساسة | قراءة `.env` أو `.git` أو ملفات الإعداد | `docker/nginx/default.conf` |
| MySQL داخل Docker فقط | الاتصال بقاعدة البيانات من الخارج | `docker-compose.yml` |
| حاويات بلا تصعيد صلاحيات + تدوير السجلات | استغلال حاوية مخترقة، امتلاء القرص بالسجلات | `docker-compose.yml` |
| السجل الأمني | معرفة من فعل ماذا ومتى ومن أي IP | `storage/logs/security-*.log` |
| نسخ احتياطي يومي | فقدان البيانات حتى لو حُذف كل شيء | `scripts/backup-db.sh` |

### تفعيل حماية السيرفر (مرة واحدة)

```bash
cd /opt/competition-results && sudo bash scripts/harden-server.sh
```

عند ربط دومين مع HTTPS: `sudo ALLOW_HTTPS=true bash scripts/harden-server.sh`

### السجل الأمني

يسجّل: الدخول الناجح والفاشل والقفل، الخروج، الاستيراد، تعديل بيانات منشورة (مع القيم القديمة والجديدة)، الحذف، تغيير حالة النشر.

```bash
# آخر الأحداث
docker compose exec app sh -c 'tail -n 50 storage/logs/security-*.log'
# محاولات الدخول الفاشلة فقط
docker compose exec app sh -c 'grep -h "login\.\(failed\|locked\)" storage/logs/security-*.log | tail -n 30'
# المحظورون من fail2ban
sudo fail2ban-client status sshd
```

### (موصى به) لوحة التحكم من الشبكة الداخلية فقط

حتى لو سُرقت كلمة مرور، لا يمكن الدخول من خارج الشبكة. في `/etc/nginx/sites-available/competition-results` أضف داخل `server { }` قبل `location /`:

```nginx
location ~ ^/(admin|api/admin)(/|$) {
    allow 192.168.0.0/16;
    allow 127.0.0.1;
    deny all;
    proxy_pass http://competition_results;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_set_header Host $http_host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $remote_addr;
    proxy_set_header X-Forwarded-Host $http_host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Port $server_port;
}
```

ثم `sudo nginx -t && sudo systemctl reload nginx`.

### (موصى به) الدخول إلى SSH بالمفتاح فقط

**لا تنفذ هذا قبل التأكد أن الدخول بمفتاح SSH يعمل من جهازك، وإلا ستُقفل خارج السيرفر.**

من جهازك (Windows PowerShell):

```powershell
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh hr@192.168.1.52 "cat >> ~/.ssh/authorized_keys"
```

جرّب الدخول في نافذة جديدة (يجب ألا يطلب كلمة مرور)، ثم على السيرفر:

```bash
printf 'PasswordAuthentication no\nKbdInteractiveAuthentication no\nPermitRootLogin no\n' | sudo tee /etc/ssh/sshd_config.d/00-hardening.conf && sudo sshd -t && sudo systemctl reload ssh && sudo sshd -T | grep -E "^(passwordauthentication|permitrootlogin)"
```

### إذا اشتبهت باختراق

1. راجع السجل الأمني ومن دخل ومتى (أعلاه).
2. غيّر كلمات المرور: `docker compose exec -u www-data app php artisan admin:create` (نفس البريد يغيّر كلمة المرور).
3. أنهِ كل الجلسات: `docker compose exec -u www-data app php artisan tinker --execute="DB::table('sessions')->truncate();"`
4. استعد آخر نسخة احتياطية سليمة إن تلاعب أحد بالبيانات (القسم 12).
5. أعد بناء الحاويات من الكود النظيف: `docker compose up -d --build --force-recreate`

### قواعد عامة

- لا ترفع `.env.docker` إلى Git، ولا ترسله لأحد.
- كلمات مرور قوية (12 حرفاً على الأقل) ولكل مستخدم حسابه الخاص.
- فعّل HTTPS عند ربط دومين.
- لا تفتح منفذ MySQL أو 8060 في الجدار الناري.
