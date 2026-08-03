<?php

namespace SchoolPalm\ModuleBridge\Services;

use SchoolPalm\ModuleBridge\Factories\DataFactoryQuery;
use SchoolPalm\ModuleBridge\Relations\RelationEngine;

abstract class SnapshotService
{
    /*
    |--------------------------------------------------------------------------
    | ENGINE
    |--------------------------------------------------------------------------
    */

    protected RelationEngine $relationEngine;

    /*
    |--------------------------------------------------------------------------
    | PENDING RELATIONS
    |--------------------------------------------------------------------------
    */

    protected array $pendingRelations = [];

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        ?RelationEngine $relationEngine = null
    ) {
        $this->relationEngine = $relationEngine
            ?? app(RelationEngine::class);
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY
    |--------------------------------------------------------------------------
    */

    public function query(): DataFactoryQuery
    {
        return new DataFactoryQuery(
            data: $this->dataset()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EXECUTION PIPELINE
    |--------------------------------------------------------------------------
    */

    protected function execute(DataFactoryQuery $query): array|object|null
    {
        $result = $query->get();

        return $this->applyRelations($result);
    }

  
    /*
    |--------------------------------------------------------------------------
    | FINDERS
    |--------------------------------------------------------------------------
    */

    public function find(int|string $id): ?object
    {
        return $this->execute(
            $this->query()->where('id', $id)
        );
    }

    public function findMany(array $ids): array
    {
        return $this->execute(
            $this->query()->whereIn('id', $ids)
        );
    }

    public function findBy(string $field, mixed $value): array
    {
        return $this->execute(
            $this->query()->where($field, $value)
        );
    }

    public function findOneBy(string $field, mixed $value): ?object
    {
        return $this->execute(
            $this->query()->where($field, $value)
        );
    }

    public function findByMany(string $field, array $values): array
    {
        return $this->execute(
            $this->query()->whereIn($field, $values)
        );
    }

    public function search(string $term, array $fields = []): array
    {
        return $this->execute(
            $this->query()->search($term, $fields)
        );
    }

    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $result = $this->query()->paginate($page, $perPage);

        $result['data'] = $this->applyRelations($result['data']);

        return $result;
    }
    /*
    |--------------------------------------------------------------------------
    | RELATION API (NO VALIDATION ANYMORE)
    |--------------------------------------------------------------------------
    */

    public function with(array|string $relations): static
    {
        $relations = is_array($relations)
            ? $relations
            : [$relations];

        $this->pendingRelations = array_values(
            array_unique(
                array_merge($this->pendingRelations, $relations)
            )
        );

        return $this;
    }

    public function without(array|string $relations): static
    {
        $relations = is_array($relations)
            ? $relations
            : [$relations];

        $this->pendingRelations = array_values(
            array_filter(
                $this->pendingRelations,
                fn ($r) => !in_array($r, $relations)
            )
        );

        return $this;
    }

    public function withoutRelations(): static
    {
        $this->pendingRelations = [];

        return $this;
    }

    public function withOnly(array|string $relations): static
    {
        return $this->withoutRelations()
            ->with($relations);
    }

    public function getPendingRelations(): array
    {
        return $this->pendingRelations;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATION ENGINE ENTRY POINT
    |--------------------------------------------------------------------------
    */

    protected function applyRelations(mixed $records): mixed
    {
        if (empty($this->pendingRelations) || $records === null) {
            return $records;
        }

        $isSingle = !is_array($records);

        $records = $isSingle ? [$records] : $records;

        $records = $this->relationEngine->load(
            items: $records,
            relations: $this->pendingRelations
        );

        $this->pendingRelations = [];

        return $isSingle ? ($records[0] ?? null) : $records;
    }

    /*
    |--------------------------------------------------------------------------
    | DATA SOURCE
    |--------------------------------------------------------------------------
    */

    abstract protected function dataset(): array;

    /*
    |--------------------------------------------------------------------------
    | HOOKS
    |--------------------------------------------------------------------------
    */

    protected function beforeStore(object $dto): void {}
    protected function afterStore(object $dto): void {}
    protected function beforeUpdate(int|string $id, object $dto): void {}
    protected function afterUpdate(int|string $id, object $dto): void {}
    protected function beforeDelete(int|string $id): void {}
    protected function afterDelete(int|string $id): void {}
}