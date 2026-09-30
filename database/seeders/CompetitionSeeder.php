<?php

namespace Database\Seeders;

use App\Models\Competition;
use Illuminate\Database\Seeder;

class CompetitionSeeder extends Seeder
{
    /**
     * The 2026 recruitment competition (draft; publish it from the dashboard after importing results).
     */
    public function run(): void
    {
        Competition::query()->firstOrCreate(['slug' => 'recruitment-2026'], [
            'title'              => 'مسابقة التوظيف 2026',
            'description'        => 'يمكنكم الاستعلام عن أسماء المقبولين في مسابقة التوظيف من خلال اختيار المحافظة والمسمى الوظيفي.',
            'status'             => 'draft',
            'ranking_method'     => 'score',
            'primary_note'       => 'يراجع الناجح الأساسي العدلية المتقدم اليها خلال مدة أقصاها اسبوع',
            'required_documents' => [
                ['title' => 'غير محكوم', 'notes' => []],
                ['title' => 'غير موظف', 'notes' => []],
                ['title' => 'نسخة مصدقة من المؤهل العلمي', 'notes' => []],
                ['title' => 'صورة عن الهوية الشخصية', 'notes' => []],
                ['title' => 'صورة عن البيان العائلي', 'notes' => ['في حال كان الموظف متزوجاً']],
                ['title' => 'شهادة صحية', 'notes' => ['لجنة فحص العاملين', 'يتطلب تحويلة من العدلية']],
                ['title' => 'ترقين قيد من نقابة المحامين', 'notes' => ['لحملة الاجازة في الحقوق']],
                ['title' => 'صورة شخصية عدد 4', 'notes' => []],
            ],
        ]);
    }
}
