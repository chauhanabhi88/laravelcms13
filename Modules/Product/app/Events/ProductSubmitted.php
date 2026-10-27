<?php

declare(strict_types=1);

namespace Modules\Product\Events;

/**
 * A product entered the QC queue (seller submission or admin unblock).
 */
class ProductSubmitted extends ProductEvent {}
