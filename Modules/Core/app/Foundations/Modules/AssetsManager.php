<?php
namespace Modules\Core\Foundations\Modules;

use Modules\Core\Foundations\AssetsManager as AssetsManagerInterFace;
use Illuminate\Support\Collection;

final class AssetsManager implements AssetsManagerInterFace
{
    /**
     * @var array
     */
    protected $css = [];
    /**
     * @var array
     */
    protected $js = [];

    public function __construct()
    {
        $this->css = new Collection();
        $this->js = new Collection();
    }

    /**
     * Add an array of possible assets
     * @param array $assets
     * @return void
     */
    public function addAssets(array $assets)
    {
        foreach ($assets as $path) {
            $this->addAsset($path);
        }
    }

    /**
     * Add a possible asset
     * @param string $path
     * @return void
     */
    public function addAsset($path)
    {
        if ($this->isJs($path)) {
            return $this->js->push($path);
        }
        if ($this->isCss($path)) {
            return $this->css->push($path);
        }
    }

        /**
     * Check if the given path is a javascript file
     * @param string $path
     * @return bool
     */
    private function isJs($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION) == 'js';
    }

    /**
     * Check if the given path is a css file
     * @param string $path
     * @return bool
     */
    private function isCss($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION) == 'css';
    }

    /**
     * @return string
     */
    public function getJs()
    {
        $assetPath = $this->js->all();
        return $assetPath;
    }

    /**
     * @return string
     */
    public function getCss()
    {
        $assetPath = $this->css->all();
        return $assetPath;
    }
}
