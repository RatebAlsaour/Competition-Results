<?php

namespace App\Traits;

trait HasNullable
{

    /**
     * Get attributes which should keep it null is passed null value in update event
     *
     * @return array
     */
    public function getNullableAttributes(): array
    {
        return $this->nullable;
    }

    /**
     * Check if @param string $column isset in model nullable attributes or not
     *
     * @return bool
     */
    public function isNullableAttribute(string $column): bool
    {
        return in_array($column, $this->getNullableAttributes());
    }
}
