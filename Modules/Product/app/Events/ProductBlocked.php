<?php

declare(strict_types=1);

namespace Modules\Product\Events;

/**
 * An admin blocked the product: it leaves the storefront (M06 removes it from search).
 */
class ProductBlocked extends ProductEvent {}
