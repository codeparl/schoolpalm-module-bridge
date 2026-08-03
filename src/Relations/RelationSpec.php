<?php

namespace SchoolPalm\ModuleBridge\Relations;

use SchoolPalm\ModuleBridge\Support\Helper;

/**
 * Formal relation spec constants and schema rules.
 */
class RelationSpec
{
    public const VERSION = '1.0.0';

    /**
     * @return array<string, array<int, string>>
     */
    public static function requiredKeysByType(): array
    {
        return [
            'belongsTo' => ['contract', 'local_key', 'foreign_key'],
            'hasOne' => ['contract', 'local_key', 'foreign_key'],
            'hasMany' => ['contract', 'local_key', 'foreign_key'],
            'belongsToMany' => [
                'contract',
                'pivot_contract',
                'pivot_parent_key',
                'pivot_related_key',
                'local_key',
                'foreign_key',
            ],
            'morphOne' => ['contract', 'local_key', 'foreign_key', 'morph_type_key', 'morph_type'],
            'morphMany' => ['contract', 'local_key', 'foreign_key', 'morph_type_key', 'morph_type'],
            'morphTo' => ['morph_map', 'morph_type_key', 'morph_id_key', 'foreign_key'],
        ];
    }


public static function normalizeRelations(array $uiRelations): array
{
    if (empty($uiRelations)) {
        return [];
    }

    return array_map(function (array $relation) {
        $key = $relation['module_key'] ?? '';
        $contractClass = Helper::moduleKeyToNamespace($key) . '\\Contracts';
        $relation['contract'] = $contractClass . '\\' . ($relation['contract_name'] ?? '');
        return $relation;
    }, $uiRelations);
}

}
