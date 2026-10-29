<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Fail closed through native Laravel Gate; Spatie Permission remains optional.
 */
final class SubbasePaymentPermission
{
    public static function allows(
        ?string $permission,
        ?string $ability = null,
        ?string $subject = null,
        ?Authenticatable $actor = null,
        ?Model $record = null,
    ): bool {
        $actor ??= Auth::user();

        if (! $actor instanceof Authenticatable) {
            return false;
        }

        $permission = trim((string) $permission);

        if ($permission === '') {
            if (blank($ability) || blank($subject)) {
                return false;
            }

            $permission = Str::studly($ability).':'.class_basename($subject);
        }

        return PaymentTenancy::allows($record, (string) $ability, $actor)
            && Gate::forUser($actor)->allows($permission);
    }
}
