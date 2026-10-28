<?php

declare(strict_types=1);

namespace Modules\Returns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Returns\Models\ReturnRequest;

/**
 * A customer raised a return or exchange (after commit).
 */
class ReturnRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest) {}
}
