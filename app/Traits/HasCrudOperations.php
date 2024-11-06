<?php

namespace App\Traits;

use App\Enum\FetchDataFunctionsEnum;
use App\Interfaces\IHasDataTransferObjects;
use App\Interfaces\IHasFilterable;
use App\Interfaces\IHasOrderable;
use App\Interfaces\IHasSearchable;
use App\Models\AuthModel;
use App\Models\Model;
use App\Models\Pivot;
use Illuminate\Http\Request;

trait HasCrudOperations
{
    use HasDataTransferObjects;

    /**
     * Retrieves records ( get | all | paginate | first | find )
     * Applying filters and search as needed.
     *
     * @return mixed The ordered collection of model instances.
     */
    public function fetch(?FetchDataFunctionsEnum $fetchFunction = null, $data = null)
    {
        $query = $this->model->query(); // Create a new query instance

        if(config('filter.filter_enabled', true) && $this instanceof IHasFilterable)
        {
            $query = $this->applyFilter($query);
        }

        if(config('search.search_enabled', true) && $this instanceof IHasSearchable)
        {
            $query = $this->applySearch($query);
        }

        if(config('order.order_enabled', true) && $this instanceof IHasOrderable)
        {
            $query = $this->applyOrder($query);
        }

        if($fetchFunction)
        {
            switch(true)
            {
                case $fetchFunction->isMustPassingData():
                    return $query->withRelationsFromRequest()->{$fetchFunction->value}($data);

                case $fetchFunction->hasPagination():
                    $maxRecordsCount = request()->input(config('pagination.request_max_key', 'max'))
                                    ?? request()->input(config('pagination.default_paginate_num', 10));
                    return $query->withRelationsFromRequest()->{$fetchFunction->value}($maxRecordsCount);

                default:
                    return $query->withRelationsFromRequest()->{$fetchFunction->value}();
            }
        }
        return $query;
    }

    /**
     * Stores a new model instance using the provided data.
     *
     * @param mixed $data The data for the new model instance.
     * @return Model|AuthModel|Pivot The newly created model instance.
     */
    public function store(mixed $data): Model|AuthModel|Pivot
    {
        if(config('dto.dto_enabled', true) && $this instanceof IHasDataTransferObjects)
        {
            return $this->create($this->getData($data))->loadRelationsFromRequest(); // Create model with transformed data
        }
        return $this->create($data instanceof Request ? $data->validated() : $data)->loadRelationsFromRequest();
    }

    /**
     * Updates an existing model instance with the provided data.
     *
     * @param mixed $data The data for updating the model.
     * @param Model|Pivot|AuthModel $model The model instance to update.
     * @return bool
     */
    public function update(mixed $data, Model|Pivot|AuthModel &$model): bool
    {
        if(config('dto.dto_enabled', true) && $this instanceof IHasDataTransferObjects)
        {
            $isUpdated = $model->update($this->getData($data)); // Update model with transformed data
            $model->loadRelationsFromRequest();
            return $isUpdated;
        }
        $isUpdated = $model->update($data instanceof Request ? $data->validated() : $data);
        $model->loadRelationsFromRequest();
        return $isUpdated;
    }

    /**
     * Show an existing model instance.
     *
     * @param mixed $data The data for updating the model.
     * @param Model|Pivot|AuthModel $model The model instance to update.
     * @return viud
     */
    public function show(Model|Pivot|AuthModel &$model): void
    {
        $model->loadRelationsFromRequest();
    }
}
