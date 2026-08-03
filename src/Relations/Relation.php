<?php

namespace SchoolPalm\ModuleBridge\Relations;

/**
 * Fluent helpers for declaring relation configuration arrays.
 *
 * These static builders define the shape consumed by RelationEngine resolvers.
 * The returned arrays are transport-friendly and module-safe (contracts + DTO data only).
 */
class Relation
{
    /**
     * -------------------------------------------------
     * Current module belongs to another module entity
     * Example:
     * IdManager.student_id -> Student.id
     * -------------------------------------------------
     */
    public static function belongsTo(
        string $contract,
        string $localKey,
        string $foreignKey = 'id'
    ): array {
        return [
            'type' => 'belongsTo',
            'contract' => $contract,
            'local_key' => $localKey,
            'foreign_key' => $foreignKey,
        ];
    }

    /**
     * -------------------------------------------------
     * Current module owns one related entity
     * Example:
     * Student.id -> StudentProfile.student_id
     * -------------------------------------------------
     */
    public static function hasOne(
        string $contract,
        string $foreignKey,
        string $localKey = 'id'
    ): array {
        return [
            'type' => 'hasOne',
            'contract' => $contract,
            'local_key' => $localKey,
            'foreign_key' => $foreignKey,
        ];
    }

    /**
     * -------------------------------------------------
     * Current module owns many related entities
     * Example:
     * Student.id -> Results.student_id
     * -------------------------------------------------
     */
    public static function hasMany(
        string $contract,
        string $foreignKey,
        string $localKey = 'id'
    ): array {
        return [
            'type' => 'hasMany',
            'contract' => $contract,
            'local_key' => $localKey,
            'foreign_key' => $foreignKey,
        ];
    }

    /**
     * -------------------------------------------------
     * Many-to-many relationship
     * Example:
     * Student <-> Club through memberships
     * -------------------------------------------------
     */
    public static function belongsToMany(
        string $contract,
        string $pivotContract,
        string $pivotParentKey,
        string $pivotRelatedKey,
        string $localKey = 'id',
        string $foreignKey = 'id'
    ): array {
        return [
            'type' => 'belongsToMany',
            'contract' => $contract,
            'pivot_contract' => $pivotContract,
            'pivot_parent_key' => $pivotParentKey,
            'pivot_related_key' => $pivotRelatedKey,
            'local_key' => $localKey,
            'foreign_key' => $foreignKey,
        ];
    }

    /**
     * -------------------------------------------------
     * Polymorphic relation (optional future support)
     * Example:
     * Comment.commentable_id + commentable_type
     * -------------------------------------------------
     */
    public static function morphTo(
        array $morphMap,
        string $typeField = 'morph_type',
        string $idField = 'morph_id',
        string $foreignKey = 'id'
    ): array {
        return [
            'type' => 'morphTo',
            'morph_map' => $morphMap,
            'morph_type_key' => $typeField,
            'morph_id_key' => $idField,
            'foreign_key' => $foreignKey,
        ];
    }

    /**
     * One polymorphic related entity scoped by morph type.
     */
    public static function morphOne(
        string $contract,
        string $foreignKey,
        string $morphType,
        string $localKey = 'id',
        string $morphTypeKey = 'morph_type'
    ): array {
        return [
            'type' => 'morphOne',
            'contract' => $contract,
            'local_key' => $localKey,
            'foreign_key' => $foreignKey,
            'morph_type_key' => $morphTypeKey,
            'morph_type' => $morphType,
        ];
    }

    /**
     * Many polymorphic related entities scoped by morph type.
     */
    public static function morphMany(
        string $contract,
        string $foreignKey,
        string $morphType,
        string $localKey = 'id',
        string $morphTypeKey = 'morph_type'
    ): array {
        return [
            'type' => 'morphMany',
            'contract' => $contract,
            'local_key' => $localKey,
            'foreign_key' => $foreignKey,
            'morph_type_key' => $morphTypeKey,
            'morph_type' => $morphType,
        ];
    }
}
