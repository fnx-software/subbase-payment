<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Activates an authorized checkout's plan only within a verified settlement
 * transaction. The caller must hold a lock on the payment row.
 */
final class PaidSubscriptionActivator
{
    public function activate(object $payment): string
    {
        $metadata = is_string($payment->metadata ?? null)
            ? (json_decode($payment->metadata, true) ?: [])
            : (array) ($payment->metadata ?? []);

        $type = (string) ($payment->billable_type ?? $metadata['billable_type'] ?? '');
        $id = (string) ($payment->billable_id ?? $metadata['billable_id'] ?? '');
        if ($type === '' && ! empty($metadata['user_id'])) {
            $type = 'user';
            $id = (string) $metadata['user_id'];
        }

        if ($type === '' || $id === '' || strlen($id) > 191) {
            throw new RuntimeException('Verified checkout lacks a valid subscription owner.');
        }

        // Explicitly configured model classes only. Never instantiate arbitrary
        // PHP classes from transaction metadata or client-supplied morph strings.
        $models = Config::get('subbase-payment.checkout.billable_models', []);
        $modelClass = null;

        foreach ((array) $models as $alias => $allowed) {
            if (! is_string($allowed) || ! is_subclass_of($allowed, Model::class)) {
                continue;
            }

            $probe = new $allowed();
            if ($type === $allowed || $type === (string) $alias || $type === $probe->getMorphClass()) {
                $modelClass = $allowed;

                break;
            }
        }

        if (! $modelClass) {
            throw new RuntimeException('Subscription owner model is not allowlisted.');
        }

        $owner = $modelClass::query()->whereKey($id)->lockForUpdate()->first();

        if (! $owner || ! method_exists($owner, 'planSubscriptions')
            || ! method_exists($owner, 'newPlanSubscription')
        ) {
            throw new RuntimeException('Subscription owner is unavailable or not subscribable.');
        }

        $planClass = Config::get('subbase.models.plan', \Nafiswatsiq\Subbase\Models\Plan::class);
        if (! is_string($planClass) || ! is_subclass_of($planClass, Model::class)) {
            throw new RuntimeException('Configured subscription plan model is invalid.');
        }

        $planId = $metadata['plan_id'] ?? null;
        if ($planId === null || $planId === '') {
            throw new RuntimeException('Verified checkout lacks a subscription plan.');
        }

        $plan = $planClass::query()->find($planId);
        if (! $plan) {
            throw new RuntimeException('The purchased subscription plan no longer exists.');
        }

        $name = (string) Config::get('subbase-payment.checkout.subscription_name', 'default');
        if ($name === '' || strlen($name) > 100) {
            throw new RuntimeException('Subscription name is invalid.');
        }

        $subscription = $owner->planSubscriptions()
            ->where('slug', $name)
            ->lockForUpdate()
            ->first();

        if ($subscription) {
            if ((string) $subscription->plan_id === (string) $plan->getKey()) {
                $subscription->renew();
            } else {
                $subscription->changePlan($plan);
            }
        } else {
            $subscription = $owner->newPlanSubscription($name, $plan);
        }

        return (string) $subscription->getKey();
    }
}
