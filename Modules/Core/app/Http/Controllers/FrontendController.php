<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Foundations\AssetsManager;
use Modules\Language\Repositories\LanguageRepository;

class FrontendController extends Controller
{
    protected $_authUser;
    protected $_assetManager;

    public function __construct()
    {
        $this->languageRepository = app(LanguageRepository::class);
        $this->_assetManager = app(AssetsManager::class);
    }

    public function getAuthUser() {
        if(!$this->_authUser) {
            $this->_authUser = Auth::user();
        }
        return $this->_authUser;
    }

    protected function getAssetManager()
    {
        return $this->_assetManager;
    }

    // Get language options from the language repository
    public function getLanguageOptions()
    {
        return $this->languageRepository->getLanguageOptions();
    }

    /* Set Currency in Session */
    public function setCurrency(Request $request)
    { 
        try {
            $params = $request->all();
            $request->session()->put('currency_code', $params['currency_code']);
            return response()->json([
                'type' => 'success',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
           
    }
}