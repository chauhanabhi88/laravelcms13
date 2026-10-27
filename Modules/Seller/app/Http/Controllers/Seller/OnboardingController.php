<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Exceptions\IfscLookupUnavailable;
use Modules\Seller\Exceptions\SellerException;
use Modules\Seller\Http\Requests\Seller\AgreementRequest;
use Modules\Seller\Http\Requests\Seller\BankAccountRequest;
use Modules\Seller\Http\Requests\Seller\BusinessDetailsRequest;
use Modules\Seller\Http\Requests\Seller\TaxDetailsRequest;
use Modules\Seller\Http\Requests\Seller\WarehouseRequest;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerKycDocument;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Services\SellerOnboardingService;
use Modules\Seller\Support\IfscLookup;
use Modules\Seller\Support\SellerSettings;
use Modules\Seller\Support\Validation\Ifsc;

/**
 * /supplier/onboarding/{step}: the KYC wizard, its status page and the small JSON helpers
 * the bank step uses (IFSC autofill, penny-drop polling).
 */
class OnboardingController extends SellerPanelController
{
    public function __construct(private readonly SellerOnboardingService $onboarding) {}

    public function show(Request $request): View|RedirectResponse
    {
        $seller = $this->seller();
        $step = $request->route('step') !== null ? (int) $request->route('step') : null;

        if (! $seller->status->isEditable()) {
            return redirect()->to($this->homeFor($seller));
        }

        if ($step === null || ! $this->onboarding->canOpenStep($seller, $step)) {
            return redirect()->to(frontRoute('seller.onboarding.show', [$this->onboarding->resumeStep($seller)]));
        }

        return view('seller::seller.onboarding.step'.$step, $this->stepData($seller, $step));
    }

    public function save(Request $request): RedirectResponse
    {
        $seller = $this->seller();
        $step = (int) $request->route('step');

        if (! $seller->status->isEditable()) {
            return redirect()->to($this->homeFor($seller));
        }

        if (! $this->onboarding->canOpenStep($seller, $step)) {
            return redirect()->to(frontRoute('seller.onboarding.show', [$this->onboarding->resumeStep($seller)]));
        }

        try {
            return match ($step) {
                SellerOnboardingService::STEP_BUSINESS => $this->saveAndContinue($seller, $step, app(BusinessDetailsRequest::class)->validated()),
                SellerOnboardingService::STEP_TAX => $this->saveTax($seller, app(TaxDetailsRequest::class)),
                SellerOnboardingService::STEP_BANK => $this->saveBank($seller, app(BankAccountRequest::class)),
                SellerOnboardingService::STEP_WAREHOUSE => $this->saveAndContinue($seller, $step, app(WarehouseRequest::class)->warehouseData()),
                default => $this->submit($request, $seller, app(AgreementRequest::class)),
            };
        } catch (SellerException|InvalidStateTransition $e) {
            return back()->withInput($request->except('pan', 'account_number', 'account_number_confirmation'))->with('error', $e->getMessage());
        }
    }

    /**
     * Under review, rejected (with the reason and a restart button) or deactivated.
     */
    public function status(): View|RedirectResponse
    {
        $seller = $this->seller();

        if ($seller->status->isEditable() && $seller->status !== SellerStatus::ChangesRequested) {
            return redirect()->to(frontRoute('seller.onboarding.show', [$this->onboarding->resumeStep($seller)]));
        }

        if ($seller->hasStatus(SellerStatus::Active, SellerStatus::Suspended)) {
            return redirect()->to(frontRoute('seller.dashboard'));
        }

        return view('seller::seller.onboarding.status', [
            'seller' => $seller,
            'steps' => $this->stepLabels(),
            'completed' => $this->onboarding->completedSteps($seller),
        ]);
    }

    public function restart(): RedirectResponse
    {
        $seller = $this->seller();

        if ($seller->status !== SellerStatus::Rejected) {
            return redirect()->to($this->homeFor($seller));
        }

        $this->onboarding->restart($seller, $this->sellerUser());

        return redirect()->to(frontRoute('seller.onboarding.show', [1]))->with('success', trans('seller::onboarding.messages.restarted'));
    }

    /**
     * Polled by the bank step while a penny drop is in flight.
     */
    public function bankStatus(): JsonResponse
    {
        $account = $this->seller()->primaryBankAccount()->first();

        return response()->json([
            'status' => $account?->verification_status,
            'label' => $account?->statusLabel(),
            'note' => $account?->verification_note,
            'verified_name' => $account?->verified_name,
            'name_match_score' => $account?->name_match_score,
            'done' => $account !== null && ! $account->isPending(),
        ]);
    }

    /**
     * IFSC autofill for the bank forms.
     */
    public function ifsc(Request $request, IfscLookup $lookup): JsonResponse
    {
        $ifsc = strtoupper((string) $request->route('ifsc'));

        if (! Ifsc::isValid($ifsc)) {
            return response()->json(['found' => false, 'message' => trans('seller::validation.ifsc.format', ['attribute' => 'IFSC'])], 422);
        }

        try {
            $details = $lookup->find($ifsc);
        } catch (IfscLookupUnavailable) {
            return response()->json(['found' => null, 'message' => trans('seller::onboarding.bank.lookup_unavailable')]);
        }

        return $details === null
            ? response()->json(['found' => false, 'message' => trans('seller::validation.ifsc.unknown')], 404)
            : response()->json(['found' => true, 'bank' => $details['bank'], 'branch' => $details['branch'], 'city' => $details['city']]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveAndContinue(Seller $seller, int $step, array $data, array $files = []): RedirectResponse
    {
        $this->onboarding->saveStep($seller, $step, $data, $files);

        return redirect()->to(frontRoute('seller.onboarding.show', [$step + 1]))
            ->with('success', trans('seller::onboarding.messages.step_saved'));
    }

    private function saveTax(Seller $seller, TaxDetailsRequest $request): RedirectResponse
    {
        return $this->saveAndContinue($seller, SellerOnboardingService::STEP_TAX, $request->validated(), [
            'gst_certificate' => $request->file('gst_certificate'),
        ]);
    }

    private function saveBank(Seller $seller, BankAccountRequest $request): RedirectResponse
    {
        $this->onboarding->saveStep($seller, SellerOnboardingService::STEP_BANK, $request->bankData(), [
            'cancelled_cheque' => $request->file('cancelled_cheque'),
        ]);

        $account = $seller->primaryBankAccount()->first();
        $here = frontRoute('seller.onboarding.show', [SellerOnboardingService::STEP_BANK]);

        return match (true) {
            $account === null => redirect()->to($here)->with('error', trans('seller::onboarding.bank.failed')),
            $account->isVerified() => redirect()->to(frontRoute('seller.onboarding.show', [SellerOnboardingService::STEP_WAREHOUSE]))
                ->with('success', trans('seller::onboarding.bank.verified')),
            $account->isPending() => redirect()->to($here)->with('success', trans('seller::onboarding.bank.verifying')),
            $account->isAcceptableForSubmission() => redirect()->to(frontRoute('seller.onboarding.show', [SellerOnboardingService::STEP_WAREHOUSE]))
                ->with('success', trans('seller::onboarding.bank.manual_review')),
            $account->verification_status === SellerBankAccount::STATUS_MANUAL_REVIEW => redirect()->to($here)
                ->with('error', trans('seller::onboarding.bank.cheque_needed')),
            default => redirect()->to($here)->with('error', trans('seller::onboarding.bank.failed')),
        };
    }

    private function submit(Request $request, Seller $seller, AgreementRequest $agreement): RedirectResponse
    {
        $agreement->validated();
        $this->onboarding->submit($seller, $this->sellerUser(), $request->ip());

        return redirect()->to(frontRoute('seller.onboarding.status'))->with('success', trans('seller::onboarding.messages.submitted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function stepData(Seller $seller, int $step): array
    {
        $data = [
            'seller' => $seller,
            'step' => $step,
            'steps' => $this->stepLabels(),
            'completed' => $this->onboarding->completedSteps($seller),
        ];

        return $data + match ($step) {
            SellerOnboardingService::STEP_BUSINESS => [
                'businessTypes' => collect((array) config('seller.business_types'))
                    ->mapWithKeys(fn (string $type): array => [$type => trans('seller::seller.business_types.'.$type)])
                    ->all(),
            ],
            SellerOnboardingService::STEP_TAX => [
                'certificate' => SellerKycDocument::query()->where('seller_id', $seller->id)->where('type', 'gst_certificate')->latest('id')->first(),
                'allowNonGst' => SellerSettings::allowsNonGstSellers(),
            ],
            SellerOnboardingService::STEP_BANK => [
                'account' => $seller->primaryBankAccount()->with('chequeDocument')->first(),
                'accountTypes' => collect((array) config('seller.bank_account_types'))
                    ->mapWithKeys(fn (string $type): array => [$type => trans('seller::seller.account_types.'.$type)])
                    ->all(),
                'usesPennyDrop' => in_array(SellerSettings::bankVerifier(), ['razorpayx', 'fake'], true),
                'poll' => (array) config('seller.bank_poll'),
            ],
            SellerOnboardingService::STEP_WAREHOUSE => [
                'warehouse' => SellerWarehouse::query()->where('seller_id', $seller->id)->where('is_default', config('core.yes'))->first(),
            ],
            default => [
                'missing' => $this->onboarding->missingSteps($seller),
                'agreementVersion' => (string) config('seller.agreement_version'),
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function stepLabels(): array
    {
        return collect(range(1, Seller::STEPS))->mapWithKeys(fn (int $step): array => [$step => trans('seller::onboarding.steps.'.$step)])->all();
    }

    private function homeFor(Seller $seller): string
    {
        return $seller->hasStatus(SellerStatus::Active, SellerStatus::Suspended)
            ? frontRoute('seller.dashboard')
            : frontRoute('seller.onboarding.status');
    }
}
