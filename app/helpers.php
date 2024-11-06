<?php


use Illuminate\Support\Facades\App;
use Carbon\Carbon;

if(!function_exists('transMsg'))
{
    /**
     * @var string $translateString the string we want to translate it
     * @var array $parameters is the parameter you want to send to trans msg
     * (start with upper case letter and the rest chars lower case)
     * @return string
     */
    function transMsg($translateString , array $parameters = [])
    {
        return trans('messages.' . $translateString, $parameters);

    }
}

if(!function_exists('transResponse'))
{
    /**
     * @var string $translateString the string we want to translate it
     * @var array $parameters is the parameter you want to send to trans msg
     * (start with upper case letter and the rest chars lower case)
     * @return string
     */
    function transResponse($translateString, array $parameters = [])
    {
        return trans('response.' . $translateString, $parameters);

    }
}

if(!function_exists('throwDeployError'))
{
    /**
     * This method just for debug
     */
    function throwDeployError($object): string
    {
        throw new \App\Exceptions\ErrorMsgException('فريق الدعم يقوم بحل المشكلة حالياً ' . json_encode($object));
    }
}

if(!function_exists('throwError'))
{
    /**
     * This method just for debug
     */
    function throwError($object): string
    {
        throw new \App\Exceptions\ErrorMsgException(json_encode($object));
    }
}


if(!function_exists('changeLang'))
{
    function changeLang(string $lang='en') : void
    {
        App::setLocale($lang);
    }
}

if (!function_exists('snakeCase'))
{
    /**
     * Convert a string to snake case.
     *
     * @param  string  $value
     * @return string
     */
    function snakeCase($value)
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $value));
        }
        return $value;
    }
}

if (!function_exists('normalCase'))
{
    /**
     * Convert a string to normal case.
     *
     * @param  string  $value
     * @return string
     */
    function normalCase($value)
    {
        $value = snakeCase($value);
        $value = str_replace('_', ' ', $value);
        return ucfirst($value);
    }
}

if (!function_exists('kebabCase'))
{
    /**
     * Convert a string to kebab case.
     *
     * @param  string  $value
     * @return string
     */
    function kebabCase($value)
    {
        $value = snakeCase($value);
        $value = str_replace('_', '-', $value);
        return $value;
    }
}

if (!function_exists('pascalCase'))
{
    /**
     * Convert a string to Pascal case.
     *
     * @param  string  $value
     * @return string
     */
    function pascalCase($value)
    {
        $value = snakeCase($value);
        $value = str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));

        return $value;
    }
}

if (!function_exists('camelCase'))
{
    /**
     * Convert a string to camel case.
     *
     * @param  string  $value
     * @return string
     */
    function camelCase($value)
    {
        $value = pascalCase($value);
        $value = lcfirst($value);

        return $value;
    }
}
if (!function_exists('pascalCaseWithSpaces')) {
    /**
     * Convert a snake_case string to Pascal case with spaces.
     *
     * @param string $value
     * @return string
     */
    function pascalCaseWithSpaces($value)
    {
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', pascalCase($value));
    }
}

if (!function_exists('roundToNearestPowerOfTen'))
{

    function roundToNearestPowerOfTen($number)
    {
        return pow(10, strlen((string)$number));
    }
}

if (!function_exists('paginateCollection')) {
    /**
     * Paginate a collection.
     *
     * @param  Illuminate\Support\Collection|Illuminate\Http\Resources\Json\AnonymousResourceCollection  $collection
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options
     * @return Illuminate\Pagination\LengthAwarePaginator
     */
    function paginateCollection($collection, $perPage, $currentPage = null, $options = [])
    {

        $currentPage = $currentPage ?: \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $offset = ($currentPage - 1) * $perPage;

        // Manually slice the items for the current page
        $currentPageItems = array_slice($collection->all(), $offset, $perPage);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $collection->count(),
            $perPage,
            $currentPage,
            $options
        );
    }
}


if (!function_exists('number_length')) {
    /**
     * Get the length of a number.
     *
     * @param int $number The number to get the length of.
     * @return int The length of the number.
     */
    function number_length($number)
    {
        return strlen((string) $number);
    }
}

if (!function_exists('calculateDistance')) {
    /**
     * Get the distance between two points
     */
    function calculateDistance($point1, $point2)
    {
        // Convert latitude and longitude from degrees to radians
        $lat1 = deg2rad($point1[0]);
        $lon1 = deg2rad($point1[1]);
        $lat2 = deg2rad($point2[0]);
        $lon2 = deg2rad($point2[1]);

        // Haversine formula
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1) * cos($lat2) * sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = 6371 * $c; // Radius of the Earth in kilometers

        return $distance;
    }

}

if (!function_exists('getDuration')) {
    /**
     * Get the duration between two dates
     */
    function getDuration($startDate, $endDate)
    {
        // Create Carbon instances from the timestamp values
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Calculate the duration
        $duration = $end->diff($start);

        // Format the duration as desired
        $formattedDuration = $duration->format('%d days, %h hours, %i minutes, %s seconds');

        // Return the formatted duration
        return $formattedDuration;
    }

}

if (!function_exists('calcPercentage')) {
    /**
     * Get the percentage for numerator and denominator
     */
    function calcPercentage($numerator, $denominator)
    {
        return percent($numerator, $denominator, 100);
    }
}

if (!function_exists('percent')) {
    /**
     * Get the percent for numerator and denominator
     */
    function percent($numerator, $denominator, $newDenominator = 100)
    {
        return $denominator > 0 ? ($numerator / $denominator) * $newDenominator : 0;
    }

}

if (!function_exists('random_color')) {
    /**
     * Get the percent for numerator and denominator
     */
    function random_color()
    {
        return '#'.random_color_part().random_color_part().random_color_part();
    }

}
if (!function_exists('random_color_part')) {
    /**
     * Get the percent for numerator and denominator
     */
    function random_color_part()
    {
        return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
    }

}

if (!function_exists('usesTrait')) {
    /**
     * Check if a given class or object uses a specific trait.
     *
     * @param string|object $class The class name or object to check.
     * @param string $trait The trait name to check for.
     * @return bool
     */
    function usesTrait($class, string $trait): bool
    {
        return in_array($trait, class_uses($class));
    }
}


