<?php

namespace SchoolPalm\ModuleBridge\Relations\Resolvers;

use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Resolves morphMany by batching IDs and grouping filtered rows by owner foreign key.
 */
class MorphManyResolver implements RelationResolver
{
    public function resolve(array $items, string $relation, array $config, RelationEngine $engine): array
    {
        $contract = app($config['contract']);

        $localKey = $config['local_key'] ?? 'id';
        $foreignKey = $config['foreign_key'];
        $typeKey = $config['morph_type_key'] ?? 'morph_type';
        $typeValue = $config['morph_type'];

        $ids = collect($items)
            ->pluck($localKey)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($ids)) {
            return $items;
        }

        $cacheKey = 'morphMany:' . md5(json_encode($ids) . $config['contract'] . $foreignKey . $typeKey . $typeValue);

        if ($engine->cache()->has($cacheKey)) {
            $related = $engine->cache()->get($cacheKey);
        } else {
            $related = collect($contract->query([
                'whereIn' => [$foreignKey => $ids],
                'where' => [$typeKey => $typeValue],
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
