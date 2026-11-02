<?php

namespace App\Services;

use App\Model\Post;
use App\Model\Stream;
use App\Model\User;
use App\Providers\StreamsServiceProvider;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SuspendedActivityService
{
    public function pause(User $user): void
    {
        Post::query()->where('user_id', $user->id)
            ->where('status', Post::APPROVED_STATUS)->where('release_date', '>', now())
            ->update(['status' => Post::PENDING_STATUS]);

        $streams = Stream::query()->where('user_id', $user->id)->where('status', Stream::IN_PROGRESS_STATUS)->get();
        // End app access immediately, without running provider calls in stream observers.
        Stream::query()->whereKey($streams->modelKeys())->update(['status' => Stream::ENDED_STATUS, 'ended_at' => now()]);

        DB::afterCommit(function () use ($streams): void {
            foreach ($streams as $stream) {
                try {
                    if ($this->remoteStop($stream)) {
                        continue;
                    }
                } catch (Throwable $exception) {
                    // The ban is committed; a provider failure cannot undo it.
                }
                Log::warning('Suspended stream could not be stopped at the provider.', ['stream_id' => $stream->id, 'driver' => $stream->driver]);
            }
        });
    }

    protected function remoteStop(Stream $stream): bool
    {
        if ((int) $stream->driver === Stream::PUSHR_DRIVER) {
            $key = trim((string) getSetting('streams.pushr_key'));
            if ($key === '' || !filled($stream->pushr_id)) {
                return false;
            }

            $response = Http::connectTimeout(3)->timeout(10)->acceptJson()->asForm()
                ->withHeaders(['APIKEY' => $key])
                ->post(StreamsServiceProvider::PUSHR_API_ENDPOINT.'/destroy', ['id' => $stream->pushr_id]);

            return $response->successful() && $response->json('status') === 'success';
        }

        if ((int) $stream->driver !== Stream::LIVEKIT_DRIVER) {
            return false;
        }

        $key = trim((string) getSetting('streams.livekit_api_key'));
        $secret = (string) getSetting('streams.livekit_api_secret');
        $url = trim((string) getSetting('streams.livekit_ws_url'));
        $url = preg_replace(['#^wss://#i', '#^ws://#i'], ['https://', 'http://'], $url);
        $parts = parse_url($url);
        if ($key === '' || trim($secret) === '' || !$parts || empty($parts['host'])
            || !in_array($parts['scheme'] ?? null, ['https', 'http'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $now = now()->timestamp;
        $token = JWT::encode([
            'iss' => $key, 'iat' => $now, 'nbf' => $now, 'exp' => $now + 60,
            'video' => ['roomCreate' => true],
        ], $secret, 'HS256');
        $response = Http::connectTimeout(3)->timeout(10)->acceptJson()->asJson()->withToken($token)
            ->post(rtrim($url, '/').'/twirp/livekit.RoomService/DeleteRoom', ['room' => 'stream_'.$stream->id]);

        return ($response->successful() && is_array($response->json()) && !$response->json('code'))
            || ($response->status() === 404 && $response->json('code') === 'not_found');
    }
}
