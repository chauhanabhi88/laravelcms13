<?php

namespace Modules\Block\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Block\Models\Block;
class BlockController extends Controller
{

    public function index(Request $request)
    {
        try {
            $slug = $request->slug;
            $locale = app()->getLocale();
            $block = Block::where("slug", $slug)
                ->where("is_enabled", config("core.enabled"))
                ->with([
                    'translations' => function ($query) use ($locale) {
                        $query->where('locale', $locale);
                    }
                ])->first();
            if (!$block || !$block->id) {
                return response()->json(['success' => false, 'message' => trans('block::block.messages.data_invalid')], 404);
            }

            return response()->json(['success' => true, 'data' => $block]);

        } catch (\Throwable $th) {
            return response()->json(["success" => false, "message" => $th->getMessage()], 500);
        }
    }
}
