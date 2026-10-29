<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Finance\Models\FeeRule;
use Modules\Finance\Support\FinanceOptions;

/**
 * Platform fee rules (plan M17 §3.2): closing / gateway fees, shipping pass-through and
 * penalties. Only rules charged to the seller reach the ledger; the defaults are 0 and
 * charged to the platform.
 */
class FeeRuleController extends RuleController
{
    protected const GRID = 'fee_rule';

    protected const VIEW = 'fee_rule';

    protected function save(?Model $rule, array $data, Request $request): Model
    {
        /** @var FeeRule|null $rule */
        return $this->rules->saveFeeRule($rule, $data, $request->user());
    }

    protected function formOptions(): array
    {
        return parent::formOptions() + [
            'codeOptions' => FinanceOptions::feeCodes(),
            'typeOptions' => FinanceOptions::feeTypes(),
            'chargedToOptions' => FinanceOptions::chargedTo(),
        ];
    }
}
