<?php

namespace Modules\LaravelPWA\Http\Controllers;

use Exception;
use Illuminate\Routing\Controller;
use Modules\LaravelPWA\Services\LaucherIconService;
use Modules\LaravelPWA\Services\ManifestService;

class IndexController extends Controller
{
    public function manifestJson()
    {
        $output = (new ManifestService)->generate();
        return response()->json($output);
    }

    public function offline(){
        return view('laravelpwa::offline');
    }
}