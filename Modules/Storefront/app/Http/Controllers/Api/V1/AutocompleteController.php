<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Storefront\Services\Search\AutocompleteService;

/**
 * GET /api/v1/autocomplete?q=kur — grouped search suggestions (throttled 30 per 10 s per IP).
 */
class AutocompleteController extends ApiController
{
    public function __construct(protected AutocompleteService $autocomplete) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->autocomplete->suggest((string) $request->query('q', '')));
    }
}
