<?php

namespace Modules\Core\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Modules\Language\Repositories\LanguageRepository;
use Modules\Core\Http\Controllers\BackendController;

class SummernoteController extends BackendController
{
    public function __construct()
    {
        $this->languageRepository = app(LanguageRepository::class);
    }

    public function imageUpload(Request $request) {
        try {
            if ($request->file('image')) {
                $imageUploadParams = array(
                    'module_name' => \Config::get('core.summernote_temp_folder_name'),
                    'dbfield' => 'image',
                );
                $uploadData = $this->languageRepository->setUploadParams($imageUploadParams)->uploadFile($request);
                $imagePath = '/storage/' . \Config::get('core.summernote_temp_folder_name') . '/' . $uploadData['image'];
                return response()->json([
                    'type' => 'sucess',
                    'imagePath' => $imagePath
                ]);
            }
            return response()->json([
                'type' => 'error',
                'message' => 'image is not upload.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}