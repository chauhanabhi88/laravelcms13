<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use DomainException;
use Illuminate\Database\Eloquent\Model;

class InvalidStateTransition extends DomainException
{
    public function __construct(
        public readonly Model $model,
        public readonly ?string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf(
            'Cannot move %s #%s from [%s] to [%s].',
            class_basename($model),
            $model->getKey() ?? 'new',
            $from ?? 'none',
            $to,
        ));
    }
}
