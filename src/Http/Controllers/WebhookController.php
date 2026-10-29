<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nafiswatsiq\SubbasePayment\Exceptions\InvalidWebhookSignatureException;
use Nafiswatsiq\SubbasePayment\Exceptions\PaymentConfigurationException;
use Nafiswatsiq\SubbasePayment\Models\PaymentWebhookLog;
use Nafiswatsiq\SubbasePayment\PaymentManager;
use Nafiswatsiq\SubbasePayment\Services\VerifiedPaymentProcessor;
use UnexpectedValueException;

final class WebhookController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, VerifiedPaymentProcessor $processor): JsonResponse
    {
        $driver = (string) config('subbase-payment.driver', 'unknown');
        $payload = $request->json()->all();
        $payload['_raw_body'] = $request->getContent();
        $headers = collect($request->headers->all())
            ->mapWithKeys(fn (array $values, string $key): array => [strtolower($key) => $values[0] ?? ''])
            ->all();

        $eventId = $payload['id'] ?? $payload['event_id'] ?? null;
        $eventType = $payload['type'] ?? $payload['event_type'] ?? null;

        // Never store a signed raw request, a provider secret, or customer PII.
        $log = PaymentWebhookLog::create([
            'gateway_driver' => $driver,
            'event_id' => is_string($eventId) ? mb_substr($eventId, 0, 190) : null,
            'event_type' => is_string($eventType) ? mb_substr($eventType, 0, 190) : null,
            'status' => 'received',
            'payload' => [
                'event_id' => is_string($eventId) ? mb_substr($eventId, 0, 190) : null,
                'event_type' => is_string($eventType) ? mb_substr($eventType, 0, 190) : null,
            ],
            'headers' => [],
        ]);

        try {
            // Each driver validates its provider-specific signature before
            // returning a trustworthy transaction and amount/currency claim.
            $result = $payments->driver()->handleWebhook($payload, $headers);
            $outcome = $processor->handle($driver, $result);
        } catch (InvalidWebhookSignatureException) {
            $log->update(['status' => 'failed', 'error_message' => 'Invalid provider signature.']);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        } catch (PaymentConfigurationException) {
            $log->update(['status' => 'failed', 'error_message' => 'Payment gateway configuration error.']);

            return response()->json(['message' => 'Gateway unavailable.'], 503);
        } catch (UnexpectedValueException) {
            $log->update(['status' => 'failed', 'error_message' => 'Payment amount or currency mismatch.']);

            return response()->json(['message' => 'Payment reconciliation failed.'], 422);
        } catch (\Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => 'Payment settlement failed.']);
            report($exception);

            // Transient failures must be retried by the provider.
            return response()->json(['message' => 'Processing unavailable.'], 503);
        }

        if ($outcome === 'missing') {
            $log->update(['status' => 'failed', 'error_message' => 'Matching checkout intent not found.']);

            return response()->json(['message' => 'Matching payment is not available yet.'], 503);
        }

        $log->update(['status' => match ($outcome) {
            'processed' => 'verified',
            'duplicate' => 'duplicate',
            default => 'ignored',
        }]);

        return response()->json(['ok' => true, 'status' => $outcome]);
    }
}
