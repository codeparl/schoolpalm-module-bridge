<?php

namespace SchoolPalm\ModuleBridge\Support;

use Illuminate\Support\Collection;
use SchoolPalm\ModuleBridge\Platform\CurriculumFetcher;

/**
 * Static façade
 */
final class LevelManager
{
    private const CONFIG_KEY = 'academic_levels';

    private static ?LevelResolver $default = null;

    /**
     * Get a chainable, immutable resolver
     */
    public static function level(?array $levels = null): LevelResolver
    {
        if ($levels !== null) {
            return LevelResolver::from($levels);
        }

        if (self::$default !== null) {
            return self::$default;
        }

        try {
            $fetcher = new CurriculumFetcher();
            $config = $fetcher->getConfig();

            $levels = self::normalizeLevels($config['levels'] ?? []);

            return self::$default = LevelResolver::from($levels);
        } catch (\Throwable $e) {
            // fallback to encrypted config (offline safety)
            return self::$default = LevelResolver::from(
                EncryptedConfig::read(self::CONFIG_KEY)
            );
        }
    }

    /**
     * Manually override default levels
     */
    public static function setDefaultLevels(array $levels): void
    {
        self::$default = LevelResolver::from(self::normalizeLevels($levels));
    }

    /**
     * Boot levels directly from SDK config (recommended)
     */
    public static function bootFromConfig(array $config): void
    {
        if (!empty($config['levels'])) {
            self::setDefaultLevels($config['levels']);
        }
    }

    /**
     * Normalize levels structure
     */
    protected static function normalizeLevels(array $levels): array
    {
        return array_map(function ($level) {
            return [
                'label' => $level['label'] ?? '',
                'code'  => $level['code'] ?? '',
            ];
        }, $levels);
    }

    /* ---- Static shortcuts ---- */

    public static function all(): array
    {
        return self::level()->all();
    }

    public static function pair($kk, $kv, $vv = 'label'): array
    {
        return (new Collection(self::level()->all()))
            ->map(function ($v, $k) use ($kk, $kv, $vv) {
                return [$kk => $k, $kv => $v[$vv] ?? null];
            })
            ->toArray();
    }

    public static function getByNumber(int $number): ?array
    {
        return self::level()->getByNumber($number);
    }

    public static function getNumber(string $value): ?int
    {
        return self::level()->getNumber($value);
    }

    public static function allLabels(): array
    {
        return self::level()->allLabels();
    }

    public static function allCodes(): array
    {
        return self::level()->allCodes();
    }

    public static function joinByCodes(array $numbers): string
    {
        return self::level()->joinByCodes($numbers);
    }

    public static function labels(array $numbers): array
    {
        return self::level()->labels($numbers);
    }

    public static function levelExists(string|int $level, string $folderName): bool
    {
        return self::level()->levelExists($level, $folderName);
    }
}

/**
 * Immutable, stateful resolver
 */
final class LevelResolver
{
    /**
     * @param array<int, array{label:string, code:string}> $levels
     */
    private function __construct(
        private readonly array $levels
    ) {}

    /**
     * Named constructor
     */
    public static function from(array $levels): self
    {
        return new self($levels);
    }

    /* ---------------- Queries ---------------- */

    public function all(): array
    {
        return $this->levels;
    }

    public function getByNumber(int $number): ?array
    {
        return $this->levels[$number] ?? null;
    }

    public function getNumber(string $value): ?int
    {
        $value = strtolower(trim($value));

        foreach ($this->levels as $number => $data) {
            if (
                strtolower($data['label'] ?? '') === $value ||
                strtolower($data['code'] ?? '') === $value
            ) {
                return $number;
            }
        }

        return null;
    }

    public function allLabels(): array
    {
        return array_map(
            static fn($d) => $d['label'],
            $this->levels
        );
    }

    public function allCodes(): array
    {
        return array_map(
            static fn($d) => $d['code'],
            $this->levels
        );
    }

    public function joinByCodes(array $numbers): string
    {
        if (empty($numbers) || in_array(0, $numbers, true)) {
            return 'Common';
        }

        $codes = [];

        foreach ($numbers as $number) {
            $level = $this->getByNumber((int) $number);
            if ($level && !empty($level['code'])) {
                $codes[] = ucfirst(strtolower($level['code']));
            }
        }

        return $codes ? implode('', $codes) : 'Common';
    }

    public function labels(array $numbers): array
    {
        if (empty($numbers) || in_array(0, $numbers, true)) {
            return ['Common'];
        }

        $labels = [];

        foreach ($numbers as $number) {
            $level = $this->getByNumber((int) $number);
            if ($level && !empty($level['label'])) {
                $labels[] = strtolower($level['label']);
            }
        }

        return $labels;
    }

    public function levelExists(string|int $level, string $folderName): bool
    {
        if (strcasecmp($folderName, 'Common') === 0) {
            return true;
        }

        $number = is_numeric($level)
            ? (int) $level
            : $this->getNumber((string) $level);

        if (!$number) {
            return false;
        }

        $data = $this->getByNumber($number);

        return $data && !empty($data['code'])
            && str_contains(
                strtolower($folderName),
                strtolower($data['code'])
            );
    }

    /**
     * Return a NEW resolver with replaced levels
     */
    public function withLevels(array $levels): self
    {
        return new self($levels);
    }
}
