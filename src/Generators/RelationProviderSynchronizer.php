<?php

namespace SchoolPalm\ModuleBridge\Generators;

use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

/**
 * Merges canonical relation definitions into an existing provider class
 * and regenerates the provider file through RelationProviderGenerator.
 *
 * This synchronizer treats relations as a plural collection:
 * [
 *     [
 *         'name' => 'students',
 *         'type' => 'hasMany',
 *     ],
 *     [
 *         'name' => 'teacher',
 *         'type' => 'belongsTo',
 *     ]
 * ]
 */
class RelationProviderSynchronizer
{
    public function __construct(
        private readonly ?RelationProviderGenerator $generator = null
    ) {
    }

    /**
     * Create or update multiple relations.
     *
     * @param class-string $providerClass
     * @param array<int,array<string,mixed>> $submittedRelations
     */
    public function createOrUpdateRelations(
        string $providerClass,
        array $submittedRelations,
        string $outputPath
    ): void {
        [$namespace, $className] = $this->splitClassName($providerClass);

        $existingDefinitions = [];

        if (class_exists($providerClass)) {
            if (!method_exists($providerClass, 'getDefinitions')) {
                throw new InvalidArgumentException(
                    "Relation provider class '{$providerClass}' must define a static getDefinitions() method."
                );
            }

            $existingDefinitions = $providerClass::getDefinitions();

            if (!is_array($existingDefinitions)) {
                throw new InvalidArgumentException(
                    "Relation provider class '{$providerClass}' returned invalid definitions."
                );
            }
        }

        $mergedDefinitions = $existingDefinitions;

        foreach ($submittedRelations as $submittedRelation) {
            $this->validateRelation($submittedRelation);

            $mergedDefinitions = $this->mergeByName(
                $mergedDefinitions,
                $submittedRelation
            );
        }

        $this->generator()->generate([
            'namespace' => $namespace,
            'class' => $className,
            'relations' => array_values($mergedDefinitions),
        ], $outputPath);
    }

    /**
     * Alias for plural updates.
     *
     * @param class-string $providerClass
     * @param array<int,array<string,mixed>> $submittedRelations
     */
    public function updateOrInsertRelations(
        string $providerClass,
        array $submittedRelations,
        string $outputPath
    ): void {

        if (!class_exists($providerClass)) {
            throw new InvalidArgumentException(
                "Relation provider class '{$providerClass}' does not exist."
            );
        }

        if (!method_exists($providerClass, 'getDefinitions')) {
            throw new InvalidArgumentException(
                "Relation provider class '{$providerClass}' must define a static getDefinitions() method."
            );
        }

        $existingDefinitions = $providerClass::getDefinitions();

        if (!is_array($existingDefinitions)) {
            throw new InvalidArgumentException(
                "Relation provider class '{$providerClass}' returned invalid definitions."
            );
        }

        $mergedDefinitions = $existingDefinitions;

        foreach ($submittedRelations as $submittedRelation) {
            $this->validateRelation($submittedRelation);

            $mergedDefinitions = $this->mergeByName(
                $mergedDefinitions,
                $submittedRelation
            );
        }

        [$namespace, $className] = $this->splitClassName($providerClass);

        $this->generator()->generate([
            'namespace' => $namespace,
            'class' => $className,
            'relations' => array_values($mergedDefinitions),
        ], $outputPath);
    }

    /**
     * Update or insert a single relation.
     *
     * @param class-string $providerClass
     * @param array<string,mixed> $submittedRelation
     */
    public function updateOrInsertRelation(
        string $providerClass,
        array $submittedRelation,
        string $outputPath
    ): void {
        $this->updateOrInsertRelations(
            $providerClass,
            [$submittedRelation],
            $outputPath
        );
    }

    /**
     * Sync relations from the UI.
     * If the provider class does not exist, skip processing (no error, no generation).
     *
     * @param class-string $providerClass
     * @param array<int,array<string,mixed>> $submittedRelations
     * @param string $outputPath
     */
    public function syncFromUi(
        string $providerClass,
        array $submittedRelations,
        string $outputPath
    ): void {
        // Skip if the class does not exist – the user may have not created the relations file yet.
        if (!class_exists($providerClass)) {
            Log::warning("Relation provider class '{$providerClass}' not found. Skipping sync.");
            return;
        }

        // If class exists, ensure it has the required method
        if (!method_exists($providerClass, 'getDefinitions')) {
            Log::warning("Relation provider class '{$providerClass}' does not have getDefinitions(). Skipping sync.");
            return;
        }

        [$namespace, $className] = $this->splitClassName($providerClass);

        $final = [];

        foreach ($submittedRelations as $relation) {
            $this->validateRelation($relation);
            $final[] = $relation;
        }

        $this->generator()->generate([
            'namespace' => $namespace,
            'class' => $className,
            'relations' => array_values($final),
        ], $outputPath);
    }

    /**
     * @param array<string,mixed> $relation
     */
    private function validateRelation(array $relation): void
    {
        $relationName = (string) ($relation['name'] ?? '');
        $relationType = (string) ($relation['type'] ?? '');

        if ($relationName === '' || $relationType === '') {
            throw new InvalidArgumentException(
                'Each relation must include non-empty name and type keys.'
            );
        }
    }

    /**
     * Update an existing relation definition by name
     * or append it when missing.
     *
     * @param array<int,mixed> $existingDefinitions
     * @param array<string,mixed> $submittedRelation
     * @return array<int,array<string,mixed>>
     */
    private function mergeByName(
        array $existingDefinitions,
        array $submittedRelation
    ): array {
        $merged = [];

        $targetName = (string) $submittedRelation['name'];
        $replaced = false;

        foreach ($existingDefinitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $existingName = (string) ($definition['name'] ?? '');

            if (
                $existingName !== ''
                && $existingName === $targetName
            ) {
                $merged[] = $submittedRelation;
                $replaced = true;
                continue;
            }

            $merged[] = $definition;
        }

        if (!$replaced) {
            $merged[] = $submittedRelation;
        }

        return array_values($merged);
    }

    /**
     * @param class-string $providerClass
     * @return array{0:string,1:string}
     */
    private function splitClassName(string $providerClass): array
    {
        $normalized = ltrim($providerClass, '\\');

        $className = class_basename($normalized);

        $namespace = trim(
            substr($normalized, 0, -strlen($className)),
            '\\'
        );

        if ($namespace === '' || $className === '') {
            throw new InvalidArgumentException(
                "Invalid relation provider class '{$providerClass}'."
            );
        }

        return [$namespace, $className];
    }

    private function generator(): RelationProviderGenerator
    {
        return $this->generator
            ?? new RelationProviderGenerator();
    }
}