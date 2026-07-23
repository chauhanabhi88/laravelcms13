<?php

namespace Modules\Core\Repositories\Eloquent;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Core\Cache\Concerns\FlushesEntityCache;
use Modules\Core\Exports\CoreExport;
use Modules\Core\Models\Activities;
use Modules\Core\Repositories\BaseRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Class EloquentCoreRepository
 */
abstract class EloquentBaseRepository implements BaseRepository
{
    use FlushesEntityCache;

    /**
     * @var Model An instance of the Eloquent Model
     */
    protected $model;

    protected $activities;

    protected $_authUser;

    /**
     * @var parameters to upload files
     */
    protected $_uploadParams;

    /**
     * @param  Model  $model
     */
    public function __construct($model)
    {
        $this->activities = new Activities;
        $this->model = $model;
    }

    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setUploadParams($params)
    {
        $this->_uploadParams = $params;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function find($id, $columns = ['*'])
    {
        if (method_exists($this->model, 'translations')) {
            return $this->model->with('translations')->find($id, $columns);
        }

        return $this->model->find($id, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        if (method_exists($this->model, 'translations')) {
            return $this->model->with('translations')->orderBy('created_at', 'DESC')->get();
        }

        return $this->model->orderBy('created_at', 'DESC')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function allWithBuilder(): Builder
    {
        if (method_exists($this->model, 'translations')) {
            return $this->model->with('translations');
        }

        return $this->model->query();
    }

    /**
     * {@inheritdoc}
     */
    public function paginate($perPage = 20)
    {
        if (method_exists($this->model, 'translations')) {
            return $this->model->with('translations')->orderBy('created_at', 'DESC')->paginate($perPage);
        }

        return $this->model->orderBy('created_at', 'DESC')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function create($data, $ignoreFields = [])
    {
        $data = escapeHtml($data, $ignoreFields);

        return $this->model->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update($model, $data, $ignoreFields = [], $orderBy = false)
    {
        $data = escapeHtml($data, $ignoreFields);

        if ($orderBy) {
            $model->orderBy('id', 'DESC')->update($data);
        } else {
            $model->update($data);
        }

        return $model;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($model)
    {
        return $model->delete();
    }

    public function deleteRecord($request, $imageRemoveParams = '')
    {
        if (isset($request) && ! empty($request) && $request instanceof Request) {
            $id = $request->id;
            if (isset($id) && ! empty($id)) {
                $model = $this->model->find($id);
                if (isset($model) && ! empty($model)) {
                    if (is_array($imageRemoveParams) && isset($imageRemoveParams['module_name']) && ! empty($imageRemoveParams['module_name']) && isset($imageRemoveParams['dbfield']) && ! empty($imageRemoveParams['dbfield'])) {
                        $this->setUploadParams($imageRemoveParams)->setModel($model)->removeFile();
                        $model->delete();
                    } else {
                        $model->delete();
                    }
                } else {
                    $model = $this->model->onlyTrashed()->where('id', $id)->get()->first();

                    if ($model && ! empty($model)) {
                        if (is_array($imageRemoveParams) && isset($imageRemoveParams['module_name']) && ! empty($imageRemoveParams['module_name']) && isset($imageRemoveParams['dbfield']) && ! empty($imageRemoveParams['dbfield'])) {
                            $this->setUploadParams($imageRemoveParams)->setModel($model)->removeFile();
                            $model->forceDelete();
                        }
                        $model->forceDelete();
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroyMultiple($request, $removeFile = false, $notDeleteIds = [])
    {
        $ids = null;
        $limit = (int) settings('core', 'max_delete_limit');

        if ($request->isFiltered && $request->selectAll) {
            do {
                $collection = $this->filter($request)->limit($limit);
                if (isset($collection) && ! empty($collection) && $collection->count() > 0) {
                    if ($request->get('idsNotToDelete') != '' || $notDeleteIds) {
                        $idsNotToDelete = explode(',', $request->get('idsNotToDelete'));
                        if (is_array($idsNotToDelete) && is_array($notDeleteIds)) {
                            $idsNotToDelete = array_merge($idsNotToDelete, $notDeleteIds);
                        }
                        $collection = $collection->whereNotIn($this->model->getTable().'.id', $idsNotToDelete);
                    }
                    if ($removeFile) {
                        foreach ($collection->get() as $item) {
                            $this->setModel($item)->removeFile();
                        }
                    }
                    $collection->delete();
                }
            } while ($collection->count() > 0);
        } else {
            if ($request->get('selectAll') && $request->get('idsNotToDelete') != '') {
                $ids = explode(',', $request->get('idsNotToDelete'));
                if ($notDeleteIds) {
                    foreach ($notDeleteIds as $keyCheck => $value) {
                        if (($key = array_search($value, $ids)) === false) {
                            $ids[] = $value;
                        }
                    }
                }
                $model = $this->model->whereNotIn('id', $ids);
                $ids = array_diff($this->model->pluck('id')->toArray(), $ids);
            } elseif ($request->get('selectAll') && $request->get('idsNotToDelete') == null) {
                $ids = $this->model->pluck('id')->toArray();
                if ($notDeleteIds) {
                    foreach ($notDeleteIds as $keyCheck => $value) {
                        if (($key = array_search($value, $ids)) !== false) {
                            unset($ids[$key]);
                        }
                    }
                }
                $model = $this->model->whereIn('id', $ids);
            } elseif ($request->get('idsToDelete') && ! $request->get('selectAll')) {
                $ids = explode(',', $request->get('idsToDelete'));
                if ($notDeleteIds) {
                    foreach ($notDeleteIds as $keyCheck => $value) {
                        if (($key = array_search($value, $ids)) !== false) {
                            unset($ids[$key]);
                        }
                    }
                }
                $model = $this->model->whereIn('id', $ids);
            }
            if ($ids) {
                if ($removeFile) {
                    $data = $this->findByMany($ids);
                    foreach ($data as $item) {
                        $this->setModel($item)->removeFile();
                    }
                }
                $model->delete();
            }
        }
    }

    /* flush cache for specific entity */
    public function flushCache($entityName)
    {
        // Goes through the shared trait: reaching for the store's own flush
        // methods threw a BadMethodCallException on any store other than
        // Modules\Core's FileStore.
        return $this->flushCacheFor($entityName);
    }

    public function restoreMultiple($request)
    {
        $ids = null;
        if ($request->isFiltered && $request->selectAll) {
            $limit = (int) settings('core', 'max_delete_limit');
            do {
                $collection = $this->filter($request)->limit($limit);
                if (isset($collection) && ! empty($collection)) {
                    if ($request->get('restoreIdsNotDelete') != '') {
                        $restoreIdsNotDelete = explode(',', $request->get('restoreIdsNotDelete'));
                        $collection = $collection->whereNotIn($this->model->getTable().'.id', $restoreIdsNotDelete);
                    }
                    $collection->restore();
                }
            } while ($collection->count() > 0);
        } else {
            if ($request->get('selectAll') && $request->get('restoreIdsNotDelete') != '') {
                $ids = explode(',', $request->get('restoreIdsNotDelete'));
                $model = $this->model->onlyTrashed()->whereNotIn('id', $ids);
            } elseif ($request->get('selectAll') && $request->get('restoreIdsNotDelete') == null) {
                $ids = $this->model->onlyTrashed()->pluck('id')->toArray();
                $model = $this->model->onlyTrashed()->whereNotNull('id');
            } elseif ($request->get('restoreIdToDelete') && ! $request->get('selectAll')) {
                $ids = explode(',', $request->get('restoreIdToDelete'));
                $model = $this->model->onlyTrashed()->whereIn('id', $ids);
            }
            if ($ids) {
                if (isset($model) && ! empty($model)) {
                    return $model->restore();
                }
            }
        }
    }

    public function forceDeleteMultiple($request, $removeFile = false)
    {
        $ids = null;
        $limit = (int) settings('core', 'max_delete_limit');
        if ($request->isFiltered && $request->selectAll) {
            do {
                $collection = $this->filter($request)->limit($limit);
                if (isset($collection) && ! empty($collection) && $collection->count() > 0) {
                    if ($request->get('idsNotToDelete') != '') {
                        $idsNotToDelete = explode(',', $request->get('idsNotToDelete'));
                        $collection = $collection->whereNotIn($this->model->getTable().'.id', $idsNotToDelete);
                    }
                    if ($removeFile) {
                        foreach ($collection->get() as $item) {
                            $this->setModel($item)->removeFile();
                        }
                    }
                    $collection->forceDelete();
                }
            } while ($collection->count() > 0);
        } else {
            if ($request->get('selectAll') && $request->get('idsNotToDelete') != '') {
                $ids = explode(',', $request->get('idsNotToDelete'));
                $model = $this->model->onlyTrashed()->whereNotIn('id', $ids);
                $ids = array_diff($this->model->onlyTrashed()->pluck('id')->toArray(), $ids);
            } elseif ($request->get('selectAll') && $request->get('idsNotToDelete') == null) {
                $ids = $this->model->onlyTrashed()->pluck('id')->toArray();
                $model = $this->model->onlyTrashed()->whereNotNull('id');
            } elseif ($request->get('idsToDelete') && ! $request->get('selectAll')) {
                $ids = explode(',', $request->get('idsToDelete'));
                $model = $this->model->onlyTrashed()->whereIn('id', $ids);
            }
            if ($ids) {
                if (isset($model) && ! empty($model)) {
                    if ($removeFile) {
                        $data = $this->findByMany($ids);
                        foreach ($data as $item) {
                            $this->setModel($item)->removeFile();
                        }
                    }

                    return $model->forceDelete();
                }
            }
        }
    }

    /** softdelete user/customer to restore */
    public function restoreAndForceDelete($model, $restore = false, $forceDelete = false)
    {
        if (isset($model) && ! empty($model)) {
            if ($restore) {
                return $model->restore();
            } elseif ($forceDelete) {
                return $model->forceDelete();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function allTranslatedIn($lang)
    {
        return $this->model->whereHas('translations', function (Builder $q) use ($lang) {
            $q->where('locale', "$lang");
        })->with('translations')->orderBy('created_at', 'DESC')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug($slug)
    {
        if (method_exists($this->model, 'translations')) {
            return $this->model->whereHas('translations', function (Builder $q) use ($slug) {
                $q->where('slug', $slug);
            })->with('translations')->first();
        }

        return $this->model->where('slug', $slug)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findByAttributes(array $attributes)
    {
        $query = $this->buildQueryByAttributes($attributes);

        return $query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getByAttributes(array $attributes, $orderBy = null, $sortOrder = 'asc')
    {
        $query = $this->buildQueryByAttributes($attributes, $orderBy, $sortOrder);

        return $query->get();
    }

    /**
     * Build Query to catch resources by an array of attributes and params
     *
     * @param  null|string  $orderBy
     * @param  string  $sortOrder
     * @return Builder
     */
    private function buildQueryByAttributes(array $attributes, $orderBy = null, $sortOrder = 'asc')
    {
        $query = $this->model->query();

        if (method_exists($this->model, 'translations')) {
            $query = $query->with('translations');
        }

        foreach ($attributes as $field => $value) {
            $query = $query->where($field, $value);
        }

        if ($orderBy !== null) {
            $query->orderBy($orderBy, $sortOrder);
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function findByMany(array $ids)
    {
        $query = $this->model->query();

        if (method_exists($this->model, 'translations')) {
            $query = $query->with('translations');
        }

        return $query->whereIn('id', $ids)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function where(string $field, $value, ?string $operator = null): Builder
    {
        if ($operator === null) {
            $operator = '=';
        } else {
            [$value, $operator] = [$operator, $value];
        }

        return $this->model->where($field, $operator, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function with($relationships)
    {
        return $this->model->with($relationships);
    }

    /**
     * {@inheritdoc}
     */
    public function whereIn(string $field, array $values): Builder
    {
        return $this->model->whereIn($field, $values);
    }

    public function whereRaw(string $field): Builder
    {
        return $this->model->whereRaw($field);
    }

    public function orWhereRaw(string $field): Builder
    {
        return $this->model->orWhereRaw($field);
    }

    public function orWhere($function)
    {
        return $this->model->orWhere($function);
    }

    public function getAuthUser()
    {
        if (! $this->_authUser) {
            $this->_authUser = Auth::user();
        }

        return $this->_authUser;
    }

    /**
     * upload files
     */
    public function uploadFileParameters($request)
    {
        $this->_uploadParams['module_name'] = strtolower($this->_uploadParams['module_name']);
        $path = 'app/public/'.$this->_uploadParams['module_name'].'/';
        if (isset($this->_uploadParams['storage_path']) && $this->_uploadParams['storage_path']) {
            $path = 'app/public/'.$this->_uploadParams['storage_path'].'/';
        }
        $thumbnailPath = $path.'thumbnails/';
        if ((! isset($this->_uploadParams['dbfield']) && $this->_uploadParams['dbfield'] == null) && (isset($this->_uploadParams['column']) && $this->_uploadParams['column'])) {
            $this->_uploadParams['dbfield'] = $this->_uploadParams['column'];
        }

        if ($this->model->{$this->_uploadParams['dbfield']} && file_exists(storage_path($path.$this->model->{$this->_uploadParams['dbfield']}))) {
            unlink(storage_path($path.$this->model->{$this->_uploadParams['dbfield']}));
        }

        if ($this->model->{$this->_uploadParams['dbfield']} && file_exists(storage_path($thumbnailPath.$this->model->{$this->_uploadParams['dbfield']}))) {
            unlink(storage_path($thumbnailPath.$this->model->{$this->_uploadParams['dbfield']}));
        }

        if (isset($this->_uploadParams['column']) && $this->_uploadParams['column']) {
            $thumbmail = $file = $request->file($this->_uploadParams['column']);
        } else {
            $thumbmail = $file = $request->file($this->_uploadParams['dbfield']);
        }

        $storagePath = (isset($this->_uploadParams['storage_path']) && $this->_uploadParams['storage_path']) ? $this->_uploadParams['module_name'].$this->_uploadParams['storage_path'] : $this->_uploadParams['module_name'];

        return ['storagePath' => $storagePath, 'file' => $file, 'thumbmail' => $thumbmail];
    }

    public function uploadFile($request)
    {
        $disk = getStorageDisk();

        $fileData = $this->uploadFileParameters($request);
        $storagePath = $fileData['storagePath'];
        $file = $fileData['file'];
        // $file->store($storagePath, ['disk' =>  $disk]);
        $file->store($storagePath, $disk);

        $fileName = $file->hashName();
        $request = $request->all();
        if (isset($this->_uploadParams['column']) && $this->_uploadParams['column']) {
            $request[$this->_uploadParams['column']] = $fileName;
        } else {
            $request[$this->_uploadParams['dbfield']] = $fileName;
        }

        return $request;
    }

    public function uploadImage($request)
    {
        try {
            $disk = getStorageDisk();
            $fileData = $this->uploadFileParameters($request);
            $storagePath = $fileData['storagePath'];
            $file = $fileData['file'];

            $fileName = $file->hashName();
            $filePath = $storagePath.'/'.$fileName;
            $thumbPath = $storagePath.'/thumbnails/'.$fileName;
            $extension = $this->getUploadedFileExtension($file);

            $resizeWidth = $this->_uploadParams['resize_original_image_width'] ?? null;
            $resizeHeight = $this->_uploadParams['resize_original_image_height'] ?? null;

            if ($resizeWidth && $resizeHeight) {
                $image = $this->readImage($file)->scaleDown((int) $resizeWidth, (int) $resizeHeight);
                Storage::disk($disk)->put($filePath, (string) $image->encodeUsingFileExtension($extension));
            } else {
                $file->store($storagePath, $disk);
            }

            $hasThumbnail = ! empty($this->_uploadParams['thumbnail']);
            if ($hasThumbnail) {
                /* callers pass either thumbnail_size or an explicit thumbnail_width / thumbnail_height pair */
                $thumbWidth = $this->_uploadParams['thumbnail_width'] ?? $this->_uploadParams['thumbnail_size'] ?? 100;
                $thumbHeight = $this->_uploadParams['thumbnail_height'] ?? $this->_uploadParams['thumbnail_size'] ?? null;

                $thumbnail = $this->readImage($file)->scaleDown((int) $thumbWidth, $thumbHeight ? (int) $thumbHeight : null);
                Storage::disk($disk)->put($thumbPath, (string) $thumbnail->encodeUsingFileExtension($extension));
            }

            $request = $request->all();

            if (! Storage::disk($disk)->exists($filePath) || ($hasThumbnail && ! Storage::disk($disk)->exists($thumbPath))) {
                throw new Exception('File not found.');
            }

            if (isset($this->_uploadParams['column']) && $this->_uploadParams['column']) {
                $request[$this->_uploadParams['column']] = $fileName;
            } else {
                $request[$this->_uploadParams['dbfield']] = $fileName;
            }

            return $request;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 0, $e);
        }
    }

    /**
     * Read an uploaded file into an Intervention image instance.
     *
     * The uploaded file must be read by its real path: the temporary upload has a
     * ".tmp" extension, so anything that relies on the extension (encoding, saving
     * in place) fails on it.
     */
    protected function readImage($file)
    {
        return (new ImageManager(new Driver))->decodePath($file->getRealPath());
    }

    /**
     * Resolve the extension the stored file will carry.
     */
    protected function getUploadedFileExtension($file): string
    {
        $extension = pathinfo($file->hashName(), PATHINFO_EXTENSION);

        if (! $extension) {
            $extension = $file->guessExtension() ?: 'jpg';
        }

        return strtolower($extension);
    }

    public function uploadMultipleImage($request)
    {
        $disk = getStorageDisk();
        $this->_uploadParams['module_name'] = strtolower($this->_uploadParams['module_name']);
        $storagePath = (isset($this->_uploadParams['storage_path']) && $this->_uploadParams['storage_path']) ? $this->_uploadParams['module_name'].$this->_uploadParams['storage_path'] : $this->_uploadParams['module_name'];
        $file = $request;
        $file->store($storagePath, $disk);
        $fileName = $file->hashName();

        if (isset($this->_uploadParams['thumbnail']) && $this->_uploadParams['thumbnail']) {
            $thumbSize = (int) ($this->_uploadParams['thumbnail_size'] ?? 100);
            $extension = $this->getUploadedFileExtension($file);

            $thumbnail = $this->readImage($file)->scaleDown($thumbSize, $thumbSize);
            Storage::disk($disk)->put(
                $storagePath.'/thumbnails/'.$fileName,
                (string) $thumbnail->encodeUsingFileExtension($extension)
            );
        }

        return $fileName;
    }

    public function removeFile($fileName = '', $moduleName = '')
    {
        $this->_uploadParams['module_name'] = strtolower($this->_uploadParams['module_name']);
        $path = 'app/public/'.$this->_uploadParams['module_name'].'/';
        if (isset($this->_uploadParams['storage_path']) && $this->_uploadParams['storage_path']) {
            $path = 'app/public/'.$this->_uploadParams['storage_path'].'/';
        }

        $thumbnailPath = $path.'thumbnails/';
        if ($this->model->{$this->_uploadParams['dbfield']} && file_exists(storage_path($path.$this->model->{$this->_uploadParams['dbfield']}))) {
            unlink(storage_path($path.$this->model->{$this->_uploadParams['dbfield']}));
        }

        if ($this->model->{$this->_uploadParams['dbfield']} && file_exists(storage_path($thumbnailPath.$this->model->{$this->_uploadParams['dbfield']}))) {
            unlink(storage_path($thumbnailPath.$this->model->{$this->_uploadParams['dbfield']}));
        }

        $fileDir = is_dir(storage_path($path)) ? scandir(storage_path($path), 1) : [];
        if (isset($fileDir) && ! empty($fileDir)) {
            foreach ($fileDir as $key => $value) {
                $folderPath = $path.$value.'/';
                if ($this->model->{$this->_uploadParams['dbfield']} && file_exists(storage_path($folderPath.$this->model->{$this->_uploadParams['dbfield']}))) {
                    unlink(storage_path($folderPath.$this->model->{$this->_uploadParams['dbfield']}));
                }
            }
        }

        if (! empty($fileName)) {
            $moduleName = strtolower($moduleName);
            $path = public_path().'/storage/'.$moduleName;
            $dir = is_dir($path) ? scandir($path, 1) : [];
            if (isset($dir) && ! empty($dir)) {
                foreach ($dir as $key => $value) {
                    $storagePath = '/storage/'.$moduleName.'/'.$value.'/'.$fileName;
                    if (file_exists(public_path().$storagePath)) {
                        unlink(public_path($storagePath));
                    }
                }
            }
        }
    }

    public function getYesNoOptions($flag = false)
    {
        $options = [];
        if ($flag) {
            $options[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }

        return $options + [
            config('core.yes') => trans('core::core.options.yesno.yes'),
            config('core.no') => trans('core::core.options.yesno.no'),
        ];
    }

    public function getStatusOptions($flag = false)
    {
        $options = [];
        if ($flag) {
            $options[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }

        return $options + [
            config('core.enabled') => trans('core::core.options.status.enable'),
            config('core.disabled') => trans('core::core.options.status.disable'),
        ];
    }

    protected function getActivityData($action)
    {
        $modelArray = explode('\\', get_class($this->model));
        if ($action == config('core.create')) {
            $actionName = trans('core::core.labels.create');
        } elseif ($action == config('core.update')) {
            $actionName = trans('core::core.labels.update');
        } elseif ($action == config('core.delete')) {
            $actionName = trans('core::core.labels.delete');
        }
        $data = null;
        $data = [
            'admin_id' => auth()->user()->id,
            'ip_address' => \Request::getClientIp(true),
            'module' => $modelArray[1],
            'action' => $action,
            'message' => auth()->user()->name.' '.$actionName.' '.$this->model->getTable(),
        ];

        return $data;
    }

    public function insert($dataArr)
    {
        if (! empty($dataArr)) {
            $timestamp = $this->model->freshTimestampString();
            $getTableColumns = $this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->model->getTable());
            foreach ($dataArr as $key => $value) {
                if (isset($getTableColumns) && ! empty($getTableColumns) && in_array($this->model->getUpdatedAtColumn(), $getTableColumns) && isset($value) && ! empty($value) && ! isset($value[$this->model->getUpdatedAtColumn()]) && empty($value[$this->model->getUpdatedAtColumn()])) {
                    $value[$this->model->getUpdatedAtColumn()] = $timestamp;
                }
                if (isset($getTableColumns) && ! empty($getTableColumns) && in_array($this->model->getCreatedAtColumn(), $getTableColumns) && isset($value) && ! empty($value) && ! isset($value[$this->model->getCreatedAtColumn()]) && empty($value[$this->model->getCreatedAtColumn()])) {
                    $value[$this->model->getCreatedAtColumn()] = $timestamp;
                }
                $value = escapeHtml($value);
                $addArr[] = $value;
            }

            return $this->model->insert($addArr);
        }
    }

    public function updateOrCreate($data, $updatedData)
    {
        return $this->model->updateOrCreate($data, $updatedData);
    }

    public function exportData($columnArray, $collectionArray, $fileName, $columnType = [])
    {

        if (isset($columnArray) && ! empty($columnArray)) {
            foreach ($columnArray as $column) {
                $header[] = ucfirst(str_replace('_', ' ', $column));
            }
        }

        return Excel::download(new CoreExport($collectionArray, $header, $columnType), $fileName);
    }

    public function exportCsv($columnArray, $collectionArray, $fileName)
    {

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        if (isset($columnArray) && ! empty($columnArray)) {
            foreach ($columnArray as $column) {
                $header[] = ucwords(str_replace('_', ' ', $column));
            }
        }

        $endSell = chr(65 + (count($columnArray) - 1));

        $this->_setExportHeader('A', $endSell, $spreadsheet, $header);
        $rowCount = 2;
        if (isset($collectionArray) && ! empty($collectionArray)) {
            foreach ($collectionArray as $data) {
                $this->_setExportData('A', $endSell, $spreadsheet, $rowCount, $columnArray, $data);
                $rowCount++;
            }
        }

        $filename = $fileName.'.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-control: private');
        header('Content-type: application/force-download');
        header("Content-transfer-encoding: binary\n");
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

        exit;
    }

    private function _setExportHeader($start, $end, $objSpreadsheet, $val = [])
    {
        $count = 0;
        $styleArray = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'rotation' => 90,
                'startColor' => [
                    'argb' => 'FF0000',
                ],
                'endColor' => [
                    'argb' => 'FFFFFF',
                ],
            ],
        ];

        for ($i = $start; $i <= $end; $i++) {

            $objSpreadsheet->getActiveSheet()->getStyle($i.'1')->applyFromArray($styleArray);
            $objSpreadsheet->setActiveSheetIndex(0)->setCellValue($i.'1', $val[$count]);
            $objSpreadsheet->getActiveSheet()->getColumnDimension($i)->setAutoSize(true);
            // $objSpreadsheet->getActiveSheet()->getRowDimension('1')->setRowHeight(-1);
            $count++;
        }

        return true;
    }

    private function _setExportData($start, $end, $val, $objSpreadsheet, $rowCount, $column)
    {
        $count = 0;
        for ($i = $start; $i <= $end; $i++) {
            /*if ($rowCount % 2 == 0) {
                $objSpreadsheet->getActiveSheet()->getStyle($i . $rowCount)->applyFromArray(
                    array(
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                            'rotation' => 90,
                            'startColor' => [
                                'argb' => 'FFA0A0A0',
                            ],
                            'endColor' => [
                                'argb' => 'D3D3D3',
                            ],
                        ],
                    )
                );
            }*/

            $objSpreadsheet->setActiveSheetIndex(0)->setCellValue($i.$rowCount, $val[$column[$count]]);
            $objSpreadsheet->getActiveSheet()->getRowDimension($rowCount)->setRowHeight(20);
            $extension = ['jpeg', 'jpg', 'png', 'docx', 'pdf'];
            $ext = strtolower(pathinfo($val[$column[$count]], PATHINFO_EXTENSION));
            if (in_array($ext, $extension) && $val[$column[$count]] && isset($val['module']) && ! empty($val['module']) && isset($val['tooltip']) && ! empty($val['tooltip'])) {
                $og_image_param = [
                    'module' => $val['module'],
                    'image' => $val[$column[$count]],
                ];
                if (getImageUrl($og_image_param)) {
                    // $file = public_path() . '/storage/' . strtolower(\Config::get($val['module'].'.name')) . '/' . $val[$column[$count]];
                    $objSpreadsheet->getActiveSheet()->getStyle($i.$rowCount)
                        ->getFont()->getColor()->setARGB(Color::COLOR_BLUE);
                    $objSpreadsheet->setActiveSheetIndex(0)->getCell($i.$rowCount)->getHyperlink()->setUrl(getImageUrl($og_image_param))->setTooltip($val['tooltip']);
                }
            }
            $count++;
        }

        return true;
    }

    public function getCsvFormatFile($fileName, $columnsHeaders = [])
    {
        $columns = $columnsHeaders;

        if (! empty($columns)) {

            $spreadSheet = new Spreadsheet;
            $sheet = $spreadSheet->getActiveSheet();
            $endCol = chr(65 + (count($columns) - 1));
            $styleArray = [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startColor' => [
                        'argb' => 'FFA0A0A0',
                    ],
                    'endColor' => [
                        'argb' => 'FFFFFF',
                    ],
                ],
            ];
            $startCol = 'A';
            $count = 0;
            for ($i = $startCol; $i <= $endCol; $i++) {

                $spreadSheet->getActiveSheet()->getStyle($i.'1')->applyFromArray($styleArray);
                $spreadSheet->setActiveSheetIndex(0)->setCellValue($i.'1', $columns[$count]);
                $count++;
            }

            $fileName = $fileName.'.xlsx';
            $writer = new Xlsx($spreadSheet);
            header('Content-type: text/xlsx');
            header('Content-Disposition: attachment; filename="'.$fileName.'"');
            header('Content-type: application/force-download');
            header("Content-transfer-encoding: binary\n");
            $writer->save('php://output');
        }
        exit;
    }

    public function defaultSort($columns, $orderBy, $dir)
    {
        if (isset($columns) && ! empty($columns)) {
            foreach ($columns as $key => $column) {
                if (! empty($column['column']) && $orderBy == $column['column']) {
                    $columns[$key]['default_sort'] = true;
                    $columns[$key]['dir'] = $dir;
                }
                if (! empty($columns[$key]['default_sort']) && $orderBy != $column['column']) {
                    unset($columns[$key]['default_sort']);
                }
            }
        }

        return $columns;
    }
}
