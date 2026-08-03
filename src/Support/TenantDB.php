<?php

namespace SchoolPalm\ModuleBridge\Support;

use Illuminate\Support\Facades\DB;

class TenantDB
{
    /**
     * Run code in central DB context
     */
    public static function central(callable $callback)
    {
        $previous = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection(config('database.default'));
            return $callback();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    /**
     * Run code in tenant DB context
     */
    public static function tenant(string $connection, callable $callback)
    {
        $previous = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection($connection);
            return $callback();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }
}