<?php

namespace SchoolPalm\ModuleBridge\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SchoolPalm\ModuleBridge\Context\CurrentContext;
use SchoolPalm\ModuleBridge\Facades\Host\Host;

abstract class Model extends EloquentModel
{
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Module that owns this model.
     *
     * Generated models should override this value.
     */
    protected string $moduleKey;

    /**
     * Whether module context must be enforced.
     */
    protected bool $enforceContext = true;

    /**
     * Boot global model behaviors.
     */
    protected static function booted()
    {
        /**
         * Enforce owning module context.
         *
         * This prevents another module from directly accessing
         * this model through Eloquent.
         */
        static::retrieved(function ($model) {
            $model->requireModuleContext();
        });

        static::creating(function ($model) {

            $model->requireModuleContext();

            $keyName = $model->getKeyName();

            unset($model->{$keyName});

            $model->{$keyName} = (string) Str::ulid();

            if (Schema::hasColumn($model->getTable(), 'school_id')) {

                $schoolId = Host::school()?->id;

                if ($schoolId === null) {
                    throw new \RuntimeException(
                        'Cannot create a school-owned model without a current school context.'
                    );
                }

                $model->school_id = $schoolId;
            }
        });

        static::updating(function ($model) {

            $model->requireModuleContext();

            if (Schema::hasColumn($model->getTable(), 'school_id')) {
                unset($model->school_id);
            }

            if ($model->isDirty('created_at')) {
                $model->created_at = $model->getOriginal('created_at');
            }

            if ($model->isDirty('deleted_at')) {
                $model->deleted_at = $model->getOriginal('deleted_at');
            }
        });
    }

    /**
     * Require the current module context.
     */
    protected function requireModuleContext(): void
    {
        if (!$this->enforceContext) {
            return;
        }

        if (empty($this->moduleKey)) {
            throw new \RuntimeException(
                sprintf(
                    'Model [%s] does not define a module key.',
                    static::class
                )
            );
        }

        CurrentContext::require($this->moduleKey);
    }

    /**
     * Get the owning module key.
     */
    public function getModuleKey(): string
    {
        return $this->moduleKey;
    }

    /**
     * Disable module context enforcement.
     *
     * Intended only for trusted internal/system operations.
     */
    public static function withoutModuleContext(): void
    {
        static::withoutGlobalScopes();
    }

    /**
     * Get the current school context.
     */
    public static function currentSchool()
    {
        return Host::school();
    }

    /**
     * Get the current tenant context.
     */
    public static function currentTenant()
    {
        return Host::tenant();
    }

    /**
     * Get the current user context.
     */
    public static function currentUser()
    {
        return Host::user();
    }

    /**
     * Determine whether an attribute should be treated as a date.
     */
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
