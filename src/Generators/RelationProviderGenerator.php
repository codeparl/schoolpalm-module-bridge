<?php

namespace SchoolPalm\ModuleBridge\Generators;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use SchoolPalm\ModuleBridge\Relations\RelationProvider;

class RelationProviderGenerator
{
    public function generate(array $definition, string $outputPath): void
    {
        $namespace = (string) ($definition['namespace'] ?? '');
        $className = (string) ($definition['class'] ?? '');
        $relations = $definition['relations'] ?? [];

      

        if ($namespace === '' || $className === '') {
            throw new InvalidArgumentException('Both namespace and class are required.');
        }

        if (!is_array($relations)) {
            throw new InvalidArgumentException('relations must be an array.');
        }

        $canonicalRelations = [];
        $relationLines = [];

        $imports = [
            'SchoolPalm\\ModuleBridge\\Relations\\Relation',
            'SchoolPalm\\ModuleBridge\\Relations\\RelationProvider',
        ];

        foreach ($relations as $relation) {

            if (!is_array($relation)) {
                continue;
            }

            $moduleKey = (string) ($relation['module_key'] ?? '');
            $name = (string) ($relation['name'] ?? '');
            $type = (string) ($relation['type'] ?? '');

            if ($moduleKey === '' || $name === '' || $type === '') {
                continue;
            }

            /**
             * Keep full metadata (for debugging, auditing, future validation)
             */
            $canonicalRelations[] = $relation;

            /**
             * =========================================================
             * CRITICAL: GLOBAL UNIQUE KEY
             * =========================================================
             * This is what guarantees no collisions across modules.
             */
            $fullKey = $moduleKey . '.' . $name;

            $relationLines[] =
                "            '" . $this->escape($fullKey) . "' => "
                . $this->buildRelationExpression($relation, $imports)
                . " // module: {$moduleKey}"
                . ',';
        }

        $useStatements = implode(
            "\n",
            array_map(
                fn($i) => "use {$i};",
                $this->unique($imports)
            )
        );

        $relationsBody = empty($relationLines)
            ? "            // No relations defined yet.\n"
            : implode("\n", $relationLines) . "\n";

        $definitionsLiteral = var_export($canonicalRelations, true);

        $template = <<<PHP
<?php

namespace {$namespace};

{$useStatements}

class {$className} implements RelationProvider
{
    /**
     * Raw relation metadata (includes module_key for traceability)
     */
    protected static array \$definitions = {$definitionsLiteral};

    public static function getDefinitions(): array
    {
        return static::\$definitions;
    }

    /**
     * =========================================================
     * IMPORTANT:
     * Registry key format:
     *   module_key + relation_name
     * Example:
     *   unnovatebrains.common.student.Student
     * =========================================================
     */
    public function relations(): array
    {
        return [
{$relationsBody}        ];
    }
}
PHP;

        File::put($outputPath, $template);
    }

    /*
    |--------------------------------------------------------------------------
    | Relation Builder
    |--------------------------------------------------------------------------
    */

    private function buildRelationExpression(array $relation, array &$imports): string
    {
        return match ($relation['type']) {
            'belongsTo' => $this->belongsTo($relation, $imports),
            'hasOne' => $this->hasOne($relation, $imports),
            'hasMany' => $this->hasMany($relation, $imports),
            'belongsToMany' => $this->belongsToMany($relation, $imports),
            'morphOne' => $this->morphOne($relation, $imports),
            'morphMany' => $this->morphMany($relation, $imports),
            'morphTo' => $this->morphTo($relation, $imports),
            default => $this->fallback($relation),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Relation Builders
    |--------------------------------------------------------------------------
    */

    private function belongsTo(array $r, array &$i): string
    {
        $i[] = $r['contract'];

        return "Relation::belongsTo("
            . class_basename($r['contract']) . "::class, '"
            . $this->escape($r['local_key']) . "', '"
            . $this->escape($r['foreign_key'] ?? 'id') . "')";
    }

    private function hasOne(array $r, array &$i): string
    {
        $i[] = $r['contract'];

        return "Relation::hasOne("
            . class_basename($r['contract']) . "::class, '"
            . $this->escape($r['foreign_key']) . "', '"
            . $this->escape($r['local_key'] ?? 'id') . "')";
    }

    private function hasMany(array $r, array &$i): string
    {
        $i[] = $r['contract'];

        return "Relation::hasMany("
            . class_basename($r['contract']) . "::class, '"
            . $this->escape($r['foreign_key']) . "', '"
            . $this->escape($r['local_key'] ?? 'id') . "')";
    }

    private function belongsToMany(array $r, array &$i): string
    {
        $i[] = $r['contract'];
        $i[] = $r['pivot_contract'];

        return "Relation::belongsToMany("
            . class_basename($r['contract']) . "::class, "
            . class_basename($r['pivot_contract']) . "::class, '"
            . $this->escape($r['pivot_parent_key']) . "', '"
            . $this->escape($r['pivot_related_key']) . "', '"
            . $this->escape($r['local_key'] ?? 'id') . "', '"
            . $this->escape($r['foreign_key'] ?? 'id') . "')";
    }

    private function morphOne(array $r, array &$i): string
    {
        $i[] = $r['contract'];

        return "Relation::morphOne("
            . class_basename($r['contract']) . "::class, '"
            . $this->escape($r['foreign_key']) . "', '"
            . $this->escape($r['morph_type']) . "', '"
            . $this->escape($r['local_key'] ?? 'id') . "', '"
            . $this->escape($r['morph_type_key'] ?? 'morph_type') . "')";
    }

    private function morphMany(array $r, array &$i): string
    {
        $i[] = $r['contract'];

        return "Relation::morphMany("
            . class_basename($r['contract']) . "::class, '"
            . $this->escape($r['foreign_key']) . "', '"
            . $this->escape($r['morph_type']) . "', '"
            . $this->escape($r['local_key'] ?? 'id') . "', '"
            . $this->escape($r['morph_type_key'] ?? 'morph_type') . "')";
    }

    private function morphTo(array $r, array &$i): string
    {
        foreach (($r['morph_map'] ?? []) as $c) {
            $i[] = $c;
        }

        return "Relation::morphTo([])";
    }

    private function fallback(array $r): string
    {
        return var_export($r, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function unique(array $i): array
    {
        return array_values(array_unique($i));
    }

    private function escape(string $v): string
    {
        return str_replace("'", "\\'", $v);
    }
}