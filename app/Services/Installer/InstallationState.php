<?php

namespace App\Services\Installer;

use Closure;
use RuntimeException;
use Throwable;

class InstallationState
{
    public const STEPS = [
        'license',
        'configuration',
        'migrations',
        'seeders',
        'settings',
        'administrator',
        'finalize',
    ];

    private string $directory;

    private string $statePath;

    private string $lockPath;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?: storage_path('app');
        $this->statePath = $this->directory.'/installing.json';
        $this->lockPath = $this->directory.'/installing.lock';
    }

    public function start(string $token): array
    {
        return $this->withLock(function () use ($token) {
            $state = $this->readState();

            if ($state !== null) {
                $this->assertAuthorized($state, $token);

                return $this->withProgress($state);
            }

            $state = [
                'version' => 1,
                'token_hash' => $this->tokenHash($token),
                'completed' => [],
                'current' => null,
                'failed_step' => null,
                'started_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ];

            $this->writeState($state);

            return $this->withProgress($state);
        });
    }

    public function status(string $token): array
    {
        try {
            return $this->withLock(function () use ($token) {
                $state = $this->readAuthorizedState($token);

                return $this->withProgress($state);
            });
        } catch (RuntimeException $exception) {
            if ($exception->getCode() !== 423) {
                throw $exception;
            }

            // State writes are atomic, so status remains safe to read while another request owns the lock.
            return $this->withProgress($this->readAuthorizedState($token));
        }
    }

    public function owns(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $this->status($token);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function isActive(): bool
    {
        try {
            return $this->withLock(fn () => $this->readState() !== null);
        } catch (RuntimeException $exception) {
            if ($exception->getCode() === 423) {
                return true;
            }

            throw $exception;
        }
    }

    public function advance(string $token, Closure $executor): array
    {
        return $this->withLock(function () use ($token, $executor) {
            $state = $this->readAuthorizedState($token);
            $nextStep = $this->nextStep($state);

            if ($nextStep === null) {
                return $this->withProgress($state);
            }

            $state['current'] = $nextStep;
            $state['failed_step'] = null;
            $state['updated_at'] = gmdate('c');
            $this->writeState($state);

            try {
                $executor($nextStep);
            } catch (Throwable $exception) {
                $state['current'] = null;
                $state['failed_step'] = $nextStep;
                $state['updated_at'] = gmdate('c');
                $this->writeState($state);

                throw $exception;
            }

            $state['completed'][] = $nextStep;
            $state['completed'] = array_values(array_unique($state['completed']));
            $state['current'] = null;
            $state['failed_step'] = null;
            $state['updated_at'] = gmdate('c');
            $this->writeState($state);

            return $this->withProgress($state);
        });
    }

    public function clear(string $token): void
    {
        $this->withLock(function () use ($token) {
            $state = $this->readState();

            if ($state === null) {
                return;
            }

            $this->assertAuthorized($state, $token);

            $this->removeState();
        });
    }

    public function restart(string $token): void
    {
        $this->withLock(function () use ($token) {
            $state = $this->readAuthorizedState($token);
            if (empty($state['failed_step'])) {
                throw new RuntimeException('Only a failed installation can be restarted.', 409);
            }

            $this->removeState();
        });
    }

    // Server-side recovery only; never expose an unauthenticated HTTP reset.
    public function reset(): void
    {
        $this->withLock(fn () => $this->removeState());
    }

    private function removeState(): void
    {
        if (is_file($this->statePath) && !unlink($this->statePath)) {
            throw new RuntimeException('Could not clear the installation state.');
        }
    }

    private function nextStep(array $state): ?string
    {
        $completed = $state['completed'] ?? [];

        foreach (self::STEPS as $step) {
            if (!in_array($step, $completed, true)) {
                return $step;
            }
        }

        return null;
    }

    private function withProgress(array $state): array
    {
        $state['next_step'] = $this->nextStep($state);
        $state['finished'] = $state['next_step'] === null;

        unset($state['token_hash']);

        return $state;
    }

    private function readAuthorizedState(string $token): array
    {
        $state = $this->readState();

        if ($state === null) {
            throw new RuntimeException('No active installation was found.', 404);
        }

        $this->assertAuthorized($state, $token);

        return $state;
    }

    private function assertAuthorized(array $state, string $token): void
    {
        $expectedHash = $state['token_hash'] ?? '';

        if ($token === '' || $expectedHash === '' || !hash_equals($expectedHash, $this->tokenHash($token))) {
            throw new RuntimeException('Another installation session is already active.', 403);
        }
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function readState(): ?array
    {
        if (!is_file($this->statePath)) {
            return null;
        }

        $contents = file_get_contents($this->statePath);

        if ($contents === false || $contents === '') {
            throw new RuntimeException('The installation state could not be read.');
        }

        $state = json_decode($contents, true);

        if (!is_array($state)) {
            throw new RuntimeException('The installation state is invalid.');
        }

        return $state;
    }

    private function writeState(array $state): void
    {
        $this->ensureDirectoryExists();

        $temporaryPath = $this->statePath.'.'.bin2hex(random_bytes(6)).'.tmp';
        $contents = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                throw new RuntimeException('The installation state could not be written.');
            }

            @chmod($temporaryPath, 0600);

            if (!rename($temporaryPath, $this->statePath)) {
                throw new RuntimeException('The installation state could not be saved.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function withLock(Closure $callback): mixed
    {
        $this->ensureDirectoryExists();

        $handle = fopen($this->lockPath, 'c+');

        if ($handle === false) {
            throw new RuntimeException('The installation lock could not be opened.');
        }

        @chmod($this->lockPath, 0600);

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new RuntimeException('An installation step is already running.', 423);
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('The installation state directory could not be created.');
        }
    }
}
