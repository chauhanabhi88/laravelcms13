<?php

declare(strict_types=1);

namespace Modules\Product\Imports;

/**
 * Rows of a new-catalog upload (category template); mapped to products by
 * Support\Import\RowToProductMapper in ProcessCatalogUpload.
 */
class CatalogUploadImport extends SpreadsheetRowsImport {}
