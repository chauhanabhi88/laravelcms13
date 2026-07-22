<?php

namespace Modules\Core\Composers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Foundations\AssetsManager;

class AssetsViewComposer
{
    /**
     * @var AssetManager
     */
    protected $assetManager;

    /**
     * @var Request
     */
    private $request;

    public function __construct(Request $request, AssetsManager $assetsManager)
    {
        $this->assetManager = $assetsManager;
    }

    public function compose(View $view)
    {
        $view->with('cssFiles', $this->assetManager->getCss());
        $view->with('jsFiles', $this->assetManager->getJs());
    }
}
