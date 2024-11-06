<?php

namespace App\Observers;

use App\Interfaces\IHasNullable;
use App\Models\AuthModel;
use App\Models\Pivot;
use App\Models\Model;

class ModelObserver
{
    public function updating(Model|AuthModel|Pivot $model)
    {
        foreach ($model->getAttributes() as $attributeName => $attributeValue)
        {
            if ($attributeValue === null)
            {
                if($model instanceof IHasNullable && $model->isNullableAttribute($attributeName))
                {
                    continue;
                }
                $model->$attributeName = $model->getOriginal($attributeName);
            }
        }
    }

    public function creating(Model|AuthModel|Pivot $model)
    {
        foreach ($model->getAttributes() as $attributeName => $attributeValue)
        {
            if ($attributeValue === null)
            {
                if($model instanceof IHasNullable && $model->isNullableAttribute($attributeName))
                {
                    continue;
                }
                unset($model->$attributeName);
            }
        }

    }
}
