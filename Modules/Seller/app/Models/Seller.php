<?php

declare(strict_types=1);

namespace Modules\Seller\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Concerns\HasStateMachine;
use Modules\Core\Models\MarketplaceModel;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Seller\Database\Factories\SellerFactory;
use Modules\Seller\Enums\SellerStatus;
use Modules\User\Models\User;

/**
 * A seller business. Logins are SellerUser rows; status changes go through
 * transitionTo() (SellerOnboardingService / SellerVerificationService), which writes
 * Core's status_history.
 *
 * @property int $id
 * @property string $code
 * @property string|null $legal_name
 * @property string|null $store_name
 * @property string|null $store_slug
 * @property string|null $business_type
 * @property string|null $pan
 * @property string|null $pan_last4
 * @property string|null $gstin
 * @property string|null $gst_status
 * @property string|null $gst_legal_name
 * @property string|null $enrolment_id
 * @property int|null $state_id
 * @property string|null $email
 * @property string $phone
 * @property string|null $logo
 * @property SellerStatus $status
 * @property int $onboarding_step
 * @property string|null $agreement_version
 * @property Carbon|null $agreement_accepted_at
 * @property string|null $agreement_accepted_ip
 * @property Carbon|null $submitted_at
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property string|null $status_reason
 * @property string|null $suspended_reason
 * @property int $auto_accept_orders
 * @property string $rating_avg
 * @property int $rating_count
 * @property int $follower_count
 * @property int $payout_hold
 */
class Seller extends MarketplaceModel
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory, HasStateMachine, SoftDeletes;

    public const GST_UNVERIFIED = 'unverified';

    public const GST_VERIFIED = 'verified';

    public const GST_MISMATCH = 'mismatch';

    public const GST_NOT_APPLICABLE = 'not_applicable';

    public const STEPS = 5;

    /**
     * @var array<string, list<string>>
     */
    protected static array $transitions = [
        'onboarding' => ['pending_verification'],
        'pending_verification' => ['active', 'rejected', 'changes_requested'],
        'changes_requested' => ['pending_verification'],
        'active' => ['suspended', 'deactivated'],
        'suspended' => ['active'],
        'rejected' => ['onboarding'],
    ];

    protected $table = 'seller';

    protected $fillable = [
        'code',
        'legal_name',
        'store_name',
        'store_slug',
        'business_type',
        'pan',
        'pan_last4',
        'gstin',
        'gst_status',
        'gst_legal_name',
        'enrolment_id',
        'state_id',
        'email',
        'phone',
        'logo',
        'status',
        'onboarding_step',
        'agreement_version',
        'agreement_accepted_at',
        'agreement_accepted_ip',
        'submitted_at',
        'verified_at',
        'verified_by',
        'status_reason',
        'suspended_reason',
        'auto_accept_orders',
        'payout_hold',
    ];

    protected $hidden = ['pan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pan' => 'encrypted',
            'status' => SellerStatus::class,
            'state_id' => 'integer',
            'onboarding_step' => 'integer',
            'agreement_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'verified_by' => 'integer',
            'auto_accept_orders' => 'integer',
            'rating_count' => 'integer',
            'follower_count' => 'integer',
            'payout_hold' => 'integer',
        ];
    }

    /**
     * @return HasMany<SellerUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(SellerUser::class, 'seller_id');
    }

    /**
     * @return HasOne<SellerUser, $this>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(SellerUser::class, 'seller_id')->where('role', SellerUser::ROLE_OWNER)->oldest('id');
    }

    /**
     * @return HasMany<SellerBankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SellerBankAccount::class, 'seller_id');
    }

    /**
     * @return HasOne<SellerBankAccount, $this>
     */
    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(SellerBankAccount::class, 'seller_id')->where('is_primary', config('core.yes'))->latest('id');
    }

    /**
     * @return HasMany<SellerWarehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(SellerWarehouse::class, 'seller_id');
    }

    /**
     * @return HasOne<SellerWarehouse, $this>
     */
    public function defaultWarehouse(): HasOne
    {
        return $this->hasOne(SellerWarehouse::class, 'seller_id')->where('is_default', config('core.yes'))->latest('id');
    }

    /**
     * @return HasMany<SellerKycDocument, $this>
     */
    public function kycDocuments(): HasMany
    {
        return $this->hasMany(SellerKycDocument::class, 'seller_id');
    }

    /**
     * @return BelongsTo<DirectoryCountryState, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(DirectoryCountryState::class, 'state_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @param  Builder<Seller>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', SellerStatus::Active->value);
    }

    /**
     * Applications in the admin KYC queue.
     *
     * @param  Builder<Seller>  $query
     */
    public function scopeApplications(Builder $query): void
    {
        $query->whereIn('status', (array) config('seller.application_statuses'));
    }

    public function isActive(): bool
    {
        return $this->status === SellerStatus::Active;
    }

    public function hasStatus(SellerStatus ...$statuses): bool
    {
        return in_array($this->status, $statuses, true);
    }

    /**
     * Store the PAN encrypted with its last four characters for display.
     */
    public function setPan(?string $pan): static
    {
        $pan = $pan === null ? null : strtoupper(trim($pan));

        $this->pan = $pan;
        $this->pan_last4 = $pan === null ? null : substr($pan, -4);

        return $this;
    }

    public function maskedPan(): ?string
    {
        return $this->pan_last4 ? 'XXXXXX'.$this->pan_last4 : null;
    }

    public function displayName(): string
    {
        return (string) ($this->store_name ?: $this->legal_name ?: $this->code);
    }

    public function isGstRegistered(): bool
    {
        return $this->gstin !== null && $this->gstin !== '';
    }

    protected static function newFactory(): SellerFactory
    {
        return SellerFactory::new();
    }
}
