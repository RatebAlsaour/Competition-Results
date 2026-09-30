# بوابة نتائج المسابقات — وزارة العدل

نظام لنشر نتائج أي مسابقة توظيف ومتابعة المقبولين:

- **البوابة العامة** (`/`): يستعلم الزائر عن المقبولين (أساسي / احتياطي) حسب المسابقة والمحافظة والمسمى الوظيفي.
- **لوحة التحكم** (`/admin`): إدارة المسابقات، استيراد النتائج من Excel، متابعة الأسماء، الإحصائيات، التصدير.

Laravel 11 + React (Vite).

## التشغيل لأول مرة

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # ينشئ «مسابقة التوظيف 2026» كمسودة
php artisan admin:create        # إنشاء مستخدم للوحة التحكم
npm run build
```

## الأداء تحت الضغط (مئات الطلبات في الثانية)

البوابة العامة لا تستعلم من PHP أو قاعدة البيانات عن النتائج:

```
الزائر ──► /                     (PHP مرة واحدة، بلا جلسة ولا كوكيز ولا قاعدة بيانات، Cache-Control: 60s)
       ──► /data/competitions.json                 ┐
       ──► /data/{slug}/{version}/index.json        ├─ ملفات JSON ثابتة يقدمها Nginx مباشرة
       ──► /data/{slug}/{version}/r/{n}.json        ┘  (البحث بالاسم يتم في متصفح الزائر)
```

- تُولَّد الملفات تلقائياً (`ResultsSnapshotService`) عند: الاستيراد، النشر/إلغاء النشر، تعديل/إضافة/حذف اسم،
  تعديل إعدادات المسابقة. تغيير **المتابعة والملاحظات** لا يعيد التوليد لأنه غير منشور.
- كل تحديث يُكتب في مجلد نسخة جديد ثم يُبدَّل `competitions.json` دفعة واحدة، فلا يرى الزائر ملفاً نصف مكتوب،
  وتبقى النسخة السابقة متاحة لمن فتح الصفحة قبل التحديث.
- توليد مسابقة من 3330 اسماً يستغرق أقل من ثانية.
- الـ API العام `/api/competitions/...` ما زال متاحاً (للتكامل مع أنظمة أخرى) لكن البوابة لا تستخدمه.

### النشر على السيرفر

النشر عبر **Docker** على Ubuntu — الدليل الكامل خطوة بخطوة في [`deploy.md`](deploy.md).

| الملف | الوظيفة |
|---|---|
| `Dockerfile` | بناء متعدد المراحل: PHP 8.3-FPM + Composer + Vite ثم صورة nginx |
| `docker-compose.yml` | الخدمات `mysql` و `app` و `nginx` (على `127.0.0.1:8060`) |
| `.env.docker.example` | إعدادات الإنتاج (انسخه إلى `.env.docker`) |
| `docker/nginx/default.conf` | nginx الحاوية: ترويسات تخزين `/data` و `/build` وضغط gzip |
| `docker/php/*` | إعدادات PHP و OPcache وعمال FPM وسكربت الإقلاع |
| `deploy/nginx.conf.example` | nginx المضيف مع HTTPS أمام Docker |
| `scripts/deploy-docker-server.sh` | تحديث النسخة المنشورة بأمر واحد |
| `scripts/backup-db.sh` | نسخة احتياطية لقاعدة البيانات |

## سير العمل

1. **لوحة التحكم ← مسابقة جديدة**: الاسم، الأوراق المطلوبة، ملاحظة المقبولين الأساسيين، طريقة الترتيب.
2. **استيراد Excel**: رفع الملف ← فحص (ملخص حسب المحافظة + الأسطر المرفوضة) ← تأكيد.
   - *استبدال*: يحذف الأسماء الحالية ويعتمد الملف. *إضافة*: يضيف إلى الموجود.
3. **متابعة الأسماء**: بحث (يتجاهل أ/ا و ة/ه)، تصفية، تعديل، إضافة يدوية، حذف، تحديث حالة المتابعة
   (بانتظار المراجعة، قدّم الأوراق، تم التعاقد، اعتذر، لم يراجع) مع ملاحظات، وتصدير Excel.
4. **نشر على البوابة**. المسابقات غير المنشورة لا تظهر للزوار.

الاستيراد متاح أيضاً من سطر الأوامر:

```bash
php artisan results:import recruitment-2026 "path/to/file.xlsx" --dry-run
php artisan results:import recruitment-2026 "path/to/file.xlsx" [--append] [--force]
```

### ملف Excel

السطر الأول عناوين الأعمدة (الورقة الأولى فقط). الأعمدة تُعرف من عناوينها في `config/results.php`:

| الحقل | عناوين مقبولة | إلزامي |
|---|---|---|
| الاسم | الاسم الكامل، الاسم الثلاثي، الاسم | ✔ |
| المحافظة | المحافظة | ✔ |
| المسمى الوظيفي | المسمى الوظيفي، الوظيفة | ✔ |
| النتيجة | نتيجة، الحالة (يحتوي «أساسي» أو «احتياط») | ✔ |
| العلامة | العلامة، الدرجة | |
| تاريخ المقابلة | تاريخ المقابلة | |

العلامة وتاريخ المقابلة وحالة المتابعة والملاحظات **لا تُنشر** في البوابة.

## البنية

```
Controller  →  FormRequest (تحقق)  →  Service (منطق العمل)  →  Repo (استعلامات)  →  Model
                                        ↘ DTO (تحويل الطلب/سطر الإكسل إلى حقول النموذج)
```

| الطبقة | الملفات |
|---|---|
| Models | `Competition`, `Candidate`, `ResultImport` |
| Enums | `CompetitionStatusEnum`, `CandidateStatusEnum`, `FollowUpStatusEnum`, `RankingMethodEnum` |
| DTOs | `CompetitionData`, `CandidateData`, `SheetReadResultData` |
| Repos | `CompetitionRepo`, `CandidateRepo`, `ResultImportRepo` (ترث `BaseRepo`) |
| Filters | `Filters/Competition/CompetitionFilter`, `Filters/Candidate/CandidateFilter` |
| Services | `CompetitionService`, `CandidateService`, `ResultsImportService`, `ResultsService` (عام، مع Cache), `CandidatesExportService`, `AuthService`, `ResultsCacheService` |
| Interfaces | `IResultsSheetReader` ← `ExcelResultsSheetReader` (مربوط في `AppServiceProvider`) |
| Controllers | `Admin/*` (لوحة التحكم)، `ResultsController` (عام) |

لدعم صيغة ملفات أخرى (CSV مثلاً) يكفي كتابة صنف جديد يطبق `IResultsSheetReader` وتغيير الربط.

## الـ API

عام (مع Cache يُفرّغ تلقائياً عند أي تعديل):

| الطلب | الوصف |
|---|---|
| `GET /api/competitions` | المسابقات المنشورة |
| `GET /api/competitions/{slug}/governorates` | المحافظات |
| `GET /api/competitions/{slug}/job-titles?governorate=` | المسميات |
| `GET /api/competitions/{slug}/results?governorate=&job_title=` | المقبولون مرتبين |

لوحة التحكم (`/api/admin/*`، جلسة + CSRF): `login`, `logout`, `me`, `competitions` (CRUD + `stats`),
`competitions/{id}/candidates` (+ `options`, `export`), `candidates/{id}`, `competitions/{id}/imports` (+ `preview`).

قائمة الأسماء تدعم معاملات المشروع المعتادة: `search-key`, `filters[candidate][governorate|job_title|status|follow_up_status]`, `max`, `page`.

## التطوير

```bash
php artisan serve
npm run dev
php artisan test
```
