<?php

namespace Modules\Core\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Repositories\Entities\EntityRepository;

class EntityController extends BackendController
{
    public function __construct(EntityRepository $entity)
    {
        parent::__construct();
        $this->entity = $entity;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function manage(Request $request, $module, EntityRepository $entity)
    {
        try {
            $entityOption = $entity->getEloquantRelationshipOptions(true);

            $entityCollection = $entity->getAllEntities();
            $moduleList = ['' => ' -- '.trans('core::core.labels.select').' -- '] + $entityCollection['list'];
            $entityCollection = $entityCollection[$module];

            return view('core::backend.entity', compact('entityCollection', 'entityOption', 'module', 'moduleList'));
        } catch (\Throwable $e) {
            return redirect(app()->getLocale().'/backend/module')->with('error', $e->getMessage());
        }
    }

    public function save(Request $request)
    {
        try {

            if ($request->get('newEntity')) {
                $entity = $request->get('entity');
                $command = 'module:make-model';

                \Artisan::call($command, [
                    'model' => $entity['name'],
                    'module' => $entity['module'],
                    '--fillable' => $entity['fillable'],
                    '-m' => true,
                ]);
            }

            $params = $request->all();
            $options['base_module'] = $params['entity']['module'];
            $options['base_entity'] = $params['entity']['name'];

            if (isset($params['join']) && $params['join']) {

                foreach ($params['join'] as $join) {
                    $options['target_entity'] = $join['entity'];
                    $options['target_module'] = $join['module'];
                    $options['target_foreign_key'] = $join['key'];

                    $this->entity
                        ->setJoin($join['type'])
                        ->setParams($options)
                        ->addRelationship();
                }
            }

            if ($request->get('newEntity')) {
                return redirect(app()->getLocale().'/backend/module')->with('success', trans('core::core.messages.entity_create'));
            }

            return redirect(app()->getLocale().'/backend/module')->with('success', trans('core::core.messages.entity_update'));
        } catch (\Throwable $e) {
            report($e);

            return redirect(app()->getLocale().'/backend/module')->with('error', $e->getMessage());
        }
    }

    public function loadEntity(Request $request)
    {
        try {

            $entityCollection = $this->entity->getModuleEntities($request->get('moduleName'));
            $options = '<option value=""> -- '.trans('core::core.labels.select').' -- </option>';
            foreach ($entityCollection as $entity) {
                $options .= "<option value='".$entity['name']."'>".$entity['name'].'</option>';
            }

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'entitySelect',
                    'html' => $options,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function loadColumns(Request $request)
    {
        try {

            $entity = $this->entity->getModuleEntities($request->get('moduleName'), $request->get('entityName'));
            $entity = $entity['object'];
            $columns = Schema::getColumnListing($entity->getTable());
            $options = '<option value=""> -- '.trans('core::core.labels.select').' -- </option>';
            foreach ($columns as $column) {
                $options .= "<option value='".$column."'>".$column.'</option>';
            }

            return response()->json([
                'type' => 'success',
                'content' => [
                    'html' => $options,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Request $request)
    {
        try {
            $entityOption = $this->entity->getEloquantRelationshipOptions(true);
            $entityCollection = $this->entity->getAllEntities();
            $moduleList = ['' => ' -- '.trans('core::core.labels.select').' -- '] + $entityCollection['list'];

            $entity = $request->get('entity');
            $module = $request->get('module');
            $joinData = $this->entity->getByAttributes([
                'base_module' => $module,
                'base_entity' => $entity,
            ]);
            $content = view('core::backend.partials.edit-entity-form', compact('joinData', 'entityCollection', 'module', 'moduleList', 'entityOption', 'entity'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'entityData',
                    'html' => $content->__toString(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
