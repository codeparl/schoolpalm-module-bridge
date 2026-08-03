<?php

namespace SchoolPalm\ModuleBridge\Relations;

use SchoolPalm\ModuleBridge\Relations\Exceptions\RelationResolutionException;
use SchoolPalm\ModuleBridge\Relations\Resolvers\BelongsToResolver;
use SchoolPalm\ModuleBridge\Relations\Resolvers\BelongsToManyResolver;
use SchoolPalm\ModuleBridge\Relations\Resolvers\HasManyResolver;
use SchoolPalm\ModuleBridge\Relations\Resolvers\HasOneResolver;
use SchoolPalm\ModuleBridge\Relations\Resolvers\MorphManyResolver;
use SchoolPalm\ModuleBridge\Relations\Resolvers\MorphOneResolver;
use SchoolPalm\ModuleBridge\Relations\Resolvers\MorphToResolver;
use SchoolPalm\ModuleBridge\Relations\Resolvers\RelationResolver;

class RelationEngine
{
    public const SPEC_VERSION = RelationSpec::VERSION;

    protected array $registry = [];

    /**
     * global index: student => [fullKeys]
     */
    protected array $globalIndex = [];

    /**
     * scoped index: module => [student => [fullKeys]]
     */
    protected array $scopedIndex = [];

    protected RelationCache $cache;
    protected RelationConfigValidator $validator;
    protected bool $strictValidation = false;

    protected array $resolvers = [
        'belongsTo' => BelongsToResolver::class,
        'hasOne' => HasOneResolver::class,
        'hasMany' => HasManyResolver::class,
        'belongsToMany' => BelongsToManyResolver::class,
        'morphOne' => MorphOneResolver::class,
        'morphMany' => MorphManyResolver::class,
        'morphTo' => MorphToResolver::class,
    ];

    public function __construct(
        array $registry = [],
        ?RelationCache $cache = null,
        ?RelationConfigValidator $validator = null
    ) {
        $this->registry = empty($registry) ?resolve('module.relations')  : $registry;

        $this->cache = $cache ?? new RelationCache();
        $this->validator = $validator ?? new RelationConfigValidator();

        $this->buildIndexes();
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX BUILDING (FIXED)
    |--------------------------------------------------------------------------
    */
    protected function buildIndexes(): void
    {
        foreach ($this->registry as $fullKey => $config) {

            $parts = explode('.', $fullKey);

            $lastSegment = strtolower(trim(end($parts)));

            // global index (MUST be array to avoid overwrite bugs)
            $this->globalIndex[$lastSegment][] = $fullKey;

            // scoped index
            $scope = implode('.', array_slice($parts, 0, 3));
 
            $this->scopedIndex[$scope][$lastSegment][] = $fullKey;

           
        }
    
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD RELATIONS
    |--------------------------------------------------------------------------
    */
    public function load(array $items, array $relations): array
    {
        $relations = $this->parseRelations($relations);

        foreach ($relations as $relationExpr => $nested) {

            [$relationName, $scope] = $this->parseScopedName($relationExpr);

            $relationKey = strtolower(trim($relationName));

            $resolvedKey = null;

            /*
            |--------------------------------------------------------------------------
            | Scoped resolution first
            |--------------------------------------------------------------------------
            */
            if (
                $scope &&
                isset($this->scopedIndex[$scope][$relationKey])
            ) {
                $resolvedKey =
                    $this->scopedIndex[$scope][$relationKey][0] ?? null;
            }

           

            /*
            |--------------------------------------------------------------------------
            | Global fallback
            |--------------------------------------------------------------------------
            */
            if (
                !$resolvedKey &&
                isset($this->globalIndex[$relationKey])
            ) {
                $resolvedKey =
                    $this->globalIndex[$relationKey][0] ?? null;
            }
 
            if (
                !$resolvedKey ||
                !isset($this->registry[$resolvedKey])
            ) {
                continue;
            }
           

            $config = $this->registry[$resolvedKey];
            $type = $config['type'] ?? 'belongsTo';

            $resolverClass = $this->resolvers[$type] ?? null;

            try {
                $this->validator->validate(
                    $resolvedKey,
                    $config,
                    array_keys($this->resolvers)
                );
            } catch (\Throwable $e) {
                if ($this->strictValidation) {
                    throw $e;
                }
                continue;
            }

            if (!$resolverClass) {
                continue;
            }

            try {
                /** @var RelationResolver $resolver */
                $resolver = new $resolverClass();

                $items = $resolver->resolve(
                    $items,
                    $relationKey,
                    $config,
                    $this
                );
                 

            } catch (\Throwable $e) {

                if ($this->strictValidation) {
                    throw new RelationResolutionException(
                        "Failed resolving '{$resolvedKey}': {$e->getMessage()}",
                        0,
                        $e
                    );
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | NESTED RELATIONS
            |--------------------------------------------------------------------------
            */
            
            if (!empty($nested)) {

                foreach ($items as &$item) {

                    if (!isset($item[$relationName])) {
                        continue;
                    }

                    
                    $related = $item[$relationName];

                    if (is_array($related)) {

                        $item[$relationName] =
                            $this->load($related, $nested);

                    } elseif (is_object($related)) {

                        $item[$relationName] =
                            $this->load([(array) $related], $nested)[0]
                            ?? $related;
                    }
                }
            }
        }

        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | PARSERS
    |--------------------------------------------------------------------------
    */
    protected function parseRelations(array $relations): array
    {
        $parsed = [];

        foreach ($relations as $relation) {

            if (str_contains($relation, '.')) {

                [$parent, $child] = explode('.', $relation, 2);

                $parsed[$parent][] = $child;

            } else {

                $parsed[$relation] = [];
            }
        }

        return $parsed;
    }

    protected function parseScopedName(string $relation): array
    {
        if (str_contains($relation, '@')) {

            return explode('@', $relation, 2);
        }

        return [$relation, null];
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC API
    |--------------------------------------------------------------------------
    */
    public function cache(): RelationCache
    {
        return $this->cache;
    }

    public function resolvers(): array
    {
        return $this->resolvers;
    }

    public function addResolver(string $type, string $resolverClass): self
    {
        $this->resolvers[$type] = $resolverClass;
        return $this;
    }

    public function addResolvers(array $resolvers): self
    {
        foreach ($resolvers as $type => $resolverClass) {
            $this->addResolver($type, $resolverClass);
        }
        return $this;
    }

    public function strictValidation(bool $strict = true): self
    {
        $this->strictValidation = $strict;
        return $this;
    }

    public function validator(): RelationConfigValidator
    {
        return $this->validator;
    }
}