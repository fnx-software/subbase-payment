<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Nafiswatsiq\SubbasePayment\Contracts\PaymentTenantAccess;

final class PaymentTenancy
{
    public static function enabled(): bool
    {
        return (bool) config('subbase-payment.tenancy.enabled', false);
    }

    public static function scope(Builder $query): Builder
    {
        if (! self::enabled()) {
            return $query;
        }

        $actor = Auth::user();
        $resolver = self::resolver();
        if (! $actor instanceof Authenticatable || ! $resolver) {
            return $query->whereRaw('1 = 0');
        }

        return $resolver->scope($query, $actor);
    }

    public static function allows(?Model $record, string $ability, Authenticatable $actor): bool
    {
        if (! self::enabled()) {
            return true;
        }

        $resolver = self::resolver();

        return $resolver !== null && $resolver->allows($record, $ability, $actor);
    }

    private static function resolver(): ?PaymentTenantAccess
    {
        $class = config('subbase-payment.tenancy.access');

        if (! is_string($class) || ! is_a($class, PaymentTenantAccess::class, true)) {
            return null;
        }

        $candidate = app($class);

        return $candidate instanceof PaymentTenantAccess ? $candidate : null;
    }
}
