<?php

namespace SchoolPalm\ModuleBridge\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

abstract class Model extends EloquentModel
{
    /**
     * Boot global model behaviors
     */
    protected static function booted()
    {
        /**
         * GLOBAL SCOPE: school filtering
         */
        static::addGlobalScope('school_scope', function (Builder $builder) {

            $schoolId = static::resolveSchoolId();

            if ($schoolId) {
                $table = $builder->getModel()->getTable();

                $builder->where("{$table}.school_id", $schoolId);
            }
        });

        /**
         * AUTO-SET school_id on create
         */
        static::creating(function ($model) {

            if (Schema::hasColumn($model->getTable(), 'school_id')) {

                $schoolId = static::resolveSchoolId();

                if ($schoolId) {
                    $model->school_id = $schoolId;
                }
            }
        });

        /**
         * Prevent accidental overwrite of school_id
         */
        static::updating(function ($model) {
           
    unset($model->school_id);

    // Remove user-supplied values
    if ($model->isDirty('created_at')) {
        $model->created_at = $model->getOriginal('created_at');
    }

    if ($model->isDirty('deleted_at')) {
        $model->deleted_at = $model->getOriginal('deleted_at');
    }
        });

 
    }

    /**
     * Centralized safe resolver for school ID
     * Works with session + fallback + tenancy-safe context
     */
    protected static function resolveSchoolId(): ?int
    {
        $key = config('sdk.current_school_session_key', 'current_school_id');

        // Safe session access (NEVER assume session exists or is string-safe)
        if (function_exists('session')) {
            try {
                $value = session()->get($key);

                if (is_numeric($value)) {
                    return (int) $value;
                }
            } catch (\Throwable $e) {
                // ignore session failures (CLI, boot, modules)
            }
        }

        // fallback (you can replace this with tenant school resolver)
        return 1;
    }

    /**
     * Disable school scope when needed
     */
    public static function withoutSchoolScope()
    {
        return static::withoutGlobalScope('school_scope');
    }


   public function isDateAttribute($key)
{
    return in_array($key, [
        $this->getCreatedAtColumn(),
        $this->getUpdatedAtColumn(),
        'deleted_at',
    ], true)
    || (
        isset($this->casts[$key]) &&
        in_array($this->casts[$key], [
            'date',
            'datetime',
            'immutable_date',
            'immutable_datetime',
        ], true)
    );
}





}
