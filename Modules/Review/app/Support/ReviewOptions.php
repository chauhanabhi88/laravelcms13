<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Modules\Review\Models\ProductReview;

/**
 * Labels and badge / chip colours of review statuses, flags and report reasons.
 */
final class ReviewOptions
{
    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return collect(ProductReview::STATUSES)->mapWithKeys(fn (string $status): array => [$status => trans('review::review.statuses.'.$status)])->all();
    }

    /**
     * How the customer sees the status of their own review.
     */
    public static function customerStatus(string $status): string
    {
        return trans('review::front.statuses.'.$status);
    }

    /**
     * Storefront chip variant (<x-theme::chip>).
     */
    public static function chip(string $status): string
    {
        return match ($status) {
            ProductReview::STATUS_PUBLISHED => 'success',
            ProductReview::STATUS_REJECTED, ProductReview::STATUS_HIDDEN => 'danger',
            default => 'neutral',
        };
    }

    /**
     * Admin (Bootstrap) badge colour.
     */
    public static function badge(string $status): string
    {
        return match ($status) {
            ProductReview::STATUS_PUBLISHED => 'success',
            ProductReview::STATUS_PENDING => 'warning',
            ProductReview::STATUS_REJECTED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function flags(): array
    {
        $flags = [ProductReview::FLAG_PROFANITY, ProductReview::FLAG_LINK, ProductReview::FLAG_CONTACT, ProductReview::FLAG_DUPLICATE, ProductReview::FLAG_TOO_SHORT];

        return collect($flags)->mapWithKeys(fn (string $flag): array => [$flag => trans('review::review.flags.'.$flag)])->all();
    }

    /**
     * @return array<string, string>
     */
    public static function reportReasons(): array
    {
        return collect((array) config('review.report_reasons', []))->mapWithKeys(fn (string $reason): array => [$reason => trans('review::review.report_reasons.'.$reason)])->all();
    }

    /**
     * @return array<int, string> 5 … 1 => "5 ★"
     */
    public static function ratings(): array
    {
        return collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $stars): array => [$stars => trans('review::review.stars', ['count' => $stars])])->all();
    }
}
