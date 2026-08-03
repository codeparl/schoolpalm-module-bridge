<?php

namespace SchoolPalm\ModuleBridge\Services;

use ReflectionClass;
use Illuminate\Support\Str;

class SmartDtoFieldAnalyzer
{
    public function analyze(string $dto): array
    {
        $reflection = new ReflectionClass($dto);

        return collect($reflection->getProperties())
            ->map(function ($prop) {

                $name = $prop->getName();
                $type = $prop->getType()?->getName() ?? 'string';

                return [
                    'name' => $name,
                    'php_type' => $type,

                    // 🔥 semantic inference
                    'semantic' => $this->inferSemanticType($name, $type),

                    // 🔥 UI mapping
                    'ui' => $this->mapToUiComponent($name, $type),

                    // 🔥 extra metadata
                    'label' => Str::title(str_replace('_', ' ', $name)),
                    'readonly' => $this->isReadonly($name),
                    'required' => $this->isRequired($name, $type),
                ];
            })
            ->values()
            ->toArray();
    }


    protected function inferSemanticType(string $name, string $type): string
{
    $name = strtolower($name);

    return match (true) {

        str_contains($name, 'email') => 'email',

        str_contains($name, 'password') => 'password',

        str_contains($name, 'phone') => 'phone',

        str_contains($name, 'date') ||
        str_contains($name, 'dob') ||
        str_contains($name, 'birth') => 'date',

        str_contains($name, 'status') ||
        str_contains($name, 'type') ||
        str_contains($name, 'role') => 'select',

        str_contains($name, 'is_') ||
        str_starts_with($name, 'has_') => 'boolean',

        $type === 'int' => 'number',
        $type === 'float' => 'number',

        default => 'text'
    };
}

    protected function mapToUiComponent(string $name, string $type): string
    {
        $semantic = $this->inferSemanticType($name, $type);

        return match ($semantic) {
            'email' => 'q-input',
            'password' => 'q-input',
            'phone' => 'q-input',
            'date' => 'q-date',
            'select' => 'q-select',
            'boolean' => 'q-toggle',
            'number' => 'q-input',
            default => 'q-input'
        };
    }

    protected function isReadonly(string $name): bool
    {
        return str_contains(strtolower($name), ['id', '_at', '_by']);
    }

    protected function isRequired(string $name, string $type): bool
    {
        return !$this->isReadonly($name) && !str_ends_with($type, '?');
    }

}