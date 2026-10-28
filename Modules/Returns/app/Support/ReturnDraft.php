<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Modules\Returns\DTO\ReturnSubmission;
use Modules\Returns\Models\ReturnRequest;

/**
 * The customer's return wizard between steps (plan M15 §5.3), kept in the session as a plain
 * array. Bank / UPI details are stored encrypted.
 */
final class ReturnDraft
{
    /**
     * @param  array<int, array{quantity: int, exchange_variant_id: int|null}>  $items
     * @param  list<array{path: string, width: int|null, height: int|null}>  $photos
     */
    public function __construct(
        public string $orderNo,
        public int $sellerOrderId,
        public ?string $type = null,
        public ?string $reasonCode = null,
        public ?string $comment = null,
        public array $items = [],
        public array $photos = [],
        public bool $photosDone = false,
        public ?string $refundMode = null,
        public ?string $beneficiary = null,
        public ?int $pickupAddressId = null,
        public bool $refundDone = false,
    ) {}

    public static function key(): string
    {
        return (string) config('returns.draft_session_key', 'returns_draft');
    }

    public static function fromRequest(Request $request): ?self
    {
        $data = $request->session()->get(self::key());

        return is_array($data) ? self::fromArray($data) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];

        foreach ((array) ($data['items'] ?? []) as $itemId => $line) {
            $items[(int) $itemId] = [
                'quantity' => (int) ($line['quantity'] ?? 0),
                'exchange_variant_id' => isset($line['exchange_variant_id']) ? (int) $line['exchange_variant_id'] : null,
            ];
        }

        return new self(
            (string) ($data['order_no'] ?? ''),
            (int) ($data['seller_order_id'] ?? 0),
            $data['type'] ?? null,
            $data['reason_code'] ?? null,
            $data['comment'] ?? null,
            $items,
            array_values((array) ($data['photos'] ?? [])),
            (bool) ($data['photos_done'] ?? false),
            $data['refund_mode'] ?? null,
            $data['beneficiary'] ?? null,
            isset($data['pickup_address_id']) ? (int) $data['pickup_address_id'] : null,
            (bool) ($data['refund_done'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order_no' => $this->orderNo,
            'seller_order_id' => $this->sellerOrderId,
            'type' => $this->type,
            'reason_code' => $this->reasonCode,
            'comment' => $this->comment,
            'items' => $this->items,
            'photos' => $this->photos,
            'photos_done' => $this->photosDone,
            'refund_mode' => $this->refundMode,
            'beneficiary' => $this->beneficiary,
            'pickup_address_id' => $this->pickupAddressId,
            'refund_done' => $this->refundDone,
        ];
    }

    public function save(Request $request): void
    {
        $request->session()->put(self::key(), $this->toArray());
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::key());
    }

    public function reasonDone(): bool
    {
        return $this->type !== null && $this->reasonCode !== null && $this->items !== [];
    }

    public function isExchange(): bool
    {
        return $this->type === ReturnRequest::TYPE_EXCHANGE;
    }

    /**
     * @param  array<string, string>|null  $details
     */
    public function setBeneficiary(?array $details): void
    {
        $this->beneficiary = $details === null ? null : Crypt::encryptString((string) json_encode($details));
    }

    /**
     * @return array<string, string>|null
     */
    public function beneficiaryDetails(): ?array
    {
        if ($this->beneficiary === null) {
            return null;
        }

        try {
            $details = json_decode(Crypt::decryptString($this->beneficiary), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($details) ? $details : null;
    }

    public function toSubmission(): ReturnSubmission
    {
        return new ReturnSubmission(
            $this->sellerOrderId,
            (string) $this->type,
            (string) $this->reasonCode,
            $this->comment,
            $this->items,
            $this->photos,
            $this->refundMode,
            $this->beneficiaryDetails(),
            $this->pickupAddressId,
        );
    }
}
