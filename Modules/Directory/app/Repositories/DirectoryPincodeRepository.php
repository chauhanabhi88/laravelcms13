<?php

namespace Modules\Directory\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Directory\Models\DirectoryPincode;

interface DirectoryPincodeRepository extends BaseRepository
{
    public function sortColumns();

    public function getFilters($request);

    public function pagination($request);

    public function getYesNoOptions($flag = false);

    public function flushCache($entityName);

    /**
     * The representative office of a pincode, with state and city (and their translations) loaded.
     */
    public function findByPincode(string $pincode): ?DirectoryPincode;

    /**
     * Set pincode-level flags (is_serviceable, cod_allowed, zone) on every office of the pincode.
     *
     * @param  array<string, mixed>  $flags
     */
    public function updateFlags(string $pincode, array $flags): int;

    /**
     * State id => name for the default country, for filters and the edit form.
     *
     * @return array<int|string, string>
     */
    public function getStateOptions(bool $withPlaceholder = false): array;

    /**
     * @return array<string, string>
     */
    public function getZoneOptions(bool $withPlaceholder = false): array;
}
