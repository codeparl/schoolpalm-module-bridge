<?php

namespace SchoolPalm\ModuleBridge\Factories;

class DataFactoryQuery
{
    protected array $data;

    /**
     * Track selected fields
     */
    protected ?array $selected = null;

    /**
     * Track hidden fields
     */
    protected array $hidden = [];

    public function __construct(array $data)
    {
        $this->data = array_values($data);
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE
    |--------------------------------------------------------------------------
    */

    public function where(
        string $field,
        mixed $value,
        string $operator = '='
    ): self {

        $this->data = array_values(array_filter(
            $this->data,
            function ($dto) use ($field, $value, $operator) {

                $current = $dto->{$field} ?? null;

                return match ($operator) {
                    '=', '==' => $current == $value,
                    '!=', '<>' => $current != $value,
                    '>' => $current > $value,
                    '<' => $current < $value,
                    '>=' => $current >= $value,
                    '<=' => $current <= $value,
                    'like' => str_contains(
                        strtolower((string) $current),
                        strtolower((string) $value)
                    ),
                    default => false
                };
            }
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE IN
    |--------------------------------------------------------------------------
    */

    public function whereIn(string $field, array $values): self
    {
        $this->data = array_values(array_filter(
            $this->data,
            fn ($dto) => in_array(
                $dto->{$field} ?? null,
                $values
            )
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE NOT IN
    |--------------------------------------------------------------------------
    */

    public function whereNotIn(string $field, array $values): self
    {
        $this->data = array_values(array_filter(
            $this->data,
            fn ($dto) => !in_array(
                $dto->{$field} ?? null,
                $values
            )
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE NULL
    |--------------------------------------------------------------------------
    */

    public function whereNull(string $field): self
    {
        $this->data = array_values(array_filter(
            $this->data,
            fn ($dto) => ($dto->{$field} ?? null) === null
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE NOT NULL
    |--------------------------------------------------------------------------
    */

    public function whereNotNull(string $field): self
    {
        $this->data = array_values(array_filter(
            $this->data,
            fn ($dto) => ($dto->{$field} ?? null) !== null
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    public function search(string $term, array $fields = []): self
    {
        $term = strtolower($term);

        $this->data = array_values(array_filter(
            $this->data,
            function ($dto) use ($term, $fields) {

                $data = (array) $dto;

                foreach ($fields ?: array_keys($data) as $field) {

                    if (
                        isset($data[$field]) &&
                        str_contains(
                            strtolower((string) $data[$field]),
                            $term
                        )
                    ) {
                        return true;
                    }
                }

                return false;
            }
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER BY
    |--------------------------------------------------------------------------
    */

    public function orderBy(
        string $field,
        string $direction = 'asc'
    ): self {

        usort($this->data, function ($a, $b) use ($field, $direction) {

            $valA = $a->{$field} ?? null;
            $valB = $b->{$field} ?? null;

            if ($valA == $valB) {
                return 0;
            }

            $result = $valA <=> $valB;

            return strtolower($direction) === 'desc'
                ? -$result
                : $result;
        });

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | LATEST / OLDEST
    |--------------------------------------------------------------------------
    */

    public function latest(string $field = 'created_at'): self
    {
        return $this->orderBy($field, 'desc');
    }

    public function oldest(string $field = 'created_at'): self
    {
        return $this->orderBy($field, 'asc');
    }

    /*
    |--------------------------------------------------------------------------
    | LIMIT
    |--------------------------------------------------------------------------
    */

    public function limit(int $limit): self
    {
        $this->data = array_slice(
            $this->data,
            0,
            max(0, $limit)
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | TAKE
    |--------------------------------------------------------------------------
    */

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /*
    |--------------------------------------------------------------------------
    | OFFSET
    |--------------------------------------------------------------------------
    */

    public function offset(int $offset): self
    {
        $this->data = array_slice(
            $this->data,
            max(0, $offset)
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | SKIP
    |--------------------------------------------------------------------------
    */

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /*
    |--------------------------------------------------------------------------
    | SELECT
    |--------------------------------------------------------------------------
    */

    public function select(array $fields): self
    {
        $this->selected = $fields;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | HIDE FIELDS
    |--------------------------------------------------------------------------
    */

    public function hidden(array $fields): self
    {
        $this->hidden = $fields;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER CALLBACK
    |--------------------------------------------------------------------------
    */

    public function filter(callable $callback): self
    {
        $this->data = array_values(
            array_filter($this->data, $callback)
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | MAP
    |--------------------------------------------------------------------------
    */

    public function map(callable $callback): self
    {
        $this->data = array_map(
            $callback,
            $this->data
        );

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | EACH
    |--------------------------------------------------------------------------
    */

    public function each(callable $callback): self
    {
        foreach ($this->data as $item) {
            $callback($item);
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | UNIQUE
    |--------------------------------------------------------------------------
    */

    public function unique(string $field): self
    {
        $seen = [];

        $this->data = array_values(array_filter(
            $this->data,
            function ($dto) use ($field, &$seen) {

                $value = $dto->{$field} ?? null;

                if (in_array($value, $seen, true)) {
                    return false;
                }

                $seen[] = $value;

                return true;
            }
        ));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | GROUP BY
    |--------------------------------------------------------------------------
    */

    public function groupBy(string $field): array
    {
        $groups = [];

        foreach ($this->data as $dto) {

            $key = $dto->{$field} ?? null;

            $groups[$key][] = $dto;
        }

        return $groups;
    }

    /*
    |--------------------------------------------------------------------------
    | SUM
    |--------------------------------------------------------------------------
    */

    public function sum(string $field): int|float
    {
        return array_reduce(
            $this->data,
            fn ($carry, $dto) =>
                $carry + ($dto->{$field} ?? 0),
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AVG
    |--------------------------------------------------------------------------
    */

    public function avg(string $field): float
    {
        $count = $this->count();

        if ($count === 0) {
            return 0;
        }

        return $this->sum($field) / $count;
    }

    /*
    |--------------------------------------------------------------------------
    | MIN
    |--------------------------------------------------------------------------
    */

    public function min(string $field): mixed
    {
        return min(array_map(
            fn ($dto) => $dto->{$field} ?? null,
            $this->data
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | MAX
    |--------------------------------------------------------------------------
    */

    public function max(string $field): mixed
    {
        return max(array_map(
            fn ($dto) => $dto->{$field} ?? null,
            $this->data
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | EXISTS
    |--------------------------------------------------------------------------
    */

    public function exists(): bool
    {
        return !empty($this->data);
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATE
    |--------------------------------------------------------------------------
    */

    public function paginate(
        int $page = 1,
        int $perPage = 10
    ): array {

        $total = count($this->data);

        $offset = ($page - 1) * $perPage;

        $data = array_slice(
            $this->data,
            $offset,
            $perPage
        );

        return [
            'data' => $this->transformResults($data),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GET RESULTS
    |--------------------------------------------------------------------------
    */

    public function get(): array
    {
        return $this->transformResults($this->data);
    }

    public function first(): ?object
    {
        return $this->transformResults(
            [$this->data[0] ?? null]
        )[0] ?? null;
    }

    public function last(): ?object
    {
        $last = end($this->data);

        return $this->transformResults([$last])[0] ?? null;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL TRANSFORMER
    |--------------------------------------------------------------------------
    */

    protected function transformResults(array $items): array
    {
        return array_map(function ($dto) {

            if ($dto === null) {
                return null;
            }

            $data = (array) $dto;

            /*
            |--------------------------------------------------------------------------
            | SELECT
            |--------------------------------------------------------------------------
            */

            if ($this->selected !== null) {

                $data = array_intersect_key(
                    $data,
                    array_flip($this->selected)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | HIDDEN
            |--------------------------------------------------------------------------
            */

            foreach ($this->hidden as $field) {
                unset($data[$field]);
            }

            return (object) $data;

        }, $items);
    }
}