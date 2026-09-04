<?php

namespace SchoolPalm\ModuleBridge\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Manifest\ManifestFactory;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;
use SchoolPalm\ModuleBridge\Profiles\ContractProfile;
use SchoolPalm\ModuleBridge\Support\Helper;

class ContractScaffoldGenerator
{
    private ReflectionScanner $scanner;
    private StubBuilder $stubBuilder;
    private ?ModuleManifest $manifest = null;
    private string $manifestPath;

    /**
     * Holds fully qualified class names discovered dynamically via Reflection
     * to be added as `use` statements at the top of the file.
     */
    protected array $dynamicImports = [];

    public function __construct()
    {
        $this->scanner = new ReflectionScanner();
        $this->stubBuilder = new StubBuilder();
    }

    public function generate(
        string $contractInterface,
        string $implementationClass,
        string $outputPath,
        ContractProfile $profile,
        ?string $manifestPath = null
    ): void {

        if (!interface_exists($contractInterface)) {
            return;
        }

        $reflection = new \ReflectionClass($contractInterface);


        if (!$reflection->isInterface()) {
            return;
        }

        // Reset dynamic imports for this generation cycle
        $this->dynamicImports = [];

        // Load manifest if path provided
        $this->manifestPath = $manifestPath;
        if ($this->manifestPath && File::exists($this->manifestPath)) {
            $this->manifest = new ModuleManifest($manifestPath);
        }

        $interfaceMethods = $this->scanner
            ->scanInterfaceMethods($contractInterface);

        $existingCode = file_exists($outputPath)
            ? file_get_contents($outputPath)
            : '';

        $generatedMethods = '';

        /*
        |--------------------------------------------------------------------------
        | RESOLVE SUPPORT CLASSES
        |--------------------------------------------------------------------------
        */

        $dtoClass = $this->resolveDtoFromContract(
            $contractInterface
        );

        $modelClass = $this->resolveModelFromContract(
            $contractInterface
        );

        $entityName = $this->resolveEntity($contractInterface);
        $hookClass = $this->resolveHookClass($entityName, $implementationClass);
        $validatorClass = $this->resolveValidatorClass($entityName, $implementationClass);
        $hookShortName = class_basename($hookClass);

        $dataFactoryClass = $profile->useDataFactory
            ? $this->resolveDataFactoryFromContract(
                $contractInterface
            )
            : null;

        /*
        |--------------------------------------------------------------------------
        | GENERATE VALIDATOR & HOOK CLASS (ONLY FOR INTERNAL PROFILES)
        |--------------------------------------------------------------------------
        */

        if ($profile->type === 'internal') {

            $this->generateValidatorClass($entityName, $dtoClass, $validatorClass, $outputPath);
            $this->generateHookClass($entityName, $hookClass, $validatorClass, $outputPath);
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE EVENTS (ONCE per contract, available to ALL services)
        |--------------------------------------------------------------------------
        */
        $eventImports = [];
        $eventMapCode = '';

        if ($this->manifest) {
            $eventGenerator = new EventGenerator(
                $contractInterface,
                $implementationClass,
                $outputPath,
                $this->manifest,
                $this->manifestPath,
                $profile
            );

            $generatedEvents = $eventGenerator->generate();
            $eventImports = $eventGenerator->getEventImports();

            // Ensure all event classes are queued for importing using the entity sub-namespace
            foreach ($generatedEvents as $event) {
                if (isset($event['class'])) {
                    $this->dynamicImports[] = ltrim($event['class'], '\\');
                }
            }

            $eventMapCode = $this->generateEventMap($generatedEvents, $entityName);
        }

        /*
        |--------------------------------------------------------------------------
        | METHOD GENERATION
        |--------------------------------------------------------------------------
        */

        foreach ($interfaceMethods as $method) {

            $methodName = $method->getName();

            // Skip if already exists
            if (
                !empty($existingCode) &&
                preg_match(
                    "/function\s+{$methodName}\s*\(/",
                    $existingCode
                )
            ) {
                continue;
            }

            // Check if this is a passthrough method (handled by engine)
            if ($this->isPassthroughMethod($methodName)) {
                $generatedMethods .= $this->generatePassthroughMethod($method);
                continue;
            }

            if (
                $profile->useDataFactory &&
                $dataFactoryClass
            ) {

                $generatedMethods .=
                    $this->stubBuilder
                    ->buildMethodStubWithFactory(
                        $method,
                        $dtoClass,
                        $dataFactoryClass,
                        $profile
                    );

                continue;
            }

            $generatedMethods .=
                $this->stubBuilder
                ->buildMethodStub(
                    $method,
                    $dtoClass,
                    $profile
                );
        }

        /*
        |--------------------------------------------------------------------------
        | NAMESPACE
        |--------------------------------------------------------------------------
        */

        $namespace = Helper::beforeLast(
            $implementationClass,
            '\\'
        );

        $className = class_basename(
            $implementationClass
        );

        $contractShortName = Helper::afterLast(
            $contractInterface,
            '\\'
        );

        /*
        |--------------------------------------------------------------------------
        | BASE CLASS
        |--------------------------------------------------------------------------
        */

        $extends = $this->resolveBaseClass(
            $profile
        );

        /*
        |--------------------------------------------------------------------------
        | ENGINE CLASS NAME
        |--------------------------------------------------------------------------
        */

        $engineClass = 'QueryEngine';
        $engineNamespace = 'SchoolPalm\\ModuleBridge\\Database';

        /*
        |--------------------------------------------------------------------------
        | IMPORTS
        |--------------------------------------------------------------------------
        */

        $imports = array_merge(
            [
                $contractInterface,
                $modelClass,
                $engineNamespace . '\\' . $engineClass,
                'SchoolPalm\\ModuleBridge\\Context\\CurrentContext',
            ],
            $this->stubBuilder->getImports(),
            $this->dynamicImports
        );

        // Add hook class import for internal services
        if ($profile->type === 'internal') {
            $imports[] = $hookClass;
        }

        // Add event imports for ALL services
        if (!empty($eventImports)) {
            $imports = array_merge($imports, $eventImports);
        }

        if (
            $profile->useDataFactory &&
            $dataFactoryClass
        ) {

            $imports[] = $dataFactoryClass;
        }

        if ($extends === 'SnapshotService') {

            $imports[] =
                'SchoolPalm\\ModuleBridge\\Services\\SnapshotService';
        }

        $imports = array_unique($imports);
        // Filter out empty strings
        $imports = array_filter($imports, fn($i) => !empty(trim($i)));

        sort($imports);

        $useStatements = implode(
            "\n",
            array_map(
                fn($i) => "use {$i};",
                $imports
            )
        );

        /*
        |--------------------------------------------------------------------------
        | FACTORY PROPERTY + CONSTRUCTOR
        |--------------------------------------------------------------------------
        */

        $factoryProperty = '';
        $factoryConstructor = '';

        if (
            $profile->useDataFactory &&
            $dataFactoryClass
        ) {

            $factoryProperty =
                $this->stubBuilder
                ->buildFactoryProperty(
                    $dataFactoryClass
                );

            $factoryConstructor =
                $this->stubBuilder
                ->buildFactoryConstructor(
                    $dataFactoryClass
                );
        }

        /*
        |--------------------------------------------------------------------------
        | ENGINE PROPERTY + CONSTRUCTOR
        |--------------------------------------------------------------------------
        */

        $modelShortName = class_basename($modelClass);

        $engineProperty = "    /**\n";
        $engineProperty .= "     * The query engine instance.\n";
        $engineProperty .= "     */\n";
        $engineProperty .= "    protected QueryEngine \$engine;\n\n";

        $engineConstructor = "    public function __construct()\n";
        $engineConstructor .= "    {\n";
        $engineConstructor .= "        parent::__construct();\n";
        $engineConstructor .= "        \n";
        $engineConstructor .= "        \$this->engine = new QueryEngine(\n";
        $engineConstructor .= "            new {$modelShortName}(),\n";
        $engineConstructor .= "            " . ($profile->type === 'internal' ? "new {$hookShortName}()" : "null") . "\n";
        $engineConstructor .= "        );\n";
        $engineConstructor .= "    }\n\n";
        $engineConstructor .= "    protected function getEngine(): QueryEngine\n";
        $engineConstructor .= "    {\n";
        $engineConstructor .= "        return \$this->engine;\n";
        $engineConstructor .= "    }\n\n";

        /*
        |--------------------------------------------------------------------------
        | MODULE SETTINGS
        |--------------------------------------------------------------------------
        */

        $isInternal = $profile->type === 'internal';

        $moduleKey = Helper::namespaceToKey(
            $namespace
        );

        $shouldEnforceContext =
            $isInternal
            ? 'true'
            : 'false';

        $base_namespace = $namespace . '\Core\BaseService';
        $namespace = $isInternal
            ? $namespace . '\Core'
            : $namespace;

        /*
        |--------------------------------------------------------------------------
        | PROFILE PROPERTY
        |--------------------------------------------------------------------------
        */

        $profileProperty = "    protected string \$profile = '{$profile->type}';\n\n";

        /*
        |--------------------------------------------------------------------------
        | CREATE FILE
        |--------------------------------------------------------------------------
        */

        if (!file_exists($outputPath)) {

            $template = "<?php\n\n";
            $template .= "namespace {$namespace};\n\n";
            $template .= "{$useStatements}\n\n";
            $template .= "use {$base_namespace};\n\n";
            $template .= "/**\n";
            $template .= " * Auto-generated service implementation\n";
            $template .= " * Profile: {$profile->type}\n";
            $template .= " */\n";
            $template .= "class {$className} extends {$extends} implements {$contractShortName}\n";
            $template .= "{\n";
            $template .= "    protected string \$moduleKey = '{$moduleKey}';\n";
            $template .= "    protected bool \$enforceContext = {$shouldEnforceContext};\n\n";
            $template .= $profileProperty;
            $template .= $engineProperty;
            $template .= $factoryProperty;
            $template .= $engineConstructor;
            $template .= $factoryConstructor;
            $template .= $eventMapCode;
            $template .= $generatedMethods;
            $template .= "}\n";

            File::put(
                $outputPath,
                $template
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE FILE
        |--------------------------------------------------------------------------
        */

        if (!empty($generatedMethods)) {

            $updatedCode = $this->injectMethodsIntoClass(
                $existingCode,
                $generatedMethods
            );

            $updatedCode = $this->mergeImports(
                $updatedCode,
                $imports
            );

            if (
                $profile->useDataFactory &&
                $dataFactoryClass &&
                !str_contains(
                    $updatedCode,
                    'protected ' .
                        class_basename($dataFactoryClass) .
                        ' $factory;'
                )
            ) {

                $updatedCode = preg_replace(
                    '/class\s+[^\{]+\{/',
                    "$0\n{$factoryProperty}",
                    $updatedCode,
                    1
                );
            }

            if (
                $profile->useDataFactory &&
                $dataFactoryClass &&
                !preg_match(
                    '/function\s+__construct\s*\(/',
                    $updatedCode
                )
            ) {

                $updatedCode = preg_replace(
                    '/class\s+[^\{]+\{/',
                    "$0\n{$factoryConstructor}",
                    $updatedCode,
                    1
                );
            }

            if (
                !str_contains($updatedCode, 'protected QueryEngine $engine;')
            ) {
                $updatedCode = preg_replace(
                    '/class\s+[^\{]+\{/',
                    "$0\n{$engineProperty}",
                    $updatedCode,
                    1
                );
            }

            if (
                !preg_match(
                    '/function\s+__construct\s*\(/',
                    $updatedCode
                )
            ) {

                $updatedCode = preg_replace(
                    '/class\s+[^\{]+\{/',
                    "$0\n{$engineConstructor}",
                    $updatedCode,
                    1
                );
            }

            // Ensure profile property exists
            if (
                !str_contains($updatedCode, 'protected string $profile')
            ) {
                $updatedCode = preg_replace(
                    '/class\s+[^\{]+\{/',
                    "$0\n{$profileProperty}",
                    $updatedCode,
                    1
                );
            }

            // Ensure event map exists
            if (
                !empty($eventMapCode) &&
                !str_contains($updatedCode, 'protected array $events')
            ) {
                $updatedCode = preg_replace(
                    '/class\s+[^\{]+\{/',
                    "$0\n{$eventMapCode}",
                    $updatedCode,
                    1
                );
            }

            File::put(
                $outputPath,
                $updatedCode
            );
        }
    }


    protected function updateGeneratedRules(
        string $validatorPath,
        string $rulesCode,
        string $messagesCode,
        string $useCode
    ): void {
        $content = File::exists($validatorPath)
            ? File::get($validatorPath)
            : null;

        if (!$content) {
            return;
        }

        $content = preg_replace(
            '/(\/\/ <generated-rules>).*?(\/\/ <\/generated-rules>)/s',
            "$1\n{$rulesCode}\n            $2",
            $content
        );

        $content = preg_replace(
            '/(\/\/ <generated-messages>).*?(\/\/ <\/generated-messages>)/s',
            "$1\n{$messagesCode}\n        $2",
            $content
        );

        if ($useCode !== '') {
            if (!str_contains($content, $useCode)) {
                $content = preg_replace(
                    '/(<\?php\n\nnamespace [^;]+;\n)/',
                    "$1\n{$useCode}\n",
                    $content,
                    1
                );
            }
        } else {
            $content = str_replace(
                "use Illuminate\\Validation\\Rule;\n",
                '',
                $content
            );
        }

        File::put($validatorPath, $content);
    }
    /*
    |--------------------------------------------------------------------------
    | PASSTHROUGH METHOD GENERATION
    |--------------------------------------------------------------------------
    */

    protected function generatePassthroughMethod(\ReflectionMethod $method): string
    {
        $methodName = $method->getName();
        $params = $method->getParameters();
        $paramString = '';
        $paramNames = [];

        foreach ($params as $param) {
            $type = $param->getType();
            $typeString = $this->formatReflectionType($type);
            if ($typeString !== '') {
                $typeString .= ' ';
            }

            $default = '';
            if ($param->isDefaultValueAvailable()) {
                $defaultValue = $param->getDefaultValue();
                if (is_array($defaultValue)) {
                    $default = ' = []';
                } elseif (is_string($defaultValue)) {
                    $default = " = '" . addslashes($defaultValue) . "'";
                } elseif (is_bool($defaultValue)) {
                    $default = ' = ' . ($defaultValue ? 'true' : 'false');
                } elseif (is_null($defaultValue)) {
                    $default = ' = null';
                } elseif (is_int($defaultValue) || is_float($defaultValue)) {
                    $default = ' = ' . $defaultValue;
                } else {
                    $default = ' = ' . var_export($defaultValue, true);
                }
            }

            $paramString .= $typeString . '$' . $param->getName() . $default . ', ';
            $paramNames[] = '$' . $param->getName();
        }

        $paramString = rtrim($paramString, ', ');
        $paramNamesString = implode(', ', $paramNames);

        $isVoid = false;
        $returnType = $method->getReturnType();
        $returnTypeString = '';

        if ($returnType) {
            $formattedType = trim($this->formatReflectionType($returnType));

            if ($formattedType === 'void') {
                $isVoid = true;
            }

            if ($formattedType !== '') {
                $returnTypeString = ': ' . $formattedType;
            }
        }

        $methodCode = "    public function {$methodName}({$paramString}){$returnTypeString}\n";
        $methodCode .= "    {\n";

        if ($isVoid) {
            $methodCode .= "        \$this->engine->{$methodName}({$paramNamesString});\n";
        } else {
            $methodCode .= "        return \$this->engine->{$methodName}({$paramNamesString});\n";
        }

        $methodCode .= "    }\n\n";

        return $methodCode;
    }

    protected function isPassthroughMethod(string $methodName): bool
    {
        $passthrough = [
            'exists',
            'count',
            'chunk',
            'cursor',
            'aggregate',
            'sum',
            'avg',
            'min',
            'max',
            'pluck',
            'first',
            'last',
            'find',
            'findMany',
            'all',
            'where',
            'findBy',
            'findOneBy',
            'search',
            'whereIn',
            'whereNotIn',
            'whereBetween',
            'whereLike',
            'orderBy',
            'limit',
            'offset',
            'paginate',
            'simplePaginate',
            'groupBy',
            'having',
            'with',
            'get',
            'raw'
        ];
        return in_array($methodName, $passthrough);
    }

    /*
    |--------------------------------------------------------------------------
    | TYPE FORMATTING UTILITIES
    |--------------------------------------------------------------------------
    */

    protected function formatReflectionType(?\ReflectionType $type): string
    {
        if (!$type) {
            return '';
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = array_map(fn($t) => $this->formatNamedType($t), $type->getTypes());
            return implode('|', $types);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $types = array_map(fn($t) => $this->formatNamedType($t), $type->getTypes());
            return implode('&', $types);
        }

        if ($type instanceof \ReflectionNamedType) {
            $prefix = ($type->allowsNull() && $type->getName() !== 'mixed') ? '?' : '';
            return $prefix . $this->formatNamedType($type);
        }

        return '';
    }

    protected function formatNamedType(\ReflectionNamedType $type): string
    {
        $name = ltrim($type->getName(), '\\');

        if ($type->isBuiltin() || in_array($name, ['self', 'static', 'parent', 'mixed', 'void'])) {
            return $name;
        }

        $this->dynamicImports[] = $name;
        return class_basename($name);
    }

    /*
    |--------------------------------------------------------------------------
    | PATH AND MODULE BASE SEPARATION HELPERS
    |--------------------------------------------------------------------------
    */

    protected function getModuleBasePath(string $servicePath): string
    {
        $basePath = dirname($servicePath);
        if (basename($basePath) === 'Core') {
            $basePath = dirname($basePath);
        }
        if (basename($basePath) === 'Services') {
            $basePath = dirname($basePath);
        }
        return $basePath;
    }

    protected function getHookPath(string $hookClass, string $servicePath): string
    {
        return $this->getModuleBasePath($servicePath) . DIRECTORY_SEPARATOR . 'Hooks' . DIRECTORY_SEPARATOR . class_basename($hookClass) . '.php';
    }

    protected function getValidatorPath(string $validatorClass, string $servicePath): string
    {
        return $this->getModuleBasePath($servicePath) . DIRECTORY_SEPARATOR . 'Validators' . DIRECTORY_SEPARATOR . class_basename($validatorClass) . '.php';
    }

    protected function getDtoPath(string $dtoClass, string $servicePath): string
    {
        return $this->getModuleBasePath($servicePath) . DIRECTORY_SEPARATOR . 'DTOs' . DIRECTORY_SEPARATOR . class_basename($dtoClass) . '.php';
    }

    /*
    |--------------------------------------------------------------------------
    | CORE STRUCTURAL GENERATION METRICS
    |--------------------------------------------------------------------------
    */

    protected function generateEventMap(array $events, string $entity): string
    {
        if (empty($events)) {
            return '';
        }

        $mapLines = [];
        foreach ($events as $event) {
            $action = str_replace([$entity, 'Event'], '', class_basename($event['class']));
            $actionLower = strtolower($action);
            $mapLines[] = "        '{$actionLower}' => " . class_basename($event['class']) . '::class,';
        }

        $mapCode = implode("\n", $mapLines);

        $code = "\n";
        $code .= "    protected array \$events = [\n";
        $code .= $mapCode . "\n";
        $code .= "    ];\n\n";
        $code .= "    protected function dispatchEvent(string \$action, array \$data, mixed \$result): void\n";
        $code .= "    {\n";
        $code .= "        \$event = \$this->events[strtolower(\$action)] ?? null;\n";
        $code .= "        \n";
        $code .= "        if (\$event) {\n";
        $code .= "            event(new \$event(\$data, \$result));\n";
        $code .= "        }\n";
        $code .= "    }\n\n";

        return $code;
    }

    protected function resolveEntity(string $contract): string
    {
        $class = Helper::afterLast($contract, '\\');
        return str_replace(['Contract', 'Core'], '', $class);
    }

    protected function resolveHookClass(string $entity, string $serviceClass): string
    {
        $namespace = Helper::beforeLast($serviceClass, '\\');
        $namespace = preg_replace('/\\\\Services\\\\Core$/', '', $namespace);
        $namespace = preg_replace('/\\\\Services$/', '', $namespace);
        return $namespace . '\\Hooks\\' . $entity . 'Hooks';
    }

    protected function resolveValidatorClass(string $entity, string $serviceClass): string
    {
        $namespace = Helper::beforeLast($serviceClass, '\\');
        $namespace = preg_replace('/\\\\Services\\\\Core$/', '', $namespace);
        $namespace = preg_replace('/\\\\Services$/', '', $namespace);
        return $namespace . '\\Validators\\' . $entity . 'Validator';
    }



    /**
     * Generates a validator class directly from the entity database schema.
     *
     * The database schema is the authoritative source for structural
     * validation rules.
     */
    /**
     * Generates a validator class directly from the entity database schema.
     *
     * The database schema is the authoritative source for structural
     * validation rules.
     */

    protected function generateValidatorClass(
        string $entity,
        string $dtoClass,
        string $validatorClass,
        string $servicePath
    ): void {
        $namespace = Helper::beforeLast($validatorClass, '\\');
        $className = class_basename($validatorClass);

        $validatorPath = $this->getValidatorPath(
            $validatorClass,
            $servicePath
        );

        File::ensureDirectoryExists(dirname($validatorPath));

        /*
     * ---------------------------------------------------------
     * Resolve schema
     * ---------------------------------------------------------
     */

        $schemaPath = $this->getEntitySchemaPath(
            $entity,
            $servicePath
        );

        $schema = $schemaPath && File::exists($schemaPath)
            ? Helper::loadJson($schemaPath)
            : [];

        if (!is_array($schema)) {
            $schema = [];
        }

        $table = $schema['table'] ?? null;
        $columns = $schema['columns'] ?? [];
        $indexes = $schema['indexes'] ?? [];

        /*
     * ---------------------------------------------------------
     * Extract unique indexes
     * ---------------------------------------------------------
     */

        $uniqueConstraints = [];

        foreach ($indexes as $index) {
            if (!($index['unique'] ?? false)) {
                continue;
            }

            $indexColumns = array_values(
                array_filter(
                    $index['columns'] ?? [],
                    fn($column) =>
                    is_string($column) && $column !== ''
                )
            );

            if (empty($indexColumns)) {
                continue;
            }

            $hasSchoolId = in_array(
                'school_id',
                $indexColumns,
                true
            );

            $dataColumns = array_values(
                array_filter(
                    $indexColumns,
                    fn($column) => $column !== 'school_id'
                )
            );

            if (empty($dataColumns)) {
                continue;
            }

            $uniqueConstraints[] = [
                'columns' => $dataColumns,
                'school_scoped' => $hasSchoolId,
            ];
        }

        /*
     * ---------------------------------------------------------
     * Generate rules
     * ---------------------------------------------------------
     */

        $rulesLines = [];
        $messagesLines = [];
        $uniqueFields = [];

        foreach ($uniqueConstraints as $constraint) {
            foreach ($constraint['columns'] as $column) {
                $uniqueFields[$column] = true;
            }
        }

        foreach ($columns as $column) {
            $name = $column['name'] ?? null;

            if (!$name) {
                continue;
            }

            /*
         * Database-managed fields.
         */
            if (
                ($column['primary'] ?? false) ||
                ($column['autoincrement'] ?? false) ||
                $name === 'id' ||
                $name === 'school_id'
            ) {
                continue;
            }

            $type = strtolower(
                (string) ($column['type'] ?? 'string')
            );

            $length = $column['length'] ?? null;
            $nullable = (bool) ($column['nullable'] ?? false);
            $default = $column['default'] ?? null;

            $rules = [];

            /*
         * Required / nullable / default
         */

            if ($nullable) {
                $rules[] = 'nullable';
            } elseif ($default !== null) {
                $rules[] = 'sometimes';
            } else {
                $rules[] = 'required';
            }

            /*
         * Type
         */

            $typeRule = $this->getValidatorTypeRule($type);

            if ($typeRule !== null) {
                $rules[] = $typeRule;
            }

            /*
         * String length
         */

            if (
                $length !== null &&
                is_numeric($length) &&
                $this->isStringSchemaType($type)
            ) {
                $rules[] = 'max:' . (int) $length;
            }

            /*
         * Unique rules
         */

            foreach ($uniqueConstraints as $constraint) {
                $constraintColumns = $constraint['columns'];

                if (!in_array($name, $constraintColumns, true)) {
                    continue;
                }

                /*
             * Composite constraints are generated once,
             * against their first data column.
             */
                if ($constraintColumns[0] !== $name) {
                    continue;
                }

                /*
             * A schema without a table cannot generate
             * a database unique rule.
             */
                if (!$table) {
                    continue;
                }

                $ruleExpr =
                    "Rule::unique('{$table}')";

                if ($constraint['school_scoped']) {
                    $ruleExpr .=
                        "->where('school_id', \$schoolId)";
                }

                foreach ($constraintColumns as $constraintColumn) {
                    if ($constraintColumn === $name) {
                        continue;
                    }

                    $ruleExpr .=
                        "->where('{$constraintColumn}', "
                        . "\$data['{$constraintColumn}'] ?? null)";
                }

                $ruleExpr .=
                    "->when(\$isUpdate && \$ignoreId !== null, "
                    . "fn (\$rule) => \$rule->ignore(\$ignoreId))";

                $rules[] = $ruleExpr;
            }

            /*
         * Separate scalar rules from PHP expressions.
         */

            $scalarRules = [];
            $ruleExpressions = [];

            foreach ($rules as $rule) {
                if (
                    is_string($rule) &&
                    !str_starts_with($rule, 'Rule::')
                ) {
                    $scalarRules[] = $rule;
                } else {
                    $ruleExpressions[] = $rule;
                }
            }

            $scalarRules = array_values(
                array_unique($scalarRules)
            );

            $rules = array_merge(
                $scalarRules,
                $ruleExpressions
            );

            /*
         * PHP array syntax.
         */

            $ruleCodeParts = [];

            foreach ($rules as $rule) {
                if (
                    is_string($rule) &&
                    str_starts_with($rule, 'Rule::')
                ) {
                    $ruleCodeParts[] = $rule;
                } else {
                    $ruleCodeParts[] =
                        "'" . addslashes($rule) . "'";
                }
            }

            $rulesCodeForField =
                '[' . implode(', ', $ruleCodeParts) . ']';

            $rulesLines[] =
                "            '{$name}' => {$rulesCodeForField},";

            /*
         * -----------------------------------------------------
         * Messages
         * -----------------------------------------------------
         */

            $readableName = str_replace('_', ' ', $name);

            if (in_array('required', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.required' => "
                    . "'The {$readableName} field is required.',";
            }

            if (in_array('string', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.string' => "
                    . "'The {$readableName} must be a string.',";
            }

            if (in_array('integer', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.integer' => "
                    . "'The {$readableName} must be an integer.',";
            }

            if (in_array('numeric', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.numeric' => "
                    . "'The {$readableName} must be numeric.',";
            }

            if (in_array('boolean', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.boolean' => "
                    . "'The {$readableName} must be true or false.',";
            }

            if (in_array('array', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.array' => "
                    . "'The {$readableName} must be an array.',";
            }

            if (in_array('date', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.date' => "
                    . "'The {$readableName} must be a valid date.',";
            }

            if (in_array('email', $scalarRules, true)) {
                $messagesLines[] =
                    "            '{$name}.email' => "
                    . "'The {$readableName} must be a valid email address.',";
            }

            foreach ($scalarRules as $rule) {
                if (
                    is_string($rule) &&
                    preg_match('/^max:(\d+)$/', $rule, $maxMatch)
                ) {
                    $messagesLines[] =
                        "            '{$name}.max' => "
                        . "'The {$readableName} may not be greater than "
                        . "{$maxMatch[1]} characters.',";

                    break;
                }
            }

            if (isset($uniqueFields[$name])) {
                $messagesLines[] =
                    "            '{$name}.unique' => "
                    . "'The {$readableName} has already been taken.',";
            }
        }

        if (empty($rulesLines)) {
            $rulesLines[] =
                "            // No client-validatable columns found.";
        }

        $rulesCode = implode("\n", $rulesLines);

        if (empty($messagesLines)) {
            $messagesLines[] =
                "            // No generated validation messages.";
        }

        $messagesCode = implode("\n", $messagesLines);

        /*
     * ---------------------------------------------------------
     * Generated imports
     * ---------------------------------------------------------
     */

        $importsCode = '';

        if (!empty($uniqueConstraints) && $table) {
            $importsCode = "use Illuminate\\Validation\\Rule;";
        }

        /*
     * ---------------------------------------------------------
     * Create validator for the first time
     * ---------------------------------------------------------
     */

        if (!File::exists($validatorPath)) {
            $stub = "<?php\n\n";
            $stub .= "namespace {$namespace};\n\n";

            if ($importsCode !== '') {
                $stub .= $importsCode . "\n";
            }

            $stub .= "use Illuminate\\Support\\Facades\\Validator;\n\n";

            $stub .= "class {$className}\n";
            $stub .= "{\n";

            $stub .= "    public static function validate(\n";
            $stub .= "        array \$data,\n";
            $stub .= "        bool \$isUpdate = false,\n";
            $stub .= "        int|string|null \$ignoreId = null,\n";
            $stub .= "        int|string|null \$schoolId = null\n";
            $stub .= "    ): array {\n";

            $stub .= "        \$rules = [\n";
            $stub .= "            // <generated-rules>\n";
            $stub .= $rulesCode . "\n";
            $stub .= "            // </generated-rules>\n\n";
            $stub .= "            // Developer-owned rules go here.\n";
            $stub .= "        ];\n\n";

            $stub .= "        if (\$isUpdate) {\n";
            $stub .= "            foreach (\$rules as \$field => \$ruleList) {\n";
            $stub .= "                if (!is_array(\$ruleList)) {\n";
            $stub .= "                    continue;\n";
            $stub .= "                }\n\n";
            $stub .= "                foreach (\$ruleList as \$i => \$rule) {\n";
            $stub .= "                    if (\$rule === 'required') {\n";
            $stub .= "                        \$rules[\$field][\$i] = 'sometimes';\n";
            $stub .= "                    }\n";
            $stub .= "                }\n";
            $stub .= "            }\n";
            $stub .= "        }\n\n";

            $stub .= "        \$messages = [\n";
            $stub .= "            // <generated-messages>\n";
            $stub .= $messagesCode . "\n";
            $stub .= "            // </generated-messages>\n\n";
            $stub .= "            // Developer-owned messages go here.\n";
            $stub .= "        ];\n\n";

            $stub .= "        return Validator::make(\n";
            $stub .= "            \$data,\n";
            $stub .= "            \$rules,\n";
            $stub .= "            \$messages\n";
            $stub .= "        )->validate();\n";

            $stub .= "    }\n";
            $stub .= "}\n";

            File::put($validatorPath, $stub);

            return;
        }

        /*
     * ---------------------------------------------------------
     * Update existing validator
     * ---------------------------------------------------------
     */

        $content = File::get($validatorPath);

        /*
     * Update generated rules only.
     */

        $content = preg_replace(
            '/(\s*\/\/ <generated-rules>).*?(\s*\/\/ <\/generated-rules>)/s',
            "\n            // <generated-rules>\n"
                . $rulesCode
                . "\n            // </generated-rules>",
            $content,
            1
        );

        /*
     * Update generated messages only.
     */

        $content = preg_replace(
            '/(\s*\/\/ <generated-messages>).*?(\s*\/\/ <\/generated-messages>)/s',
            "\n            // <generated-messages>\n"
                . $messagesCode
                . "\n            // </generated-messages>",
            $content,
            1
        );

        /*
     * Update generated Rule import.
     */

        $ruleImport = "use Illuminate\\Validation\\Rule;";

        if ($importsCode !== '') {
            if (!str_contains($content, $ruleImport)) {
                $content = preg_replace(
                    '/(namespace\s+[^;]+;\s*)/s',
                    "$1\n{$ruleImport}",
                    $content,
                    1
                );
            }
        } else {
            $content = preg_replace(
                '/\n?use Illuminate\\\\Validation\\\\Rule;\n/',
                "\n",
                $content,
                1
            );
        }

        File::put($validatorPath, $content);
    }




    /**
     * Resolve the database schema for an entity.
     *
     * Schema files are migration-based, for example:
     *
     * Database/migrations/schemas/
     * └── 2026_08_30_150841_create_schoolpalm_common_student_forms_table.json
     *
     * The entity is "Forms", while the actual table is:
     *
     * schoolpalm_common_student_forms
     */
    protected function getEntitySchemaPath(
        string $entity,
        string $servicePath
    ): ?string {
        $schemaDirectory =
            $this->getModuleBasePath($servicePath)
            . DIRECTORY_SEPARATOR
            . 'Database'
            . DIRECTORY_SEPARATOR
            . 'migrations'
            . DIRECTORY_SEPARATOR
            . 'schemas';

        if (!$this->manifest || !File::isDirectory($schemaDirectory)) {
            return null;
        }

        $namespace = Str::before(
            $this->manifest->info()->namespace(),
            '\\Backend'
        );

        $modulePrefix =
            str_replace('\\', '_', trim($namespace, '\\'));

        $expectedTable = strtolower($modulePrefix . '_' . $entity);


        foreach (File::files($schemaDirectory) as $file) {
            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $schema = Helper::loadJson($file->getPathname());

            if (
                $schema['table'] === $expectedTable
            ) {
                return $file->getPathname();
            }
        }

        return null;
    }


    protected function generateHookClass(string $entity, string $hookClass, string $validatorClass, string $servicePath): void
    {
        $namespace = Helper::beforeLast($hookClass, '\\');
        $className = class_basename($hookClass);
        $validatorShortName = class_basename($validatorClass);
        $hookPath = $this->getHookPath($hookClass, $servicePath);

        if (File::exists($hookPath)) {
            return;
        }

        // Apply Laravel's pluralization engine to correctly map the plural entity domain subfolder
        $pluralEntity = Str::plural($entity);
        $eventNamespace = $this->resolveEventNamespace($hookClass) . '\\' . $pluralEntity;

        $stub = "<?php\n\n";
        $stub .= "namespace {$namespace};\n\n";
        $stub .= "use {$validatorClass};\n";
        $stub .= "use {$eventNamespace}\\{$entity}CreatedEvent;\n";
        $stub .= "use {$eventNamespace}\\{$entity}UpdatedEvent;\n";
        $stub .= "use {$eventNamespace}\\{$entity}DeletedEvent;\n\n";
        $stub .= "class {$className}\n";
        $stub .= "{\n";
        $stub .= "    public function beforeInsert(array \$data): array\n";
        $stub .= "    {\n";
        $stub .= "        {$validatorShortName}::validate(\$data);\n";
        $stub .= "        return \$data;\n";
        $stub .= "    }\n\n";
        $stub .= "    public function afterInsert(mixed \$result, array \$data): void\n";
        $stub .= "    {\n";
        $stub .= "        event(new {$entity}CreatedEvent(\$data, \$result));\n";
        $stub .= "    }\n\n";
        $stub .= "    public function beforeUpdate(array \$data, array \$criteria): array\n";
        $stub .= "    {\n";
        $stub .= "        {$validatorShortName}::validate(\$data, true);\n";
        $stub .= "        return \$data;\n";
        $stub .= "    }\n\n";
        $stub .= "    public function afterUpdate(mixed \$result, array \$data, array \$criteria): void\n";
        $stub .= "    {\n";
        $stub .= "        event(new {$entity}UpdatedEvent(\$data, \$result));\n";
        $stub .= "    }\n\n";
        $stub .= "    public function beforeDelete(mixed \$identifier): void\n";
        $stub .= "    {\n";
        $stub .= "    }\n\n";
        $stub .= "    public function afterDelete(mixed \$result, mixed \$identifier): void\n";
        $stub .= "    {\n";
        $stub .= "        event(new {$entity}DeletedEvent(['id' => \$identifier], null));\n";
        $stub .= "    }\n\n";
        $stub .= "    public function beforeInsertMany(array \$dataSet): array\n";
        $stub .= "    {\n";
        $stub .= "        foreach (\$dataSet as \$data) {\n";
        $stub .= "            {$validatorShortName}::validate(\$data);\n";
        $stub .= "        }\n";
        $stub .= "        return \$dataSet;\n";
        $stub .= "    }\n\n";
        $stub .= "    public function afterInsertMany(mixed \$results, array \$dataSet): void\n";
        $stub .= "    {\n";
        $stub .= "        foreach (\$results as \$index => \$result) {\n";
        $stub .= "            event(new {$entity}CreatedEvent(\$dataSet[\$index] ?? [], \$result));\n";
        $stub .= "        }\n";
        $stub .= "    }\n";
        $stub .= "}\n";

        File::ensureDirectoryExists(dirname($hookPath));
        File::put($hookPath, $stub);
    }

    protected function resolveEventNamespace(string $hookClass): string
    {
        $namespace = Helper::beforeLast($hookClass, '\\');
        return str_replace('\\Hooks', '\\Events', $namespace);
    }

    private function resolveBaseClass(ContractProfile $profile): string
    {
        return match ($profile->type) {
            'snapshot' => 'SnapshotService',
            default => 'BaseService',
        };
    }

    protected function resolveDtoFromContract(string $contract): string
    {
        $class = Helper::afterLast($contract, '\\');
        $dtoName = str_replace('Contract', 'Data', $class);
        $dtoName = str_replace('Core', '', $dtoName);
        $baseNamespace = Helper::beforeLast($contract, '\\');
        $dtoNamespace = str_replace('\\Contracts', '\\DTOs', $baseNamespace);
        $dtoNamespace = str_replace('\\Core\\', '\\', $dtoNamespace);
        return $dtoNamespace . '\\' . $dtoName;
    }

    protected function resolveModelFromContract(string $contract): string
    {
        $class = Helper::afterLast($contract, '\\');
        $entity = str_replace(['CoreContract', 'Contract'], '', $class);
        $namespace = Helper::beforeLast($contract, '\\');
        $namespace = str_replace('\\Contracts\\Core', '\\Models', $namespace);
        $namespace = str_replace('\\Contracts', '\\Models', $namespace);
        return $namespace . '\\' . $entity;
    }


    protected function getValidatorTypeRule(string $type): ?string
    {
        return match (strtolower($type)) {
            'string',
            'char',
            'varchar',
            'text',
            'tinytext',
            'mediumtext',
            'longtext' => 'string',

            'integer',
            'bigint',
            'biginteger',
            'mediumint',
            'smallint',
            'tinyint' => 'integer',

            'decimal',
            'numeric',
            'float',
            'double',
            'real' => 'numeric',

            'boolean',
            'bool' => 'boolean',

            'date',
            'datetime',
            'timestamp',
            'datetimetz',
            'timestampz' => 'date',

            'json',
            'jsonb' => 'array',

            'uuid',
            'ulid' => 'string',

            default => null,
        };
    }

    protected function isStringSchemaType(string $type): bool
    {
        return in_array(strtolower($type), [
            'string',
            'char',
            'varchar',
            'text',
            'tinytext',
            'mediumtext',
            'longtext',
        ], true);
    }

    protected function resolveDataFactoryFromContract(string $contract): string
    {
        $class = Helper::afterLast($contract, '\\');
        $entity = str_replace('Contract', '', $class);
        $entity = str_replace('Core', '', $entity);
        $baseNamespace = Helper::beforeLast($contract, '\\');
        $factoryNamespace = str_replace('\\Contracts', '\\Factories', $baseNamespace);
        $factoryNamespace = str_replace('\\Core\\', '\\', $factoryNamespace);
        return $factoryNamespace . '\\' . $entity . 'DataFactory';
    }

    private function injectMethodsIntoClass(string $existingCode, string $generatedMethods): string
    {
        $position = strrpos($existingCode, '}');
        if ($position === false) {
            return $existingCode;
        }
        return substr($existingCode, 0, $position) . rtrim($generatedMethods) . "\n\n}";
    }

    private function mergeImports(string $existingCode, array $newImports): string
    {
        preg_match_all('/^use\s+([^;]+);/m', $existingCode, $matches);
        $existingImports = $matches[1] ?? [];
        $allImports = array_unique(array_merge($existingImports, $newImports));
        $allImports = array_filter($allImports, fn($i) => !empty(trim($i)));
        sort($allImports);

        $useBlock = implode("\n", array_map(fn($i) => "use {$i};", $allImports));
        $codeWithoutUses = preg_replace('/^use\s+[^;]+;\n/m', '', $existingCode);
        return preg_replace('/namespace\s+[^;]+;/', "$0\n\n{$useBlock}", $codeWithoutUses, 1);
    }
}
