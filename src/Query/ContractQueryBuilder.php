<?php

namespace SchoolPalm\ModuleBridge\Query;

use Closure;
use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Laravel-style query builder for module contracts.
 *
 * This builder is:
 * - NOT database-aware
 * - NOT Eloquent
 * - NOT SQL-based
 *
 * It acts as:
 * - query state container
 * - fluent pipeline
 * - relation hydration coordinator
 * - module query abstraction layer
 */
class ContractQueryBuilder
{
    protected mixed $service;

    protected RelationEngine $relationEngine;

    /*
    |--------------------------------------------------------------------------
    | QUERY STATE
    |--------------------------------------------------------------------------
    */

    protected array $filters = [];

    protected array $relations = [];

    protected array $sort = [];

    protected array $select = [];

    protected array $groupBy = [];

    protected array $having = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        mixed $service,
        RelationEngine $relationEngine
    ) {
        $this->service = $service;
        $this->relationEngine = $relationEngine;
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC ENTRY
    |--------------------------------------------------------------------------
    */

    public static function for(
        mixed $service,
        RelationEngine $engine
    ): self {

        return new self(
            $service,
            $engine
        );
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE CLAUSES
    |--------------------------------------------------------------------------
    */

    public function where(
        string $field,
        mixed $value,
        string $operator = '='
    ): static {

        $this->filters[] = [
            'type' => 'where',
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function orWhere(
        string $field,
        mixed $value,
        string $operator = '='
    ): static {

        $this->filters[] = [
            'type' => 'orWhere',
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function whereIn(
        string $field,
        array $values
    ): static {

        $this->filters[] = [
            'type' => 'whereIn',
            'field' => $field,
            'values' => $values,
        ];

        return $this;
    }

    public function whereNotIn(
        string $field,
        array $values
    ): static {

        $this->filters[] = [
            'type' => 'whereNotIn',
            'field' => $field,
            'values' => $values,
        ];

        return $this;
    }

    public function whereNull(
        string $field
    ): static {

        $this->filters[] = [
            'type' => 'whereNull',
            'field' => $field,
        ];

        return $this;
    }

    public function whereNotNull(
        string $field
    ): static {

        $this->filters[] = [
            'type' => 'whereNotNull',
            'field' => $field,
        ];

        return $this;
    }

    public function whereLike(
        string $field,
        string $value
    ): static {

        $this->filters[] = [
            'type' => 'whereLike',
            'field' => $field,
            'value' => $value,
        ];

        return $this;
    }

    public function whereBetween(
        string $field,
        array $range
    ): static {

        $this->filters[] = [
            'type' => 'whereBetween',
            'field' => $field,
            'range' => $range,
        ];

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function with(...$relations): static
    {
        $this->relations = array_merge(
            $this->relations,
            $this->flatten($relations)
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | SELECTS
    |--------------------------------------------------------------------------
    */

    public function select(...$fields): static
    {
        $this->select = array_merge(
            $this->select,
            $this->flatten($fields)
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | GROUPING
    |--------------------------------------------------------------------------
    */

    public function groupBy(...$fields): static
    {
        $this->groupBy = array_merge(
            $this->groupBy,
            $this->flatten($fields)
        );

        return $this;
    }

    public function having(
        string $field,
        mixed $value,
        string $operator = '='
    ): static {

        $this->having[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | SORTING
    |--------------------------------------------------------------------------
    */

    public function orderBy(
        string $field,
        string $direction = 'asc'
    ): static {

        $this->sort[] = [
            'field' => $field,
            'direction' => strtolower($direction) === 'desc'
                ? 'desc'
                : 'asc',
        ];

        return $this;
    }

    public function latest(
        string $field = 'created_at'
    ): static {

        return $this->orderBy(
            $field,
            'desc'
        );
    }

    public function oldest(
        string $field = 'created_at'
    ): static {

        return $this->orderBy(
            $field,
            'asc'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIMIT / OFFSET
    |--------------------------------------------------------------------------
    */

    public function limit(
        int $limit
    ): static {

        $this->limit = $limit;

        return $this;
    }

    public function take(
        int $limit
    ): static {

        return $this->limit($limit);
    }

    public function offset(
        int $offset
    ): static {

        $this->offset = $offset;

        return $this;
    }

    public function skip(
        int $offset
    ): static {

        return $this->offset($offset);
    }

    /*
    |--------------------------------------------------------------------------
    | CONDITIONAL PIPELINES
    |--------------------------------------------------------------------------
    */

    public function when(
        mixed $condition,
        callable $callback,
        ?callable $default = null
    ): static {

        if ($condition) {

            $callback($this, $condition);
        } elseif ($default) {

            $default($this, $condition);
        }

        return $this;
    }

    public function unless(
        mixed $condition,
        callable $callback
    ): static {

        return $this->when(
            !$condition,
            $callback
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TAP
    |--------------------------------------------------------------------------
    */

    public function tap(
        callable $callback
    ): static {

        $callback($this);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | EXECUTION
    |--------------------------------------------------------------------------
    */

    public function get(): array
    {
        $localRelations = [];
        $globalRelations = [];

        foreach ($this->relations as $relation) {
            if (str_contains($relation, '@')) {
                $globalRelations[] = $relation;
            } else {
                $localRelations[] = $relation;
            }
        }

        /*
     * Local relations are loaded by the underlying query engine/Eloquent.
     *
     * Global relations are loaded later by RelationEngine because they
     * resolve through module contracts.
     */
        $items = $this->service->querySearchWithRelations(
            $this->filters,
            $localRelations
        );

        $items = $this->applyWheres($items);
        $items = $this->applySelects($items);
        $items = $this->applySorting($items);
        $items = $this->applyLimitOffset($items);

        /*
     * Only scoped relations reach RelationEngine.
     *
     * Example:
     * contact@schoolpalm.common.contact
     */
        if (!empty($globalRelations)) {
            $items = $this->relationEngine->load(
                $items,
                $globalRelations
            );
        }

        return $items;
    }

    /**
     * Alias for get().
     */
    public function all(): array
    {
        return $this->get();
    }
    public function first(): mixed
    {
        return $this
            ->limit(1)
            ->get()[0] ?? null;
    }

    public function last(): mixed
    {
        $items = $this->get();

        return end($items) ?: null;
    }

    public function count(): int
    {
        return count(
            $this->service
                ->querySearch($this->filters)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    public function paginate(
        int $page = 1,
        int $perPage = 15
    ): array {

        $all = $this->service
            ->querySearch($this->filters);

        $total = count($all);

        $offset = ($page - 1) * $perPage;

        $data = array_slice(
            $all,
            $offset,
            $perPage
        );

        if (!empty($this->relations)) {

            $data = $this->relationEngine
                ->load(
                    $data,
                    $this->relations
                );
        }

        return [
            'data' => $data,

            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | COLLECTION HELPERS
    |--------------------------------------------------------------------------
    */

    public function pluck(
        string $field
    ): array {

        return array_map(
            fn($item) => is_array($item)
                ? ($item[$field] ?? null)
                : ($item->{$field} ?? null),
            $this->get()
        );
    }

    public function map(
        callable $callback
    ): array {

        return array_map(
            $callback,
            $this->get()
        );
    }

    public function filter(
        ?callable $callback = null
    ): array {

        return array_values(
            array_filter(
                $this->get(),
                $callback
            )
        );
    }

    public function each(
        callable $callback
    ): static {

        foreach ($this->get() as $item) {
            $callback($item);
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL HELPERS
    |--------------------------------------------------------------------------
    */

    protected function applySorting(
        array $items
    ): array {

        foreach (
            array_reverse($this->sort)
            as $sort
        ) {

            usort($items, function ($a, $b) use ($sort) {

                $field = $sort['field'];

                $valA = is_array($a)
                    ? ($a[$field] ?? null)
                    : ($a->{$field} ?? null);

                $valB = is_array($b)
                    ? ($b[$field] ?? null)
                    : ($b->{$field} ?? null);

                if ($valA == $valB) {
                    return 0;
                }

                $result = $valA <=> $valB;

                return $sort['direction'] === 'desc'
                    ? -$result
                    : $result;
            });
        }

        return $items;
    }

    protected function applyLimitOffset(
        array $items
    ): array {

        if ($this->offset !== null) {

            $items = array_slice(
                $items,
                $this->offset
            );
        }

        if ($this->limit !== null) {

            $items = array_slice(
                $items,
                0,
                $this->limit
            );
        }

        return $items;
    }

    protected function applySelects(
        array $items
    ): array {

        if (empty($this->select)) {
            return $items;
        }

        return array_map(function ($item) {

            $result = [];

            foreach ($this->select as $field) {

                if (is_array($item)) {

                    $result[$field] =
                        $item[$field] ?? null;
                } else {

                    $result[$field] =
                        $item->{$field} ?? null;
                }
            }

            return $result;
        }, $items);
    }

    protected function applyWheres(
        array $items
    ): array {

        if (empty($this->filters)) {
            return array_values($items);
        }

        return array_values(array_filter(
            $items,
            function ($item) {

                $passed = true;

                foreach ($this->filters as $filter) {

                    $type = $filter['type'];

                    $field = $filter['field'];

                    $value = is_array($item)
                        ? ($item[$field] ?? null)
                        : ($item->{$field} ?? null);

                    switch ($type) {

                        case 'where':

                            if ($value != $filter['value']) {
                                return false;
                            }

                            break;

                        case 'whereIn':

                            if (
                                !in_array(
                                    $value,
                                    $filter['values']
                                )
                            ) {
                                return false;
                            }

                            break;

                        case 'whereNotIn':

                            if (
                                in_array(
                                    $value,
                                    $filter['values']
                                )
                            ) {
                                return false;
                            }

                            break;

                        case 'whereNull':

                            if ($value !== null) {
                                return false;
                            }

                            break;

                        case 'whereNotNull':

                            if ($value === null) {
                                return false;
                            }

                            break;

                        case 'whereLike':

                            if (
                                stripos(
                                    (string) $value,
                                    $filter['value']
                                ) === false
                            ) {
                                return false;
                            }

                            break;

                        case 'whereBetween':

                            $min = $filter['range'][0] ?? null;
                            $max = $filter['range'][1] ?? null;

                            if (
                                $value < $min
                                || $value > $max
                            ) {
                                return false;
                            }

                            break;
                    }
                }

                return $passed;
            }
        ));
    }

    protected function flatten(
        array $items
    ): array {

        return array_reduce(
            $items,
            function ($carry, $item) {

                if (is_array($item)) {

                    return array_merge(
                        $carry,
                        $item
                    );
                }

                $carry[] = $item;

                return $carry;
            },
            []
        );
    }
}
