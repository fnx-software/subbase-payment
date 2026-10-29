<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Nafiswatsiq\Subbase\Models\LicenseKey;
use Nafiswatsiq\Subbase\Models\LicenseOffer;
use Nafiswatsiq\Subbase\Models\LicenseOrder;
use Nafiswatsiq\Subbase\Models\LicenseProvider;
use Nafiswatsiq\Subbase\Services\LicenseInventoryService;
use Nafiswatsiq\Subbase\Services\LicenseOrderService;
use Nafiswatsiq\Subbase\Services\LicenseProviderDashboard;
use Nafiswatsiq\Subbase\Services\LicenseProviderService;
use Nafiswatsiq\Subbase\Services\LicenseRedemptionService;
use Nafiswatsiq\Subbase\Services\LicenseResaleService;
use Nafiswatsiq\SubbasePayment\PaymentManager;
use Nafiswatsiq\SubbasePayment\Services\CheckoutBillableManager;
use Nafiswatsiq\SubbasePayment\Services\LicenseCheckoutService;

final class LicenseMarketplaceController extends Controller
{
    public function offers(): JsonResponse
    {
        $offers = LicenseOffer::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with('product:id,sku,name')
            ->orderBy('sku')
            ->get();

        return response()->json(['offers' => $offers->map(static fn (LicenseOffer $offer): array => [
            'id' => $offer->getKey(),
            'sku' => $offer->sku,
            'product' => $offer->product->sku,
            'name' => $offer->product->name,
            'currency' => $offer->currency,
            'retail_price' => $offer->retail_price,
            'minimum_wholesale_quantity' => $offer->wholesale_minimum,
            'term' => ['count' => $offer->term_count, 'unit' => $offer->term_unit],
            // The wholesale price is provider-specific privileged information.
        ])->all()]);
    }

    public function apply(Request $request, CheckoutBillableManager $billables, LicenseProviderService $providers): JsonResponse
    {
        $validated = $request->validate(['business_name' => ['required', 'string', 'max:150']]);
        $provider = $providers->apply($billables->resolve($request), $request->user(), $validated['business_name']);

        return response()->json(['provider_id' => $provider->getKey(), 'status' => $provider->status], 201);
    }

    public function wholesalePrices(Request $request, CheckoutBillableManager $billables): JsonResponse
    {
        $owner = $billables->resolve($request);
        $provider = LicenseProvider::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())->firstOrFail();

        if ($provider->approved() === false || Gate::forUser($request->user())->denies('purchaseWholesaleLicenses', $provider)) {
            throw new AuthorizationException('Only approved resellers can view wholesale pricing.');
        }

        $offers = LicenseOffer::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->orderBy('sku')
            ->get(['id', 'sku', 'currency', 'retail_price', 'wholesale_price', 'wholesale_minimum']);

        return response()->json(['provider_id' => $provider->getKey(), 'offers' => $offers]);
    }

    public function dashboard(Request $request, CheckoutBillableManager $billables, LicenseProviderDashboard $dashboard): JsonResponse
    {
        $owner = $billables->resolve($request);
        $provider = LicenseProvider::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->firstOrFail();

        return response()->json($dashboard->summary($provider, $request->user()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function buy(
        Request $request,
        CheckoutBillableManager $billables,
        LicenseOrderService $orders,
        LicenseCheckoutService $checkout,
        PaymentManager $payments,
    ) {
        $input = $request->validate([
            'offer_id' => ['required', 'integer', 'min:1'],
            'provider_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:80'],
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $billable = $billables->resolve($request);
        $provider = isset($input['provider_id']) ? LicenseProvider::query()->findOrFail($input['provider_id']) : null;
        $offer = LicenseOffer::query()->findOrFail($input['offer_id']);
        $order = $orders->place(
            $offer, $billable, $request->user(), (int) $input['quantity'], $provider,
            $input['idempotency_key'],
        );

        try {
            $result = $checkout->begin(
                $order, $payments, $input['name'], $input['email'], (string) $request->user()->getAuthIdentifier(),
            );
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'license' => 'Checkout is unavailable or already started. Contact support before retrying this order.',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'order_id' => $order->public_id,
                'approval_url' => $result->approvalUrl,
                'status' => 'pending',
            ], 201)->header('Cache-Control', 'no-store');
        }

        return redirect()->away($result->approvalUrl);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        $record = LicenseOrder::query()->where('public_id', $order)->firstOrFail();
        Gate::forUser($request->user())->authorize('viewLicenseOrder', $record);

        return response()->json([
            'order_id' => $record->public_id,
            'status' => $record->status,
            'quantity' => $record->quantity,
            'currency' => $record->currency,
            'total' => $record->total_amount,
            'issued_keys' => $record->keys()->count(),
        ])->header('Cache-Control', 'no-store');
    }

    public function reveal(Request $request, string $order, LicenseInventoryService $inventory): JsonResponse
    {
        $record = LicenseOrder::query()->where('public_id', $order)->firstOrFail();

        return response()->json(['license_keys' => $inventory->reveal($record, $request->user())])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function redeem(Request $request, CheckoutBillableManager $billables, LicenseRedemptionService $licenses): JsonResponse
    {
        $data = $request->validate(['license_key' => ['required', 'string', 'max:80']]);
        $key = $licenses->redeem($data['license_key'], $billables->resolve($request), $request->user());

        return response()->json([
            'status' => 'activated',
            'license_id' => $key->getKey(),
            'subscription_id' => $key->subscription_id,
            'activated_at' => $key->redeemed_at,
        ])->header('Cache-Control', 'no-store');
    }

    public function resale(Request $request, int $key, LicenseResaleService $resales): JsonResponse
    {
        $validated = $request->validate([
            'sale_price' => ['required', 'string', 'max:24'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $record = LicenseKey::query()->findOrFail($key);
        $provider = $record->provider()->firstOrFail();

        $result = $resales->record($record, $provider, $request->user(), $validated['sale_price'], $validated['reference'] ?? null);

        return response()->json([
            'sale_id' => $result['sale']->getKey(),
            'currency' => $result['currency'],
            'gross_margin' => $result['gross_margin'],
        ], 201);
    }
}
