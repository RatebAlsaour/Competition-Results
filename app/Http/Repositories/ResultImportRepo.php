<?php

namespace App\Http\Repositories;

use App\Models\Competition;
use App\Models\ResultImport;

class ResultImportRepo extends BaseRepo
{
    public function __construct()
    {
        parent::__construct(new ResultImport());
    }

    public function forCompetition(Competition $competition)
    {
        return $competition->imports()->with('user:id,name')->latest()->get();
    }
}
