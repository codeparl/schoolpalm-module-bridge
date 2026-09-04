<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\SchoolHost;
use SchoolPalm\ModuleBridge\Support\ContextData;
use SchoolPalm\ModuleBridge\Support\Helper;

/**
 * SchoolHostService
 *
 * Resolves current school context for:
 * - SchoolPalm runtime (tenant-based school)
 * - SDK runtime (fake JSON data)
 */
class SchoolHostService implements SchoolHost
{
    /**
     * Cached SDK data array
     */
    protected ?array $sdkSchool = null;

    /**
     * Get current school context array.
     */
    public function currentArray(): ?array
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkSchoolArray();
        }

        return $this->realSchoolArray();
    }

    /**
     * Get current school context.
     * Pass $asArray = true for queue job payloads and Blade view parameters.
     */
    public function current(bool $asArray = false): null|array|ContextData
    {
        $data = $this->currentArray();

        if ($data === null) {
            return null;
        }

        return $asArray ? $data : ContextData::make($data);
    }

    /**
     * School ID
     */
    public function id(): int|string|null
    {
        return $this->currentArray()['id'] ?? null;
    }

    /**
     * School name
     */
    public function name(): ?string
    {
        return $this->currentArray()['name'] ?? null;
    }

    /**
     * School code
     */
    public function code(): ?string
    {
        return $this->currentArray()['school_code'] ?? null;
    }



    /**
     * Academic level
     */
    public function academicLevel(): ?string
    {
        return $this->currentArray()['academic_level'] ?? null;
    }

    /**
     * Status
     */
    public function status(): ?string
    {
        return $this->currentArray()['status'] ?? null;
    }

    /**
     * Metadata
     */
    public function metadata(): array
    {
        return $this->currentArray()['metadata'] ?? [];
    }

    /**
     * Real school array representation
     */
    protected function realSchoolArray(): ?array
    {
        $school = function_exists('currentSchool') ? currentSchool() : null;

        if (!$school) {
            return null;
        }

        return [
            'id'             => $school->id ?? null,
            'name'           => $school->name ?? null,
            'school_code'    => $school->school_code ?? null,
            'academic_level' => $school->academic_level ?? null,
            'status'         => $school->status ?? null,
            'metadata'       => $school->metadata ?? [],
        ];
    }

    /**
     * Load SDK school array
     */
    protected function sdkSchoolArray(): array
    {
        if ($this->sdkSchool !== null) {
            return $this->sdkSchool;
        }

        $path = Helper::dataFolder('tenants/schools/demo_tenant/school_1.json');

        if (!file_exists($path)) {
            return $this->sdkSchool = [
                'id'             => 1,
                'name'           => 'SDK Demo School',
                'school_code'    => 'SDK-002',
                'academic_level' => 'secondary',
                'status'         => 'active',
                'metadata'       => [],
            ];
        }

        return $this->sdkSchool = Helper::loadJson($path) ?? [];
    }
}
