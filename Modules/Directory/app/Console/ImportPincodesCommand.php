<?php

declare(strict_types=1);

namespace Modules\Directory\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Directory\Models\DirectoryCountryCity;
use Modules\Directory\Models\DirectoryCountryCityTranslation;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Models\DirectoryCountryStateTranslation;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Directory\Repositories\DirectoryPincodeRepository;
use Modules\Directory\Support\DirectorySettings;
use SplFileObject;

/**
 * Loads the India Post "All India Pincode Directory" CSV into directory_pincode.
 *
 * Re-runnable: rows are upserted on (pincode, office_name), and the admin-managed
 * flags (serviceable, COD, zone) are never overwritten. States are matched by name
 * (with historic spellings) and created from the canonical list when missing; cities
 * are matched or created from the district name.
 */
class ImportPincodesCommand extends Command
{
    protected $signature = 'directory:import-pincodes
        {path : CSV file, absolute or relative to the project root (e.g. storage/app/pincodes.csv)}
        {--chunk=1000 : Rows per upsert}
        {--country= : ISO-2 country code the states and cities belong to (default: Directory setting, then IN)}';

    protected $description = 'Import the India Post pincode directory CSV into directory_pincode';

    protected $help = <<<'HELP'
        Dataset: "All India Pincode Directory" published by India Post on the Open Government
        Data Platform (Government Open Data Licence - India):
          https://www.data.gov.in/catalog/all-india-pincode-directory

        Download the CSV, save it as storage/app/pincodes.csv and run:
          php artisan directory:import-pincodes storage/app/pincodes.csv

        Expected columns (header names are matched case-insensitively):
          officename, pincode, district (or districtname), statename, latitude, longitude
        HELP;

    /**
     * Canonical states and union territories: ISO 3166-2:IN subdivision code => name.
     */
    private const STATES = [
        'AN' => 'Andaman and Nicobar Islands',
        'AP' => 'Andhra Pradesh',
        'AR' => 'Arunachal Pradesh',
        'AS' => 'Assam',
        'BR' => 'Bihar',
        'CH' => 'Chandigarh',
        'CG' => 'Chhattisgarh',
        'DH' => 'Dadra and Nagar Haveli and Daman and Diu',
        'DL' => 'Delhi',
        'GA' => 'Goa',
        'GJ' => 'Gujarat',
        'HR' => 'Haryana',
        'HP' => 'Himachal Pradesh',
        'JK' => 'Jammu and Kashmir',
        'JH' => 'Jharkhand',
        'KA' => 'Karnataka',
        'KL' => 'Kerala',
        'LA' => 'Ladakh',
        'LD' => 'Lakshadweep',
        'MP' => 'Madhya Pradesh',
        'MH' => 'Maharashtra',
        'MN' => 'Manipur',
        'ML' => 'Meghalaya',
        'MZ' => 'Mizoram',
        'NL' => 'Nagaland',
        'OD' => 'Odisha',
        'PY' => 'Puducherry',
        'PB' => 'Punjab',
        'RJ' => 'Rajasthan',
        'SK' => 'Sikkim',
        'TN' => 'Tamil Nadu',
        'TG' => 'Telangana',
        'TR' => 'Tripura',
        'UP' => 'Uttar Pradesh',
        'UK' => 'Uttarakhand',
        'WB' => 'West Bengal',
    ];

    /**
     * Spellings found in India Post data (after normalisation) => canonical code.
     */
    private const STATE_ALIASES = [
        'ORISSA' => 'OD',
        'PONDICHERRY' => 'PY',
        'UTTARANCHAL' => 'UK',
        'CHHATISGARH' => 'CG',
        'CHATTISGARH' => 'CG',
        'TELENGANA' => 'TG',
        'NCT OF DELHI' => 'DL',
        'NEW DELHI' => 'DL',
        'JAMMU KASHMIR' => 'JK',
        'ANDAMAN AND NICOBAR' => 'AN',
        'ANDAMAN NICOBAR ISLANDS' => 'AN',
        'DADRA AND NAGAR HAVELI' => 'DH',
        'DAMAN AND DIU' => 'DH',
        'DADRA NAGAR HAVELI AND DAMAN DIU' => 'DH',
    ];

    /**
     * @var array<string, string[]>
     */
    private const COLUMNS = [
        'pincode' => ['pincode'],
        'office' => ['officename', 'office_name', 'office name'],
        'district' => ['district', 'districtname', 'district_name'],
        'state' => ['statename', 'state_name', 'state'],
        'latitude' => ['latitude', 'lat'],
        'longitude' => ['longitude', 'long', 'lng'],
    ];

    private string $country;

    /** @var array<string, array{id: int, code: string}> normalised name => state */
    private array $states = [];

    /** @var array<string, int> "STATECODE|NORMALISED NAME" => city id */
    private array $cities = [];

    /** @var array<string, int> */
    private array $unknownStates = [];

    private int $createdStates = 0;

    private int $createdCities = 0;

    public function handle(DirectoryPincodeRepository $pincodes): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));

        if ($path === null) {
            $this->components->error('File not found: '.$this->argument('path'));

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $this->country = strtoupper((string) ($this->option('country') ?: DirectorySettings::defaultCountry()));

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');

        $columns = $this->mapHeader($file->current() ?: []);

        if (($missing = array_diff(['pincode', 'office', 'district', 'state'], array_keys($columns))) !== []) {
            $this->components->error('Missing CSV columns: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->loadStates();
        $this->loadCities();

        $startedAt = microtime(true);
        $imported = 0;
        $skipped = 0;
        $chunk = [];
        $file->next();

        while ($file->valid()) {
            $row = $this->toRecord((array) $file->current(), $columns);
            $file->next();

            if ($row === null) {
                $skipped++;

                continue;
            }

            $chunk[$row['pincode'].'|'.mb_strtolower($row['office_name'])] = $row;

            if (count($chunk) >= $chunkSize) {
                $imported += $this->flush($chunk);
                $chunk = [];
            }
        }

        $imported += $this->flush($chunk);

        $pincodes->flushCache(config('directory.cache.pincode'));
        $pincodes->flushCache(config('directory.cache.state'));
        $pincodes->flushCache(config('directory.cache.city'));

        $this->components->info(sprintf(
            'Imported %d rows in %.1fs (%d skipped, %d states and %d cities created).',
            $imported, microtime(true) - $startedAt, $skipped, $this->createdStates, $this->createdCities
        ));

        if ($this->unknownStates !== []) {
            $this->components->warn('Unrecognised state names (rows kept without state/city): '.implode(', ', array_map(
                fn (string $name, int $count): string => "{$name} ({$count})", array_keys($this->unknownStates), $this->unknownStates
            )));
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $path): ?string
    {
        foreach ([$path, base_path($path)] as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int> logical column => CSV index
     */
    private function mapHeader(array $header): array
    {
        $normalised = array_map(fn ($name): string => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name))), $header);
        $columns = [];

        foreach (self::COLUMNS as $column => $aliases) {
            foreach ($aliases as $alias) {
                if (($index = array_search($alias, $normalised, true)) !== false) {
                    $columns[$column] = $index;

                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, string|null>  $values
     * @param  array<string, int>  $columns
     * @return array<string, mixed>|null
     */
    private function toRecord(array $values, array $columns): ?array
    {
        $value = fn (string $column): string => isset($columns[$column]) ? trim((string) ($values[$columns[$column]] ?? '')) : '';

        $pincode = $value('pincode');
        $office = Str::limit(preg_replace('/\s+/', ' ', $value('office')), 150, '');
        $district = $this->title($value('district'));

        if (! preg_match('/^[1-9]\d{5}$/', $pincode) || $office === '' || $district === '') {
            return null;
        }

        $state = $this->resolveState($value('state'));

        return [
            'pincode' => $pincode,
            'office_name' => $office,
            'district' => Str::limit($district, 100, ''),
            'state_id' => $state['id'] ?? null,
            'city_id' => $state === null ? null : $this->resolveCity($state['code'], $district),
            'latitude' => $this->coordinate($value('latitude'), 90),
            'longitude' => $this->coordinate($value('longitude'), 180),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $chunk
     */
    private function flush(array $chunk): int
    {
        if ($chunk === []) {
            return 0;
        }

        $now = Carbon::now();
        $rows = array_map(fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now], array_values($chunk));

        DirectoryPincode::query()->upsert(
            $rows,
            ['pincode', 'office_name'],
            ['district', 'state_id', 'city_id', 'latitude', 'longitude', 'updated_at'],
        );

        return count($rows);
    }

    private function coordinate(string $value, int $limit): ?string
    {
        if (! is_numeric($value) || abs((float) $value) > $limit || (float) $value === 0.0) {
            return null;
        }

        return number_format((float) $value, 7, '.', '');
    }

    /**
     * @return array{id: int, code: string}|null
     */
    private function resolveState(string $name): ?array
    {
        $key = $this->normalise($name);

        if ($key === '') {
            return null;
        }

        if (isset($this->states[$key])) {
            return $this->states[$key];
        }

        $code = self::STATE_ALIASES[$key] ?? array_search($key, array_map($this->normalise(...), self::STATES), true);

        if ($code === false) {
            $this->unknownStates[$name] = ($this->unknownStates[$name] ?? 0) + 1;

            return null;
        }

        $state = $this->states[$this->normalise(self::STATES[$code])] ?? $this->states['#'.$code] ?? $this->createState($code);

        return $this->states[$key] = $state;
    }

    private function resolveCity(string $stateCode, string $name): int
    {
        $key = $stateCode.'|'.$this->normalise($name);

        return $this->cities[$key] ??= $this->createCity($stateCode, $name);
    }

    /**
     * @return array{id: int, code: string}
     */
    private function createState(string $code): array
    {
        $id = DB::transaction(function () use ($code): int {
            $id = DirectoryCountryState::query()->insertGetId([
                'country' => $this->country,
                'code' => $code,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DirectoryCountryStateTranslation::query()->insert($this->translations('directory_country_state_id', $id, self::STATES[$code]));

            return $id;
        });

        $this->createdStates++;

        return $this->states[$this->normalise(self::STATES[$code])] = $this->states['#'.$code] = ['id' => $id, 'code' => $code];
    }

    private function createCity(string $stateCode, string $name): int
    {
        $id = DB::transaction(function () use ($stateCode, $name): int {
            $id = DirectoryCountryCity::query()->insertGetId([
                'country' => $this->country,
                'state' => $stateCode,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DirectoryCountryCityTranslation::query()->insert($this->translations('directory_country_city_id', $id, $name));

            return $id;
        });

        $this->createdCities++;

        return $id;
    }

    /**
     * One translation row per active language; admins translate the non-English ones later.
     *
     * @return list<array<string, mixed>>
     */
    private function translations(string $foreignKey, int $id, string $name): array
    {
        $now = Carbon::now();

        return array_map(fn (string $locale): array => [
            $foreignKey => $id,
            'name' => $name,
            'locale' => $locale,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys(getLanguageOptions() ?: [config('app.locale') => config('app.locale')]));
    }

    private function loadStates(): void
    {
        $states = new DirectoryCountryState;
        $translations = new DirectoryCountryStateTranslation;

        $rows = DB::table($states->getTable().' as s')
            ->leftJoin($translations->getTable().' as t', 't.directory_country_state_id', '=', 's.id')
            ->where('s.country', $this->country)
            ->get(['s.id', 's.code', 't.name']);

        foreach ($rows as $row) {
            $state = ['id' => (int) $row->id, 'code' => (string) $row->code];
            $this->states['#'.strtoupper((string) $row->code)] ??= $state;

            if ($row->name !== null && $this->normalise($row->name) !== '') {
                $this->states[$this->normalise($row->name)] ??= $state;
            }
        }
    }

    private function loadCities(): void
    {
        $cities = new DirectoryCountryCity;
        $translations = new DirectoryCountryCityTranslation;

        $rows = DB::table($cities->getTable().' as c')
            ->join($translations->getTable().' as t', 't.directory_country_city_id', '=', 'c.id')
            ->where('c.country', $this->country)
            ->get(['c.id', 'c.state', 't.name']);

        foreach ($rows as $row) {
            $this->cities[strtoupper((string) $row->state).'|'.$this->normalise((string) $row->name)] ??= (int) $row->id;
        }
    }

    /**
     * "the Dadra & Nagar Haveli" => "DADRA AND NAGAR HAVELI".
     */
    private function normalise(string $name): string
    {
        $name = strtoupper(str_replace('&', ' AND ', $name));
        $name = preg_replace('/[^A-Z0-9 ]+/', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return preg_replace('/^THE /', '', $name);
    }

    private function title(string $value): string
    {
        return Str::title(Str::lower(preg_replace('/\s+/', ' ', $value)));
    }
}
