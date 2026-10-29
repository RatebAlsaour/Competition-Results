<?php

namespace App\Interfaces;

use App\Http\DTOs\ExamSheetReadResultData;

interface IExamSheetReader
{
    /**
     * Read examinees (and results when present) from every sheet of the file.
     * Each row: session, exam_number, file_number, full_name, admin_note, result, score, source.
     *
     * @throws \App\Exceptions\ErrorMsgException when the file can't be read or has no examinees table.
     */
    public function read(string $path): ExamSheetReadResultData;
}
