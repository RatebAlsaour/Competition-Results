<?php

namespace App\Interfaces;

use App\Http\DTOs\SheetReadResultData;

interface IResultsSheetReader
{
    /**
     * Read a results sheet into normalized rows.
     * Each row: full_name, governorate, job_title, status, score, interview_date, source_row.
     *
     * @throws \App\Exceptions\ErrorMsgException when the file can't be read or required columns are missing.
     */
    public function read(string $path): SheetReadResultData;
}
