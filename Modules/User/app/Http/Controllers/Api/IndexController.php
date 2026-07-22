<?php

namespace Modules\User\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IndexController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['type' => 'success', 'message' => 'User api called successfully.']);
    }
}
