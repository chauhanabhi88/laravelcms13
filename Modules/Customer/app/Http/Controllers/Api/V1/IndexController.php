<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class IndexController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['type' => 'success', 'message' => 'Customer api called successfully.']);
    }

}
