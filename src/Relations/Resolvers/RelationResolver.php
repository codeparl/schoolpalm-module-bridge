<?php

namespace SchoolPalm\ModuleBridge\Relations\Resolvers;

use SchoolPalm\ModuleBridge\Relations\RelationEngine;

/**
 * Strategy interface for relation resolution.
 *
 * Implementations must batch fetch related records and return mutated items.
 */
interface RelationResolver
{
    public function resolve(array $items, string $relation, array $config, RelationEngine $engine): array;
}
