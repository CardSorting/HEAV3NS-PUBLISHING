<?php

namespace App\Http\Controllers;

use App\Model\ConversionAttribution;
use App\Model\ConversionDelivery;
use App\Services\ConversionTracking\ConversionAttributionService;
use App\Services\ConversionTracking\ConversionConfiguration;
use App\Services\ConversionTracking\ConversionEligibility;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversionTrackingController extends Controller
{
    public function __construct(
        protected ConversionConfiguration $configuration,
        protected ConversionAttributionService $attribution,
    ) {}

    public function consent(Request $request): JsonResponse
    {
        $granted = $request->input('granted');
        $clickId = $request->input('click_id');
        if (!$this->validBoolean($granted)) {
            return $this->json(['message' => 'The tracking request is invalid.'], 422);
        }
        $granted = (bool) $granted;
        if ($granted && (($clickId !== null && !is_string($clickId)) || !ConversionAttributionService::validClickId($clickId))) {
            return $this->json(['message' => 'The tracking request is invalid.'], 422);
        }
        // A malformed campaign parameter must never prevent withdrawing consent.
        $clickId = $granted ? $clickId : null;

        try {
            $this->configuration->refresh();
            $token = $this->attribution->consent($request, $granted, $clickId);
            if ($token !== null) {
                Cookie::queue(Cookie::make(
                    ConversionAttributionService::COOKIE,
                    $token,
                    $this->configuration->attributionDays() * 1440,
                    '/',
                    null,
                    $request->isSecure() || (bool) config('session.secure'),
                    true,
                    false,
                    'lax',
                ));
            } else {
                Cookie::queue(Cookie::forget(ConversionAttributionService::COOKIE, '/'));
            }

            return $this->json(['enabled' => $this->configuration->enabled(), 'consented' => $token !== null]);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);

            return $this->json(['message' => 'Tracking preferences could not be saved.'], 503);
        }
    }

    public function pending(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return $this->json(['message' => 'Authentication is required.'], 401);
        }

        try {
            $this->configuration->refresh();
            if (!$this->configuration->enabled() || $this->configuration->method() !== 'browser'
                || $this->configuration->testMode()) {
                return $this->json(['events' => []]);
            }

            $events = $this->atomic(function () use ($request): array {
                // Hold the consent-group lock until claims are written so revocation and claims serialize.
                $current = $this->attribution->current($request);
                if (!$current) {
                    return [];
                }

                $userId = (int) $request->user()->getAuthIdentifier();
                $events = [];
                foreach ($this->pendingForUser($userId, (string) $current->token_hash) as $delivery) {
                    $reason = $this->ineligibleReason($delivery, $current, $userId);
                    if ($reason !== null) {
                        $delivery->status = 'skipped';
                        $delivery->last_error = $reason;
                        $this->saveDelivery($delivery);
                        continue;
                    }

                    $claimToken = bin2hex(random_bytes(32));
                    $delivery->status = 'browser_claimed';
                    $delivery->browser_claim_token = hash('sha256', $claimToken);
                    $delivery->attempts = (int) $delivery->attempts + 1;
                    $delivery->last_error = null;
                    $this->saveDelivery($delivery);
                    $events[] = array_merge($delivery->payload(), [
                        'id' => $delivery->getKey(),
                        'code' => $this->configuration->browserCode(),
                        'claim_token' => $claimToken,
                        'test_mode' => false,
                    ]);
                    if (count($events) === 5) {
                        break;
                    }
                }

                return $events;
            });

            return $this->json(['events' => $events]);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);

            return $this->json(['message' => 'Conversion tracking is temporarily unavailable.'], 503);
        }
    }

    public function acknowledge(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return $this->json(['message' => 'Authentication is required.'], 401);
        }

        $id = $request->input('id');
        $claimToken = $request->input('claim_token');
        $success = $request->input('success');
        if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id < 1
            || !is_string($claimToken) || preg_match('/\A[a-f0-9]{64}\z/D', $claimToken) !== 1
            || !$this->validBoolean($success)) {
            return $this->json(['message' => 'The tracking acknowledgement is invalid.'], 422);
        }

        try {
            $found = $this->atomic(function () use ($request, $id, $claimToken, $success): bool {
                $delivery = $this->deliveryForUser((int) $id, (int) $request->user()->getAuthIdentifier());
                if (!$delivery || !is_string($delivery->browser_claim_token)
                    || !hash_equals($delivery->browser_claim_token, hash('sha256', $claimToken))) {
                    return false;
                }

                // A repeated acknowledgement is harmless and never makes the snippet eligible again.
                if ($delivery->status === 'browser_claimed') {
                    $delivery->status = $success ? 'browser_dispatched' : 'failed';
                    $delivery->sent_at = $success ? Carbon::now() : null;
                    $delivery->last_error = $success ? null : 'The browser could not dispatch the conversion snippet.';
                    $this->saveDelivery($delivery);
                }

                return true;
            });

            return $found ? $this->json(['acknowledged' => true]) : $this->json(['message' => 'Conversion not found.'], 404);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);

            return $this->json(['message' => 'Conversion tracking is temporarily unavailable.'], 503);
        }
    }

    protected function ineligibleReason(ConversionDelivery $delivery, ConversionAttribution $current, int $userId): ?string
    {
        if ($delivery->test_mode || $delivery->method !== 'browser'
            || !hash_equals($this->configuration->fingerprint(), (string) $delivery->config_hash)) {
            return 'Tracking configuration changed before browser delivery.';
        }

        $transaction = $delivery->transaction;
        if (!$transaction || (int) $transaction->sender_user_id !== $userId
            || !ConversionEligibility::isPaidPurchase($transaction) || !$transaction->purchase_completed_at) {
            return 'The purchase is no longer eligible.';
        }

        $attribution = $delivery->attribution;
        if (!$attribution || !$attribution->permitsReporting() || !$current->permitsReporting()
            || (int) $attribution->user_id !== $userId || (int) $current->user_id !== $userId
            || !hash_equals((string) $current->token_hash, (string) $attribution->token_hash)
            || !hash_equals($this->configuration->fingerprint(), (string) $attribution->config_hash)) {
            return 'Marketing consent or the original browser attribution is no longer available.';
        }

        return null;
    }

    protected function pendingForUser(int $userId, string $tokenHash): Collection
    {
        return ConversionDelivery::query()->with(['transaction', 'attribution'])->where('method', 'browser')
            ->where('status', 'pending')->whereHas('transaction', fn ($query) => $query->where('sender_user_id', $userId))
            ->whereHas('attribution', fn ($query) => $query->where('token_hash', $tokenHash))
            ->orderBy('id')->limit(25)->lockForUpdate()->get();
    }

    protected function deliveryForUser(int $id, int $userId): ?ConversionDelivery
    {
        return ConversionDelivery::query()->whereKey($id)->where('method', 'browser')
            ->whereHas('transaction', fn ($query) => $query->where('sender_user_id', $userId))->lockForUpdate()->first();
    }

    protected function saveDelivery(ConversionDelivery $delivery): void
    {
        $delivery->save();
    }

    protected function atomic(callable $callback): mixed
    {
        return DB::transaction($callback, 3);
    }

    protected function validBoolean(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    protected function json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store, private']);
    }

    protected function reportFailure(Throwable $exception): void
    {
        // Never log a cookie token, click ID, provider snippet or exception message containing SQL data.
        try {
            Log::warning('A conversion tracking browser request failed.', ['exception_type' => get_class($exception)]);
        } catch (Throwable) {
            // A logging problem must not break the non-essential tracking response.
        }
    }
}
