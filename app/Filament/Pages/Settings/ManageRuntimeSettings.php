<?php

namespace App\Filament\Pages\Settings;

use App\Providers\SettingsServiceProvider;
use App\Services\Queue\QueueRuntimeService;
use App\Settings\RuntimeSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Throwable;

class ManageRuntimeSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $slug = 'settings/runtime';

    protected static string $settings = RuntimeSettings::class;

    protected static ?string $title = 'Runtime Settings';

    protected string $previousCacheDriver = 'file';

    protected string $previousSessionDriver = 'file';

    public ?string $confirmedSessionDriverChange = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Runtime Settings')
                ->columnSpanFull()
                ->persistTabInQueryString('tab')
                ->tabs([
                    Tabs\Tab::make('Cache')
                        ->columns(2)
                        ->schema([
                            Select::make('cache_driver')
                                ->label('Cache driver')
                                ->options($this->cacheDriverOptions())
                                ->required()
                                ->reactive()
                                ->placeholder('Select a driver')
                                ->helperText('Changing the cache driver may clear or bypass currently cached data until the new store warms up.')
                                ->columnSpanFull(),

                            TextInput::make('cache_prefix')
                                ->label('Cache key prefix')
                                ->helperText('Optional prefix used to avoid key collisions with other apps.')
                                ->columnSpanFull(),

                            TextInput::make('cache_redis_host')
                                ->label('Redis host')
                                ->default('127.0.0.1')
                                ->required()
                                ->visible(fn ($get) => $get('cache_driver') === 'redis'),
                            TextInput::make('cache_redis_port')
                                ->label('Redis port')
                                ->numeric()
                                ->default(6379)
                                ->required()
                                ->visible(fn ($get) => $get('cache_driver') === 'redis'),
                            TextInput::make('cache_redis_password')
                                ->label('Redis password')
                                ->visible(fn ($get) => $get('cache_driver') === 'redis')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password')
                                ->columnSpanFull(),

                        ]),

                    Tabs\Tab::make('Sessions')
                        ->columns(2)
                        ->schema([
                            Select::make('session_driver')
                                ->label('Session driver')
                                ->options([
                                    'file' => 'File',
                                    'database' => 'Database',
                                    'redis' => 'Redis',
                                ])
                                ->required()
                                ->reactive()
                                ->placeholder('Select a driver')
                                ->helperText('Changing the session driver may log out currently signed-in users because active sessions are stored by the current driver.')
                                ->columnSpanFull(),

                            Toggle::make('session_expire_on_close')
                                ->label('Expire on browser close'),
                            Toggle::make('session_encrypt')
                                ->label('Encrypt session data'),

                            TextInput::make('session_lifetime')
                                ->label('Lifetime')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('minutes')
                                ->required()
                                ->columnSpanFull(),

                            TextInput::make('session_redis_host')
                                ->label('Redis host')
                                ->default('127.0.0.1')
                                ->required()
                                ->visible(fn ($get) => $get('session_driver') === 'redis'),
                            TextInput::make('session_redis_port')
                                ->label('Redis port')
                                ->numeric()
                                ->default(6379)
                                ->required()
                                ->visible(fn ($get) => $get('session_driver') === 'redis'),
                            TextInput::make('session_redis_password')
                                ->label('Redis password')
                                ->visible(fn ($get) => $get('session_driver') === 'redis')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password')
                                ->columnSpanFull(),

                        ]),

                    Tabs\Tab::make('Queue')
                        ->columns(2)
                        ->schema($this->queueSettingsSchema()),
                ]),
        ]);
    }

    protected function beforeSave(): void
    {
        $settings = app(RuntimeSettings::class);
        $this->previousCacheDriver = $settings->cache_driver;
        $this->previousSessionDriver = $settings->session_driver;
    }

    protected function afterSave(): void
    {
        try {
            SettingsServiceProvider::setUpRuntimeCredentials(applySessionConfig: false);
            SettingsServiceProvider::setUpQueueConfiguration();

            $key = 'runtime-cache-test-'.uniqid();
            Cache::store($this->data['cache_driver'])->put($key, 'ok', 30);

            if (Cache::store($this->data['cache_driver'])->get($key) !== 'ok') {
                throw new \RuntimeException('Cache test failed: the saved value could not be read back.');
            }

            Cache::store($this->data['cache_driver'])->forget($key);
        } catch (Throwable $e) {
            config([
                'cache.default' => $this->previousCacheDriver,
                'session.driver' => $this->previousSessionDriver,
            ]);

            $settings = app(RuntimeSettings::class);
            $settings->cache_driver = $this->previousCacheDriver;
            $settings->session_driver = $this->previousSessionDriver;
            $settings->save();

            $this->data['cache_driver'] = $this->previousCacheDriver;
            $this->data['session_driver'] = $this->previousSessionDriver;
            $this->addError('cache_driver', 'Runtime settings test failed: '.$e->getMessage());

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->testQueue(automatic: true);
    }

    protected function getSavedNotificationMessage(): ?string
    {
        return null;
    }

    protected function cacheDriverOptions(): array
    {
        return [
            'file' => 'File',
            'database' => 'Database',
            'redis' => 'Redis',
        ];
    }

    protected function queueSettingsSchema(): array
    {
        return [
            Placeholder::make('queue_status')
                ->hiddenLabel()
                ->content(function (Get $get) {
                    $snapshot = app(QueueRuntimeService::class)->snapshot();
                    $selectedValue = $get('queue_execution_mode');
                    $selectedMode = filled($selectedValue)
                        ? QueueRuntimeService::normalizeMode((string) $selectedValue)
                        : $snapshot['mode'];
                    $selectedDriver = QueueRuntimeService::normalizeWorkerConnection(
                        (string) ($get('queue_driver') ?: $snapshot['connection']),
                    );
                    $selectedQueueName = QueueRuntimeService::normalizeQueueName(
                        (string) ($get('queue_name') ?: $snapshot['queue_name']),
                    );
                    $selectionIsSaved = $selectedMode === $snapshot['mode']
                        && ($selectedMode !== QueueRuntimeService::MODE_WORKER
                            || ($selectedDriver === $snapshot['connection']
                                && $selectedQueueName === $snapshot['queue_name']));

                    return view('filament.partials.queue-runtime-status', [
                        'snapshot' => $snapshot,
                        'modeSelection' => [
                            ...$this->queueModeDetails($selectedMode),
                            'is_saved' => $selectionIsSaved,
                            'worker_command' => QueueRuntimeService::workerCommandFor(
                                $selectedMode,
                                $selectedDriver,
                                $selectedQueueName,
                                base_path(),
                            ),
                        ],
                    ]);
                })
                ->columnSpanFull(),

            Select::make('queue_execution_mode')
                ->label('Execution mode')
                ->options([
                    QueueRuntimeService::MODE_SYNC => 'Inline request',
                    QueueRuntimeService::MODE_DEFERRED => 'Deferred after response',
                    QueueRuntimeService::MODE_BACKGROUND => 'Spawned background process',
                    QueueRuntimeService::MODE_WORKER => 'Queue worker',
                ])
                ->required()
                ->live()
                ->helperText('Choose when and where jobs execute. Inline is the safest default for shared hosting.')
                ->columnSpanFull(),

            Select::make('queue_driver')
                ->label('Queue service')
                ->options([
                    'database' => 'Database',
                    'redis' => 'Redis',
                    'sqs' => 'Amazon SQS',
                ])
                ->required(fn (Get $get): bool => $get('queue_execution_mode') === QueueRuntimeService::MODE_WORKER)
                ->live()
                ->helperText('This stores background jobs. The worker can run on this server or a separate server.')
                ->visible(fn (Get $get): bool => $get('queue_execution_mode') === QueueRuntimeService::MODE_WORKER)
                ->columnSpanFull(),

            TextInput::make('queue_name')
                ->label('Queue name')
                ->default('default')
                ->live()
                ->required(fn (Get $get): bool => $get('queue_execution_mode') === QueueRuntimeService::MODE_WORKER)
                ->rules(['regex:/^[A-Za-z0-9_.:{}-]+$/'])
                ->helperText('A logical lane for jobs. “default” is suitable for most installations.')
                ->visible(fn (Get $get): bool => $get('queue_execution_mode') === QueueRuntimeService::MODE_WORKER)
                ->columnSpanFull(),

            TextInput::make('queue_redis_url')
                ->label('Redis URL')
                ->helperText('Optional. Managed Redis providers commonly supply a single connection URL.')
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis'))
                ->columnSpanFull(),

            Select::make('queue_redis_scheme')
                ->label('Connection scheme')
                ->options([
                    'tcp' => 'TCP',
                    'tls' => 'TLS',
                ])
                ->default('tcp')
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_host')
                ->label('Redis host')
                ->default('127.0.0.1')
                ->required(fn (Get $get): bool => $this->workerUses($get, 'redis') && blank($get('queue_redis_url')))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_port')
                ->label('Redis port')
                ->numeric()
                ->default(6379)
                ->required(fn (Get $get): bool => $this->workerUses($get, 'redis') && blank($get('queue_redis_url')))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_username')
                ->label('Redis username')
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_password')
                ->label('Redis password')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_database')
                ->label('Redis database index')
                ->numeric()
                ->default(0)
                ->required(fn (Get $get): bool => $this->workerUses($get, 'redis'))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_prefix')
                ->label('Redis key prefix')
                ->helperText('Optional. Use a unique prefix when Redis is shared by multiple applications.')
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_retry_after')
                ->label('Retry after')
                ->numeric()
                ->minValue(960)
                ->default(960)
                ->suffix('seconds')
                ->helperText('Must stay above the 15-minute video job timeout. Other jobs keep the worker’s 240-second default.')
                ->required(fn (Get $get): bool => $this->workerUses($get, 'redis'))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_redis_block_for')
                ->label('Block for')
                ->numeric()
                ->minValue(0)
                ->suffix('seconds')
                ->helperText('Leave empty to use Laravel’s default polling behavior.')
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'redis')),

            TextInput::make('queue_sqs_key')
                ->label('AWS access key ID')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->required(fn (Get $get): bool => $this->workerUses($get, 'sqs'))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'sqs')),

            TextInput::make('queue_sqs_secret')
                ->label('AWS secret access key')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->required(fn (Get $get): bool => $this->workerUses($get, 'sqs'))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'sqs')),

            TextInput::make('queue_sqs_prefix')
                ->label('SQS prefix URL')
                ->placeholder('https://sqs.us-east-1.amazonaws.com/123456789012')
                ->required(fn (Get $get): bool => $this->workerUses($get, 'sqs'))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'sqs'))
                ->columnSpanFull(),

            TextInput::make('queue_sqs_region')
                ->label('AWS region')
                ->default('us-east-1')
                ->required(fn (Get $get): bool => $this->workerUses($get, 'sqs'))
                ->visible(fn (Get $get): bool => $this->workerUses($get, 'sqs'))
                ->columnSpanFull(),

            SchemaActions::make([
                Action::make('testQueue')
                    ->label(fn (): string => app(QueueRuntimeService::class)->usesWorker() ? 'Check worker' : 'Test setup')
                    ->icon('heroicon-o-play')
                    ->disabled(fn (): bool => app(QueueRuntimeService::class)->snapshot()['test_state']['status'] === 'checking')
                    ->action(fn () => $this->testQueue()),
                Action::make('refreshQueueStatus')
                    ->label('Refresh status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (): bool => app(QueueRuntimeService::class)->mode() !== QueueRuntimeService::MODE_SYNC)
                    ->action(fn () => null),
            ])
                ->extraAttributes(['class' => 'queue-runtime-actions'])
                ->columnSpanFull(),
        ];
    }

    protected function workerUses(Get $get, string $driver): bool
    {
        return $get('queue_execution_mode') === QueueRuntimeService::MODE_WORKER
            && $get('queue_driver') === $driver;
    }

    /**
     * @return array{mode: string, label: string, description: string}
     */
    protected function queueModeDetails(string $mode): array
    {
        return match ($mode) {
            QueueRuntimeService::MODE_DEFERRED => [
                'mode' => $mode,
                'label' => 'Deferred after response',
                'description' => 'Runs after the response in the same PHP process. No worker is required, but the job is not durable and still uses the web process.',
            ],
            QueueRuntimeService::MODE_BACKGROUND => [
                'mode' => $mode,
                'label' => 'Spawned background process',
                'description' => 'Runs after the response in a spawned PHP process. No worker is required, but the host must allow process spawning and the job is not durable.',
            ],
            QueueRuntimeService::MODE_WORKER => [
                'mode' => $mode,
                'label' => 'Queue worker',
                'description' => 'Stores durable jobs for a queue worker. This supports retries and failed-job tracking, but a worker process or cron worker must be configured.',
            ],
            default => [
                'mode' => QueueRuntimeService::MODE_SYNC,
                'label' => 'Inline request',
                'description' => 'Runs immediately during the current request. No worker is required, but the user waits for completion and normal request limits apply.',
            ],
        };
    }

    protected function testQueue(bool $automatic = false): void
    {
        $runtime = app(QueueRuntimeService::class);

        if ($automatic && !$runtime->usesWorker()) {
            return;
        }

        if (!SchemaFacade::hasTable('queue_health_checks')) {
            Notification::make()
                ->title($automatic ? 'Automatic worker check is not available yet' : 'Queue test is not available yet')
                ->body('Run the latest database migrations, then try again.')
                ->danger()
                ->send();

            return;
        }

        if ($runtime->mode() === QueueRuntimeService::MODE_BACKGROUND && !$runtime->backgroundSupported()) {
            Notification::make()
                ->title('Background processes are unavailable')
                ->body(__('Background mode requires process spawning and a CLI PHP executable meeting PHP :version and extension requirements. Review System Health, or choose inline, deferred, or a queue worker.', ['version' => \App\Support\PhpRuntimeRequirements::MINIMUM_VERSION]))
                ->danger()
                ->send();

            return;
        }

        $snapshot = $runtime->snapshot();
        $workerState = $snapshot['worker_state']['status'];
        $testState = $snapshot['test_state']['status'];

        if ($automatic) {
            if ($workerState === 'online' || $testState === 'checking') {
                return;
            }
        } elseif ($testState === 'checking') {
            Notification::make()
                ->title('Queue check already in progress')
                ->body('The status card will refresh automatically when the saved setup responds.')
                ->info()
                ->send();

            return;
        }

        try {
            $healthCheck = $runtime->dispatchHealthCheck();

            if ($healthCheck->status === 'failed') {
                Notification::make()
                    ->title('Queue test failed')
                    ->body($healthCheck->error ?: 'The health-check job could not be dispatched.')
                    ->danger()
                    ->send();

                return;
            }

            if ($healthCheck->status === 'completed') {
                Notification::make()
                    ->title('Queue test completed')
                    ->body('The saved execution mode ran the test successfully.')
                    ->success()
                    ->send();

                return;
            }

            Notification::make()
                ->title($automatic ? 'Settings saved; checking queue worker' : 'Queue test dispatched')
                ->body($automatic
                    ? 'The status card will refresh automatically for up to '.QueueRuntimeService::WORKER_CHECK_TIMEOUT_SECONDS.' seconds.'
                    : match ($runtime->mode()) {
                        QueueRuntimeService::MODE_DEFERRED => 'The test will run after this response. The status card will refresh automatically.',
                        QueueRuntimeService::MODE_BACKGROUND => 'The test was handed to a spawned PHP process. The status card will refresh automatically.',
                        default => 'The test is waiting for a queue worker. The status card will refresh automatically.',
                    })
                ->info()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Queue test failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
