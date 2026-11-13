<?php

namespace Modules\Core\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Language\Repositories\LanguageRepository;

class SummernoteController extends BackendController
{
    public function __construct()
    {
        $this->languageRepository = app(LanguageRepository::class);
    }

    public function imageUpload(Request $request)
    {
        try {
            // Images only (plan M21 S6): the file lands in public storage.
            $validator = \Validator::make($request->all(), [
                'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'type' => 'error',
                    'message' => $validator->errors()->first('image'),
                ], 422);
            }

            if ($request->file('image')) {
                $imageUploadParams = [
                    'module_name' => \Config::get('core.summernote_temp_folder_name'),
                    'dbfield' => 'image',
                ];
                $uploadData = $this->languageRepository->setUploadParams($imageUploadParams)->uploadFile($request);
                $imagePath = '/storage/'.\Config::get('core.summernote_temp_folder_name').'/'.$uploadData['image'];

                return response()->json([
                    'type' => 'sucess',
                    'imagePath' => $imagePath,
                ]);
            }

            return response()->json([
                'type' => 'error',
                'message' => 'image is not upload.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
