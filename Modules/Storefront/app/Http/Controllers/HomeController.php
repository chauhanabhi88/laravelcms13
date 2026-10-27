<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Storefront\Services\Home\HomePageBuilder;
use Modules\Storefront\Services\ListingRenderer;
use Modules\Storefront\Support\ListingPage;

/**
 * The storefront home page (route "homepage"): configured sections, then the "Picked for
 * You" listing with filters, sort and infinite scroll (its partial responses come here too).
 */
class HomeController extends Controller
{
    public function __construct(
        protected HomePageBuilder $builder,
        protected ListingRenderer $listings,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $sections = $this->builder->sections();
        $listingSection = collect($sections)->firstWhere('type', HomePageBuilder::LISTING_TYPE);
        $page = new ListingPage(
            kind: ListingPage::HOME,
            title: (string) (($listingSection['title'] ?? null) ?: trans('storefront::storefront.home.picked_for_you')),
            baseUrl: frontRoute('homepage'),
        );

        if ($request->filled('partial')) {
            abort_if($listingSection === null, 404);

            return $this->listings->render($request, $page);
        }

        $blocks = [];
        foreach ($sections as $section) {
            if ($section['type'] === HomePageBuilder::LISTING_TYPE) {
                $blocks[] = ['section' => $section, 'html' => null];

                continue;
            }

            if (($html = $this->builder->html($section)) !== '') {
                $blocks[] = ['section' => $section, 'html' => $html];
            }
        }

        return view('storefront::frontend.home', [
            'blocks' => $blocks,
            'listing' => $listingSection !== null ? $this->listings->listing($request, $page) : null,
        ]);
    }
}
