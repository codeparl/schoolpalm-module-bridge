<?php

namespace SchoolPalm\ModuleBridge\Factories;

use SchoolPalm\ModuleBridge\Contracts\IMC\QueryOptions;

class DataFactoryQueryBridge
{
    protected DataFactoryQuery $query;

    public function __construct(DataFactoryQuery $query)
    {
        $this->query = $query;
    }

    /**
     * ---------------------------------------
     * Execute QueryOptions → DataFactoryQuery
     * $factory = new StudentDataFactory();

     *  $bridge = new DataFactoryQueryBridge(
     *     $factory->query()
     * );

     *  $result = $bridge
     *     ->apply($options)
     *    ->get();
     * ---------------------------------------
     */
    public function apply(QueryOptions $options): DataFactoryQuery
    {
        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */
        if ($options->search) {
            $this->query->search(
                $options->search,
                $options->searchFields
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTERS (WHERE)
        |--------------------------------------------------------------------------
        */
        foreach ($options->filters as $field => $value) {
            $this->query->where($field, $value);
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */
        if ($options->sortBy) {
            $this->query->orderBy(
                $options->sortBy,
                $options->direction
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        if ($options->page || $options->perPage) {

            $offset = ($options->page - 1) * $options->perPage;

            $this->query->offset($offset)
                ->limit($options->perPage);
        }

        return $this->query;
    }
}
