<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Seller\Exceptions\IfscLookupUnavailable;
use Modules\Seller\Support\IfscLookup;
use Modules\Seller\Support\Validation\Ifsc;

/**
 * Onboarding step 3 and Settings › Bank: payout account, IFSC checked against the IFSC
 * directory (bank and branch filled from it), optional cancelled cheque.
 */
class BankAccountRequest extends FormRequest
{
    /**
     * @var array{bank: string, branch: string}|null
     */
    private ?array $ifscDetails = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'account_holder_name' => trim((string) $this->input('account_holder_name')),
            'account_number' => preg_replace('/\s+/', '', (string) $this->input('account_number')),
            'account_number_confirmation' => preg_replace('/\s+/', '', (string) $this->input('account_number_confirmation')),
            'ifsc' => strtoupper(trim((string) $this->input('ifsc'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_holder_name' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[\pL .&\'\-]+$/u'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{9,18}$/', 'confirmed'],
            'ifsc' => ['required', 'string', 'size:11', new Ifsc],
            'account_type' => ['required', Rule::in((array) config('seller.bank_account_types'))],
            'cancelled_cheque' => ['nullable', 'file', 'mimes:'.config('seller.kyc_mimes'), 'max:'.((int) config('seller.kyc_max_file_mb') * 1024)],
        ];
    }

    /**
     * The IFSC must exist in the directory. If the directory is down, the format check alone
     * lets the seller continue; an admin still sees the account in review.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('ifsc')) {
                    return;
                }

                try {
                    $details = app(IfscLookup::class)->find((string) $this->input('ifsc'));
                } catch (IfscLookupUnavailable $e) {
                    report($e);

                    return;
                }

                if ($details === null) {
                    $validator->errors()->add('ifsc', trans('seller::validation.ifsc.unknown'));

                    return;
                }

                $this->ifscDetails = ['bank' => $details['bank'], 'branch' => $details['branch']];
            },
        ];
    }

    /**
     * @return array{account_holder_name: string, account_number: string, ifsc: string, account_type: string, bank_name: string|null, branch: string|null}
     */
    public function bankData(): array
    {
        $validated = $this->validated();

        return [
            'account_holder_name' => $validated['account_holder_name'],
            'account_number' => $validated['account_number'],
            'ifsc' => $validated['ifsc'],
            'account_type' => $validated['account_type'],
            'bank_name' => $this->ifscDetails['bank'] ?? null,
            'branch' => $this->ifscDetails['branch'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_number.regex' => trans('seller::validation.account_number'),
            'account_number.confirmed' => trans('seller::validation.account_number_confirmed'),
            'account_holder_name.regex' => trans('seller::validation.holder_name'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('seller::onboarding.attributes');
    }
}
