<?php

namespace Modules\Attribute\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Modules\Role\Repositories\RoleRepository;
use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Repositories\AttributeRepository;
use Modules\Attribute\Repositories\AttributeOptionRepository;
use Modules\Attribute\Http\Requests\CreateRequest;
use Modules\Attribute\Http\Requests\UpdateRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Menu\Models\Menu;

class AttributeController extends BackendController
{
    /**
     * @var Repository
     */
    private $attribute;

    /**
     * @var Repository
     */
    private $attributeOption;

    /**
     * @var UserEntity
     */
    private $attributeEntity;

    public function __construct(AttributeOptionRepository $attributeOption, AttributeRepository $attribute, Attribute $entity)
    {
        parent::__construct();
        $this->attribute = $attribute;
        $this->attributeEntity = $entity;
        $this->attributeOption = $attributeOption;
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule('attribute', $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $collection = $this->attribute->pagination($request);
            $filters = $this->attribute->getFilters($request);
            // $columns = $this->attribute->sortColumns($request);
            $yesNoOptions = $this->attribute->getYesNoOptions();
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            return view('attribute::backend.index', compact('request', 'collection', 'columns', 'filters', 'yesNoOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("attribute.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("attribute.name"), $request);
            $yesNoOptions = $this->attribute->getYesNoOptions();
            $filters = $this->attribute->getFilters($request);
            $collection = $this->attribute->pagination($request);
            // $columns = $this->attribute->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.attribute.index');
            $columns = getColumnObject()->getColumns($activeMenuId);
            $content = view('attribute::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'yesNoOptions', 'activeMenuId'));
            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        try {
            $languageOptions = $this->getLanguageOptions();
            $yesNoOptions = $this->attribute->getYesNoOptions(true);
            $inputTypeOptions = $this->attribute->getInputTypeOptions(true);
            $uploadLimit = settings('attribute', 'max_upload_size');
            $imageTypes = $this->getImageTypes();
            return view('attribute::backend.create', compact('inputTypeOptions', 'yesNoOptions', 'languageOptions','imageTypes','uploadLimit'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    private function getImageTypes()
    {
        $imageTypes = settings('attribute', 'image_type');
        $imageTypes = explode(',', $imageTypes);
        $imageTypes = '.' . implode(',.', $imageTypes);
        return $imageTypes;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $params['custom_value'] = (isset($params['custom_value']) && $params['custom_value']) ? config("core.yes") : config("core.no");
            $attribute = $this->attribute->create($params);

            // start attribute option save
            if (isset($params['option']) && (!empty($params['option']))) {
                foreach ($params['option'] as $value) {
                    $value['attribute_id'] = $attribute->id;
                    if ($value['image']) {
                        $imageUploadParams = array(
        
                            'module_name' => config("attribute.name"),
                            'dbfield' => 'image',
                            'thumbnail' => true,
                            'thumbnail_size' => 100
                        );
                        
                        $imageName = $this->attribute->setUploadParams($imageUploadParams)->uploadMultipleImage($value['image']);
                        $value['image'] = $imageName;
                        $this->attributeOption->create($value);
                    }
                }
            }
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.attribute.edit', updateUrlParams([$attribute->id]))->with("success", trans("attribute::attribute.messages.created_success"));
            }
            return redirect()->route('admin.attribute.index',updateUrlParams())->with("success", trans("attribute::attribute.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.attribute.create',updateUrlParams())->with("error", $e->getMessage());
        }
    }
    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */

    public function edit(Request $request, RoleRepository $roleOptions)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("attribute::attribute.messages.data_invalid"));
            }
            $languageOptions = $this->getLanguageOptions();
            $optionableInput = array('select', 'checkbox', 'multiselect', 'radio');
            $attribute = $this->attribute->find($id);
            $attributeOption = $this->attributeOption->getByAttributes(['attribute_id' => $id]);
            if (!$attribute) {
                throw new \Exception(trans("attribute::attribute.messages.data_invalid"));
            }
            $yesNoOptions = $this->attribute->getYesNoOptions(true);
            $inputTypeOptions = $this->attribute->getInputTypeOptions(true);
            return view('attribute::backend.edit', compact('inputTypeOptions', 'yesNoOptions', 'attribute', 'attributeOption', 'roleOptions', 'optionableInput', 'languageOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.attribute.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }
    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */

    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("attribute::attribute.messages.data_invalid"));
            }
            $params = $request->all();
            
            //for attribute update
            $attribute = $this->attribute->find($id);
            
            // echo "<pre>";print_r($params);die();
            $params['custom_value'] = (isset($params['custom_value']) && $params['custom_value']) ? config("core.yes") : config("core.no");
            if (!$attribute) {
                throw new \Exception(trans("attribute::attribute.messages.data_invalid"));
            }
            $attribute = $this->attribute->update($attribute, $params);
           
            //**** update start for attribute option ****
            // For option delete
            if (isset($params['delete_ids'])) {
                $deleteIdsArray = explode(",", $params['delete_ids']);
                foreach ($deleteIdsArray as $deleteId) {
                    $attributeOption = $this->attributeOption->find($deleteId);
                    if (!$attributeOption) {
                        throw new \Exception(trans("attribute::attribute.messages.data_invalid"));
                    }
                    $this->attributeOption->destroy($attributeOption);
                }
            }
            //--ends delete

            // attribute exsiting option update start
            if (isset($params['option']['old']) && (!empty($params['option']['old']))) {
                foreach ($params['option']['old'] as  $value) {
                    $value['custom_option'] = ($params['custom_value'] != config("core.yes")) ? null : $value['custom_option'];
                    $attributeOption = $this->attributeOption->find($value['id']);
                    if(!isset($value['image']) && empty($value['image']))
                    {
                         unset($value['image']);
                    }else{
                        $imageUploadParams = array(
        
                            'module_name' => config("attribute.name"),
                            'dbfield' => 'image',
                            'thumbnail' => true,
                            'thumbnail_size' => 100
                        );
                        $imageRemoveParams = array(
                            'module_name' => config("attribute.name"),
                            'dbfield' => 'image'
                        );
                        $imageName = $this->attribute->setUploadParams($imageUploadParams)->uploadMultipleImage($value['image']);
                        $this->attribute->setUploadParams($imageRemoveParams)->setModel($attributeOption)->removeFile($attribute->image, 'Banner');
                        $value['image'] = $imageName;
                    }
                    $this->attributeOption->update($attributeOption, $value);
                }
            }
            //new attribute Option create start
            if (isset($params['option']['new']) && (!empty($params['option']['new']))) {
                foreach ($params['option']['new'] as $value) {
                    $value['custom_option'] = ($params['custom_value'] != config("core.yes")) ? null : $value['custom_option'];
                    $value['attribute_id'] = $attribute->id;
                    if ($value['image']) {
                        $imageUploadParams = array(
        
                            'module_name' => config("attribute.name"),
                            'dbfield' => 'image',
                            'thumbnail' => true,
                            'thumbnail_size' => 100
                        );
                        
                        $imageName = $this->attribute->setUploadParams($imageUploadParams)->uploadMultipleImage($value['image']);
                        $value['image'] = $imageName;
                    }
                    $this->attributeOption->create($value);
                }
            }
            //new attribute Option create end

            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.attribute.edit', updateUrlParams([$id]))->with("success", trans("attribute::attribute.messages.updated_success"));
            }
            return redirect()->route('admin.attribute.index',updateUrlParams())->with("success", trans("attribute::attribute.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.attribute.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            $this->attribute->deleteRecord($request);
            return redirect()->route('admin.attribute.index',updateUrlParams())->with("success", trans("attribute::attribute.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.attribute.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->attribute->destroyMultiple($request);
            return redirect()->route('admin.attribute.index',updateUrlParams())->with("success", trans("attribute::attribute.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.attribute.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }
}
