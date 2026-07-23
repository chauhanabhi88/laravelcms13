<?php

namespace Modules\Pages\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Pages\Models\Pages;

class PageController extends Controller
{
    public function index(Request $request)
    {
        try {
            $slug = $request->slug;
            $locale = app()->getLocale();
            $page = Pages::where('slug', $slug)
                ->where('status', config('core.enabled'))
                ->with([
                    'translations' => function ($query) use ($locale) {
                        $query->where('locale', $locale);
                    },
                ])->first();
            if (! $page || ! $page->id) {
                return response()->json(['success' => false, 'message' => trans('pages::pages.messages.data_invalid')], 404);
            }

            return response()->json(['success' => true, 'data' => $page]);

        } catch (\Throwable $th) {
            Log::error($th);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }
}
