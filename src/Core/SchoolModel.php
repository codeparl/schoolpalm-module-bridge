<?php

namespace SchoolPalm\ModuleBridge\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SchoolModel
 *
 * Base model for all school-related models in SchoolPalm modules.
 * 
 * Modules that operate on school-specific data (e.g., Students, Teachers, Classes)
 * should extend this class instead of directly extending Eloquent's Model.
 *
 * This ensures **multi-tenant safety** by automatically scoping queries to the 
 * current school and provides helpers for explicit school queries.
 *
 * ─────────────────────────────────────────────────────────────
 * MULTI-SCHOOL SCOPING
 * ─────────────────────────────────────────────────────────────
 * - Uses a **global scope** that automatically filters queries by `school_id` 
 *   according to the `current_school` context.
 * - Prevents modules from accidentally accessing data from other schools.
 *
 * ─────────────────────────────────────────────────────────────
 * USAGE
 * ─────────────────────────────────────────────────────────────
 * ```php
 * class Student extends SchoolModel
 * {
 *     protected $table = 'students';
 * }
 *
 * $students = Student::all(); // Automatically scoped to current_school
 * $otherSchoolStudents = Student::forSchool($schoolId)->get(); // Override scope
 * ```
 *
 * ─────────────────────────────────────────────────────────────
 * NOTES
 * ─────────────────────────────────────────────────────────────
 * - `current_school` must be resolved from the application context
 *   (e.g., via a tenant resolver or service container).
 * - Vendor modules should always extend `SchoolModel` for school-specific tables
 *   to ensure proper scoping in both production and SDK/test environments.
 *
 * @package SchoolPalm\ModuleBridge\Core
 */
abstract class SchoolModel extends Model
{
    /**
     * Boot the model and apply the global school scope.
     *
     * Automatically adds a `where('school_id', ...)` filter to all queries
     * based on the current school context.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('school', function ($query) {
            $school = app('current_school'); 
            if ($school) {
                $query->where('school_id', $school->id);
            }
        });
    }

    /**
     * Helper to override the school scope if needed.
     *
     * Allows fetching records for a specific school explicitly,
     * bypassing the `current_school` global scope.
     *
     * @param int $schoolId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function forSchool(int $schoolId)
    {
        return $this->where('school_id', $schoolId);
    }
}
