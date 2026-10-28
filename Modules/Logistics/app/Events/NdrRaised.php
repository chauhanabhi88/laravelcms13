<?php

declare(strict_types=1);

namespace Modules\Logistics\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Logistics\Models\NdrCase;

/**
 * A delivery attempt failed and the case waits for an action (fired after commit).
 */
class NdrRaised implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public NdrCase $ndrCase) {}
}
