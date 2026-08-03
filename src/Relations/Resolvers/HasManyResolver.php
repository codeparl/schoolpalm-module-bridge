<?php

namespace SchoolPalm\ModuleBridge\Relations\Resolvers;

use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Resolves hasMany by batching owner IDs and grouping related records by foreign key.
 */
class HasManyResolver implements RelationResolver
{
    public function resolve(array $items, string $relation, array $config, RelationEngine $engine): array
    {
        $contract = app($config['contract']);
        $localKey = $config['local_key'];
        $foreignKey = $config['foreign_key'];

        $ids = collect($items)
            ->pluck($localKey)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($ids)) {
            return $items;
        }

        $cacheKey = 'hasMany:' . md5(json_encode($ids) . $config['contract'] . $foreignKey);

        if ($engine->cache()->has($cacheKey)) {
            $related = $engine->cache()->get($cacheKey);
        } else {
            $related = collect($contract->query([
                'whereIn' => [$foreignKey => $ids],
            ])->get())->groupBy($foreignKey);

            $engine->cache()->set($cacheKey, $related);
        }

        foreach ($items as &$item) {
            $key = $item[$localKey] ?? null;
            $item[$relation] = $related[$key] ?? collect([])->values()->all();
        }

        return $items;
    }
}
