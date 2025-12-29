<?php

namespace SchoolPalm\ModuleBridge\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class SchoolModel
 *
 * Base Eloquent model that automatically applies school scoping.
 * All models extending this class will be filtered by the current school context.
 *
 * @package SchoolPalm\ModuleBridge\Core
 */
abstract class SchoolModel extends Model
{
    /**
     * Boot the model and apply global scopes.
     */
    protected static function boot()
    {
        parent::boot();

        // Apply global scope for current school
        static::addGlobalScope('currentSchool', function (Builder $builder) {
            $currentSchool = app('current_school');
            if ($currentSchool) {
                $builder->where('school_id', $currentSchool->id);
            }
        });
    }

    /**
     * Scope a query to override the global school scope.
     *
     * @param Builder $query
     * @param int $schoolId
     * @return Builder
     */
    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->withoutGlobalScope('currentSchool')->where('school_id', $schoolId);
    }
}
