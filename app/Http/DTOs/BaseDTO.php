<?php


namespace App\Http\DTOs;

use App\Exceptions\ErrorMsgException;
use App\Traits\HasArgsParams;
use Spatie\DataTransferObject\DataTransferObject;

class BaseDTO extends DataTransferObject
{
    use HasArgsParams;

    /**
     *
     * @param array-key of strings
     * @return static
     * @throws \App\Exceptions\ErrorMsgException
     *
     */
    public function merge(array $fields) : static
    {
        foreach ($fields as $fieldName =>$field)
        {
            if($this->isPropertyExists($fieldName))
            {
                $this->{$fieldName} = $field;
            }
        }
        return $this;
    }

    /**
     *
     * Check if the property @param string $name is exists in the class variables
     * @return true
     * else @throws \App\Exceptions\ErrorMsgException
     *
     */
    public function isPropertyExists(string $name): bool
    {
        if(!property_exists($this, $name))
        {
            throw new ErrorMsgException(
                'You trying to sign to field doesnt exist in the '.get_class($this)
            );
        }
        return true;
    }
}
