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
            'exists', 'count', 'chunk', 'cursor', 'aggregate',
            'sum', 'avg', 'min', 'max', 'pluck', 'first', 'last',
            'find', 'findMany', 'all', 'where', 'findBy', 'findOneBy',
            'search', 'whereIn', 'whereNotIn', 'whereBetween', 'whereLike',
            'orderBy', 'limit', 'offset', 'paginate', 'simplePaginate',
            'groupBy', 'having', 'with', 'get', 'raw'
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
     * Reads properties out of the mapped Data DTO using dual evaluation mechanisms 
     * (Reflection + Direct *Data.php File Parser fallback) and infers semantic rules.
     */
    protected function generateValidatorClass(string $entity, string $dtoClass, string $validatorClass, string $servicePath): void
    {
        $namespace = Helper::beforeLast($validatorClass, '\\');
        $className = class_basename($validatorClass);
        $validatorPath = $this->getValidatorPath($validatorClass, $servicePath);

        if (File::exists($validatorPath)) {
            return;
        }

        $extractedProperties = [];

        // 1. Evaluate via Runtime Reflection Engine if loaded
        if (class_exists($dtoClass)) {
            $reflection = new \ReflectionClass($dtoClass);
            foreach ($reflection->getProperties() as $prop) {
                if ($prop->isStatic()) {
                    continue;
                }
                $typeStr = '';
                if ($propType = $prop->getType()) {
                    if ($propType instanceof \ReflectionNamedType) {
                        $typeStr = ($propType->allowsNull() ? '?' : '') . $propType->getName();
                    } elseif ($propType instanceof \ReflectionUnionType) {
                        $types = array_map(fn($t) => $t->getName(), $propType->getTypes());
                        $typeStr = implode('|', $types);
                    }
                }
                $extractedProperties[$prop->getName()] = $typeStr;
            }
        } else {
            // 2. Deterministic File Fallback Engine targeting standard {$entity}Data.php files
            $dtoFolder = $this->getModuleBasePath($servicePath) . DIRECTORY_SEPARATOR . 'DTOs';
            $expectedDtoPath = $dtoFolder . DIRECTORY_SEPARATOR . $entity . 'Data.php';
            
            // Fallback check using the base resolved name if directory maps differ
            if (!File::exists($expectedDtoPath)) {
                $expectedDtoPath = $this->getDtoPath($dtoClass, $servicePath);
            }

            if (File::exists($expectedDtoPath)) {
                $content = File::get($expectedDtoPath);
                
                // Clean comments out to isolate authentic variable assignments
                $cleanContent = preg_replace('!/\*.*?\*/!s', '', $content);
                $cleanContent = preg_replace('!//.*!', '', $cleanContent);
                
                // Matches standard typed properties and constructor promoted properties
                preg_match_all('/(?:public|protected|private)\s+(?:readonly\s+)?([\w\|\\\\?]+)?\s*\$(\w+)/', $cleanContent, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $propType = !empty($match[1]) ? trim($match[1]) : 'mixed';
                    $propName = $match[2];
                    $extractedProperties[$propName] = $propType;
                }
            }
        }

        $rulesLines = [];
        $messagesLines = [];

        if (!empty($extractedProperties)) {
            foreach ($extractedProperties as $name => $type) {
                // IDs are handled autonomously by the database engine sequence layer
                if ($name === 'id') {
                    continue;
                }

                $rules = [];
                $isNullable = str_contains($type, '?') || str_contains(strtolower($type), 'null');
                $rules[] = $isNullable ? 'nullable' : 'required';

                $cleanType = str_replace('?', '', $type);
                $typeRule = match(true) {
                    str_contains($cleanType, 'int') => 'integer',
                    str_contains($cleanType, 'float') || str_contains($cleanType, 'double') => 'numeric',
                    str_contains($cleanType, 'bool') => 'boolean',
                    str_contains($cleanType, 'array') => 'array',
                    default => 'string',
                };
                $rules[] = $typeRule;

                // Semantic Context Engine mapping rules directly against naming taxonomy
                $lowerName = strtolower($name);
                $readableName = str_replace('_', ' ', $name);

                if (str_contains($lowerName, 'email')) {
                    $rules[] = 'email';
                    $rules[] = 'max:255';
                } elseif (str_contains($lowerName, 'password')) {
                    $rules[] = 'min:8';
                } elseif (str_contains($lowerName, 'url')) {
                    $rules[] = 'url';
                } elseif (str_contains($lowerName, 'uuid')) {
                    $rules[] = 'uuid';
                } elseif (preg_match('/_(at|date)$/', $lowerName) || $lowerName === 'date') {
                    $rules[] = 'date';
                } elseif (str_ends_with($lowerName, '_id')) {
                    if (!in_array('integer', $rules)) {
                        $rules[] = 'integer';
                    }
                } elseif (in_array($lowerName, ['first_name', 'last_name', 'name', 'title', 'subject'])) {
                    $rules[] = 'max:255';
                }

                $rulesString = implode('|', array_unique($rules));
                $rulesLines[] = "            '{$name}' => '{$rulesString}',";

                // Map human-readable validation error messaging parameters
                if (!$isNullable) {
                    $messagesLines[] = "            '{$name}.required' => 'The {$readableName} field is required.',";
                }
                $messagesLines[] = "            '{$name}.{$typeRule}' => 'The {$readableName} must be a valid {$typeRule}.',";
                
                if (in_array('email', $rules)) {
                    $messagesLines[] = "            '{$name}.email' => 'The {$readableName} must be a valid email address.',";
                }
                if (in_array('min:8', $rules)) {
                    $messagesLines[] = "            '{$name}.min' => 'The {$readableName} must be at least 8 characters.',";
                }
                if (preg_match('/max:(\d+)/', $rulesString, $maxMatch)) {
                    $messagesLines[] = "            '{$name}.max' => 'The {$readableName} may not be greater than {$maxMatch[1]} characters.',";
                }
            }
        }

        if (empty($rulesLines)) {
            $rulesLines[] = "            // Contextual properties map empty. Define custom requirements here.";
        }

        $rulesCode = implode("\n", $rulesLines);
        $messagesCode = implode("\n", $messagesLines);

        $stub = "<?php\n\n";
        $stub .= "namespace {$namespace};\n\n";
        $stub .= "use Illuminate\Support\Facades\Validator;\n\n";
        $stub .= "class {$className}\n";
        $stub .= "{\n";
        $stub .= "    public static function validate(array \$data, bool \$isUpdate = false): array\n";
        $stub .= "    {\n";
        $stub .= "        \$rules = [\n";
        $stub .= $rulesCode . "\n";
        $stub .= "        ];\n\n";
        $stub .= "        if (\$isUpdate) {\n";
        $stub .= "            foreach (\$rules as \$field => \$rule) {\n";
        $stub .= "                if (is_string(\$rule)) {\n";
        $stub .= "                    \$rules[\$field] = str_replace('required', 'sometimes', \$rule);\n";
        $stub .= "                }\n";
        $stub .= "            }\n";
        $stub .= "        }\n\n";
        $stub .= "        \$messages = [\n";
        $stub .= (!empty($messagesCode) ? $messagesCode . "\n" : "") . "        ];\n\n";
        $stub .= "        return Validator::make(\$data, \$rules, \$messages)->validate();\n";
        $stub .= "    }\n";
        $stub .= "}\n";

        File::ensureDirectoryExists(dirname($validatorPath));
        File::put($validatorPath, $stub);
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