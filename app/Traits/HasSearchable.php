<?php

namespace App\Traits;

trait HasSearchable
{
    use SearchingFunctions;

    /**
     * Array containing fields that can be searched within
     * You can pass relations fields by writing "." between each realtion (ex: student.profile.first_name)
     */
    public $searchable = [];
}
