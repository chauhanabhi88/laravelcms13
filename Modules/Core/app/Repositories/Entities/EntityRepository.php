<?php

namespace Modules\Core\Repositories\Entities;

use Modules\Core\Models\Entity;
use Nwidart\Modules\Facades\Module;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EntityRepository extends EloquentBaseRepository
{
    protected $entityParams = [
        'FunctionName' => 'joinFunction',
    ];

    protected $params = [];
    protected $joinType = [];

    public function __construct()
    {
        parent::__construct(new Entity);
    }

    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    public function getEloquantRelationshipOptions($flag = false)
    {
        $options = [];
        if($flag) {
            $options[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
    	return $options + [
            config("core.oneToOne") => trans('core::core.options.eloquantRelationship.oneToOne'),
            config("core.oneToMany") => trans('core::core.options.eloquantRelationship.oneToMany'),
            config("core.manyToOne") => trans('core::core.options.eloquantRelationship.manyToOne'),
            config("core.ManyToMany") => trans('core::core.options.eloquantRelationship.ManyToMany'),
        ];
    }

    public function getAllEntities() 
    {
        $moduleCollection = Module::getCached();
        $entityCollection = [];
        foreach ($moduleCollection as $module => $moduleData) {
            $moduleAlias = $moduleData['alias'];
            $entityCollection[$moduleAlias] = $this->getModuleEntities($module);
            if(!empty($entityCollection[$moduleAlias])) {
                $entityCollection['list'][$moduleAlias] = $module;
            }
        }
        return $entityCollection;
    }

    public function getModuleEntities($module, $entityName = false)
    {
        $module = Module::find($module);
        
        if(!$module) {
            return;
        }

        $entitiesObj = [];
        $path = $module->getExtraPath('Entities');
        $files = glob($path."/*.php");
        $entities = $this->getEntityNameSpace($files, $module);
        
        if($entities) {
            $i = 0;
            foreach ($entities as $entity) {
                if(class_exists($entity['namespace'])) {
                    
                    $entitiesObj[$i]['object'] = new $entity['namespace'];
                    $entitiesObj[$i]['name'] = $entity['name'];
                    
                    if($entityName && $entity['name'] == $entityName) {
                        return $entitiesObj[$i];
                    }
                }
                $i++;
            }
        }

        return $entitiesObj;
    }

    public function setEntityParams(Array $params)
    {
        $this->entityParams = $params;
        return $this;
    }

    protected function getEntityNameSpace($files, $module)
    {
        $entities = [];
        if($files) {
            $i = 0;
            foreach ($files as $entityFile) {
    
                $pathParts = pathinfo($entityFile);
                $entities[$i]['namespace'] = "\Modules\\".$module->getStudlyName()."\Entities\\".$pathParts['filename'];
                $entities[$i]['name'] = $pathParts['filename'];
                $i++;
            }
        }
        return $entities;
    }

    public function checkRelationshipExist($filePath, $module, $functionName)
    {
        $module = Module::find($module);
        $filePath = pathinfo($filePath);
        $module = "\Modules\\".$module->getStudlyName()."\Entities\\".$filePath['filename'];

        if(method_exists(new $module, $functionName)) {
            return true;
        }
        return false;
    }

    public function setJoin($joinType)
    {
        if($joinType == config("core.oneToOne")){
            $joinTypeArray = [
                'baseEntity' => ['getHasOneStub'],
                'targetEntity' => ['getBelongsToStub'],
                'baseType' => config("core.oneToOne"),
                'targetType' => config("core.oneToOne"),
            ];
        } elseif ($joinType == config("core.oneToMany")) {
            $joinTypeArray = [
                'baseEntity' => ['getHasManyStub'],
                'targetEntity' => ['getBelongsToStub'],
                'baseType' => config("core.oneToMany"),
                'targetType' => config("core.manyToOne"),
            ];
        } elseif ($joinType == config("core.manyToOne")) {
            $joinTypeArray = [
                'baseEntity' => ['getBelongsToStub'],
                'targetEntity' => ['getHasManyStub'],
                'baseType' => config("core.manyToOne"),
                'targetType' => config("core.oneToMany"),
            ];
        } elseif ($joinType == config("core.ManyToMany")) {
            $joinTypeArray = [
                'baseEntity' => ['getBelongsToManyStub'],
                'targetEntity' => ['getBelongsToManyStub'],
                'baseType' => config("core.ManyToMany"),
                'targetType' => config("core.ManyToMany"),
            ];
        }
        $this->joinType = $joinTypeArray;
        return $this;
    }

    public function addRelationship()
    {
        $entityParams = $this->entityParams;

        $baseEntity = $this->getModuleEntities($this->params['base_module'], $this->params['base_entity']);
        $baseEntity = new $baseEntity['object'];
        $targetEntity = $this->getModuleEntities($this->params['target_module'], $this->params['target_entity']);
        $targetEntity = new $targetEntity['object'];

        $baseEntityStubs = $this->joinType['baseEntity'];
        $fileWritten = false;
        
        if($baseEntityStubs) {
            foreach ($baseEntityStubs as $stubFunction) {
                $entityParams['NameSpace'] = get_class($targetEntity);
                $entityParams['FunctionName'] = getCamelCase($targetEntity->getTable());
                $entityParams['Arguments'] = "'{$entityParams['NameSpace']}'";
                
                if(isset($this->params['target_foreign_key']) && $this->params['target_foreign_key']) {
                    $entityParams['Arguments'] .= ", '{$this->params['target_foreign_key']}'";
                }

                if($this->_writeStub($this->params['base_entity'], $stubFunction, $entityParams, $this->params['base_module'])) {
                    $entityData = [
                        'join_type' => $this->joinType['baseType'],
                        'base_module' => $this->params['base_module'],
                        'base_entity' => $this->params['base_entity'],
                        'target_module' => $this->params['target_module'],
                        'target_entity' => $this->params['target_entity']
                    ];

                    if(isset($this->params['target_foreign_key']) && $this->params['target_foreign_key']) {
                        $entityData['target_foreign_key'] = $this->params['target_foreign_key'];
                    }

                    $this->create($entityData);
                }
            }
        }

        $targetEntityStubs = $this->joinType['targetEntity'];
        if($targetEntityStubs) {
            foreach ($targetEntityStubs as $stubFunction) {
                $entityParams['NameSpace'] = get_class($baseEntity);
                $entityParams['FunctionName'] = getCamelCase($baseEntity->getTable());
                $entityParams['Arguments'] = "'{$entityParams['NameSpace']}'";
                
                if(isset($this->params['target_foreign_key']) && $this->params['target_foreign_key']) {
                    $entityParams['Arguments'] .= ", '{$this->params['target_foreign_key']}'";
                }

                if($this->_writeStub($this->params['target_entity'], $stubFunction, $entityParams, $this->params['target_module'])) {
                    $entityData = [
                        'join_type' => $this->joinType['targetType'],
                        'base_module' => $this->params['base_module'],
                        'base_entity' => $this->params['target_entity'],
                        'target_module' => $this->params['target_module'],
                        'target_entity' => $this->params['base_entity']
                    ];

                    if(isset($this->params['target_foreign_key']) && $this->params['target_foreign_key']) {
                        $entityData['target_foreign_key'] = $this->params['target_foreign_key'];
                    }

                    $this->create($entityData);
                }
            }
        }
    }

    protected function _writeStub($entity, $stubFunction, $entityParams, $module) 
    {
        $stubPath = $this->{$stubFunction}();
        $filePath = $this->getFilePath($module, $entity);
        if($this->checkRelationshipExist($filePath, $module, $entityParams['FunctionName'])) {
            return false;
        }

        $stubString = file_get_contents($stubPath);
        $str_to_insert = $this->_replaceDataWithVariable($stubString, $entityParams);

        $f = fopen($filePath, "r+");
        $oldstr = file_get_contents($filePath);
        $specificLine = "//AppendFunctionHere";

        while (($buffer = fgets($f)) !== false) {
            if (strpos($buffer, $specificLine) !== false) {
                $pos = ftell($f);
                $newstr = substr_replace($oldstr, $str_to_insert, $pos, 0);
                file_put_contents($filePath, $newstr);
                break;
            }
        }
        fclose($f);
        return true;
    }

    /* public function removeRelationship($module, $entity)
    {
        $str = 'getHasManyStub';
        $stubPath = $this->{$str}();
        $filePath = $this->getFilePath($module, $entity);

        $stubString = file_get_contents($stubPath);
        $stringToRemove = $this->_replaceDataWithVariable($stubString);

        $f = fopen($filePath, "r+");

        $oldstr = file_get_contents($filePath);

        $newstr = str_replace($stringToRemove,'', $oldstr);
        file_put_contents($filePath, $newstr);
        fclose($f);
    } */

    public function getHasManyStub()
    {
        return $this->getStubPath().'entities/join/has-many.stub';
    }

    public function getBelongsToStub()
    {
        return $this->getStubPath().'entities/join/belongs-to.stub';
    }

    public function getBelongsToManyStub()
    {
        return $this->getStubPath().'entities/join/belongs-to-many.stub';
    }

    public function getHasOneStub()
    {
        return $this->getStubPath().'entities/join/has-one.stub';
    }

    public function getStubPath()
    {
        $module = Module::find('core');
        $path = $module->getPath();
        return $path.'/Commands/stubs/';
    }

    protected function getFilePath($module, $entity)
    {
        $module = Module::find($module);
        $path = $module->getPath();
        $path = $path.'/Entities';
        return $file = $path.'/'.$entity.'.php';
    }

    protected function _replaceDataWithVariable(String $content = null, $entityParams)
	{
        if(!$content) {
            return;
        }
		if(!$entityParams) {
            return $content;
        }

        foreach($entityParams as $key => $params)
        {
            if(is_object($params)) {
                foreach($params as $field => $object) {
                    $content = str_replace("#".$field."#", ($params->$field) ? $params->$field : "-", $content);
                }
            } elseif(is_array($params)) {
                foreach($params as $field => $object) {
                    $content = str_replace("#".$field."#", ($params[$field]) ? $params[$field] : "-" , $content);
                }
            }
            else{
                $content = str_replace("#$key#", ($params) ? $params : "-", $content);
            }
        }
        return $content;
    }
}