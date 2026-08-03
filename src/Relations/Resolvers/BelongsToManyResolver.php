<?php

namespace SchoolPalm\ModuleBridge\Relations\Resolvers;

use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Resolves many-to-many via a pivot contract + related contract in two batch steps:
 * 1) fetch pivot rows by parent IDs, 2) fetch related rows by related IDs.
 */
class BelongsToManyResolver implements RelationResolver
{
    public function resolve(array $items, string $relation, array $config, RelationEngine $engine): array
    {
        $relatedContract = app($config['contract']);
        $pivotContract = app($config['pivot_contract']);

        $localKey = $config['local_key'] ?? 'id';
        $pivotParentKey = $config['pivot_parent_key'] ?? 'parent_id';
        $pivotRelatedKey = $config['pivot_related_key'] ?? 'related_id';
        $relatedKey = $config['foreign_key'] ?? 'id';

        $parentIds = collect($items)
            ->pluck($localKey)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($parentIds)) {
            return $items;
        }

        $pivotCacheKey = 'belongsToMany:pivot:' . md5(json_encode($parentIds) . $config['pivot_contract'] . $pivotParentKey . $pivotRelatedKey);

        if ($engine->cache()->has($pivotCacheKey)) {
            $pivotRows = $engine->cache()->get($pivotCacheKey);
        } else {
            $pivotRows = collect($pivotContract->query([
                'whereIn' => [$pivotParentKey => $parentIds],
            ])->get());

            $engine->cache()->set($pivotCacheKey, $pivotRows);
        }

        $relatedIds = $pivotRows->pluck($pivotRelatedKey)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($relatedIds)) {
            foreach ($items as &$item) {
                $item[$relation] = [];
            }

            return $items;
        }

        $relatedCacheKey = 'belongsToMany:related:' . md5(json_encode($relatedIds) . $config['contract'] . $relatedKey);

        if ($engine->cache()->has($relatedCacheKey)) {
            $relatedById = $engine->cache()->get($relatedCacheKey);
        } else {
            $relatedById = collect($relatedContract->findMany($relatedIds))->keyBy($relatedKey);
            $engine->cache()->set($relatedCacheKey, $relatedById);
        }

        $pivotByParent = $pivotRows->groupBy($pivotParentKey);

        foreach ($items as &$item) {
            $parentId = $item[$localKey] ?? null;
            $rows = $pivotByParent[$parentId] ?? collect([]);

            $item[$relation] = $rows
                ->map(fn ($row) => $relatedById[$row[$pivotRelatedKey] ?? null] ?? null)
                ->filter()
                ->values()
                ->all();
        }

        return $items;
    }
}
