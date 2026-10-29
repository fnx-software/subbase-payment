<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Host-specific scoping of transaction and diagnostic resources.
 */
interface PaymentTenantAccess
{
    public function scope(Builder $query, Authenticatable $actor): Builder;

    public function allows(?Model $record, string $ability, Authenticatable $actor): bool;
}
