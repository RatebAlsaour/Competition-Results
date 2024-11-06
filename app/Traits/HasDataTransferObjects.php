<?php

namespace App\Traits;

trait HasDataTransferObjects
{
    /**
     * Retrieves and transforms the request data into an object.
     *
     * @param mixed $data The request instance or data array.
     * @return array The transformed data object.
     */
    public function getData($data): array
    {
        return $this->objectDataClass::fromObject((object) $data)->all();
    }
}
