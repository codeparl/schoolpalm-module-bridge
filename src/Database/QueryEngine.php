<?php

namespace SchoolPalm\ModuleBridge\Database;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use SchoolPalm\ModuleBridge\Traits\HasQueryEngineHooks;

/**
 * Generic Query Engine – works with any model passed at runtime.
 * Supports hooks via a hook handler (usually the service class).
 */
class QueryEngine
{
    use HasQueryEngineHooks;

    /**
     * The model instance this engine is working with.
     */
    protected Model $model;

    /**
     * The hook handler (usually the service class).
     * If provided, it will be called for hook methods.
     */
    protected ?object $hookHandler = null;

    /**
     * Create a new query engine instance.
     *
     * @param Model $model
     * @param object|null $hookHandler  The service class that provides hook methods
     */
    public function __construct(Model $model, ?object $hookHandler = null)
    {
        $this->model = $model;
        $this->hookHandler = $hookHandler;
    }

    /**
     * Set the hook handler (usually the service class).
     */
    public function setHookHandler(object $handler): self
    {
        $this->hookHandler = $handler;
        return $this;
    }

    /**
     * Get a fresh query builder for the model.
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Get the model class name.
     */
    protected function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Sanitize a single record payload.
     * This is ALWAYS called before insert/update operations.
     */
    protected function sanitizePayload(array $data): array
    {
        foreach ($data as $key => $value) {
            // 1. Remove system fields
            if (in_array($key, ['created_at', 'updated_at', 'deleted_at', 'school_id'], true)) {
                unset($data[$key]);
                continue;
            }

            // 2. Normalize ISO dates
            if (
                $value &&
                is_string($value) &&
                method_exists($this->model, 'isDateAttribute') &&
                $this->model->isDateAttribute($key)
            ) {
                try {
                    $data[$key] = \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    // ignore invalid dates
                }
            }
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | HOOK DELEGATION METHODS (ALWAYS SANITIZE FIRST)
    |--------------------------------------------------------------------------
    | sanitizePayload() is ALWAYS called before insert/update operations.
    | Then the service hook is called (if provided) for additional business logic.
    */

    /**
     * Always sanitize first, then optionally call service hook.
     */
    protected function beforeInsert(array $data): array
    {
        // 1. ALWAYS sanitize first (CRITICAL)
        $data = $this->sanitizePayload($data);
        
        // 2. Then allow service hook to add business logic
        if ($this->hookHandler && method_exists($this->hookHandler, 'beforeInsert')) {
            $data = $this->hookHandler->beforeInsert($data);
        }
        
        return $data;
    }

    /**
     * Always sanitize first, then optionally call service hook.
     */
    protected function beforeUpdate(array $data, array $criteria): array
    {
        // 1. ALWAYS sanitize first (CRITICAL)
        $data = $this->sanitizePayload($data);
        
        // 2. Then allow service hook to add business logic
        if ($this->hookHandler && method_exists($this->hookHandler, 'beforeUpdate')) {
            $data = $this->hookHandler->beforeUpdate($data, $criteria);
        }
        
        return $data;
    }

    /**
     * Optionally call service hook (no sanitization needed).
     */
    protected function beforeDelete($identifier): void
    {
        if ($this->hookHandler && method_exists($this->hookHandler, 'beforeDelete')) {
            $this->hookHandler->beforeDelete($identifier);
        }
    }

    /**
     * Optionally call service hook (no sanitization needed).
     */
    protected function afterInsert($result, array $data): void
    {
        if ($this->hookHandler && method_exists($this->hookHandler, 'afterInsert')) {
            $this->hookHandler->afterInsert($result, $data);
        }
    }

    /**
     * Optionally call service hook (no sanitization needed).
     */
    protected function afterUpdate($result, array $data, array $criteria): void
    {
        if ($this->hookHandler && method_exists($this->hookHandler, 'afterUpdate')) {
            $this->hookHandler->afterUpdate($result, $data, $criteria);
        }
    }

    /**
     * Optionally call service hook (no sanitization needed).
     */
    protected function afterDelete($result, $identifier): void
    {
        if ($this->hookHandler && method_exists($this->hookHandler, 'afterDelete')) {
            $this->hookHandler->afterDelete($result, $identifier);
        }
    }

    /**
     * Always sanitize each record, then optionally call service hook.
     */
    protected function beforeInsertMany(array $dataSet): array
    {
        // 1. ALWAYS sanitize each record first (CRITICAL)
        $dataSet = array_map([$this, 'sanitizePayload'], $dataSet);
        
        // 2. Then allow service hook to add business logic
        if ($this->hookHandler && method_exists($this->hookHandler, 'beforeInsertMany')) {
            $dataSet = $this->hookHandler->beforeInsertMany($dataSet);
        }
        
        return $dataSet;
    }

    /**
     * Optionally call service hook (no sanitization needed).
     */
    protected function afterInsertMany($results, array $dataSet): void
    {
        if ($this->hookHandler && method_exists($this->hookHandler, 'afterInsertMany')) {
            $this->hookHandler->afterInsertMany($results, $dataSet);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BASE QUERY
    |--------------------------------------------------------------------------
    */

    protected function query(array $filters = []): Builder
    {
        $query = $this->newQuery();

        foreach ($filters as $filter) {
            $type  = $filter['type'] ?? 'where';
            $field = $filter['field'] ?? null;

            if (!$field && $type !== 'raw') {
                continue;
            }

            switch ($type) {
                case 'where':
                    $query->where($field, $filter['operator'] ?? '=', $filter['value'] ?? null);
                    break;
                case 'orWhere':
                    $query->orWhere($field, $filter['operator'] ?? '=', $filter['value'] ?? null);
                    break;
                case 'whereIn':
                    $query->whereIn($field, $filter['values'] ?? []);
                    break;
                case 'whereNotIn':
                    $query->whereNotIn($field, $filter['values'] ?? []);
                    break;
                case 'whereNull':
                    $query->whereNull($field);
                    break;
                case 'whereNotNull':
                    $query->whereNotNull($field);
                    break;
                case 'whereBetween':
                    $query->whereBetween($field, $filter['range'] ?? []);
                    break;
                case 'whereLike':
                    $query->where($field, 'LIKE', '%' . ($filter['value'] ?? '') . '%');
                    break;
            }
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | BASIC READ OPERATIONS
    |--------------------------------------------------------------------------
    */

    public function find(int|string $id): ?array
    {
        return $this->model->find($id)?->toArray();
    }

    public function findMany(array $ids): array
    {
        return $this->model->whereIn('id', $ids)->get()->toArray();
    }

    public function all(): array
    {
        return $this->model->all()->toArray();
    }

    public function exists(int|string $id): bool
    {
        return $this->model->where('id', $id)->exists();
    }

    public function first(): ?array
    {
        return $this->newQuery()->first()?->toArray();
    }

    public function last(): ?array
    {
        return $this->newQuery()->latest('id')->first()?->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | FILTERING CORE
    |--------------------------------------------------------------------------
    */

    public function where(array $filters): array
    {
        return $this->query($filters)->get()->toArray();
    }

    public function findBy(array $criteria): array
    {
        $query = $this->newQuery();
        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }
        return $query->get()->toArray();
    }

    public function findOneBy(array $criteria): ?array
    {
        $query = $this->newQuery();
        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }
        return $query->first()?->toArray();
    }

    public function search(array $filters): array
    {
        return $this->where($filters);
    }

    /*
    |--------------------------------------------------------------------------
    | ADVANCED FILTER OPERATORS
    |--------------------------------------------------------------------------
    */

    public function whereIn(string $field, array $values): array
    {
        return $this->model->whereIn($field, $values)->get()->toArray();
    }

    public function whereNotIn(string $field, array $values): array
    {
        return $this->model->whereNotIn($field, $values)->get()->toArray();
    }

    public function whereBetween(string $field, array $range): array
    {
        return $this->model->whereBetween($field, $range)->get()->toArray();
    }

    public function whereLike(string $field, string $value): array
    {
        return $this->model->where($field, 'LIKE', "%{$value}%")->get()->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    public function paginate(int $page = 1, int $perPage = 15, array $filters = [], array $sort = []): array
    {
        $query = $this->query($filters);

        foreach ($sort as $item) {
            $query->orderBy($item['field'], $item['direction'] ?? 'asc');
        }

        $result = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
                'from' => $result->firstItem(),
                'to' => $result->lastItem(),
            ],
        ];
    }

    public function simplePaginate(int $perPage = 15, array $filters = []): array
    {
        return [
            'data' => $this->query($filters)->simplePaginate($perPage)->items(),
            'has_more' => true,
        ];
    }

    public function count(array $filters = []): int
    {
        return $this->query($filters)->count();
    }

    /*
    |--------------------------------------------------------------------------
    | SORTING & LIMITING
    |--------------------------------------------------------------------------
    */

    public function orderBy(string $field, string $direction = 'asc', array $filters = []): array
    {
        return $this->query($filters)->orderBy($field, $direction)->get()->toArray();
    }

    public function limit(int $limit, array $filters = [], array $sort = []): array
    {
        $query = $this->query($filters)->limit($limit);
        foreach ($sort as $item) {
            $query->orderBy($item['field'], $item['direction'] ?? 'asc');
        }
        return $query->get()->toArray();
    }

    public function offset(int $offset, int $limit, array $filters = []): array
    {
        return $this->query($filters)->offset($offset)->limit($limit)->get()->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | BULK OPERATIONS
    |--------------------------------------------------------------------------
    */

    public function chunk(int $size, Closure $callback): void
    {
        $this->model->chunk($size, $callback);
    }

    public function cursor(array $filters = []): iterable
    {
        return $this->query($filters)->cursor();
    }

    public function pluck(string $field, array $filters = []): array
    {
        return $this->query($filters)->pluck($field)->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | AGGREGATIONS
    |--------------------------------------------------------------------------
    */

    public function aggregate(string $function, string $field, array $filters = []): mixed
    {
        return $this->query($filters)->{$function}($field);
    }

    public function sum(string $field, array $filters = []): float|int
    {
        return $this->query($filters)->sum($field);
    }

    public function avg(string $field, array $filters = []): float
    {
        return (float) $this->query($filters)->avg($field);
    }

    public function min(string $field, array $filters = []): mixed
    {
        return $this->query($filters)->min($field);
    }

    public function max(string $field, array $filters = []): mixed
    {
        return $this->query($filters)->max($field);
    }

    /*
    |--------------------------------------------------------------------------
    | GROUPING
    |--------------------------------------------------------------------------
    */

    public function groupBy(string $field, array $filters = []): array
    {
        return $this->query($filters)->groupBy($field)->get()->toArray();
    }

    public function having(array $conditions, array $filters = []): array
    {
        $query = $this->query($filters);
        foreach ($conditions as $condition) {
            $query->having($condition['field'], $condition['operator'], $condition['value']);
        }
        return $query->get()->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function with(array $relations, array $filters = []): array
    {
        return $this->query($filters)->with($relations)->get()->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATIONS (with hooks)
    |--------------------------------------------------------------------------
    */

    public function insert(array $data): array
    {
        $data = $this->beforeInsert($data);
        $model = $this->model->create($data);
        $this->afterInsert($model, $data);
        return [$model];
    }

    public function insertMany(array $dataSet): array
    {
        $dataSet = $this->beforeInsertMany($dataSet);
        $results = collect($dataSet)->map(fn($data) => $this->model->create($data))->toArray();
        $this->afterInsertMany($results, $dataSet);
        return $results;
    }

    public function updateById(int|string $id, array $data): bool
    {
        $criteria = ['id' => $id];
        $data = $this->beforeUpdate($data, $criteria);
        $result = (bool) $this->model->where('id', $id)->update($data);
        $this->afterUpdate($result, $data, $criteria);
        return $result;
    }

    public function updateWhere(array $criteria, array $data): int
    {
        $data = $this->beforeUpdate($data, $criteria);
        $result = $this->applyCriteria($criteria)->update($data);
        $this->afterUpdate($result, $data, $criteria);
        return $result;
    }

    public function deleteById(int|string $id): bool
    {
        $this->beforeDelete($id);
        $result = (bool) $this->model->where('id', $id)->delete();
        $this->afterDelete($result, $id);
        return $result;
    }

    public function deleteWhere(array $criteria): int
    {
        $this->beforeDelete($criteria);
        $result = $this->applyCriteria($criteria)->delete();
        $this->afterDelete($result, $criteria);
        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function applyCriteria(array $criteria): Builder
    {
        $query = $this->newQuery();
        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }
        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | RAW ACCESS
    |--------------------------------------------------------------------------
    */

    public function raw(): Builder
    {
        return $this->newQuery();
    }
}