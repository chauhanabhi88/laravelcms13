<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Support\FinanceOptions;

/**
 * Commission rules (FR-FIN-001): global / category / seller / seller + category, percent or
 * flat per item, with effective dates. The seeded global rule is 0 %.
 */
class CommissionRuleController extends RuleController
{
    protected const GRID = 'commission_rule';

    protected const VIEW = 'commission_rule';

    protected function save(?Model $rule, array $data, Request $request): Model
    {
        /** @var CommissionRule|null $rule */
        return $this->rules->saveCommissionRule($rule, $data, $request->user());
    }

    protected function formOptions(): array
    {
        return parent::formOptions() + [
            'typeOptions' => FinanceOptions::commissionTypes(),
            'appliesToOptions' => FinanceOptions::appliesTo(),
        ];
    }
}
