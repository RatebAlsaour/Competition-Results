<?php

namespace App\Interfaces;

interface IHasNullable
{
    /**
     * Get attributes which should keep it null is passed null value in update event
    *
     * @return array
     */
    public function getNullableAttributes();

    /**
     * Check if @param string $column isset in model nullable attributes or not
     *
     * @return bool
     */
    public function isNullableAttribute(string $column);
}
