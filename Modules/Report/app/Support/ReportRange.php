<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A report period (whole days, inclusive) and its comparison mode: the previous period of
 * the same length, the same period last year, or none. Invalid input falls back to the
 * default preset with a warning instead of failing the page.
 */
final class ReportRange
{
    public const COMPARE_PREVIOUS = 'previous';

    public const COMPARE_LAST_YEAR = 'last_year';

    public const COMPARE_NONE = 'none';

    /**
     * @param  list<string>  $warnings  lang keys under report::report.warnings
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset = 'custom',
        public readonly string $compareMode = self::COMPARE_PREVIOUS,
        public readonly array $warnings = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input  preset, from, to (Y-m-d), compare
     * @param  list<string>|null  $presets  allowed presets (default: config report.presets)
     */
    public static function fromInput(array $input, ?string $defaultPreset = null, ?array $presets = null): self
    {
        $presets ??= (array) config('report.presets');
        $defaultPreset ??= (string) config('report.default_preset', 'last_30');
        $warnings = [];

        $compare = (string) ($input['compare'] ?? self::COMPARE_PREVIOUS);
        if (! in_array($compare, (array) config('report.compare_modes'), true)) {
            $compare = self::COMPARE_PREVIOUS;
        }

        $preset = (string) ($input['preset'] ?? '');
        if ($preset === '') {
            $preset = (isset($input['from']) || isset($input['to'])) && in_array('custom', $presets, true) ? 'custom' : $defaultPreset;
        }

        if (! in_array($preset, $presets, true)) {
            $warnings[] = 'invalid_range';
            $preset = $defaultPreset;
        }

        if ($preset !== 'custom') {
            [$from, $to] = self::presetDates($preset);

            return new self($from, $to, $preset, $compare, $warnings);
        }

        $from = self::parseDate($input['from'] ?? null);
        $to = self::parseDate($input['to'] ?? null);
        $today = CarbonImmutable::today();

        if ($from === null || $to === null || $from->greaterThan($to)) {
            [$from, $to] = self::presetDates($defaultPreset);

            return new self($from, $to, $defaultPreset, $compare, [...$warnings, 'invalid_range']);
        }

        if ($to->greaterThan($today)) {
            $to = $today;
            $from = $from->greaterThan($to) ? $to : $from;
        }

        $maxDays = max(1, (int) config('report.max_range_days', 366));
        if ($from->diffInDays($to) + 1 > $maxDays) {
            $from = $to->subDays($maxDays - 1);
            $warnings[] = 'range_clamped';
        }

        return new self($from, $to, 'custom', $compare, $warnings);
    }

    public static function between(string|Carbon|CarbonImmutable $from, string|Carbon|CarbonImmutable $to, string $compare = self::COMPARE_PREVIOUS): self
    {
        return new self(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay(), 'custom', $compare);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function presetDates(string $preset): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last_7' => [$today->subDays(6), $today],
            'last_90' => [$today->subDays(89), $today],
            'this_month' => [$today->startOfMonth(), $today],
            default => [$today->subDays(29), $today],
        };
    }

    /**
     * The comparison period, or null when comparison is off.
     */
    public function compare(): ?self
    {
        return match ($this->compareMode) {
            self::COMPARE_NONE => null,
            self::COMPARE_LAST_YEAR => new self($this->from->subYear(), $this->to->subYear(), 'custom', self::COMPARE_NONE),
            default => new self($this->from->subDays($this->days()), $this->from->subDay(), 'custom', self::COMPARE_NONE),
        };
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * @return list<string> Y-m-d, oldest first
     */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from; $day->lessThanOrEqualTo($this->to); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    public function includesToday(): bool
    {
        $today = CarbonImmutable::today();

        return $this->from->lessThanOrEqualTo($today) && $this->to->greaterThanOrEqualTo($today);
    }

    public function label(): string
    {
        return $this->from->equalTo($this->to)
            ? $this->from->format('d M Y')
            : $this->from->format('d M Y').' – '.$this->to->format('d M Y');
    }

    /**
     * Query parameters that rebuild this range (links, exports).
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = ['preset' => $this->preset, 'compare' => $this->compareMode];

        if ($this->preset === 'custom') {
            $query['from'] = $this->from->toDateString();
            $query['to'] = $this->to->toDateString();
        }

        return $query;
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date !== false && $date->toDateString() === $value ? $date : null;
    }
}
