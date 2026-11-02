<?php

namespace App\Services\Queue;

use App\Jobs\QueueHealthCheckJob;
use App\Model\QueueHealthCheck;
use App\Providers\InstallerServiceProvider;
use App\Support\PhpRuntimeRequirements;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

class QueueRuntimeService
{
    public const MODE_SYNC = 'sync';
    public const MODE_DEFERRED = 'deferred';
    public const MODE_BACKGROUND = 'background';
    public const MODE_WORKER = 'worker';
    public const WORKER_CONNECTIONS = ['database', 'redis', 'sqs'];
    public const TEST_CHECK_TIMEOUT_SECONDS = 30;
    public const WORKER_CHECK_TIMEOUT_SECONDS = 90;
    public const WORKER_CHECK_FRESH_SECONDS = 300;

    private ?array $backgroundRuntimeResult = null;

    public static function normalizeMode(string $mode): string
    {
        return in_array($mode, [self::MODE_SYNC, self::MODE_DEFERRED, self::MODE_BACKGROUND, self::MODE_WORKER], true)
            ? $mode
            : self::MODE_SYNC;
    }

    public static function normalizeWorkerConnection(string $connection): string
    {
        return in_array($connection, self::WORKER_CONNECTIONS, true) ? $connection : 'database';
    }

    public static function normalizeQueueName(string $queueName): string
    {
        $queueName = trim($queueName);

        if ($queueName === '' || !preg_match('/^[A-Za-z0-9_.:{}-]+$/', $queueName)) {
            return 'default';
        }

        return $queueName;
    }

    public static function connectionFor(string $mode, string $workerConnection = 'database'): string
    {
        $mode = self::normalizeMode($mode);

        return $mode === self::MODE_WORKER
            ? self::normalizeWorkerConnection($workerConnection)
            : $mode;
    }

    public function mode(): string
    {
        return self::normalizeMode((string) config('queue.runtime_mode', config('queue.default', self::MODE_SYNC)));
    }

    public function connection(): string
    {
        return self::connectionFor($this->mode(), (string) config('queue.default', 'database'));
    }

    public function queueName(): string
    {
        $connection = $this->connection();

        return self::normalizeQueueName((string) config("queue.connections.{$connection}.queue", 'default'));
    }

    public function usesWorker(): bool
    {
        return $this->mode() === self::MODE_WORKER;
    }

    public function backgroundSupported(): bool
    {
        return $this->backgroundRuntime()['supported'];
    }

    public static function phpBinary(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }

    public function backgroundRuntime(): array
    {
        if ($this->backgroundRuntimeResult !== null) {
            return $this->backgroundRuntimeResult;
        }

        $runtime = [
            'supported' => false,
            'status' => 'unavailable',
            'php_version' => null,
            'missing_extensions' => [],
        ];

        if (!function_exists('proc_open') || !is_file(base_path('artisan')) || !is_readable(base_path('artisan'))) {
            return $this->backgroundRuntimeResult = $runtime;
        }

        $phpBinary = (new PhpExecutableFinder)->find(false);
        if (!$phpBinary || (DIRECTORY_SEPARATOR !== '\\' && !is_executable($phpBinary))) {
            return $this->backgroundRuntimeResult = $runtime;
        }

        try {
            // Inspect the same executable Laravel uses for background jobs without booting the app.
            $result = Process::timeout(5)->run([
                $phpBinary,
                '-r',
                'echo json_encode(["php_version" => PHP_VERSION, "extensions" => get_loaded_extensions()]);',
            ]);
            $observed = $result->successful() ? json_decode($result->output(), true) : null;
            if (!is_array($observed) || !is_string($observed['php_version'] ?? null) || !is_array($observed['extensions'] ?? null)) {
                return $this->backgroundRuntimeResult = $runtime;
            }

            $extensions = array_map('strtolower', array_filter($observed['extensions'], 'is_string'));
            $runtime['php_version'] = $observed['php_version'];
            $runtime['missing_extensions'] = array_values(array_filter(
                InstallerServiceProvider::getRequiredExtensions(),
                static fn ($extension) => !in_array(strtolower($extension), $extensions, true),
            ));
            $runtime['supported'] = PhpRuntimeRequirements::supports($runtime['php_version']) && $runtime['missing_extensions'] === [];
            $runtime['status'] = $runtime['supported'] ? 'available' : 'requirements_not_met';
        } catch (Throwable) {
            // Host process failures must not expose environment values or prevent inline execution.
        }

        return $this->backgroundRuntimeResult = $runtime;
    }

    public function workerCommand(): ?string
    {
        return self::workerCommandFor(
            $this->mode(),
            $this->connection(),
            $this->queueName(),
            base_path(),
            self::phpBinary(),
        );
    }

    public static function workerCommandFor(
        string $mode,
        string $workerConnection,
        string $queueName,
        string $applicationPath,
        ?string $phpBinary = null,
    ): ?string {
        if (self::normalizeMode($mode) !== self::MODE_WORKER) {
            return null;
        }

        return sprintf(
            'cd %s && %s artisan queue:work %s --queue=%s --tries=3 --timeout=240',
            escapeshellarg($applicationPath),
            escapeshellarg($phpBinary ?? self::phpBinary()),
            self::normalizeWorkerConnection($workerConnection),
            self::normalizeQueueName($queueName),
        );
    }

    public static function schedulerCommandFor(string $applicationPath, ?string $phpBinary = null): string
    {
        return '* * * * * cd '.escapeshellarg($applicationPath).' && '.escapeshellarg($phpBinary ?? self::phpBinary()).' artisan schedule:run >> /dev/null 2>&1';
    }

    public function snapshot(): array
    {
        $mode = $this->mode();
        $connection = $this->connection();
        $latestTest = $this->latestTest();
        $testState = $this->testState($latestTest);
        $workerState = $this->workerState($latestTest);
        $backgroundRuntime = $mode === self::MODE_BACKGROUND ? $this->backgroundRuntime() : null;

        return [
            'mode' => $mode,
            'mode_label' => $this->modeLabel($mode),
            'connection' => $connection,
            'queue_name' => $this->queueName(),
            'uses_worker' => $this->usesWorker(),
            'worker_command' => $this->workerCommand(),
            'background_supported' => $backgroundRuntime['supported'] ?? null,
            'background_runtime' => $backgroundRuntime,
            'database' => $connection === 'database' ? $this->databaseStats() : null,
            'failed_jobs_count' => $this->failedJobsCount($connection),
            'latest_test' => $latestTest,
            'test_state' => $testState,
            'worker_state' => $workerState,
            'runtime_state' => $this->runtimeState($testState, $workerState),
        ];
    }

    public function dispatchHealthCheck(): QueueHealthCheck
    {
        if (!Schema::hasTable('queue_health_checks')) {
            throw new RuntimeException('The queue health-check table is not available.');
        }

        QueueHealthCheck::query()
            ->where('created_at', '<', now()->subDays(7))
            ->delete();

        $connection = $this->connection();
        $queueName = $this->queueName();
        $healthCheck = QueueHealthCheck::query()->create([
            'id' => (string) Str::uuid(),
            'execution_mode' => $this->mode(),
            'connection' => $connection,
            'queue' => $queueName,
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        $dispatch = static function () use ($healthCheck, $connection, $queueName): void {
            try {
                QueueHealthCheckJob::dispatch($healthCheck->id)
                    ->onConnection($connection)
                    ->onQueue($queueName);
            } catch (Throwable $e) {
                QueueHealthCheck::query()
                    ->whereKey($healthCheck->id)
                    ->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'error' => $e->getMessage(),
                    ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }

        return $healthCheck->refresh();
    }

    public static function classifyWorkerState(
        ?string $status,
        ?CarbonInterface $observedAt,
        ?CarbonInterface $now = null,
    ): string {
        $testState = self::classifyTestState(
            $status,
            $observedAt,
            $now,
            self::WORKER_CHECK_TIMEOUT_SECONDS,
        );

        if ($testState !== 'completed') {
            return $testState;
        }

        $now ??= CarbonImmutable::now();

        return abs($now->getTimestamp() - $observedAt->getTimestamp()) <= self::WORKER_CHECK_FRESH_SECONDS
            ? 'online'
            : 'stale';
    }

    public static function classifyTestState(
        ?string $status,
        ?CarbonInterface $observedAt,
        ?CarbonInterface $now = null,
        int $timeoutSeconds = self::TEST_CHECK_TIMEOUT_SECONDS,
    ): string {
        if ($status === null || $observedAt === null) {
            return 'unchecked';
        }

        $now ??= CarbonImmutable::now();
        $ageInSeconds = abs($now->getTimestamp() - $observedAt->getTimestamp());

        return match ($status) {
            'dispatched', 'processing' => $ageInSeconds <= $timeoutSeconds
                ? 'checking'
                : 'not_responding',
            'completed' => 'completed',
            'failed' => 'failed',
            default => 'unchecked',
        };
    }

    public function modeLabel(?string $mode = null): string
    {
        return match ($mode ?: $this->mode()) {
            self::MODE_DEFERRED => 'Deferred after response',
            self::MODE_BACKGROUND => 'Spawned background process',
            self::MODE_WORKER => 'Queue worker',
            default => 'Inline request',
        };
    }

    protected function databaseStats(): array
    {
        $failedTableExists = Schema::hasTable('failed_jobs');

        if (!Schema::hasTable('jobs')) {
            return [
                'table_exists' => false,
                'failed_table_exists' => $failedTableExists,
                'waiting' => 0,
                'delayed' => 0,
                'processing' => 0,
            ];
        }

        $now = now()->timestamp;
        $query = DB::table('jobs')->where('queue', $this->queueName());

        return [
            'table_exists' => true,
            'failed_table_exists' => $failedTableExists,
            'waiting' => (clone $query)->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
            'delayed' => (clone $query)->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
            'processing' => (clone $query)->whereNotNull('reserved_at')->count(),
        ];
    }

    protected function failedJobsCount(string $connection): int
    {
        if (!$this->usesWorker() || !Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->where('connection', $connection)
            ->where('queue', $this->queueName())
            ->count();
    }

    protected function latestTest(): ?QueueHealthCheck
    {
        try {
            if (!Schema::hasTable('queue_health_checks')) {
                return null;
            }

            return QueueHealthCheck::query()
                ->where('execution_mode', $this->mode())
                ->where('connection', $this->connection())
                ->where('queue', $this->queueName())
                ->latest('created_at')
                ->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function workerState(?QueueHealthCheck $latestTest): array
    {
        if (!$this->usesWorker()) {
            return [
                'status' => 'not_required',
                'label' => 'No worker required',
                'class' => 'is-success',
            ];
        }

        $status = self::classifyWorkerState(
            $latestTest?->status,
            $this->healthCheckObservedAt($latestTest),
        );

        return match ($status) {
            'checking' => [
                'status' => $status,
                'label' => 'Checking worker…',
                'class' => 'is-warning',
            ],
            'online' => [
                'status' => $status,
                'label' => 'Worker online',
                'class' => 'is-success',
            ],
            'not_responding' => [
                'status' => $status,
                'label' => 'Worker not confirmed',
                'class' => 'is-warning',
            ],
            'failed' => [
                'status' => $status,
                'label' => 'Worker check failed',
                'class' => 'is-danger',
            ],
            'stale' => [
                'status' => $status,
                'label' => 'Worker status stale',
                'class' => 'is-neutral',
            ],
            default => [
                'status' => 'unchecked',
                'label' => 'Worker not checked',
                'class' => 'is-neutral',
            ],
        };
    }

    protected function testState(?QueueHealthCheck $latestTest): array
    {
        return [
            'status' => self::classifyTestState(
                $latestTest?->status,
                $this->healthCheckObservedAt($latestTest),
                timeoutSeconds: $this->usesWorker()
                    ? self::WORKER_CHECK_TIMEOUT_SECONDS
                    : self::TEST_CHECK_TIMEOUT_SECONDS,
            ),
        ];
    }

    protected function runtimeState(array $testState, array $workerState): array
    {
        if ($this->usesWorker()) {
            return $workerState;
        }

        $status = $testState['status'];

        if ($this->mode() === self::MODE_BACKGROUND) {
            if (!$this->backgroundSupported()) {
                return [
                    'status' => 'unavailable',
                    'label' => 'Background unavailable',
                    'class' => 'is-danger',
                ];
            }

            return match ($status) {
                'checking' => ['status' => $status, 'label' => 'Checking background…', 'class' => 'is-warning'],
                'completed' => ['status' => $status, 'label' => 'Background verified', 'class' => 'is-success'],
                'not_responding' => ['status' => $status, 'label' => 'Background not verified', 'class' => 'is-warning'],
                'failed' => ['status' => $status, 'label' => 'Background check failed', 'class' => 'is-danger'],
                default => ['status' => 'ready', 'label' => 'Background available', 'class' => 'is-success'],
            };
        }

        if ($this->mode() === self::MODE_DEFERRED) {
            return match ($status) {
                'checking' => ['status' => $status, 'label' => 'Checking deferred…', 'class' => 'is-warning'],
                'completed' => ['status' => $status, 'label' => 'Deferred verified', 'class' => 'is-success'],
                'not_responding' => ['status' => $status, 'label' => 'Deferred not verified', 'class' => 'is-warning'],
                'failed' => ['status' => $status, 'label' => 'Deferred check failed', 'class' => 'is-danger'],
                default => ['status' => 'ready', 'label' => 'Deferred ready', 'class' => 'is-success'],
            };
        }

        return match ($status) {
            'completed' => ['status' => $status, 'label' => 'Inline verified', 'class' => 'is-success'],
            'failed' => ['status' => $status, 'label' => 'Inline check failed', 'class' => 'is-danger'],
            default => ['status' => 'ready', 'label' => 'Inline ready', 'class' => 'is-success'],
        };
    }

    protected function healthCheckObservedAt(?QueueHealthCheck $healthCheck): ?CarbonInterface
    {
        if (!$healthCheck) {
            return null;
        }

        return match ($healthCheck->status) {
            'completed' => $healthCheck->completed_at ?? $healthCheck->updated_at ?? $healthCheck->created_at,
            'failed' => $healthCheck->failed_at ?? $healthCheck->updated_at ?? $healthCheck->created_at,
            'processing' => $healthCheck->started_at ?? $healthCheck->dispatched_at ?? $healthCheck->created_at,
            default => $healthCheck->dispatched_at ?? $healthCheck->created_at,
        };
    }
}
