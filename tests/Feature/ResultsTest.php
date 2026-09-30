<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ResultsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['م', 'الاسم الكامل', 'المحافظة', 'المسمى الوظيفي', 'تاريخ المقابلة', 'العلامة النهائية لاختبار ومقابلة', 'نتيجة المقابلة النهائية للترشيح'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('results_static');
    }

    private function staticJson(string $path): mixed
    {
        return json_decode(Storage::disk('results_static')->get($path), true);
    }

    public function test_static_files_are_published_versioned_and_removed(): void
    {
        $competition = $this->competition(['status' => 'draft']);
        $this->artisan('results:import', ['competition' => $competition->id, 'file' => $this->makeSheet($this->sampleRows()), '--force' => true]);

        // مسودة: لا شيء منشور
        $this->assertSame([], $this->staticJson('competitions.json'));

        $this->admin()->putJson("/api/admin/competitions/{$competition->id}", ['status' => 'published'])->assertOk();

        $list = $this->staticJson('competitions.json');
        $this->assertCount(1, $list);
        $path = $list[0]['path'];
        $this->assertStringStartsWith('test-2026/', $path);

        $index = $this->staticJson("{$path}/index.json");
        $this->assertSame(['دمشق', 'الحسكة'], array_column($index['governorates'], 'name'));

        $file = $index['governorates'][1]['titles'][0]['file'];
        $results = $this->staticJson("{$path}/{$file}");
        $this->assertSame(3, $results['total']);
        $this->assertSame(['seq' => 1, 'name' => 'عمر محمد البرخو', 'status' => 'primary'], $results['candidates'][0]);
        $this->assertStringNotContainsString('score', json_encode($results));

        // فهرس البحث بالاسم
        $names = $this->staticJson("{$path}/names.json");
        $this->assertCount(4, $names['rows']);
        $row = collect($names['rows'])->firstWhere(0, 'عمر محمد البرخو');
        $this->assertSame(['الحسكة', 'رئيس ديوان قضائي'], $names['groups'][$row[1]]);
        $this->assertSame([1, 1], [$row[2], $row[3]]);

        // تغيير المتابعة (داخلي) لا يولّد نسخة جديدة
        $candidate = $competition->candidates()->first();
        $this->putJson("/api/admin/candidates/{$candidate->id}", ['follow_up_status' => 'contracted'])->assertOk();
        $this->assertSame($path, $this->staticJson('competitions.json')[0]['path']);

        // تعديل الاسم يولّد نسخة جديدة
        $this->putJson("/api/admin/candidates/{$candidate->id}", ['full_name' => 'اسم معدل'])->assertOk();
        $newPath = $this->staticJson('competitions.json')[0]['path'];
        $this->assertNotSame($path, $newPath);
        Storage::disk('results_static')->assertExists("{$path}/index.json"); // النسخة السابقة باقية للزوار الحاليين

        // إلغاء النشر يحذف الملفات
        $this->putJson("/api/admin/competitions/{$competition->id}", ['status' => 'draft'])->assertOk();
        $this->assertSame([], $this->staticJson('competitions.json'));
        Storage::disk('results_static')->assertMissing('test-2026');
    }

    public function test_portal_page_has_no_session_cookie(): void
    {
        $response = $this->get('/')->assertOk();
        $this->assertEmpty($response->headers->getCookies());
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    }

    private function makeSheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'res') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADER));
        foreach ($rows as $i => $r)
        {
            $writer->addRow(Row::fromValues(array_merge([$i + 1], $r)));
        }
        $writer->close();

        return $path;
    }

    private function sampleRows(): array
    {
        return [
            ['خالد احمد المطلق', 'الحسكة ', 'رئيس ديوان قضائي', '20/7/2026', 85, 'تعاقد أساسي'],
            ['عمر محمد البرخو', 'الحسكة', 'رئيس ديوان قضائي', '8/8/2026', 90, 'تعاقد أساسي'],
            ['رقيه عباس العبدالله', 'الحسكة', 'رئيس ديوان قضائي', '8/8/2026', 70, 'ناجح احتياط '],
            ['نجم علي الخليل', 'دمشق', 'ناسخ', '8/8/2026', 80, 'ناجح احتياط'],
            ['', 'دمشق', 'ناسخ', '8/8/2026', 80, 'ناجح احتياط'],
        ];
    }

    private function competition(array $attributes = []): Competition
    {
        return Competition::query()->create(array_merge([
            'title'  => 'مسابقة تجريبية',
            'slug'   => 'test-2026',
            'status' => 'published',
        ], $attributes));
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'results.xlsx', null, null, true);
    }

    public function test_cli_import_and_public_results(): void
    {
        $competition = $this->competition();

        $this->artisan('results:import', ['competition' => 'test-2026', 'file' => $this->makeSheet($this->sampleRows()), '--force' => true])
            ->assertSuccessful();

        $this->assertSame(4, $competition->candidates()->count());

        $this->getJson('/api/competitions')->assertOk()->assertJsonPath('data.0.slug', 'test-2026');

        $this->getJson('/api/competitions/test-2026/governorates')
            ->assertOk()
            ->assertJsonPath('data', ['دمشق', 'الحسكة']);

        $res = $this->getJson('/api/competitions/test-2026/results?' . http_build_query(['governorate' => 'الحسكة', 'job_title' => 'رئيس ديوان قضائي']))
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.primaryCount', 2)
            ->assertJsonPath('data.candidates.0', ['seq' => 1, 'name' => 'عمر محمد البرخو', 'status' => 'primary'])
            ->assertJsonPath('data.candidates.2.status', 'reserve');

        // العلامة لا تُنشر
        $this->assertStringNotContainsString('score', $res->getContent());
    }

    public function test_draft_competition_is_not_public(): void
    {
        $this->competition(['status' => 'draft']);

        $this->getJson('/api/competitions')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/competitions/test-2026/governorates')->assertNotFound();
    }

    public function test_dashboard_requires_login(): void
    {
        $this->getJson('/api/admin/competitions')->assertUnauthorized();
    }

    public function test_login(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_competition_crud(): void
    {
        $this->admin();

        $id = $this->postJson('/api/admin/competitions', [
            'title'              => 'مسابقة المحاسبين',
            'slug'               => 'accountants',
            'required_documents' => [['title' => 'صورة الهوية', 'notes' => ['']], ['title' => '']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.required_documents', [['title' => 'صورة الهوية', 'notes' => []]])
            ->json('data.id');

        // slug مكرر يحصل على لاحقة
        $this->postJson('/api/admin/competitions', ['title' => 'أخرى', 'slug' => 'accountants'])
            ->assertJsonPath('data.slug', 'accountants-2');

        $this->putJson("/api/admin/competitions/{$id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.title', 'مسابقة المحاسبين');

        $this->assertNotNull(Competition::find($id)->published_at);

        $this->getJson('/api/admin/competitions')->assertOk()->assertJsonPath('data.total', 2);

        $this->deleteJson("/api/admin/competitions/{$id}")->assertOk();
        $this->assertNull(Competition::find($id));
    }

    public function test_import_preview_and_store_from_dashboard(): void
    {
        $competition = $this->competition();
        $this->admin();
        $file = $this->makeSheet($this->sampleRows());

        $this->post("/api/admin/competitions/{$competition->id}/imports/preview", ['file' => $this->upload($file)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.valid_count', 4)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.errors.0.row', 6);

        $this->assertSame(0, $competition->candidates()->count());

        $this->post("/api/admin/competitions/{$competition->id}/imports", ['file' => $this->upload($file)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.imported_count', 4);

        // إضافة بدل الاستبدال
        $extra = $this->makeSheet([['اسم جديد', 'حلب', 'ناسخ', '', 75, 'تعاقد أساسي']]);
        $this->post("/api/admin/competitions/{$competition->id}/imports", ['file' => $this->upload($extra), 'mode' => 'append'], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(5, $competition->candidates()->count());
        $this->getJson("/api/admin/competitions/{$competition->id}/imports")->assertOk()->assertJsonCount(2, 'data');

        // الاستبدال
        $this->post("/api/admin/competitions/{$competition->id}/imports", ['file' => $this->upload($extra)], ['Accept' => 'application/json'])
            ->assertCreated();
        $this->assertSame(1, $competition->candidates()->count());
    }

    public function test_candidates_listing_search_filters_and_follow_up(): void
    {
        $competition = $this->competition();
        $this->artisan('results:import', ['competition' => $competition->id, 'file' => $this->makeSheet($this->sampleRows()), '--force' => true]);
        $this->admin();

        $url = "/api/admin/competitions/{$competition->id}/candidates";

        // البحث يتجاهل اختلاف الهمزة
        $this->getJson($url . '?' . http_build_query(['search-key' => 'أحمد']))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.full_name', 'خالد احمد المطلق');

        $this->getJson($url . '?' . http_build_query(['filters' => ['candidate' => ['status' => 'reserve']]]))
            ->assertJsonPath('data.total', 2);

        $this->getJson("{$url}/options")->assertOk()->assertJsonPath('data.governorates', ['دمشق', 'الحسكة']);

        $candidate = $competition->candidates()->where('full_name', 'خالد احمد المطلق')->first();

        // تحديث جزئي: المتابعة فقط، بقية الحقول تبقى كما هي
        $this->putJson("/api/admin/candidates/{$candidate->id}", ['follow_up_status' => 'contracted', 'notes' => 'راجع يوم الأحد'])
            ->assertOk()
            ->assertJsonPath('data.follow_up_status', 'contracted')
            ->assertJsonPath('data.score', 85)
            ->assertJsonPath('data.rank', 2);

        // تعديل العلامة يعيد الترتيب
        $this->putJson("/api/admin/candidates/{$candidate->id}", ['score' => 95])->assertJsonPath('data.rank', 1);
        $this->assertSame(2, Candidate::where('full_name', 'عمر محمد البرخو')->value('rank'));

        $this->getJson("/api/admin/competitions/{$competition->id}/stats")
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.follow_up.contracted', 1);

        // إضافة يدوية
        $this->postJson($url, ['full_name' => 'مرشح يدوي', 'governorate' => 'دمشق', 'job_title' => 'ناسخ', 'status' => 'primary', 'score' => 99])
            ->assertCreated()
            ->assertJsonPath('data.rank', 1)
            ->assertJsonPath('data.follow_up_status', 'pending');

        $this->deleteJson("/api/admin/candidates/{$candidate->id}")->assertOk();
        $this->assertSame(4, $competition->candidates()->count());

        $this->get("{$url}/export")->assertOk()->assertDownload("candidates-{$competition->slug}.xlsx");
    }

    public function test_public_cache_is_flushed_after_changes(): void
    {
        $competition = $this->competition();
        $this->artisan('results:import', ['competition' => $competition->id, 'file' => $this->makeSheet($this->sampleRows()), '--force' => true]);

        $query = '/api/competitions/test-2026/results?' . http_build_query(['governorate' => 'دمشق', 'job_title' => 'ناسخ']);
        $this->getJson($query)->assertJsonPath('data.total', 1);

        $this->admin()->postJson("/api/admin/competitions/{$competition->id}/candidates", [
            'full_name' => 'مرشح جديد', 'governorate' => 'دمشق', 'job_title' => 'ناسخ', 'status' => 'reserve',
        ])->assertCreated();

        $this->getJson($query)->assertJsonPath('data.total', 2);
    }
}
