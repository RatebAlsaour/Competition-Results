<?php

namespace App\Http\Repositories;

use App\Models\AuthModel;
use App\Models\Model;
use App\Models\Pivot;
use App\Traits\HasCrudOperations;
use App\Traits\HasFilterable;
use App\Traits\HasOrderable;
use App\Traits\HasSearchable;

class BaseRepo
{
    use HasCrudOperations, HasSearchable, HasFilterable, HasOrderable;

    /**
     * Constructor to initialize the model and optionally the object data class.
     *
     * @param Model $model The model instance for the repository.
     * @param string|null $objectDataClass Optional data class for data transformation.
     */
    public function __construct(protected Model|Pivot|AuthModel $model, protected ?string $objectDataClass = null)
    {
    }

    /**
     * Magic method to handle calls to undefined methods.
     *
     * This allows for method delegation to the underlying model instance.
     *
     * @param string $method The name of the method being called.
     * @param array $args The arguments passed to the method.
     * @return mixed The result of the called method on the model.
     */
    public function __call($method, $args)
    {
        return $this->model->$method(...$args);
    }

    /**
     * Retrieves the class path of the model.
     *
     * @return string The class name of the model.
     */
    public function getModelPath()
    {
        return get_class($this->model); // Return the model's fully qualified class name
    }

       /**
     * Applies a global scope to the model's query.
     *
     * @param string $scope The name of the global scope to apply.
     */
    public function applyScope(string $scope)
    {
        $this->model->addGlobalScope(new $scope());
    }

    /**
     * Ignores a specified global scope for the model's query.
     *
     * @param string $scope The name of the global scope to ignore.
     */
    public function igonreScope(string $scope)
    {
        $this->model->query()->withoutGlobalScope($scope);
    }
}
