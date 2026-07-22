<?php

namespace Modules\Core\Http\Controllers;

use File;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Foundations\AssetsManager;
use Modules\Language\Repositories\LanguageRepository;

class BackendController extends Controller
{
    protected $_authUser;
    protected $_assetManager;

    public function __construct()
    {
        $this->languageRepository = app(LanguageRepository::class);
        $this->_assetManager = app(AssetsManager::class);
        $this->_assetManager->addAsset('modules/theme/backend/css/custom_theme.css');
    }

    public function getAuthUser() {
        if(!$this->_authUser) {
            $this->_authUser = Auth::user();
        }
        return $this->_authUser;
    }

    public function isMasterAdmin() {
        return $this->getAuthUser()->hasRoleSlug('master_admin');
    }

    public function isAdmin() {
        
        return $this->getAuthUser()->hasRoleSlug('admin');
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

    public function moveImage($path, $folderName)
    {
        if ( (!$path) || (!$folderName) ) {
            return null;
        }
        if(!is_dir(public_path('storage') . '/' . $folderName)) {
            \File::makeDirectory(public_path('storage') . '/' . $folderName, 0777, true);
        }
        $storagePath = public_path('storage') . '/' . \Config::get('core.summernote_temp_folder_name') . $path;
        $newPath = public_path('storage') . '/' . $folderName . $path;
        if (!is_file($storagePath)) {
            return null;
        }
        File::move($storagePath, $newPath);
        return $newPath;
    }

    /* replace image content of summernote image */

    public function replaceSummernoteImageContent($html, $folderName) {
        if( (!$html) || (!$folderName) ) {
            return null;
        }
        preg_match_all( '@src="([^"]+)"@' , $html, $match );
        if(empty($match)) {
            return $html;
        }
        $src = array_pop($match);
        if(isset($src) && !empty($src)) {
            foreach($src as $key => $value) {
                if(!strpos($value, \Config::get('core.summernote_temp_folder_name'))) {
                    unset($src[$key]);
                    continue;
                }
                $index = strpos($value, \Config::get('core.summernote_temp_folder_name')) + strlen(\Config::get('core.summernote_temp_folder_name'));
                $fileName = substr($value, $index);
                $newFileName = $this->moveImage($fileName, $folderName);
                if(is_null($newFileName)) {
                    continue;
                }
            }
        }
        $upadatedContent = str_replace(\Config::get('core.summernote_temp_folder_name'),  $folderName, $html);
        return $upadatedContent;
    }

}
