<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Adapters;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use SchoolPalm\AppLogger\Context\AppContext;
use SchoolPalm\AppLogger\Contracts\LogDriver;
use SchoolPalm\AppLogger\Contracts\LogRepository;
use SchoolPalm\AppLogger\Facades\AppLogger;
use SchoolPalm\AppLogger\Support\LogPayload;
use SchoolPalm\ModuleBridge\Services\ContextResolver;
use Throwable;

/**
 * Log Adapter with Automated Context & Channel Resolution
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
final class LoggerAdapter
{
    private ?string $channel = null;

    public function __construct(
        private readonly ContextResolver $contextResolver
    ) {}

    /**
     * Set a custom channel fluently.
     */
    public function channel(?string $channel): self
    {
        $clone = clone $this;
        $clone->channel = $channel;

        return $clone;
    }

    /**
     * Standardized logging methods that automatically resolve AppContext.
     */
    public function info(string $message, array $data = []): bool
    {
        return $this->logPayload(LogPayload::info($message, $this->resolveContext(), $data));
    }

    public function debug(string $message, array $data = []): bool
    {
        return $this->logPayload(LogPayload::debug($message, $this->resolveContext(), $data));
    }

    public function warning(string $message, array $data = []): bool
    {
        return $this->logPayload(LogPayload::warning($message, $this->resolveContext(), $data));
    }

    public function error(string $message, array $data = [], ?Throwable $exception = null): bool
    {
        $payload = LogPayload::error($message, $this->resolveContext(), $data);

        if ($exception !== null) {
            $payload = $payload->withException($exception);
        }

        return $this->logPayload($payload);
    }

    public function exception(Throwable $exception, array $data = []): bool
    {
        return $this->error($exception->getMessage(), $data, $exception);
    }

    /**
     * Dispatch payload with auto-resolved context & channel attached.
     */
    public function log(LogPayload $payload): bool
    {
        return $this->logPayload($payload);
    }

    /**
     * Proxy remaining LoggerManager calls directly to the facade.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $context = $this->resolveContext();
        $channel = $this->resolveChannel($context);

        $logger = AppLogger::withContext($context);

        if ($channel !== null) {
            $logger = $logger->channel($channel);
        }

        return $logger->{$method}(...$arguments);
    }

    private function logPayload(LogPayload $payload): bool
    {
        $context = $this->resolveContext();
        $channel = $this->resolveChannel($context);

        $logger = AppLogger::withContext($context);

        if ($channel !== null) {
            $logger = $logger->channel($channel);
        }

        return $logger->log($payload);
    }

    private function resolveContext(): AppContext
    {
        return $this->contextResolver->appContext();
    }

    /**
     * Resolve explicit channel or fallback to context channel/module name.
     */
    private function resolveChannel(AppContext $context): ?string
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        // Check if AppContext exposes channel via method, property, or array key
        if (method_exists($context, 'channel')) {
            return $context->channel();
        }

        if (method_exists($context, 'get')) {
            return $context->get('channel') ?? $context->get('module');
        }

        return null;
    }
}
