<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\FeeRule;

/**
 * Admin writes of commission and fee rules. Scope decides which of category / seller is kept;
 * order items keep their commission snapshot, so editing or deleting a rule only affects
 * orders placed afterwards.
 */
class FinanceRuleService
{
    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function saveCommissionRule(?CommissionRule $rule, array $data, ?Model $actor = null): CommissionRule
    {
        $rule ??= new CommissionRule(['created_by' => $actor?->getKey()]);
        $rule->fill($this->scoped($data) + [
            'type' => $data['type'],
            'value' => $data['value'],
            'applies_to' => $data['applies_to'] ?? CommissionRule::APPLIES_TO_ITEM_PRICE,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => $data['status'],
        ])->save();

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function saveFeeRule(?FeeRule $rule, array $data, ?Model $actor = null): FeeRule
    {
        $rule ??= new FeeRule(['created_by' => $actor?->getKey()]);
        $rule->fill($this->scoped($data) + [
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $data['value'],
            'charged_to' => $data['charged_to'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => $data['status'],
        ])->save();

        return $rule;
    }

    public function delete(CommissionRule|FeeRule $rule): void
    {
        $rule->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{scope: string, catalog_category_id: int|null, seller_id: int|null}
     */
    private function scoped(array $data): array
    {
        $scope = (string) $data['scope'];

        return [
            'scope' => $scope,
            'catalog_category_id' => in_array($scope, ['category', 'seller_category'], true) ? (int) $data['catalog_category_id'] : null,
            'seller_id' => in_array($scope, ['seller', 'seller_category'], true) ? (int) $data['seller_id'] : null,
        ];
    }
}
