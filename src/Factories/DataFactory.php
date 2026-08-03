<?php

namespace SchoolPalm\ModuleBridge\Factories;

use Illuminate\Support\Facades\File;

abstract class DataFactory
{
    /**
     * Cached hydrated DTOs (important for performance)
     */
    protected ?array $cache = null;

    abstract protected function dtoClass(): string;
    abstract protected function mockDataPath(): string;

    /*
    |--------------------------------------------------------------------------
    | HYDRATION CORE
    |--------------------------------------------------------------------------
    */

    protected function hydrate(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = $this->read();

        $dtoClass = $this->dtoClass();

        $this->cache = array_map(
            fn ($row) => $this->makeDto($dtoClass, $row),
            $rows
        );

        return $this->cache;
    }

    protected function flushCache(): void
    {
        $this->cache = null;
    }

    /*
    |--------------------------------------------------------------------------
    | READ OPERATIONS
    |--------------------------------------------------------------------------
    */

    public function all(): array
    {
        return $this->hydrate();
    }

    public function first(): ?object
    {
        return $this->hydrate()[0] ?? null;
    }

    public function find(int|string $id): ?object
    {
        foreach ($this->hydrate() as $dto) {
            if (($dto->id ?? null) == $id) {
                return $dto;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER CORE (single source of truth)
    |--------------------------------------------------------------------------
    */

    /**
 * =========================
 * BULK READ OPERATIONS
 * =========================
 */
public function findMany(array $ids): array
{
    $results = [];

    foreach ($this->all() as $dto) {

        if (in_array($dto->id ?? null, $ids, true)) {
            $results[] = $dto;
        }
    }

    return $results;
}
    protected function filterCollection(array $items, callable $callback): array
    {
        return array_values(array_filter($items, $callback));
    }

    public function where(string $field, mixed $value): array
    {
        return $this->filterCollection(
            $this->hydrate(),
            fn ($dto) => ($dto->{$field} ?? null) == $value
        );
    }

    public function filter(array $conditions): array
    {
        return $this->filterCollection(
            $this->hydrate(),
            function ($dto) use ($conditions) {

                foreach ($conditions as $field => $value) {
                    if (($dto->{$field} ?? null) != $value) {
                        return false;
                    }
                }

                return true;
            }
        );
    }

    public function search(string $term, array $fields = []): array
    {
        $term = strtolower($term);

        return $this->filterCollection(
            $this->hydrate(),
            function ($dto) use ($term, $fields) {

                $data = (array) $dto;

                foreach ($fields ?: array_keys($data) as $field) {

                    if (
                        isset($data[$field]) &&
                        str_contains(strtolower((string)$data[$field]), $term)
                    ) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SORTING
    |--------------------------------------------------------------------------
    */

    public function orderBy(string $field, string $direction = 'asc'): array
    {
        $data = $this->hydrate();

        usort($data, function ($a, $b) use ($field, $direction) {

            $valA = $a->{$field} ?? null;
            $valB = $b->{$field} ?? null;

            if ($valA == $valB) return 0;

            $result = $valA <=> $valB;

            return $direction === 'desc' ? -$result : $result;
        });

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $all = $this->hydrate();

        $total = count($all);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($all, $offset, $perPage),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | WRITE OPERATIONS
    |--------------------------------------------------------------------------
    */

    public function create(array $data): object
    {
        $rows = $this->read();

        $data['id'] = $data['id'] ?? $this->generateId($rows);

        $rows[] = $data;

        $this->write($rows);

        $this->flushCache();

        return $this->makeDto($this->dtoClass(), $data);
    }

    public function update(int|string $id, array $data): bool
    {
        $rows = $this->read();

        foreach ($rows as &$row) {

            if (($row['id'] ?? null) == $id) {

                $row = array_merge($row, $data);

                $this->write($rows);

                $this->flushCache();

                return true;
            }
        }

        return false;
    }

    public function delete(int|string $id): bool
    {
        $rows = $this->read();

        $newRows = array_filter(
            $rows,
            fn ($row) => ($row['id'] ?? null) != $id
        );

        $changed = count($rows) !== count($newRows);

        if ($changed) {
            $this->write(array_values($newRows));
            $this->flushCache();
        }

        return $changed;
    }

    public function createMany(array $items): array
    {
        $rows = $this->read();

        $created = [];

        foreach ($items as $data) {

            $data['id'] = $data['id'] ?? $this->generateId($rows);

            $rows[] = $data;

            $created[] = $this->makeDto($this->dtoClass(), $data);
        }

        $this->write($rows);
        $this->flushCache();

        return $created;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL STORAGE
    |--------------------------------------------------------------------------
    */

    protected function read(): array
    {
        $path = $this->mockDataPath();

        if (!is_file($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?? [];
    }

    protected function write(array $data): void
    {
        File::put(
            $this->mockDataPath(),
            json_encode($data, JSON_PRETTY_PRINT)
        );
    }

    protected function makeDto(string $dtoClass, array $data): object
    {
        return new $dtoClass(...array_values($data));
    }

    protected function generateId(array $rows): int
    {
        $ids = array_column($rows, 'id');
        return $ids ? (max($ids) + 1) : 1;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY ENTRY
    |--------------------------------------------------------------------------
    */

    public function query(): DataFactoryQuery
    {
        return new DataFactoryQuery($this->hydrate());
    }
}