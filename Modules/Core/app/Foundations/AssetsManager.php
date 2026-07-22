<?php

namespace Modules\Core\Foundations;

interface AssetsManager
{
    /**
     * Add a possible asset
     * @param string $path
     * @return void
     */
    public function addAsset($path);

    /**
     * Add an array of possible assets
     * @param array $assets
     * @return void
     */
    public function addAssets(array $assets);

    /**
     * @return string
     */
    public function getJs();

    /**
     * @return string
     */
    public function getCss();
}
