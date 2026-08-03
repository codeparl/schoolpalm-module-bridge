<?php

namespace SchoolPalm\ModuleBridge\Database;

use RuntimeException;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeTraverser;
use PhpParser\Node;

class MigrationSignatureValidator
{
    protected const BASE_CLASS = 'SchoolPalm\\ModuleBridge\\Database\\BaseMigration';

    public static function validate(string $migrationPath): void
    {
        $code = file_get_contents($migrationPath);

        self::validateForbiddenSchema($code);
        self::validateInheritance($code);
    }

    protected static function validateInheritance(string $code): void
    {
        $parser = (new ParserFactory())
            ->createForNewestSupportedVersion();

        $ast = $parser->parse($code);

        if (!$ast) {
            throw new RuntimeException("Migration parsing failed.");
        }

        $found = false;
        $baseClass = self::BASE_CLASS;

        $traverser = new NodeTraverser();

        $traverser->addVisitor(new class($found, $baseClass) extends NodeVisitorAbstract {

            private bool $found;
            private string $baseClass;

            public function __construct(bool &$found, string $baseClass)
            {
                $this->found = &$found;
                $this->baseClass = ltrim($baseClass, '\\');
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_ && $node->extends) {

                    $extendsName = $node->extends->toString();

                    $baseShort = basename(str_replace('\\', '/', $this->baseClass));

                    if (
                        $extendsName === $this->baseClass ||
                        $extendsName === $baseShort
                    ) {
                        $this->found = true;
                    }
                }

                if ($node instanceof Node\Name && $node->toString() === 'Schema') {
                    throw new RuntimeException(
                        "Direct Schema usage is forbidden. Use  our provided DSL."
                    );
                }
            }
        });

        $traverser->traverse($ast);

        if (!$found) {
            throw new RuntimeException(
                "Migration signature violation: Migration must extend BaseMigration."
            );
        }
    }

    protected static function validateForbiddenSchema(string $code): void
    {
        $forbiddenPatterns = [
            '/\$table->id\s*\(/i',
            '/\$table->increments\s*\(/i',
            '/\$table->integer\s*\(\s*[\'"]id[\'"]\s*\)/i',
            '/school_id/i',
            '/created_at/i',
            '/updated_at/i'
        ];

        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $code)) {
                throw new RuntimeException(
                    "Migration signature violation: SDK reserved schema columns are not allowed."
                );
            }
        }
    }
}
