<?php

declare(strict_types=1);

namespace Modules\Returns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Returns\Models\ReturnRequest;

/**
 * A return moved from $from to $to (fired after commit by the state machine).
 */
class ReturnStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ReturnRequest $returnRequest,
        public ?string $from,
        public string $to,
    ) {}
}
