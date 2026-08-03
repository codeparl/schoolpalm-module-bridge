<?php

namespace SchoolPalm\ModuleBridge\Relations\Resolvers;

use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Resolves morphTo by grouping items per morph type and querying each mapped contract in batches.
 */
class MorphToResolver implements RelationResolver
{
    public function resolve(array $items, string $relation, array $config, RelationEngine $engine): array
    {
        $typeKey = $config['morph_type_key'] ?? 'morph_type';
        $idKey = $config['morph_id_key'] ?? 'morph_id';
        $foreignKey = $config['foreign_key'] ?? 'id';
        $map = $config['morph_map'] ?? [];

        $grouped = collect($items)->groupBy($typeKey);
        $resolvedByType = [];

        foreach ($grouped as $type => $rows) {
            $contractClass = $map[$type] ?? null;

            if ($contractClass === null) {
                continue;
            }

            $ids = collect($rows)
                ->pluck($idKey)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (empty($ids)) {
                continue;
            }

            $cacheKey = 'morphTo:' . md5($type . json_encode($ids) . $contractClass . $foreignKey);

            if ($engine->cache()->has($cacheKey)) {
                $resolvedByType[$type] = $engine->cache()->get($cacheKey);
                continue;
            }

            $contract = app($contractClass);
            $resolved = collect($contract->findMany($ids))->keyBy($foreignKey);
            $engine->cache()->set($cacheKey, $resolved);

            $resolvedByType[$type] = $resolved;
        }

        foreach ($items as &$item) {
            $type = $item[$typeKey] ?? null;
            $id = $item[$idKey] ?? null;

            $item[$relation] = $resolvedByType[$type][$id] ?? null;
        }

        return $items;
    }
}
