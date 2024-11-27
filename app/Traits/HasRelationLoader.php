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

        // Initialize an array to store the relations to load
        $relationsToLoad = [];

        foreach ($relations as $relationKey) {
            try {
                $relation = LoadableRelationsEnum::from($relationKey);
                $relationParts = $relation->relationName();
            } catch (\ValueError $e) {
                // Skip if the relation is invalid (not part of the enum)
                continue;
            }


            // Split the relation key by '.' to support nested relationships
            $relationsNames = explode('.', $relationParts);

            // Get the base relation name (the first part)
            $relationName = $relationsNames[0];


            // Check if the relation method exists on the model
            if (method_exists($this, $relationName)) {
                // If there are additional parts (nested relations), add them
                if (count($relationsNames) > 1) {
                    // Add the nested relations to the base relation name
                    $relationName .= '.' . implode('.', array_slice($relationsNames, 1));
                }

                // Add to the relations to load
                $relationsToLoad[] = $relationName;
            }
        }

        // If no valid relations are found, log a warning and return an empty array
        if (empty($relationsToLoad)) {
            Log::warning("No valid relations to load on model " . static::class);
            return [];
        }

        return $relationsToLoad;
    }
}
