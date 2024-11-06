<?php

namespace App\Traits;

use App\Enum\LoadableRelationsEnum;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

trait HasRelationLoader
{
    /**
     * Load relations based on the 'include' query parameter (array format),
     * with validation and optional logging.
     *
     * @return $this
     * @throws InvalidArgumentException if validation is enabled and no valid relations are found
     */
    public function loadRelationsFromRequest()
    {
        if(config('relations.loading_relatoins_enabled', true))
        {
            $this->loadMissing($this->getRelationsShouldLoaded());
        }
        return $this;
    }

    /**
     * Load relations within query based on the 'include' query parameter (array format),
     * with validation and optional logging.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws InvalidArgumentException if validation is enabled and no valid relations are found
     */
    public function scopeWithRelationsFromRequest($query)
    {
        if(config('relations.loading_relatoins_enabled', true))
        {
            $query->with($this->getRelationsShouldLoaded());
        }
        return $query;
    }

    /**
     * get relations based on the 'include' query parameter (array format),
     * with validation and optional logging.
     *
     * @return array
     */
    protected function getRelationsShouldLoaded(): array
    {
        // Retrieve 'include' from the query parameters as an array
        $relations = request()->query(config('relations.load_realtion_request_key', 'include'), []);

        // Check if 'include' is empty or not set
        if (empty($relations) || !is_array($relations)) {
            return []; // Do not load anything
        }
        else
        {
            // Filter and map relations to include only those that are defined as relationships on the model,
            // and get their respective relation names
            $validatedRelations = array_map(function ($relationKey) {
                $relation = LoadableRelationsEnum::from($relationKey);

                // Check if the relation method exists on the model
                return method_exists($this, $relation->relationName()) ? $relation->relationName() : null;
            }, $relations);

            // Filter out any null values in case any relation methods don't exist
            $validatedRelations = array_filter($validatedRelations);

            if (empty($validatedRelations)) {
                Log::warning("No valid relations to load on model " . static::class);
                return [];
            }

            // Load only the validated relations
            return [$validatedRelations];
        }
    }
}
