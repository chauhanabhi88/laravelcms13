<?php

namespace Modules\Core\Repositories\Cache;

use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Cache\Concerns\FlushesEntityCache;
use Modules\Core\Repositories\BaseRepository;

abstract class BaseCacheDecorator implements BaseRepository
{
    use FlushesEntityCache;

    /**
     * Container binding memoising the per-request guard identities.
     */
    private const IDENTITIES_KEY = 'core.cache.guard-identities';

    /**
     * @var BaseRepository
     */
    protected $repository;

    /**
     * @var Repository
     */
    protected $cache;

    /**
     * @var string The entity name
     */
    protected $entityName;

    public function __construct()
    {
        $this->cache = app(Repository::class);
    }

    /**
     * Flush through the same repository the reads were cached with.
     */
    protected function cacheRepository(): Repository
    {
        return $this->cache;
    }

    public function setModel($model)
    {
        $this->repository->setModel($model);

        return $this;
    }

    public function setUploadParams($params)
    {
        $this->repository->setUploadParams($params);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function find($id)
    {
        return $this->remember(function () use ($id) {
            return $this->repository->find($id);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->remember(function () {
            return $this->repository->all();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function allWithBuilder(): Builder
    {
        // Never cached: a Builder holds the database Connection (and an
        // unresolved PDO closure), so serialising it throws. The caller
        // executes the query itself.
        return $this->repository->allWithBuilder();
    }

    /**
     * {@inheritdoc}
     */
    public function paginate($perPage = 20)
    {
        // Not cached. The key carries the current user and their session filter
        // bucket, so a grid entry is only ever reusable by the same user, on the
        // same filters, on the same page - it is written far more often than it
        // is read, and every write widens what a flush has to throw away.
        return $this->repository->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function allTranslatedIn($lang)
    {
        return $this->remember(function () use ($lang) {
            return $this->repository->allTranslatedIn($lang);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug($slug)
    {
        return $this->remember(function () use ($slug) {
            return $this->repository->findBySlug($slug);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function create($data, $ignoreFields = [])
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->create($data, $ignoreFields);
    }

    /**
     * {@inheritdoc}
     */
    public function update($model, $data, $ignoreFields = [], $orderBy = false)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->update($model, $data, $ignoreFields, $orderBy);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($model)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->destroy($model);
    }

    public function deleteRecord($request, $imageRemoveParams = '')
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->deleteRecord($request, $imageRemoveParams);
    }

    /**
     * {@inheritdoc}
     */
    public function destroyMultiple($request, $removeFile = false)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->destroyMultiple($request, $removeFile);
    }

    public function restoreMultiple($request)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->restoreMultiple($request);
    }

    public function forceDeleteMultiple($request, $removeFile = false)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->forceDeleteMultiple($request, $removeFile);
    }

    public function flushCache($entityName)
    {
        return $this->flushCacheFor($entityName);
    }

    public function restoreAndForceDelete($request, $restore = false, $forceDelete = false)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->restoreAndForceDelete($request, $restore, $forceDelete);
    }

    /**
     * {@inheritdoc}
     */
    public function findByAttributes(array $attributes)
    {
        return $this->remember(function () use ($attributes) {
            return $this->repository->findByAttributes($attributes);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getByAttributes(array $attributes, $orderBy = null, $sortOrder = 'asc')
    {
        return $this->remember(function () use ($attributes, $orderBy, $sortOrder) {
            return $this->repository->getByAttributes($attributes, $orderBy, $sortOrder);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findByMany(array $ids)
    {
        return $this->remember(function () use ($ids) {
            return $this->repository->findByMany($ids);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache()
    {
        // Scoped to this entity. The previous fallback flushed the whole
        // application cache whenever the store had no tag support.
        return $this->flushCacheFor($this->entityName);
    }

    /**
     * @param  null|string  $key
     * @param  null|int  $time
     * @return mixed
     */
    protected function remember(\Closure $callback, $key = null, $time = null)
    {
        $cacheKey = $this->makeCacheKey($key);

        $store = $this->cache;
        if (method_exists($this->cache->getStore(), 'tags')) {
            $store = $store->tags([$this->entityName, 'global']);
        }
        // If no $time is passed, just use the default from config
        $cacheTime = $time ?? config('core.cache_expired_after');

        return $store->remember($cacheKey, $cacheTime, $callback);
    }

    /**
     * Generate a cache key with the called method name and its arguments
     * If a key is provided, use that instead
     *
     * @param  null|string  $key
     */
    private function makeCacheKey($key = null): string
    {
        if ($key !== null) {
            return $key;
        }

        $cacheKey = $this->getBaseKey();

        // Depth 3 is the public repository method that called remember().
        // Limiting the depth keeps this off the hot path; anything deeper is
        // irrelevant to the key anyway. Flag 0 keeps the args (which the key is
        // built from) while dropping the "object" entry, which is unused.
        $backtrace = debug_backtrace(0, 3)[2] ?? [];

        $arg = [];
        if (isset($backtrace['args']) && $backtrace['args']) {
            foreach ($backtrace['args'] as $argument) {
                $arg[] = $this->normaliseArgument($argument);
            }
        }

        return sprintf(
            '%s %s %s',
            $cacheKey,
            $backtrace['function'] ?? 'unknown',
            sha1(\serialize($arg))
        );
    }

    /**
     * Reduce a single argument to something stable and serialisable.
     *
     * Every argument must contribute to the key: silently skipping one makes
     * two different calls collapse onto the same entry and return each other's
     * data.
     *
     * @param  mixed  $argument
     * @return mixed
     */
    private function normaliseArgument($argument)
    {
        if (! is_object($argument)) {
            return $argument;
        }

        if (method_exists($argument, 'all')) {
            return [get_class($argument), $argument->all()];
        }

        if ($argument instanceof \DateTimeInterface) {
            return [get_class($argument), $argument->format('c')];
        }

        if ($argument instanceof \UnitEnum) {
            return [get_class($argument), $argument->name];
        }

        if (method_exists($argument, 'toArray')) {
            return [get_class($argument), $argument->toArray()];
        }

        return [get_class($argument), get_object_vars($argument)];
    }

    /**
     * The key prefix for cached reads.
     *
     * FileStore::path() matches the segment between the first two colons to
     * pick the entity's directory, so the entity name stays first and the
     * context fingerprint is appended after it.
     */
    protected function getBaseKey(): string
    {
        return $this->entityCacheKeyPrefix($this->entityName).$this->contextFingerprint();
    }

    /**
     * Fingerprint of the request-scoped state the repositories read but that
     * never reaches them as an argument.
     *
     * Grid queries pull their filters and page number straight out of the
     * session (see getSessionFilter()), and translatable repositories join on
     * app()->getLocale(). None of that is visible in the method arguments, so
     * without it two different users - or the same user in another language -
     * share a cache entry and are served each other's results.
     */
    protected function contextFingerprint(): string
    {
        return sha1(\serialize([
            app()->getLocale(),
            $this->guardIdentities(),
            $this->filterSessionState(),
        ]));
    }

    /**
     * The authenticated id for every configured guard.
     *
     * Resolving a guard is not free - the Passport "api" guard parses the
     * bearer token and hits the database - and this runs on every cached read,
     * so the result is memoised in the container. That binding lives and dies
     * with the application instance, which makes it per-request without a
     * static that would leak between queue jobs or tests.
     *
     * Caveat: a login or logout later in the same request will not be
     * reflected. Cached reads taken after an explicit auth change in the same
     * request keep the pre-change key.
     *
     * @return array<string, mixed>
     */
    protected function guardIdentities(): array
    {
        if (app()->bound(self::IDENTITIES_KEY)) {
            return app(self::IDENTITIES_KEY);
        }

        $identities = [];
        foreach (array_keys((array) config('auth.guards', [])) as $guard) {
            try {
                $identities[$guard] = auth()->guard($guard)->id();
            } catch (\Throwable) {
                // Guard not usable in this context (e.g. console) - ignore it.
            }
        }

        app()->instance(self::IDENTITIES_KEY, $identities);

        return $identities;
    }

    /**
     * The module's filter bucket in the session, if there is a session at all.
     *
     * @return mixed
     */
    protected function filterSessionState()
    {
        if (empty($this->entityName) || ! app()->bound('session')) {
            return null;
        }

        try {
            $session = app('session');

            if (! $session->isStarted()) {
                return null;
            }

            return $session->get(strtolower((string) $this->entityName).'_filter');
        } catch (\Throwable) {
            // No usable session (console, queue worker) - nothing to fingerprint.
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function with($relationships)
    {
        // Returns a Builder - see allWithBuilder() for why this is not cached.
        return $this->repository->with($relationships);
    }

    public function getYesNoOptions($flag = false)
    {
        return $this->remember(function () use ($flag) {
            return $this->repository->getYesNoOptions($flag);
        });
    }

    public function getStatusOptions($flag = false)
    {
        return $this->remember(function () use ($flag) {
            return $this->repository->getStatusOptions($flag);
        });
    }

    public function uploadFile($request)
    {
        return $this->repository->uploadFile($request);
    }

    public function uploadImage($request)
    {
        return $this->repository->uploadImage($request);
    }

    public function uploadMultipleImage($request)
    {
        return $this->repository->uploadMultipleImage($request);
    }

    public function uploadFileParameters($request)
    {
        return $this->repository->uploadFileParameters($request);
    }

    public function removeFile($fileName = '', $moduleName = '')
    {
        return $this->repository->removeFile($fileName, $moduleName);
    }

    public function getActivityData($action)
    {
        return $this->repository->getActivityData($action);
    }

    public function insert($action)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->insert($action);
    }

    public function updateOrCreate($data, $updatedData)
    {
        $this->flushCacheFor($this->entityName);

        return $this->repository->updateOrCreate($data, $updatedData);
    }

    public function exportData($columnArray, $collectionArray, $fileName, $columnType = [])
    {
        return $this->repository->exportData($columnArray, $collectionArray, $fileName, $columnType);
    }

    public function where(string $field, $value, ?string $operator = null)
    {
        return $this->repository->where($field, $value, $operator);
    }

    public function exportCsv($columnArray, $collectionArray, $fileName)
    {
        return $this->repository->exportCsv($columnArray, $collectionArray, $fileName);
    }

    public function defaultSort($columns, $orderBy, $dir)
    {
        return $this->repository->defaultSort($columns, $orderBy, $dir);
    }
}
