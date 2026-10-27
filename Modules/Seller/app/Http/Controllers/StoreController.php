<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Seller\Repositories\SellerRepository;

/**
 * Public store page /s/{slug} (shell): store header, rating and followers. The product
 * grid is filled by the storefront (M06).
 */
class StoreController extends Controller
{
    public function __construct(private readonly SellerRepository $sellers) {}

    public function show(Request $request): View
    {
        $seller = $this->sellers->findPublicBySlug((string) $request->route('storeSlug'));
        abort_if($seller === null, 404);

        return view('seller::frontend.store', [
            'seller' => $seller,
            'logoUrl' => $seller->logo ? Storage::disk('public')->url($seller->logo) : null,
            'isAvailable' => $seller->isActive(),
        ]);
    }
}
