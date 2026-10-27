<?php

declare(strict_types=1);

namespace Modules\Product\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Product\Exports\Sheets\TemplateCatalogSheet;
use Modules\Product\Exports\Sheets\TemplateInstructionsSheet;
use Modules\Product\Exports\Sheets\TemplateOptionsSheet;
use Modules\Product\Support\Import\CatalogTemplate;

/**
 * The bulk catalog template of one leaf category (FR-SCT-003): sheet 1 "Catalog" (headings,
 * an example row, dropdowns), sheet 2 "Instructions", sheet 3 "Options" (the dropdown
 * values; the dropdowns reference these ranges because Excel caps inline lists at 255
 * characters).
 */
class CategoryTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public const CATALOG_SHEET = 'Catalog';

    public const INSTRUCTIONS_SHEET = 'Instructions';

    public const OPTIONS_SHEET = 'Options';

    public function __construct(protected CatalogTemplate $template) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        return [
            new TemplateCatalogSheet($this->template),
            new TemplateInstructionsSheet($this->template),
            new TemplateOptionsSheet($this->template),
        ];
    }

    /**
     * Options sheet column of each list column: column key => 1-based column index.
     *
     * @return array<string, int>
     */
    public static function optionColumns(CatalogTemplate $template): array
    {
        $index = 0;
        $columns = [];

        foreach ($template->columns() as $column) {
            if ($column['type'] === 'list' && $column['options'] !== []) {
                $columns[$column['key']] = ++$index;
            }
        }

        return $columns;
    }
}
