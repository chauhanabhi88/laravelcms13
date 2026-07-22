<?php

use Modules\Banner\Repositories\BannerRepository;

if(!function_exists("getBannerByCode"))
{
    function getBannerByCode($code = false)
    {
        $bannerData = app(BannerRepository::class)->getBannerByCode($code);
        return $bannerData;
    }
}
