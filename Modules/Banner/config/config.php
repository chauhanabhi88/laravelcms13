<?php

return [
    'name' => 'Banner',
    'cache' => [
		'name' => 'Banner',
        "banner_group_name" => "BannerGroup",
	],
    'path' => 'app/public/Banner',
    //This variable for banner country code
    'country_code' => 'country',
    'status'=>[
    	'enable' 	=> 1,
    	'disable'	=> 2
    ],
    //end
    'lang_path' => 'banner::banner.labels'
];
