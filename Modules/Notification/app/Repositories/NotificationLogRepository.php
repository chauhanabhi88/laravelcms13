<?php

namespace Modules\Notification\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Notification\Models\NotificationLog;

interface NotificationLogRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  event, channel and status select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<NotificationLog>
     */
    public function filter(Request $request): Builder;
}
