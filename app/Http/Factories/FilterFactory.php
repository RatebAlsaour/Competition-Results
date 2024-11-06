<?php

namespace App\Http\Factories;

use App\Exceptions\InvalidFilterClassException;
use App\Exceptions\InvalidFilterKeyException;
use App\Exceptions\MissingFiltersKeysPropertyException;
use App\Http\Filters\Filter;

class FilterFactory
{
    /**
     *  Create method for filter facotry
     *  @param string $filterKey
     *  @param array $fitlerData
     *  @param App\Http\Repositories\BaseRepo $repository
     *  @return Filter
     *  @throws InvalidFilterKeyException
     *  @throws InvalidFilterClassException
     *  @throws MissingFiltersKeysMethodException
     */
    public static function create($filterKey, $filterData, $repository): Filter
    {
        if (property_exists($repository, 'filtersKeys'))
        {
            $paths = $repository->filtersKeys;

            if(!key_exists($filterKey, $paths))
            {
                throw new InvalidFilterKeyException($filterKey);
            }

            $filterClassPath = $paths[$filterKey];
            if(class_exists($filterClassPath))
            {
                return new $filterClassPath($filterData);
            }

            throw new InvalidFilterClassException($filterClassPath);
        }
        throw new MissingFiltersKeysPropertyException($repository);
    }
}
