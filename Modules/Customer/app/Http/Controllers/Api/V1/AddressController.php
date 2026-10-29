<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Http\Requests\Api\AddressRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Services\AddressService;
use Modules\Customer\Transformers\AddressResource;

/**
 * Address book over the API (FR-CUS-004): /api/v1/customer/addresses[/{address}].
 * Lookups are scoped to the token's customer; anyone else's address is a 404.
 */
class AddressController extends ApiController
{
    public function __construct(private readonly AddressService $addresses) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $this->customer($request)->address()
            ->orderByRaw('is_default_address = ? desc', [config('customer.is_default_address.yes')])
            ->latest('id')
            ->get();

        return AddressResource::collection($addresses)->additional(['success' => true]);
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $address = $this->addresses->save($this->customer($request), $request->addressData());

        return $this->saved($address, 201);
    }

    public function update(AddressRequest $request): JsonResponse
    {
        $address = $this->addresses->save($this->customer($request), $request->addressData(), $this->findOwned($request));

        return $this->saved($address, 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->addresses->delete($this->customer($request), $this->findOwned($request));

        return $this->ok(message: trans('customer::customer_front.messages.address_deleted'));
    }

    protected function saved(CustomerAddress $address, int $status): JsonResponse
    {
        return (new AddressResource($address))
            ->additional([
                'success' => true,
                'message' => trans('customer::customer_front.messages.address_saved'),
                'meta' => ['serviceable' => $this->addresses->isServiceable($address->pincode)],
            ])
            ->response()
            ->setStatusCode($status);
    }

    protected function findOwned(Request $request): CustomerAddress
    {
        return $this->customer($request)->address()->findOrFail((int) $request->route('address'));
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}
