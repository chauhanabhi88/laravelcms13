<?php

namespace Modules\Core\Support\Migrations;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SchemaParser implements Arrayable
{
    /**
     * The array of custom attributes.
     */
    protected array $customAttributes = [
        'remember_token' => 'rememberToken()',
        'soft_delete' => 'softDeletes()',
    ];

    /**
     * The migration schema.
     */
    protected ?string $schema;

    /**
     * The relationship keys.
     */
    protected array $relationshipKeys = [
        'belongsTo',
    ];

    protected array $foreignKeys = [
        'foreign',
    ];

    /**
     * Create new instance.
     */
    public function __construct(?string $schema = null)
    {
        $this->schema = $schema;
    }

    /**
     * Parse a string to array of formatted schema.
     */
    public function parse(?string $schema): array
    {
        $this->schema = $schema;

        $parsed = [];

        foreach ($this->getSchemas() as $schemaArray) {
            $column = $this->getColumn($schemaArray);

            $attributes = $this->getAttributes($column, $schemaArray);

            $parsed[$column] = $attributes;
        }

        return $parsed;
    }

    /**
     * Get array of schema.
     */
    public function getSchemas(): array
    {
        if (is_null($this->schema)) {
            return [];
        }

        // Handle complex schema with ## delimiters
        $schema = str_replace(' ', '', $this->schema);

        // Split by ## but keep track of field boundaries
        $parts = explode('##', $schema);
        $schemas = [];
        $currentSchema = '';

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            // If part contains field definition (has colon), it's a new field
            if (strpos($part, ':') !== false && ! empty($currentSchema)) {
                $schemas[] = $currentSchema;
                $currentSchema = $part;
            } else {
                // Continue building current field
                $currentSchema .= (! empty($currentSchema) ? '##' : '').$part;
            }
        }

        // Add the last schema
        if (! empty($currentSchema)) {
            $schemas[] = $currentSchema;
        }

        return $schemas;
    }

    /**
     * Convert string migration to array.
     */
    public function toArray(): array
    {
        return $this->parse($this->schema);
    }

    /**
     * Render the migration to formatted script.
     */
    public function render(): string
    {
        $results = '';

        foreach ($this->toArray() as $column => $attributes) {
            $results .= $this->createField($column, $attributes);
        }

        return $results;
    }

    /**
     * Render up migration fields.
     */
    public function up(): string
    {
        return $this->render();
    }

    /**
     * Render down migration fields.
     */
    public function down(): string
    {
        $results = '';

        foreach ($this->toArray() as $column => $attributes) {
            $attributes = [head($attributes)];
            $results .= $this->createField($column, $attributes, 'remove');
        }

        return $results;
    }

    /**
     * Create field.
     */
    public function createField(string $column, array $attributes, string $type = 'add'): string
    {
        $results = "\t\t\t".'$table';

        foreach ($attributes as $key => $field) {
            if (in_array($column, $this->relationshipKeys)) {
                $results .= $this->addRelationColumn($key, $field, $column);
            } elseif ((strpos($column, '&foreign&') !== false) && ($type == 'add')) {
                $results .= $this->addForeignColumn($key, $field, $column);
            } elseif ((strpos($column, '&foreign&') !== false) && ($type == 'remove')) {
                $results = '';
            } else {
                $results .= $this->{"{$type}Column"}($key, $field, $column);
            }
        }

        return $results.';'.PHP_EOL;
    }

    /**
     * Add relation column.
     *
     *
     * @return string
     */
    protected function addRelationColumn(int $key, string $field, ?string $column = null)
    {
        if ($key === 0) {
            $relatedColumn = Str::snake(class_basename($field)).'_id';

            return "->unsignedBigInteger('{$relatedColumn}');".PHP_EOL."\t\t\t"."\$table->foreign('{$relatedColumn}')";
        }
        if ($key === 1) {
            return "->references('{$field}')";
        }
        if ($key === 2) {
            return "->on('{$field}')";
        }
        if (Str::contains($field, '(')) {
            return '->'.$field;
        }

        return '->'.$field.'()';
    }

    /**
     * Format foreign key field to script.
     */
    protected function addForeignColumn(int $key, string $field, string $column): string
    {
        return '->'.$field;
    }

    /**
     * Format field to script.
     */
    protected function addColumn(int $key, string $field, string $column): string
    {
        if ($this->hasCustomAttribute($column)) {
            return '->'.$field;
        }

        if ($key == 0) {
            $split = explode(',', $column, 2);
            $columnName = $split[0];

            // Remove &foreign& markers if present
            $columnName = str_replace('&foreign&', '', $columnName);
            $length = (isset($split[1]) && ! empty($split[1])) ? ','.$split[1] : '';

            return '->'.$field."('".$columnName."' $length)";
        }

        if (Str::contains($field, '(')) {
            return '->'.$field;
        }

        return '->'.$field.'()';
    }

    /**
     * Format field to script.
     */
    protected function removeColumn(int $key, string $field, string $column): string
    {
        if ($this->hasCustomAttribute($column)) {
            return '->'.$field;
        }

        return '->dropColumn('."'".$column."')";
    }

    /**
     * Get column name from schema.
     */
    public function getColumn(string $schema): string
    {
        return Arr::get(explode(':', $schema), 0);
    }

    /**
     * Get column attributes.
     */
    public function getAttributes(string $column, string $schema): array
    {
        $fields = str_replace($column.':', '', $schema);

        if ($this->hasCustomAttribute($column)) {
            return $this->getCustomAttribute($column);
        }

        // Handle complex attribute string that may contain ## delimiters
        $attributes = [];

        // Split by : first, then handle ## within each part
        $parts = explode(':', $fields);

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            // If part contains ##, split it further
            if (strpos($part, '##') !== false) {
                $subParts = explode('##', $part);
                foreach ($subParts as $subPart) {
                    if (! empty(trim($subPart))) {
                        $attributes[] = trim($subPart);
                    }
                }
            } else {
                if (! empty(trim($part))) {
                    $attributes[] = trim($part);
                }
            }
        }

        return $attributes;
    }

    /**
     * Determine whether the given column is exist in customAttributes array.
     */
    public function hasCustomAttribute(string $column): bool
    {
        return array_key_exists($column, $this->customAttributes);
    }

    /**
     * Get custom attributes value.
     */
    public function getCustomAttribute(string $column): array
    {
        return (array) $this->customAttributes[$column];
    }

    /**
     * script to remove foreign key constraints from the table.
     */
    public function foreignKeyDown(string $tableName): string
    {
        $results = [];
        $data = '';
        foreach ($this->toArray() as $column => $attributes) {
            if (strpos($column, '&foreign&') !== false) {
                $results[] = str_replace('&foreign&', '', $column);
            }
        }
        if (! $results) {
            return $data;
        }
        $data .= 'Schema::table($module->getTable(), function (Blueprint $table) {';
        foreach ($results as $key => $value) {
            $data .= "\n".'$table->dropForeign(["'.$value.'"]);';
        }
        $data = rtrim($data, ',');
        $data .= "\n".'});';

        return $data;
    }

    /**
     * Get foreign keys for the table.
     */
    public function getForeignKeys(string $table): string
    {
        $result = '';
        $temp = [];

        // Schema::getForeignKeys() replaces a hand-rolled information_schema
        // query that interpolated $table straight into SQL. $table reaches here
        // from the entity form, and the query was MySQL-only besides.
        $data = \Schema::hasTable($table) ? \Schema::getForeignKeys($table) : [];

        if (! $data) {
            return $result;
        }
        foreach ($data as $foreignKey) {
            $value = str_replace('_foreign', '', $foreignKey['name']);
            $value = str_replace($table.'_', '', $value);
            $temp[$value] = $value;
        }
        $intersect = array_intersect_key($temp, $this->toArray());
        if (! $intersect) {
            return $result;
        }
        $result .= 'Schema::table("'.$table.'", function (Blueprint $table) {';

        foreach ($this->toArray() as $column => $attributes) {
            if (array_key_exists($column, $temp)) {
                $result .= "\n".'$table->dropForeign(["'.$column.'"]);';
            }
        }
        $result = rtrim($result, ',');
        $result .= "\n".'});';

        return $result;
    }

    /**
     * Get the entity name from the given name.
     */
    public function getEntityName(string $name): string
    {
        $name = Str::studly($name);

        return $name;
    }
}
