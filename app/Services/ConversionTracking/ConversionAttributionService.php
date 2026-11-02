<?php

namespace App\Services\ConversionTracking;

use App\Model\ConversionAttribution;
use App\Model\ConversionDelivery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConversionAttributionService
{
    public const COOKIE = 'conversion_attribution';

    public function __construct(protected ConversionConfiguration $configuration) {}

    public function current(Request $request): ?ConversionAttribution
    {
        $token = $this->token($request);
        if (!$this->configuration->enabled() || $token === null) {
            return null;
        }

        return $this->atomic(function () use ($request, $token): ?ConversionAttribution {
            $hash = hash('sha256', $token);
            $latest = $this->latest($hash);
            $userId = $this->userId($request);
            if (!$latest || !$latest->permitsReporting() || $this->hasDifferentOwner($hash, $userId)
                || !hash_equals($this->configuration->fingerprint(), (string) $latest->config_hash)) {
                return null;
            }

            $current = $this->matching($hash, $this->configuration->fingerprint());
            if (!$current || !$current->permitsReporting()) {
                return null;
            }

            if ($userId !== null && $current->user_id === null) {
                $this->bindGroup($hash, $userId);
                $current->user_id = $userId;
            }

            return $current;
        });
    }

    /**
     * Returns an opaque cookie token, never a click ID. The controller encrypts the cookie normally.
     * A new campaign produces a new row so an existing purchase's attribution never changes.
     */
    public function consent(Request $request, bool $granted, ?string $clickId = null): ?string
    {
        if ($granted && !self::validClickId($clickId)) {
            throw new InvalidArgumentException('The tracking click identifier is invalid.');
        }
        if ((!$granted || !$this->configuration->enabled()) && $this->token($request) === null) {
            return null;
        }

        return $this->atomic(function () use ($request, $granted, $clickId): ?string {
            $token = $this->token($request);
            $hash = $token !== null ? hash('sha256', $token) : null;
            $latest = $hash !== null ? $this->latest($hash) : null;

            // Revocation must work after administrators disable the integration or cookie notice.
            if (!$granted || !$this->configuration->enabled()) {
                if ($hash !== null) {
                    $this->revokeGroup($hash);
                }

                return null;
            }

            $userId = $this->userId($request);
            if ($latest && (!$latest->permitsReporting() || $this->hasDifferentOwner($hash, $userId))) {
                // A different account or expired consent never inherits the earlier account's click.
                $this->revokeGroup($hash);
                $latest = null;
                $token = null;
            }

            if (!$latest) {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
            } elseif ($userId !== null) {
                $this->bindGroup($hash, $userId);
            }

            $fingerprint = $this->configuration->fingerprint();
            $current = $latest && hash_equals($fingerprint, (string) $latest->config_hash)
                ? $this->matching($hash, $fingerprint) : null;
            $clickId = $clickId !== '' ? $clickId : null;
            if ($current && $current->permitsReporting()
                && ($clickId === null || $clickId === $current->click_id)) {
                // Visiting another page without a new campaign does not extend attribution lifetime.
                return $token;
            }

            $this->createSnapshot([
                'token_hash' => $hash,
                'user_id' => $userId,
                'config_hash' => $fingerprint,
                'click_id' => $clickId,
                'consented_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays($this->configuration->attributionDays()),
            ]);

            return $token;
        });
    }

    public static function validClickId(?string $clickId): bool
    {
        return $clickId === null || (strlen($clickId) <= 512 && preg_match('/\A[\x20-\x7E]*\z/D', $clickId) === 1);
    }

    protected function token(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1 ? $token : null;
    }

    protected function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id !== null ? (int) $id : null;
    }

    protected function atomic(callable $callback): mixed
    {
        return DB::transaction($callback, 3);
    }

    protected function latest(string $hash): ?ConversionAttribution
    {
        return ConversionAttribution::query()->where('token_hash', $hash)->orderByDesc('id')->lockForUpdate()->first();
    }

    protected function matching(string $hash, string $fingerprint): ?ConversionAttribution
    {
        return ConversionAttribution::query()->where('token_hash', $hash)->where('config_hash', $fingerprint)
            ->whereNull('revoked_at')->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id')->lockForUpdate()->first();
    }

    protected function hasDifferentOwner(string $hash, ?int $userId): bool
    {
        $query = ConversionAttribution::query()->where('token_hash', $hash)->whereNotNull('user_id');
        if ($userId !== null) {
            $query->where('user_id', '!=', $userId);
        }

        return $query->exists();
    }

    protected function bindGroup(string $hash, int $userId): void
    {
        ConversionAttribution::query()->where('token_hash', $hash)->whereNull('user_id')->update(['user_id' => $userId]);
    }

    protected function createSnapshot(array $attributes): ConversionAttribution
    {
        return ConversionAttribution::query()->create($attributes);
    }

    protected function revokeGroup(string $hash): void
    {
        ConversionAttribution::query()->where('token_hash', $hash)->whereNull('revoked_at')->update(['revoked_at' => Carbon::now()]);
        ConversionDelivery::query()->whereIn('attribution_id', ConversionAttribution::query()->select('id')->where('token_hash', $hash))
            ->whereIn('status', ['pending', 'delivering', 'browser_claimed'])->update([
                'status' => 'skipped',
                'last_error' => 'Marketing consent was withdrawn or changed to another account.',
                'browser_claim_token' => null,
                'leased_until' => null,
                'updated_at' => Carbon::now(),
            ]);
    }
}
