<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Examinee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('results_static');
        $this->actingAs(User::factory()->create());
    }

    /**
     * Same shape as the sworn translators file: a sheet per language with a title row,
     * spare numbers without names, a private note column, and a sheet with several tables.
     */
    private function examineesFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'exam') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('انكليزي');
        $writer->addRow(Row::fromValues(['اللغة الإنكليزية']));
        $writer->addRow(Row::fromValues(['الرقم الامتحاني', 'رقم ملف المتسابق', 'الاسم الثلاثي', 'اللغة']));
        $writer->addRow(Row::fromValues([1, 272, 'دليل عبد المناف سعدو', 'إنكليزية']));
        $writer->addRow(Row::fromValues([2, '88  تمديد', 'رنا محمد ورده', 'إنكليزية']));
        $writer->addRow(Row::fromValues([3, 10, 'محمد رشيد حسن', 'إنكليزية', 'الطابق الأرضي بسبب الإعاقة']));
        $writer->addRow(Row::fromValues([40, 'خطأ سجلت بالانكليزي برقم 1203', 'أحمد علي الخطيب', 'إنكليزية']));
        $writer->addRow(Row::fromValues([41, null, null, 'إنكليزية']));   // رقم احتياطي
        $writer->addRow(Row::fromValues([42, null, null, 'إنكليزية']));

        $writer->addNewSheetAndMakeItCurrent()->setName('ورقة1');
        $writer->addRow(Row::fromValues(['توزيع القاعات']));
        $writer->addRow(Row::fromValues(['من 1 -35']));

        $writer->addNewSheetAndMakeItCurrent()->setName('باقي اللغات');
        $writer->addRow(Row::fromValues(['الهولندية']));
        $writer->addRow(Row::fromValues(['الرقم الامتحاني ', 'رقم ملف المتسابق', 'الاسم الثلاثي', 'اللغة']));
        $writer->addRow(Row::fromValues([1, 4, 'عمر حسين الرجا', 'الهولندية']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['تركية قديم']));
        $writer->addRow(Row::fromValues(['الرقم الامنحاني', 'رقم ملف المتسابق', 'الاسم الثلاثي', 'اللغة']));   // خطأ إملائي في العنوان
        $writer->addRow(Row::fromValues([1, 1, 'عمار ياسر بيطار', null]));   // اللغة من عنوان الجدول

        $writer->close();
        return $path;
    }

    private function resultsFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'res') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['الرقم الامتحاني', 'الاسم الثلاثي', 'اللغة', 'العلامة', 'النتيجة']));
        $writer->addRow(Row::fromValues([1, 'دليل عبد المناف سعدو', 'إنكليزية', 82, 'ناجح']));
        $writer->addRow(Row::fromValues([2, 'رنا محمد ورده', 'إنكليزية', 40, 'راسب']));
        $writer->addRow(Row::fromValues([3, 'محمد رشيد حسن', 'إنكليزية', null, 'غائب']));
        $writer->addRow(Row::fromValues([99, 'غير موجود', 'إنكليزية', 70, 'ناجح']));
        $writer->addRow(Row::fromValues([1, 'عمر حسين الرجا', 'الهولندية', 55, 'غير مقبول']));
        $writer->close();
        return $path;
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'file.xlsx', null, null, true);
    }

    private function exam(array $attributes = []): Exam
    {
        return Exam::query()->create(array_merge(['title' => 'مسابقة التراجمة المحلفين', 'slug' => 'translators', 'status' => 'published'], $attributes))->refresh();
    }

    private function importExaminees(Exam $exam): void
    {
        $this->post("/api/admin/exams/{$exam->id}/imports", ['file' => $this->upload($this->examineesFile())], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.imported', 6)
            ->assertJsonPath('data.sessions', 3);
    }

    private function names(): array
    {
        $index = json_decode(Storage::disk('results_static')->get('exams/index.json'), true);
        return json_decode(Storage::disk('results_static')->get($index[0]['path'] . '/names.json'), true);
    }

    public function test_exam_crud(): void
    {
        $id = $this->postJson('/api/admin/exams', ['title' => 'مسابقة انتقاء التراجمة المحلفين', 'slug' => 'translators-2026', 'instructions' => 'إحضار الهوية'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.results_published', false)
            ->json('data.id');

        $this->putJson("/api/admin/exams/{$id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.instructions', 'إحضار الهوية');

        $this->getJson('/api/admin/exams')->assertOk()->assertJsonPath('data.total', 1);
        $this->deleteJson("/api/admin/exams/{$id}")->assertOk();
        $this->assertNull(Exam::find($id));
    }

    public function test_import_preview_reads_all_sheets_and_tables(): void
    {
        $exam = $this->exam();

        $this->post("/api/admin/exams/{$exam->id}/imports/preview", ['file' => $this->upload($this->examineesFile())], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.valid_count', 6)
            ->assertJsonPath('data.placeholders', 2)
            ->assertJsonPath('data.has_results', false)
            ->assertJsonPath('data.summary.0', ['session' => 'إنكليزية', 'total' => 4, 'passed' => 0, 'failed' => 0, 'absent' => 0]);

        $this->assertSame(0, $exam->examinees()->count());
    }

    public function test_import_creates_sessions_and_keeps_private_notes_private(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);

        $this->assertSame(['إنكليزية', 'الهولندية', 'تركية قديم'], $exam->sessions()->pluck('name')->all());

        $noted = Examinee::where('full_name', 'محمد رشيد حسن')->first();
        $this->assertSame('الطابق الأرضي بسبب الإعاقة', $noted->admin_note);
        $this->assertSame('88 تمديد', Examinee::where('full_name', 'رنا محمد ورده')->value('file_number'));

        // ملاحظة كُتبت مكان رقم الملف: تصبح ملاحظة داخلية ولا تُنشر كرقم ملف
        $misplaced = Examinee::where('full_name', 'أحمد علي الخطيب')->first();
        $this->assertNull($misplaced->file_number);
        $this->assertSame('رقم الملف: خطأ سجلت بالانكليزي برقم 1203', $misplaced->admin_note);

        // الملاحظة الداخلية لا تظهر في الملفات العامة
        $public = Storage::disk('results_static')->get(json_decode(Storage::disk('results_static')->get('exams/index.json'), true)[0]['path'] . '/names.json');
        $this->assertStringNotContainsString('الإعاقة', $public);
        $this->assertStringNotContainsString('سجلت', $public);
        $this->assertStringContainsString('محمد رشيد حسن', $public);
    }

    public function test_schedule_and_halls_appear_in_public_data(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);
        $session = $exam->sessions()->where('name', 'إنكليزية')->first();

        $this->putJson("/api/admin/exam-sessions/{$session->id}", [
            'exam_date' => '2026-11-15', 'start_time' => '09:00', 'end_time' => '11:00', 'location' => 'كلية الآداب — جامعة دمشق',
        ])->assertOk()->assertJsonPath('data.location', 'كلية الآداب — جامعة دمشق');

        // توزيع تلقائي: 35 في القاعة، أعلى رقم 40 → قاعتان
        $this->putJson("/api/admin/exam-sessions/{$session->id}/halls", ['capacity' => 35])
            ->assertOk()
            ->assertJsonCount(2, 'data.halls')
            ->assertJsonPath('data.halls.0', fn ($h) => $h['name'] === 'القاعة 1' && $h['from_number'] === 1 && $h['to_number'] === 35)
            ->assertJsonPath('data.halls.1.to_number', 40);

        // قاعة خاصة لمتقدم (الطابق الأرضي) تتجاوز التوزيع
        $special = Examinee::where('full_name', 'محمد رشيد حسن')->first();
        $this->putJson("/api/admin/examinees/{$special->id}", ['hall_name' => 'القاعة الأرضية'])
            ->assertOk()
            ->assertJsonPath('data.hall', 'القاعة الأرضية')
            ->assertJsonPath('data.admin_note', 'الطابق الأرضي بسبب الإعاقة');

        $names = $this->names();
        $english = collect($names['sessions'])->search(fn ($s) => $s['name'] === 'إنكليزية');
        $this->assertSame(['name' => 'إنكليزية', 'date' => '2026-11-15', 'start' => '09:00', 'end' => '11:00', 'location' => 'كلية الآداب — جامعة دمشق', 'notes' => null], $names['sessions'][$english]);

        $rows = collect($names['rows'])->keyBy(0);
        $this->assertSame(['دليل عبد المناف سعدو', $english, 1, '272', 'القاعة 1'], $rows['دليل عبد المناف سعدو']);
        $this->assertSame('القاعة 2', $rows['أحمد علي الخطيب'][4]);
        $this->assertSame('القاعة الأرضية', $rows['محمد رشيد حسن'][4]);
        $this->assertCount(5, $rows['دليل عبد المناف سعدو']);   // لا نتائج قبل نشرها

        // نسخ الموعد لكل اللغات
        $this->postJson("/api/admin/exam-sessions/{$session->id}/copy-to-all")->assertOk();
        $this->assertSame(3, $exam->sessions()->where('location', 'كلية الآداب — جامعة دمشق')->count());

        // مجالات متداخلة مرفوضة
        $this->putJson("/api/admin/exam-sessions/{$session->id}/halls", ['halls' => [
            ['name' => 'أ', 'from_number' => 1, 'to_number' => 20],
            ['name' => 'ب', 'from_number' => 15, 'to_number' => 40],
        ]])->assertStatus(422);
    }

    public function test_results_are_public_only_after_publishing_them(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);

        $this->post("/api/admin/exams/{$exam->id}/imports", ['file' => $this->upload($this->resultsFile()), 'type' => 'results'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.updated', 4)
            ->assertJsonPath('data.unmatched_count', 1);

        $this->assertSame('passed', Examinee::where('full_name', 'دليل عبد المناف سعدو')->first()->result->value);
        $this->assertSame('failed', Examinee::where('full_name', 'عمر حسين الرجا')->first()->result->value);   // «غير مقبول» ليست «مقبول»

        // النتائج مسجلة لكنها غير منشورة بعد
        $this->assertCount(5, $this->names()['rows'][0]);
        $this->assertStringNotContainsString('passed', json_encode($this->names()));

        $this->getJson("/api/admin/exams/{$exam->id}/stats")
            ->assertOk()
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.passed', 1)
            ->assertJsonPath('data.failed', 2)
            ->assertJsonPath('data.absent', 1)
            ->assertJsonPath('data.pending', 2);

        $this->putJson("/api/admin/exams/{$exam->id}", ['results_published' => true])->assertOk()->assertJsonPath('data.results_published', true);

        $index = json_decode(Storage::disk('results_static')->get('exams/index.json'), true);
        $this->assertTrue($index[0]['resultsPublished']);

        $rows = collect($this->names()['rows'])->keyBy(0);
        $this->assertSame(['passed', 82, null], array_slice($rows['دليل عبد المناف سعدو'], 5));
        $this->assertSame(['absent', null, null], array_slice($rows['محمد رشيد حسن'], 5));
        $this->assertSame([null, null, null], array_slice($rows['أحمد علي الخطيب'], 5));
    }

    public function test_results_template_round_trip(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);
        Examinee::where('full_name', 'رنا محمد ورده')->first()->update(['result' => 'failed', 'score' => 30]);

        // زر «تحميل القالب»
        $this->get("/api/admin/exams/{$exam->id}/templates/results")->assertOk()->assertDownload('results-template-translators.xlsx');
        $this->get("/api/admin/exams/{$exam->id}/templates/examinees")->assertOk()->assertDownload('examinees-template-translators.xlsx');
        $this->get("/api/admin/exams/{$exam->id}/templates/other")->assertNotFound();

        // القالب يحتوي كل المتقدمين ويُقرأ كما هو (ورقة التعليمات تُتجاهل)
        $template = app(\App\Http\Services\ExamTemplateService::class)->results($exam);
        $read = app(\App\Interfaces\IExamSheetReader::class)->read($template);
        $this->assertCount(6, $read->rows);
        $this->assertSame([], $read->errors);
        $this->assertTrue($read->has_results);
        $this->assertSame(['إنكليزية', 'الهولندية', 'تركية قديم'], collect($read->rows)->pluck('session')->unique()->values()->all());
        $rana = collect($read->rows)->firstWhere('full_name', 'رنا محمد ورده');
        $this->assertSame(['failed', 30.0], [$rana['result'], $rana['score']]);   // نتيجة مدخلة سابقاً تبقى في القالب

        // الموظف يملأ عمودي العلامة والنتيجة ثم يرفع الملف نفسه
        $filled = tempnam(sys_get_temp_dir(), 'filled') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($filled);
        $writer->addRow(Row::fromValues(\App\Http\Services\ExamTemplateService::HEADERS_RESULTS));
        foreach ($read->rows as $row) {
            $pass = $row['exam_number'] === 1;
            $writer->addRow(Row::fromValues([$row['exam_number'], $row['file_number'] ?? '', $row['full_name'], $row['session'], $pass ? 75 : '', $pass ? 'ناجح' : '']));
        }
        $writer->close();

        $this->post("/api/admin/exams/{$exam->id}/imports", ['file' => $this->upload($filled), 'type' => 'results'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.updated', 3)          // الرقم 1 في ثلاث لغات
            ->assertJsonPath('data.unmatched_count', 0);

        $this->assertSame(3, $exam->examinees()->where('result', 'passed')->count());
        $this->assertSame('failed', Examinee::where('full_name', 'رنا محمد ورده')->first()->result->value);   // سطر فارغ لا يمسح نتيجة سابقة

        // قالب الأسماء الفارغ: عناوين صحيحة بلا بيانات
        $this->expectException(\App\Exceptions\ErrorMsgException::class);
        app(\App\Interfaces\IExamSheetReader::class)->read(app(\App\Http\Services\ExamTemplateService::class)->examinees());
    }

    public function test_examinees_listing_search_and_filters(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);
        $url = "/api/admin/exams/{$exam->id}/examinees";
        $english = $exam->sessions()->where('name', 'إنكليزية')->value('id');

        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 6);
        $this->getJson($url . '?' . http_build_query(['search-key' => 'احمد الخطيب']))->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.full_name', 'أحمد علي الخطيب');
        $this->getJson($url . '?' . http_build_query(['search-key' => '272']))->assertJsonPath('data.total', 1);   // رقم الملف
        $this->getJson($url . '?' . http_build_query(['filters' => ['examinee' => ['exam_session_id' => $english]]]))->assertJsonPath('data.total', 4);
        $this->getJson($url . '?' . http_build_query(['filters' => ['examinee' => ['result' => 'none']]]))->assertJsonPath('data.total', 6);
        $this->getJson("{$url}/options")->assertOk()->assertJsonCount(3, 'data.sessions');

        $id = $this->postJson($url, ['full_name' => 'متقدم جديد', 'exam_session_id' => $english, 'exam_number' => 50])->assertCreated()->json('data.id');
        $this->putJson("/api/admin/examinees/{$id}", ['result' => 'passed', 'score' => 90])->assertOk()->assertJsonPath('data.result_label', 'ناجح');
        $this->deleteJson("/api/admin/examinees/{$id}")->assertOk();

        // جلسة من امتحان آخر مرفوضة
        $other = $this->exam(['slug' => 'other'])->sessions()->create(['name' => 'x']);
        $this->postJson($url, ['full_name' => 'x', 'exam_session_id' => $other->id, 'exam_number' => 1])->assertStatus(422);
    }

    public function test_unpublished_exam_is_removed_from_public_files(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);
        $this->assertCount(1, json_decode(Storage::disk('results_static')->get('exams/index.json'), true));

        $this->putJson("/api/admin/exams/{$exam->id}", ['status' => 'draft'])->assertOk();

        $this->assertSame([], json_decode(Storage::disk('results_static')->get('exams/index.json'), true));
        Storage::disk('results_static')->assertMissing('exams/translators');
    }

    public function test_replace_import_keeps_schedule(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);
        $session = $exam->sessions()->where('name', 'إنكليزية')->first();
        $session->update(['location' => 'المكان']);

        $this->importExaminees($exam);   // استبدال

        $this->assertSame(6, $exam->examinees()->count());
        $this->assertSame('المكان', $session->fresh()->location);
    }

    public function test_upsert_import_updates_existing_and_adds_new(): void
    {
        $exam = $this->exam();
        $this->importExaminees($exam);
        $url = "/api/admin/exams/{$exam->id}/imports";

        // بيانات مُدخلة من اللوحة يجب أن تبقى
        $english = $exam->sessions()->where('name', 'إنكليزية')->first();
        $rana = $exam->examinees()->where('full_name', 'رنا محمد ورده')->first();
        $rana->update(['result' => 'passed', 'score' => 80, 'hall_name' => 'قاعة خاصة']);
        $note = $exam->examinees()->where('exam_number', 3)->where('exam_session_id', $english->id)->value('admin_note');
        $this->assertNotNull($note);

        $path = tempnam(sys_get_temp_dir(), 'ups') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['الرقم الامتحاني', 'رقم ملف المتسابق', 'الاسم الثلاثي', 'اللغة']));
        $writer->addRow(Row::fromValues([1, 272, 'دليل عبد المناف سعدو', 'إنكليزية']));     // بلا تغيير
        $writer->addRow(Row::fromValues([7, 88, 'رنا محمد ورده', 'إنكليزية']));             // نفس الاسم: رقم امتحاني جديد
        $writer->addRow(Row::fromValues([3, 10, 'محمد رشيد الحسن', 'إنكليزية']));           // نفس الرقم: تصحيح الاسم
        $writer->addRow(Row::fromValues([50, 900, 'متقدم جديد', 'إنكليزية']));              // جديد
        $writer->addRow(Row::fromValues([1, 5, 'شخص آخر بالفرنسية', 'فرنسية']));            // لغة جديدة
        $writer->close();

        $this->post("{$url}/preview", ['file' => $this->upload($path), 'mode' => 'upsert'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.existing_count', 3)
            ->assertJsonPath('data.new_count', 2);

        $this->post($url, ['file' => $this->upload($path), 'mode' => 'upsert'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.unchanged', 1);

        $this->assertSame(8, $exam->examinees()->count());   // 6 + 2، ولم يُحذف أحد

        $rana->refresh();
        $this->assertSame(7, $rana->exam_number);
        $this->assertSame('88', $rana->file_number);
        $this->assertSame('passed', $rana->result->value ?? $rana->result);
        $this->assertSame(80.0, $rana->score);
        $this->assertSame('قاعة خاصة', $rana->hall_name);

        $third = $exam->examinees()->where('exam_number', 3)->where('exam_session_id', $english->id)->first();
        $this->assertSame('محمد رشيد الحسن', $third->full_name);
        $this->assertSame($note, $third->admin_note);   // الخانة الفارغة لا تمسح الملاحظة

        // إعادة رفع نفس الملف لا تغيّر شيئاً
        $this->post($url, ['file' => $this->upload($path), 'mode' => 'upsert'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.updated', 0);
        $this->assertSame(8, $exam->examinees()->count());
    }

    private function fileWithHalls(array $halls): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hal') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('انكليزي');
        $writer->addRow(Row::fromValues(['الرقم الامتحاني', 'رقم ملف المتسابق', 'الاسم الثلاثي', 'اللغة']));
        foreach ([1 => 'أحمد علي حسن', 2 => 'سارة محمد يوسف', 3 => 'خالد سمير ديب', 4 => 'ريم عادل نصر', 5 => 'هبة فادي سعيد'] as $number => $name)
        {
            $writer->addRow(Row::fromValues([$number, $number + 100, $name, 'إنكليزية']));
        }
        $writer->addNewSheetAndMakeItCurrent()->setName('توزيع القاعات');
        $writer->addRow(Row::fromValues(['توزيع القاعات']));
        $writer->addRow(Row::fromValues(['القاعة ', 'البناء', 'الرقم ']));
        foreach ($halls as $hall)
        {
            $writer->addRow(Row::fromValues($hall));
        }
        $writer->addNewSheetAndMakeItCurrent()->setName('مجموع المتقدمين');
        $writer->addRow(Row::fromValues(['اللغة', 'عدد المتقدمين']));
        $writer->addRow(Row::fromValues(['اللغة الإنكليزية', 5]));
        $writer->close();
        return $path;
    }

    public function test_halls_table_in_the_file_is_previewed_and_applied(): void
    {
        $exam = $this->exam();
        $url = "/api/admin/exams/{$exam->id}/imports";
        $file = $this->fileWithHalls([
            ['المدرج الأول ', 'بناء المدرجات', '1-2'],
            ['القاعة A', 'بناء الإدارة', '4-9'],     // الرقم 3 بلا قاعة
        ]);

        $this->post("{$url}/preview", ['file' => $this->upload($file)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.valid_count', 5)
            ->assertJsonCount(2, 'data.halls')
            ->assertJsonPath('data.halls.0.name', 'المدرج الأول')
            ->assertJsonPath('data.halls.0.building', 'بناء المدرجات')
            ->assertJsonPath('data.halls.1.from_number', 4)
            ->assertJsonPath('data.halls_coverage.إنكليزية.uncovered_count', 1)
            ->assertJsonPath('data.halls_coverage.إنكليزية.uncovered.0', [3, 3]);

        // بدون اختيار لغة: الأسماء تُستورد والقاعات لا
        $this->post($url, ['file' => $this->upload($file), 'mode' => 'upsert'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.halls', 0);
        $session = $exam->sessions()->where('name', 'إنكليزية')->first();
        $this->assertSame(0, $session->halls()->count());

        $this->post($url, ['file' => $this->upload($file), 'mode' => 'upsert', 'halls_session' => 'إنكليزية'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.halls', 2);
        $this->assertSame(['المدرج الأول', 'القاعة A'], $session->halls()->orderBy('from_number')->pluck('name')->all());
        $this->assertSame(['بناء المدرجات', 'بناء الإدارة'], $session->halls()->orderBy('from_number')->pluck('building')->all());

        // المتقدم يرى القاعة والبناء كلاً بسطر: [القاعة، البناء]
        $rows = collect($this->names()['rows'])->keyBy(2);
        $this->assertSame(['المدرج الأول', 'بناء المدرجات'], $rows[1][4]);
        $this->assertSame(['القاعة A', 'بناء الإدارة'], $rows[4][4]);
        $this->assertNull($rows[3][4]);
        $this->assertSame('المدرج الأول — بناء المدرجات', $session->fresh()->hallFor(1));

        // مجالات متداخلة: يُرفض الاستيراد كله ولا يتغير شيء
        $bad = $this->fileWithHalls([['قاعة 1', '', '1-3'], ['قاعة 2', '', '3-5']]);
        $this->post($url, ['file' => $this->upload($bad), 'mode' => 'upsert', 'halls_session' => 'إنكليزية'], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertSame(2, $session->halls()->count());
    }
}
