<?php

namespace App\Http\Services;

use App\Http\Repositories\CandidateRepo;
use App\Models\Candidate;
use App\Models\Competition;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exports the (filtered) candidates of a competition to an .xlsx file.
 */
class CandidatesExportService
{
    public function __construct(
        protected CandidateRepo $candidateRepo,
    ) {}

    /**
     * @return string path of the generated temp file
     */
    public function export(Competition $competition): string
    {
        $path = tempnam(sys_get_temp_dir(), 'candidates') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setSheetView((new SheetView())->withRightToLeft(true));

        $writer->addRow(Row::fromValuesWithStyle([
            'الترتيب', 'الاسم الكامل', 'المحافظة', 'المسمى الوظيفي', 'تاريخ المقابلة',
            'العلامة', 'النتيجة', 'المتابعة', 'ملاحظات',
        ], (new Style())->withFontBold(true)));

        foreach ($this->candidateRepo->forCompetition($competition)->limit(null)->cursor() as $c)
        {
            /** @var Candidate $c */
            $writer->addRow(Row::fromValues([
                $c->rank,
                $c->full_name,
                $c->governorate,
                $c->job_title,
                $c->interview_date?->format('Y-m-d') ?? '',
                $c->score ?? '',
                $c->status->label(),
                $c->follow_up_status->label(),
                $c->notes ?? '',
            ]));
        }

        $writer->close();

        return $path;
    }
}
