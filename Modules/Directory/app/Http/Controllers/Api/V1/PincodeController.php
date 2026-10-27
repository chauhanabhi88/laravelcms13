<?php

declare(strict_types=1);

namespace Modules\Directory\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Directory\Repositories\DirectoryPincodeRepository;
use Modules\Directory\Transformers\PincodeResource;

/**
 * Unauthenticated pincode lookup: city, state, serviceability and COD. Unknown pincodes are a 404.
 */
class PincodeController extends Controller
{
    public function __construct(protected DirectoryPincodeRepository $pincodes) {}

    public function show(string $pincode): PincodeResource|JsonResponse
    {
        $found = $this->pincodes->findByPincode($pincode);

        if ($found === null) {
            return response()->json(['success' => false, 'message' => trans('directory::directory_pincode.messages.not_found'), 'code' => '404'], 404);
        }

        return (new PincodeResource($found))->additional(['success' => true]);
    }
}
