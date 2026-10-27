<?php

declare(strict_types=1);

namespace Modules\Seller\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Seller\Database\Factories\SellerBankAccountFactory;

/**
 * A payout bank account. The number is encrypted; screens show account_last4 only.
 *
 * @property int $id
 * @property int $seller_id
 * @property string $account_holder_name
 * @property string $account_number
 * @property string $account_last4
 * @property string $ifsc
 * @property string|null $bank_name
 * @property string|null $branch
 * @property string $account_type
 * @property string $verification_status
 * @property string|null $verification_method
 * @property string|null $verified_name
 * @property int|null $name_match_score
 * @property string|null $provider_ref
 * @property string|null $razorpayx_contact_id
 * @property string|null $razorpayx_fund_account_id
 * @property string|null $verification_note
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property int $is_primary
 * @property int|null $cheque_document_id
 * @property-read Seller $seller
 * @property-read SellerKycDocument|null $chequeDocument
 */
class SellerBankAccount extends MarketplaceModel
{
    /** @use HasFactory<SellerBankAccountFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_MANUAL_REVIEW = 'manual_review';

    public const METHOD_PENNY_DROP = 'penny_drop';

    public const METHOD_CHEQUE = 'cheque';

    protected $table = 'seller_bank_account';

    protected $fillable = [
        'seller_id',
        'account_holder_name',
        'account_number',
        'account_last4',
        'ifsc',
        'bank_name',
        'branch',
        'account_type',
        'verification_status',
        'verification_method',
        'verified_name',
        'name_match_score',
        'provider_ref',
        'razorpayx_contact_id',
        'razorpayx_fund_account_id',
        'verification_note',
        'verified_at',
        'verified_by',
        'is_primary',
        'cheque_document_id',
    ];

    protected $hidden = ['account_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'seller_id' => 'integer',
            'name_match_score' => 'integer',
            'is_primary' => 'integer',
            'verified_at' => 'datetime',
            'verified_by' => 'integer',
            'cheque_document_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @return BelongsTo<SellerKycDocument, $this>
     */
    public function chequeDocument(): BelongsTo
    {
        return $this->belongsTo(SellerKycDocument::class, 'cheque_document_id');
    }

    public function maskedNumber(): string
    {
        return 'XXXXXX'.$this->account_last4;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    public function isPending(): bool
    {
        return $this->verification_status === self::STATUS_PENDING;
    }

    /**
     * The bank step can be submitted: verified, or waiting on an admin with a cheque on file.
     */
    public function isAcceptableForSubmission(): bool
    {
        return $this->isVerified()
            || $this->verification_status === self::STATUS_PENDING
            || ($this->verification_status === self::STATUS_MANUAL_REVIEW && $this->cheque_document_id !== null);
    }

    public function statusLabel(): string
    {
        return trans('seller::seller.bank_statuses.'.$this->verification_status);
    }

    protected static function newFactory(): SellerBankAccountFactory
    {
        return SellerBankAccountFactory::new();
    }
}
