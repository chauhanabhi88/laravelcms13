<?php

declare(strict_types=1);

namespace Modules\Promotion\Support;

/**
 * Translated select options of the promotion screens (lang promotion::promotion.options.*).
 */
final class PromotionOptions
{
    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return self::from('types', (array) config('promotion.types'));
    }

    /**
     * @return array<string, string>
     */
    public static function scopes(): array
    {
        return self::from('scopes', (array) config('promotion.scopes'));
    }

    /**
     * @return array<string, string>
     */
    public static function sellerScopes(): array
    {
        return self::from('scopes', (array) config('promotion.seller_scopes'));
    }

    /**
     * @return array<string, string>
     */
    public static function funding(): array
    {
        return self::from('funding', (array) config('promotion.funding'));
    }

    /**
     * @return array<string, string>
     */
    public static function channels(): array
    {
        return self::from('channels', (array) config('promotion.channels'));
    }

    /**
     * @return array<string, string>
     */
    public static function paymentMethods(): array
    {
        return self::from('payment_methods', (array) config('promotion.payment_methods'));
    }

    /**
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return self::from('kinds', ['code', 'auto', 'seller', 'personal']);
    }

    /**
     * @return array<string, string>
     */
    public static function dealStates(): array
    {
        return self::from('deal_states', ['running', 'scheduled', 'ended']);
    }

    /**
     * @return array<string, string>
     */
    public static function enabled(): array
    {
        return [
            (string) config('core.enabled') => trans('promotion::promotion.options.status.enabled'),
            (string) config('core.disabled') => trans('promotion::promotion.options.status.disabled'),
        ];
    }

    /**
     * Grid filter options by filter name.
     *
     * @return array<string, array<string, string>>
     */
    public static function filterOptions(string $grid): array
    {
        return $grid === 'flash_deal'
            ? ['state' => self::dealStates(), 'status' => self::enabled()]
            : ['type' => self::types(), 'kind' => self::kinds(), 'funded_by' => self::funding(), 'status' => self::enabled()];
    }

    public static function label(string $group, ?string $value): string
    {
        return $value === null || $value === '' ? '-' : trans('promotion::promotion.options.'.$group.'.'.$value);
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function from(string $group, array $values): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[(string) $value] = trans('promotion::promotion.options.'.$group.'.'.$value);
        }

        return $options;
    }
}
