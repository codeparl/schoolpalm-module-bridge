<?php

namespace SchoolPalm\ModuleBridge\Relations\Resolvers;

use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Resolves hasOne by batching owner IDs and querying related records with whereIn.
 */
class HasOneResolver implements RelationResolver
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

        $cacheKey = 'hasOne:' . md5(json_encode($ids) . $config['contract'] . $foreignKey);

        if ($engine->cache()->has($cacheKey)) {
            $related = $engine->cache()->get($cacheKey);
        } else {
            $related = collect($contract->query([
                'whereIn' => [$foreignKey => $ids],
            ])->get())->keyBy($foreignKey);

            $engine->cache()->set($cacheKey, $related);
        }

        foreach ($items as &$item) {
            $key = $item[$localKey] ?? null;
            $item[$relation] = $related[$key] ?? null;
        }

        return $items;
    }
}
