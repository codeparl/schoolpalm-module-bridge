<?php

namespace SchoolPalm\ModuleBridge\Relations\Resolvers;

use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Resolves morphOne by batching IDs and filtering related rows by morph type discriminator.
 */
class MorphOneResolver implements RelationResolver
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

        $cacheKey = 'morphOne:' . md5(json_encode($ids) . $config['contract'] . $foreignKey . $typeKey . $typeValue);

        if ($engine->cache()->has($cacheKey)) {
            $related = $engine->cache()->get($cacheKey);
        } else {
            $related = collect($contract->query([
                'whereIn' => [$foreignKey => $ids],
                'where' => [$typeKey => $typeValue],
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
