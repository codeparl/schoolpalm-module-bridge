<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\SchoolHost;
use SchoolPalm\ModuleBridge\Support\Helper;
use App\Models\School;

/**
 * SchoolHostService
 *
 * Resolves current school context for:
 * - SchoolPalm runtime (tenant-based school)
 * - SDK runtime (fake JSON data)
 */
class SchoolHostService implements SchoolHost
{
    protected ?array $sdkSchool = null;

    /**
     * Get current school
     */
    public function current(): ?object
    {
        if (Helper::isSdkRuntime()) {
            return (object) $this->sdkSchool();
        }

        $school = function_exists('currentSchool')
            ? currentSchool()
            : null;

        return $school ? (object) [
            'id' => $school->id,
            'name' => $school->name,
            'school_code' => $school->school_code ?? null,
            'academic_level' => $school->academic_level ?? null,
            'status' => $school->status ?? null,
            'metadata' => $school->metadata ?? [],
        ] : null;
    }

    /**
     * School ID
     */
    public function id(): ?int
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkSchool()['id'] ?? 1;
        }

        return currentSchool()?->id;
    }

    /**
     * School name
     */
    public function name(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkSchool()['name'] ?? 'SDK School';
        }

        return currentSchool()?->name;
    }

    /**
     * School code
     */
    public function code(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkSchool()['school_code'] ?? 'SDK-001';
        }

        return currentSchool()?->school_code;
    }

    /**
     * Academic level
     */
    public function academicLevel(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkSchool()['academic_level'] ?? 'secondary';
        }

        return currentSchool()?->academic_level;
    }

    /**
     * Status
     */
    public function status(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkSchool()['status'] ?? 'active';
        }

        return currentSchool()?->status;
    }

    /**
     * Metadata
     */
    public function metadata(): array
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkSchool()['metadata'] ?? [];
        }

        return currentSchool()?->metadata ?? [];
    }

    /**
     * Load SDK school
     */
    protected function sdkSchool(): array
    {
        if ($this->sdkSchool !== null) {
            return $this->sdkSchool;
        }

        $path = Helper::dataFolder('tenants/schools/school_1.json');

        if (!file_exists($path)) {
            return $this->sdkSchool = [
                'id' => 1,
                'name' => 'SDK Demo School',
                'school_code' => 'SDK-001',
                'academic_level' => 'secondary',
                'status' => 'active',
                'metadata' => [],
            ];
        }

        return $this->sdkSchool = Helper::loadJson($path);
    }
}