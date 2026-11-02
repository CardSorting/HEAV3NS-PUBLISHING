<?php

namespace App\Services\Media;

use App\Model\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProfileAssetService
{
    public static function isManagedPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('~\Ausers/(avatar|cover)/[a-zA-Z0-9_-]+\.[a-zA-Z0-9]{1,10}\z~', $path) === 1;
    }

    public function replace(User $user, string $type, string $diskName, string $path, string $contents): User
    {
        $this->validateType($type);
        if (!self::isManagedPath($path) || !str_starts_with($path, "users/{$type}/")) {
            throw new \InvalidArgumentException('Invalid profile asset path.');
        }

        $disk = Storage::disk($diskName);
        $stored = false;
        try {
            return DB::transaction(function () use ($user, $type, $disk, $path, $contents, &$stored): User {
                $locked = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->getRawOriginal($type) === $path) {
                    throw new RuntimeException('A replacement profile image must use a new storage path.');
                }
                // Preserve the old profile and its file if storing the replacement fails.
                $stored = true;
                if (!$disk->put($path, $contents, 'public')) {
                    throw new RuntimeException('The profile image could not be stored.');
                }
                $locked->update([$type => $path]);
                if ($locked->getRawOriginal($type) !== $path) {
                    throw new RuntimeException('The profile image could not be saved.');
                }
                return $locked;
            });
        } catch (Throwable $exception) {
            if ($stored) {
                try {
                    $disk->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            throw $exception;
        }
    }

    public function remove(User $user, string $type): User
    {
        $this->validateType($type);
        return DB::transaction(function () use ($user, $type): User {
            $locked = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $locked->update([$type => '']);
            return $locked;
        });
    }

    private function validateType(string $type): void
    {
        if (!in_array($type, ['avatar', 'cover'], true)) {
            throw new \InvalidArgumentException('Invalid profile asset type.');
        }
    }
}
