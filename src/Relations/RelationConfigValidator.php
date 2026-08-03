<?php

namespace SchoolPalm\ModuleBridge\Relations;

use SchoolPalm\ModuleBridge\Relations\Exceptions\InvalidRelationConfigException;
use SchoolPalm\ModuleBridge\Relations\Exceptions\UnsupportedRelationTypeException;

/**
 * Validates relation config against RelationSpec schemas.
 */
class RelationConfigValidator
{
    /**
     * @param array<string,mixed> $config
     */
    public function validate(string $relationName, array $config, array $knownTypes): void
    {
        $type = (string) ($config['type'] ?? 'belongsTo');

        if (!in_array($type, $knownTypes, true)) {
            throw new UnsupportedRelationTypeException(
                "Unsupported relation type '{$type}' for relation '{$relationName}'."
            );
        }

        $requiredByType = RelationSpec::requiredKeysByType();
        $requiredKeys = $requiredByType[$type] ?? [];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw new InvalidRelationConfigException(
                    "Relation '{$relationName}' of type '{$type}' is missing required key '{$key}'."
                );
            }
        }

        $this->assertNonEmptyStringKeys($relationName, $type, $config, [
            'contract', 'pivot_contract', 'local_key', 'foreign_key',
            'pivot_parent_key', 'pivot_related_key', 'morph_type_key', 'morph_type', 'morph_id_key',
        ]);

        if ($type === 'morphTo') {
            $map = $config['morph_map'] ?? null;
            if (!is_array($map) || empty($map)) {
                throw new InvalidRelationConfigException(
                    "Relation '{$relationName}' of type 'morphTo' must define a non-empty morph_map."
                );
            }

            foreach ($map as $morphType => $contract) {
                if (!is_string($morphType) || $morphType === '' || !is_string($contract) || $contract === '') {
                    throw new InvalidRelationConfigException(
                        "Relation '{$relationName}' has invalid morph_map entries."
                    );
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $config
     * @param array<int,string> $keys
     */
    private function assertNonEmptyStringKeys(string $relationName, string $type, array $config, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $config)) {
                continue;
            }

            $value = $config[$key];
            if (!is_string($value) || $value === '') {
                throw new InvalidRelationConfigException(
                    "Relation '{$relationName}' of type '{$type}' requires '{$key}' to be a non-empty string."
                );
            }
        }
    }
}
