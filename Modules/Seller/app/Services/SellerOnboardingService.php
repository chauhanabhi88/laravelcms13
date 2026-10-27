<?php

declare(strict_types=1);

namespace Modules\Seller\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Seller\Contracts\GstinVerifier;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Events\SellerSubmittedForVerification;
use Modules\Seller\Exceptions\SellerException;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Support\NameMatcher;
use Modules\Seller\Support\SellerSettings;
use Modules\Seller\Support\Validation\Gstin;

/**
 * The five-step onboarding wizard: 1 business, 2 tax, 3 bank, 4 pickup address,
 * 5 agreement + submit. Each step saves on submit and moves the resume point
 * (onboarding_step) forward; earlier steps stay editable while the seller is in
 * "onboarding" or "changes_requested".
 */
class SellerOnboardingService
{
    public const STEP_BUSINESS = 1;

    public const STEP_TAX = 2;

    public const STEP_BANK = 3;

    public const STEP_WAREHOUSE = 4;

    public const STEP_AGREEMENT = 5;

    public function __construct(
        private readonly KycDocumentService $documents,
        private readonly BankAccountService $bankAccounts,
        private readonly WarehouseService $warehouses,
        private readonly GstinVerifier $gstinVerifier,
    ) {}

    /**
     * Save one step. $files holds the step's uploads (gst_certificate, cancelled_cheque).
     *
     * @param  array<string, mixed>  $data  validated input
     * @param  array<string, UploadedFile|null>  $files
     */
    public function saveStep(Seller $seller, int $step, array $data, array $files = []): Seller
    {
        $this->ensureEditable($seller);

        match ($step) {
            self::STEP_BUSINESS => $this->saveBusiness($seller, $data),
            self::STEP_TAX => $this->saveTax($seller, $data, $files['gst_certificate'] ?? null),
            self::STEP_BANK => $this->bankAccounts->save($seller, $data, $files['cancelled_cheque'] ?? null),
            self::STEP_WAREHOUSE => $this->saveWarehouse($seller, $data),
            default => throw new SellerException(trans('seller::onboarding.messages.invalid_step')),
        };

        $seller->refresh();
        $next = min(Seller::STEPS, $step + 1);

        if ($seller->onboarding_step < $next) {
            $seller->update(['onboarding_step' => $next]);
        }

        return $seller;
    }

    /**
     * Accept the agreement and send the application for review.
     *
     * @throws SellerException when a step is incomplete
     */
    public function submit(Seller $seller, SellerUser $user, ?string $ip): Seller
    {
        $this->ensureEditable($seller);

        $missing = $this->missingSteps($seller);
        if ($missing !== []) {
            throw new SellerException(trans('seller::onboarding.messages.steps_incomplete', [
                'steps' => implode(', ', array_map(fn (int $step): string => trans('seller::onboarding.steps.'.$step), $missing)),
            ]));
        }

        DB::transaction(function () use ($seller, $user, $ip): void {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $this->ensureEditable($seller);

            $seller->fill([
                'agreement_version' => (string) config('seller.agreement_version'),
                'agreement_accepted_at' => Carbon::now(),
                'agreement_accepted_ip' => $ip ? mb_substr($ip, 0, 45) : null,
                'submitted_at' => Carbon::now(),
                'onboarding_step' => Seller::STEPS,
                'status_reason' => null,
            ]);

            $seller->transitionTo(SellerStatus::PendingVerification, null, [], $user);

            event(new SellerSubmittedForVerification($seller));
        });

        return $seller->refresh();
    }

    /**
     * A rejected seller starts the wizard again (data is kept for editing).
     */
    public function restart(Seller $seller, SellerUser $user): Seller
    {
        DB::transaction(function () use ($seller, $user): void {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $seller->onboarding_step = self::STEP_BUSINESS;
            $seller->transitionTo(SellerStatus::Onboarding, null, [], $user);
        });

        return $seller->refresh();
    }

    /**
     * @return array<int, bool> step => complete
     */
    public function completedSteps(Seller $seller): array
    {
        $bank = $seller->primaryBankAccount()->first();
        $certificate = $this->documents->latestOfType($seller, 'gst_certificate');

        return [
            self::STEP_BUSINESS => filled($seller->store_name) && filled($seller->legal_name) && filled($seller->business_type) && filled($seller->pan_last4),
            self::STEP_TAX => $seller->isGstRegistered()
                ? $certificate !== null
                : (filled($seller->enrolment_id) && SellerSettings::allowsNonGstSellers()),
            self::STEP_BANK => $bank instanceof SellerBankAccount && $bank->isAcceptableForSubmission(),
            self::STEP_WAREHOUSE => SellerWarehouse::query()->where('seller_id', $seller->id)->where('is_default', config('core.yes'))->exists(),
            self::STEP_AGREEMENT => $seller->agreement_accepted_at !== null && $seller->agreement_version === (string) config('seller.agreement_version'),
        ];
    }

    /**
     * Steps 1–4 that still need input (the agreement is accepted on submit).
     *
     * @return list<int>
     */
    public function missingSteps(Seller $seller): array
    {
        $completed = $this->completedSteps($seller);
        unset($completed[self::STEP_AGREEMENT]);

        return array_keys(array_filter($completed, fn (bool $done): bool => ! $done));
    }

    /**
     * Where to send a seller who opens the wizard without a step: the first incomplete step
     * up to the furthest step reached.
     */
    public function resumeStep(Seller $seller): int
    {
        $missing = $this->missingSteps($seller);
        $first = $missing[0] ?? self::STEP_AGREEMENT;

        return max(1, min($first, $seller->onboarding_step, Seller::STEPS));
    }

    /**
     * A step can be opened once every earlier step has been saved at least once.
     */
    public function canOpenStep(Seller $seller, int $step): bool
    {
        return $step >= 1 && $step <= Seller::STEPS && $step <= max(1, $seller->onboarding_step);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveBusiness(Seller $seller, array $data): void
    {
        DB::transaction(function () use ($seller, $data): void {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $panChanged = $seller->pan !== strtoupper((string) $data['pan']);

            $seller->fill([
                'store_name' => trim((string) $data['store_name']),
                'legal_name' => trim((string) $data['legal_name']),
                'business_type' => $data['business_type'],
                'email' => strtolower(trim((string) $data['email'])),
            ]);
            $seller->setPan((string) $data['pan']);

            if ($seller->isDirty('store_name') || $seller->store_slug === null) {
                $seller->store_slug = $this->uniqueSlug((string) $seller->store_name, $seller->id);
            }

            // The GSTIN embeds the PAN: a new PAN invalidates the saved GSTIN.
            if ($panChanged && $seller->gstin !== null && Gstin::embeddedPan($seller->gstin) !== $seller->pan) {
                $seller->fill(['gstin' => null, 'gst_status' => null, 'gst_legal_name' => null, 'state_id' => null]);
            }

            $seller->save();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveTax(Seller $seller, array $data, ?UploadedFile $certificate): void
    {
        $hasGst = (int) ($data['has_gst'] ?? config('core.yes')) === (int) config('core.yes');

        if ($hasGst && $certificate === null && $this->documents->latestOfType($seller, 'gst_certificate') === null) {
            throw new SellerException(trans('seller::onboarding.messages.gst_certificate_required'));
        }

        $gstin = $hasGst ? Gstin::normalise($data['gstin'] ?? '') : null;
        $enrolmentId = $hasGst ? null : strtoupper(trim((string) ($data['enrolment_id'] ?? '')));
        $verification = $gstin !== null && $gstin !== $seller->gstin ? $this->gstinVerifier->verify($gstin) : null;

        DB::transaction(function () use ($seller, $hasGst, $gstin, $enrolmentId, $verification, $certificate): void {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);

            if (! $hasGst) {
                $seller->fill([
                    'gstin' => null,
                    'gst_status' => Seller::GST_NOT_APPLICABLE,
                    'gst_legal_name' => null,
                    'enrolment_id' => $enrolmentId,
                    'state_id' => null,
                ])->save();

                return;
            }

            if ($certificate !== null) {
                $this->documents->store($seller, 'gst_certificate', $certificate);
            }

            $seller->fill([
                'gstin' => $gstin,
                'enrolment_id' => null,
                'state_id' => $this->stateIdFor((string) $gstin),
            ]);

            if ($verification !== null) {
                $seller->gst_legal_name = $verification->legalName ? mb_substr($verification->legalName, 0, 200) : null;
                $seller->gst_status = match (true) {
                    ! $verification->checked => Seller::GST_UNVERIFIED,
                    $verification->active && NameMatcher::bestScore($verification->legalName, [$seller->legal_name, $seller->store_name]) >= SellerSettings::nameMatchThreshold() => Seller::GST_VERIFIED,
                    default => Seller::GST_MISMATCH,
                };
            } elseif ($seller->gst_status === null || $seller->gst_status === Seller::GST_NOT_APPLICABLE) {
                $seller->gst_status = Seller::GST_UNVERIFIED;
            }

            $seller->save();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveWarehouse(Seller $seller, array $data): void
    {
        $default = SellerWarehouse::query()->where('seller_id', $seller->id)->where('is_default', config('core.yes'))->first();

        $this->warehouses->save($seller, $data, $default, true);
    }

    /**
     * directory_state.id for the GSTIN's state code (null for 97/99 or a missing state row).
     */
    private function stateIdFor(string $gstin): ?int
    {
        $iso = Gstin::isoStateCode($gstin);

        if ($iso === null) {
            return null;
        }

        $country = (string) (settings('directory', 'default_country') ?: 'IN');
        $id = DirectoryCountryState::query()->where('country', $country)->where('code', $iso)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function uniqueSlug(string $storeName, int $ignoreId): string
    {
        $base = Str::limit(Str::slug($storeName), 90, '') ?: 'store';
        $slug = $base;
        $suffix = 2;

        while (Seller::withTrashed()->where('store_slug', $slug)->whereKeyNot($ignoreId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function ensureEditable(Seller $seller): void
    {
        if (! $seller->status->isEditable()) {
            throw new SellerException(trans('seller::onboarding.messages.not_editable'));
        }
    }
}
