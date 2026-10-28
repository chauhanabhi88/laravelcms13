<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Http\Requests\Seller\ReturnClaimRequest;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Services\ReturnClaimService;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\Response;

/**
 * A seller's claim on a received return (plan M15 §5.6): within the claim window, with photos.
 */
class ReturnClaimController extends SellerPanelController
{
    public function store(ReturnClaimRequest $request, ReturnClaimService $claims): RedirectResponse
    {
        $return = $request->returnRequest();
        $back = redirect(frontRoute('seller.return.show', ['returnNo' => $return->return_no]));

        try {
            $claims->openForReturn($return, (string) $request->validated('reason'), $request->validated('description'), $request->photos());
        } catch (ReturnException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('success', trans('returns::seller.messages.claim_opened'));
    }

    public function photo(Request $request, ReturnPhotoStore $photos): Response
    {
        $claim = ReturnClaim::query()->findOrFail((int) $request->route('id'));
        $this->authorizeOwned($claim);
        $path = ((array) $claim->images)[(int) $request->route('index')] ?? null;

        abort_unless(is_string($path) && $photos->disk()->exists($path), 404);

        return $photos->disk()->response($path);
    }
}
