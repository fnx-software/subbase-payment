<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Policies;

use Illuminate\Contracts\Auth\Authenticatable as AuthUser;
use Nafiswatsiq\SubbasePayment\Models\SubscriptionPayment;
use Nafiswatsiq\SubbasePayment\Support\SubbasePaymentPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionPaymentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return SubbasePaymentPermission::allows(config('subbase-payment.permissions.subscription_payment'), 'viewAny', SubscriptionPayment::class, $authUser);
    }

    public function view(AuthUser $authUser, SubscriptionPayment $subscriptionPayment): bool
    {
        return SubbasePaymentPermission::allows(config('subbase-payment.permissions.subscription_payment'), 'view', SubscriptionPayment::class, $authUser, $subscriptionPayment);
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, SubscriptionPayment $subscriptionPayment): bool
    {
        return SubbasePaymentPermission::allows(config('subbase-payment.permissions.subscription_payment'), 'restore', SubscriptionPayment::class, $authUser);
    }

    public function forceDelete(AuthUser $authUser, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return SubbasePaymentPermission::allows(config('subbase-payment.permissions.subscription_payment'), 'restoreAny', SubscriptionPayment::class, $authUser);
    }

    public function replicate(AuthUser $authUser, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}