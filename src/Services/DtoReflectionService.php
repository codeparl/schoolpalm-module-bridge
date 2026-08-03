<?php

namespace SchoolPalm\ModuleBridge\Services;

use Illuminate\Support\Str;
use ReflectionClass;
use SchoolPalm\ModuleBridge\Manifest\ModuleManifest;

class DtoReflectionService
{
    protected ModuleManifest $manifest;

    public function __construct(ModuleManifest $manifest)
    {
        $this->manifest = $manifest;
    }

    /**
     * Normalize a DTO identifier to its fully qualified class name.
     *
     * If the input already contains a namespace separator (\), it is assumed to be a FQCN.
     * Otherwise, it is resolved against the module's DTO namespace.
     *
     * @param string $dto
     * @return string
     */
    protected function normalizeDto(string $dto): string
    {
        if (str_contains($dto, '\\')) {
            return $dto;
        }
        return $this->manifest->info()->namespace() . '\\DTOs\\' . $dto;
    }

    /**
     * Get all properties of a DTO class with their types.
     *
     * @param string $dto DTO class name (short or FQCN)
     * @return array
     * @throws \RuntimeException if the DTO class does not exist
     */
    public function fields(string $dto): array
    {
        $fqcn = $this->normalizeDto($dto);
        if (!class_exists($fqcn)) {
            throw new \RuntimeException("DTO class not found: {$fqcn}");
        }

        $reflection = new ReflectionClass($fqcn);
        return collect($reflection->getProperties())
            ->map(fn($p) => [
                'name' => $p->getName(),
                'type' => $p->getType()?->getName() ?? 'mixed'
            ])
            ->values()
            ->toArray();
    }

    /**
     * Resolve contract information from a DTO class name.
     *
     * @param string $dto DTO class name (short or FQCN)
     * @param bool $core If true, returns CoreContract (for write operations)
     * @return array ['class' => 'StudentContract', 'variable' => 'student', 'fqcn' => '...']
     */
    public function resolveContract(string $dto, bool $core = false): array
    {
        $fqcn = $this->normalizeDto($dto);
        $dtoShort = class_basename($fqcn);
        $entity = str_replace('Data', '', $dtoShort);
        $contractShort = $core ? $entity . 'CoreContract' : $entity . 'Contract';
        $variable = Str::camel($entity);

        $contractNamespace = $core
            ? $this->manifest->info()->namespace() . '\\Contracts\\Core'
            : $this->manifest->info()->namespace() . '\\Contracts';
        $contractFqcn = $contractNamespace . '\\' . $contractShort;

        return [
            'class'    => $contractShort,
            'variable' => $variable,
            'fqcn'     => $contractFqcn,
        ];
    }
}