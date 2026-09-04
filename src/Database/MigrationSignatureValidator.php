<?php

namespace SchoolPalm\ModuleBridge\Database;

use RuntimeException;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeTraverser;
use PhpParser\Node;

class MigrationSignatureValidator
{
    protected const BASE_CLASS =
    'SchoolPalm\\ModuleBridge\\Database\\BaseMigration';

    protected const SCHEMA_BUILDER =
    'SchoolPalm\\ModuleBridge\\Database\\SchemaBuilder';

    public static function validate(string $migrationPath): void
    {
        if (!is_file($migrationPath)) {
            throw new RuntimeException(
                "Migration file not found: {$migrationPath}"
            );
        }

        $code = file_get_contents($migrationPath);

        if ($code === false) {
            throw new RuntimeException(
                "Unable to read migration: {$migrationPath}"
            );
        }

        self::validateForbiddenSchema($code);
        self::validateInheritance($code);
        self::validateSchemaBuilderUsage($code);
    }

    /*
    |--------------------------------------------------------------------------
    | Inheritance
    |--------------------------------------------------------------------------
    */

    protected static function validateInheritance(
        string $code
    ): void {
        $parser = (new ParserFactory())
            ->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Migration parsing failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (!$ast) {
            throw new RuntimeException(
                "Migration parsing failed."
            );
        }

        $found = false;

        $baseClass = ltrim(
            self::BASE_CLASS,
            '\\'
        );

        $baseShort = basename(
            str_replace(
                '\\',
                '/',
                $baseClass
            )
        );

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class(
                $found,
                $baseClass,
                $baseShort
            ) extends NodeVisitorAbstract {

                private bool $found;

                private string $baseClass;

                private string $baseShort;

                public function __construct(
                    bool &$found,
                    string $baseClass,
                    string $baseShort
                ) {
                    $this->found = &$found;
                    $this->baseClass = $baseClass;
                    $this->baseShort = $baseShort;
                }

                public function enterNode(Node $node)
                {
                    if (
                        $node instanceof Node\Stmt\Class_ &&
                        $node->extends
                    ) {
                        $extendsName =
                            $node->extends->toString();

                        if (
                            $extendsName === $this->baseClass ||
                            $extendsName === $this->baseShort
                        ) {
                            $this->found = true;
                        }
                    }
                }
            }
        );

        $traverser->traverse($ast);

        if (!$found) {
            throw new RuntimeException(
                "Migration signature violation: " .
                    "Migration must extend BaseMigration."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SchemaBuilder Contract
    |--------------------------------------------------------------------------
    |
    | The generated migration must use:
    |
    |     function (SchemaBuilder $table)
    |
    | instead of:
    |
    |     function (Blueprint $table)
    |
    */

    protected static function validateSchemaBuilderUsage(
        string $code
    ): void {
        $parser = (new ParserFactory())
            ->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Migration parsing failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (!$ast) {
            throw new RuntimeException(
                "Migration parsing failed."
            );
        }

        $schemaBuilderClass =
            ltrim(self::SCHEMA_BUILDER, '\\');

        $schemaBuilderShort =
            basename(
                str_replace(
                    '\\',
                    '/',
                    $schemaBuilderClass
                )
            );

        $foundCreateTable = false;
        $foundSchemaBuilderCallback = false;

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class(
                $foundCreateTable,
                $foundSchemaBuilderCallback,
                $schemaBuilderClass,
                $schemaBuilderShort
            ) extends NodeVisitorAbstract {

                private bool $foundCreateTable;

                private bool $foundSchemaBuilderCallback;

                private string $schemaBuilderClass;

                private string $schemaBuilderShort;

                public function __construct(
                    bool &$foundCreateTable,
                    bool &$foundSchemaBuilderCallback,
                    string $schemaBuilderClass,
                    string $schemaBuilderShort
                ) {
                    $this->foundCreateTable =
                        &$foundCreateTable;

                    $this->foundSchemaBuilderCallback =
                        &$foundSchemaBuilderCallback;

                    $this->schemaBuilderClass =
                        $schemaBuilderClass;

                    $this->schemaBuilderShort =
                        $schemaBuilderShort;
                }

                public function enterNode(Node $node)
                {
                    /*
                 * ----------------------------------------------------------
                 * 1. Blueprint is never allowed.
                 * ----------------------------------------------------------
                 */

                    if ($node instanceof Node\Name) {
                        $name = ltrim(
                            $node->toString(),
                            '\\'
                        );

                        if (
                            $name === 'Blueprint' ||
                            str_ends_with(
                                $name,
                                '\\Blueprint'
                            )
                        ) {
                            throw new RuntimeException(
                                "Migration signature violation: " .
                                    "Blueprint is not allowed. " .
                                    "Use SchemaBuilder."
                            );
                        }

                        /*
                     * ------------------------------------------------------
                     * 2. Direct Schema usage is forbidden.
                     * ------------------------------------------------------
                     *
                     * We only reject an actual Schema class reference.
                     *
                     * This does NOT interfere with:
                     *
                     *     $table->foreign(...)
                     *     $table->constrained(...)
                     *     $table->cascadeOnDelete(...)
                     *
                     * because those are method calls on SchemaBuilder.
                     */

                        if (
                            $name === 'Schema' ||
                            $name ===
                            'Illuminate\\Support\\Facades\\Schema'
                        ) {
                            throw new RuntimeException(
                                "Migration signature violation: " .
                                    "Direct Schema usage is forbidden. " .
                                    "Use SchemaBuilder."
                            );
                        }
                    }

                    /*
                 * ----------------------------------------------------------
                 * 3. Find createTable(...)
                 * ----------------------------------------------------------
                 */

                    if (
                        !$node instanceof Node\Expr\MethodCall ||
                        !(
                            $node->name instanceof Node\Identifier
                        ) ||
                        $node->name->toString() !== 'createTable'
                    ) {
                        return;
                    }

                    $this->foundCreateTable = true;

                    if (count($node->args) < 2) {
                        throw new RuntimeException(
                            "Migration signature violation: " .
                                "createTable() must receive a " .
                                "SchemaBuilder callback."
                        );
                    }

                    $callback =
                        $node->args[1]->value;

                    /*
                 * ----------------------------------------------------------
                 * 4. Closure callback
                 * ----------------------------------------------------------
                 */

                    if (
                        !$callback instanceof Node\Expr\Closure
                    ) {
                        throw new RuntimeException(
                            "Migration signature violation: " .
                                "createTable() must use a " .
                                "SchemaBuilder closure."
                        );
                    }

                    if (count($callback->params) === 0) {
                        throw new RuntimeException(
                            "Migration signature violation: " .
                                "createTable() callback must " .
                                "typehint SchemaBuilder."
                        );
                    }

                    $parameter =
                        $callback->params[0];

                    if (!$parameter->type) {
                        throw new RuntimeException(
                            "Migration signature violation: " .
                                "createTable() callback parameter " .
                                "must be typehinted SchemaBuilder."
                        );
                    }

                    if (
                        !$parameter->type instanceof Node\Name
                    ) {
                        throw new RuntimeException(
                            "Migration signature violation: " .
                                "createTable() callback parameter " .
                                "must be typehinted SchemaBuilder."
                        );
                    }

                    $type =
                        ltrim(
                            $parameter->type->toString(),
                            '\\'
                        );

                    /*
                 * Accept:
                 *
                 * SchemaBuilder
                 *
                 * and:
                 *
                 * SchoolPalm\ModuleBridge\Database\SchemaBuilder
                 *
                 * and:
                 *
                 * \SchoolPalm\ModuleBridge\Database\SchemaBuilder
                 */
                    if (
                        $type !== $this->schemaBuilderShort &&
                        $type !== $this->schemaBuilderClass
                    ) {
                        throw new RuntimeException(
                            "Migration signature violation: " .
                                "createTable() callback must use " .
                                "SchemaBuilder, not {$type}."
                        );
                    }

                    $this->foundSchemaBuilderCallback = true;
                }
            }
        );

        $traverser->traverse($ast);

        /*
    |--------------------------------------------------------------------------
    | 5. Required createTable()
    |--------------------------------------------------------------------------
    */

        if (!$foundCreateTable) {
            throw new RuntimeException(
                "Migration signature violation: " .
                    "Migration must use createTable()."
            );
        }

        if (!$foundSchemaBuilderCallback) {
            throw new RuntimeException(
                "Migration signature violation: " .
                    "createTable() must use a SchemaBuilder callback."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Forbidden Reserved Schema
    |--------------------------------------------------------------------------
    |
    | These columns belong to BaseMigration and must never be manually
    | declared by module migrations.
    |
    */

    protected static function validateForbiddenSchema(
        string $code
    ): void {
        $forbiddenPatterns = [

            /*
             * Legacy primary-key definitions.
             */
            '/\$table\s*->\s*id\s*\(/i',

            '/\$table\s*->\s*increments\s*\(/i',

            '/\$table\s*->\s*bigIncrements\s*\(/i',

            '/\$table\s*->\s*integer\s*\(\s*[\'"]id[\'"]\s*\)/i',

            '/\$table\s*->\s*bigInteger\s*\(\s*[\'"]id[\'"]\s*\)/i',

            '/\$table\s*->\s*uuid\s*\(\s*[\'"]id[\'"]\s*\)/i',

            /*
             * SchoolPalm automatically owns the tenant column.
             */
            '/\$table\s*->\s*\w+\s*\(\s*[\'"]school_id[\'"]/i',

            /*
             * SchoolPalm automatically owns timestamps.
             */
            '/\$table\s*->\s*\w+\s*\(\s*[\'"]created_at[\'"]/i',

            '/\$table\s*->\s*\w+\s*\(\s*[\'"]updated_at[\'"]/i',

            /*
             * Explicit Laravel Blueprint import.
             */
            '/use\s+Illuminate\\\\Database\\\\Schema\\\\Blueprint\s*;/i',

            /*
             * Explicit Laravel Schema import.
             */
            '/use\s+Illuminate\\\\Support\\\\Facades\\\\Schema\s*;/i',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $code, $matches)) {
                throw new RuntimeException(
                    "Migration signature violation: " .
                        "SDK reserved schema or forbidden Laravel " .
                        "schema API detected. " .
                        "Matched: " .
                        ($matches[0] ?? $pattern)
                );
            }
        }
    }
}
