<?php

declare(strict_types=1);

namespace Modules\Order\Repositories\Eloquent\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The date_range filter of core::partials.filters: values arrive in the admin datepicker
 * format (config core.encrypt.php_datepicker_format, d-m-Y).
 */
trait FiltersByDateRange
{
    /**
     * @return array<string, mixed>
     */
    protected function dateRangeFilter(string $prefix, string $label, Closure $filter, string $row = '1'): array
    {
        return [
            'type' => 'date_range',
            'name' => [$prefix.'_from', $prefix.'_to'],
            'row' => $row,
            'value' => [$prefix.'_from' => $filter($prefix.'_from'), $prefix.'_to' => $filter($prefix.'_to')],
            'options' => [
                $prefix.'_from' => ['label' => $label, 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                $prefix.'_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
            ],
        ];
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function applyDateRange(Builder $query, string $column, mixed $from, mixed $to): void
    {
        if (($start = $this->parseFilterDate($from)) !== null) {
            $query->where($column, '>=', $start->startOfDay());
        }

        if (($end = $this->parseFilterDate($to)) !== null) {
            $query->where($column, '<=', $end->endOfDay());
        }
    }

    protected function parseFilterDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat((string) config('core.encrypt.php_datepicker_format', 'd-m-Y'), trim($value)) ?: null;
        } catch (Throwable) {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }
    }
}
