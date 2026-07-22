<?php

namespace Modules\Theme\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Theme\Repositories\ThemeRepository;
use Modules\Core\Http\Controllers\BackendController;

class ThemeController extends BackendController
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function setTheme()
    {
        return view('theme::backend/index');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request, ThemeRepository $theme)
    {
       try
        {
            $_theme = $theme->find(1);
            $settingArray = json_decode($request->input('setting'), true);
            if($request->file('logo')) 
            {
                if(isset($settingArray['logo']) && ( file_exists(storage_path('app/public/Theme/').$settingArray['logo'])))
                    unlink(storage_path('app/public/Theme/').$settingArray['logo']);
                if(isset($settingArray['logo']) && ( file_exists(storage_path('app/public/Theme/thumbnails/').$settingArray['logo'])))
                    unlink(storage_path('app/public/Theme/thumbnails/').$settingArray['logo']);
                $imageUploadParams = array(
                    'module_name' => \Config::get('theme.name'),
                    'dbfield' => 'logo',
                    'thumbnail' => true,
                    'thumbnail_size' => 50
                );
                $params = $theme->setUploadParams($imageUploadParams)->setModel($_theme)->uploadFile($request);;
                $settingArray['logo'] = $params['logo'];
            }
            if($request->input('defualtlogo'))
            {  
                if(isset($settingArray['logo']) && ( file_exists(storage_path('app/public/Theme/').$settingArray['logo'])))
                    unlink(storage_path('app/public/Theme/').$settingArray['logo']);
                if(isset($settingArray['logo']) && ( file_exists(storage_path('app/public/Theme/thumbnails/').$settingArray['logo'])))
                    unlink(storage_path('app/public/Theme/thumbnails/').$settingArray['logo']);
                unset($settingArray['logo']);
            }
            $theme->update($_theme, ['setting' => json_encode($settingArray)]);
            return redirect()->route('admin.theme.index',updateUrlParams())->with("success", trans("theme::theme.messages.updated_success"));
        }catch(\Throwable $e){
            return redirect()->route('admin.theme.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    public function reset(Request $request, ThemeRepository $theme)
    {
        $_theme = $theme->find(1);
        $theme->update($_theme, ['setting' => $request->input('setting')]);
        $request->session()->flash('success', trans("theme::theme.messages.reset_success"));
    }
}