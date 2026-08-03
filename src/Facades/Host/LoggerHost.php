<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Facades\Host;

use Illuminate\Support\Facades\Facade;
use SchoolPalm\ModuleBridge\Adapters\LoggerAdapter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use SchoolPalm\AppLogger\Context\AppContext;
use SchoolPalm\AppLogger\Contracts\LogDriver;
use SchoolPalm\AppLogger\Contracts\LogRepository;
use SchoolPalm\AppLogger\Support\LogPayload;
use Throwable;

/**
 * Log Adapter with Automated Context Resolution
 *
 * --- Channel & Context ---
 * @method static self channel(?string $channel)
 * @method static string getDefaultDriver()
 * @method static AppContext context()
 *
 * --- Drivers & Repository ---
 * @method static LogDriver createDatabaseDriver()
 * @method static LogDriver createFileDriver()
 * @method static LogRepository logs()
 *
 * --- Logging Methods (Context Injected Automatically) ---
 * @method static bool log(LogPayload $payload)
 * @method static bool info(string $message, array $data = [])
 * @method static bool debug(string $message, array $data = [])
 * @method static bool warning(string $message, array $data = [])
 * @method static bool error(string $message, array $data = [], ?Throwable $exception = null)
 * @method static bool exception(Throwable $exception, array $data = [])
 *
 * --- Maintenance & Query ---
 * @method static Collection logsByContext(string $key, mixed $value = null, int $limit = 100)
 * @method static bool delete(mixed $id)
 * @method static bool flush()
 * @method static int prune(CarbonInterface $before)
 *
 * --- Exporting & Formatting ---
 * @method static mixed format(Collection $logs, string $formatter)
 * @method static string export(mixed $content, string $filename)
 * @method static string exportLogs(Collection $logs, string $format, string $filename)
 */
class LoggerHost extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LoggerAdapter::class;
    }
}
