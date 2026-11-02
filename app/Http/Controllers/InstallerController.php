<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallerSaveAdminInfoRequest;
use App\Http\Requests\InstallerSaveDBInfoRequest;
use App\Providers\AuthServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\InstallerServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Model\User;
use App\Services\Installer\InstallationState;
use Carbon\Carbon;
use Illuminate\Database\Connectors\Connector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InstallerController extends Controller
{
    /**
     * Renders installer steps views.
     *
     * @param Request $request
     * @param Connector $connector
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function install(Request $request, Connector $connector)
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return Redirect::to(route('home'));
        }
        $step = $request->get('step') ? (int) $request->get('step') : 1;
        if ($step == 1) {
            $phpExtensions = InstallerServiceProvider::getRequiredExtensions();

            return view('installer.requirements', [
                'requiredExtensions' => $phpExtensions,
                'passesRequirements' => InstallerServiceProvider::passesRequirements(),
            ]);
        } elseif ($step == 2) {
            if ($request->session()->has('installer_database_warning') && $request->session()->has(['db_host', 'db_port', 'db_name', 'db_username'])) {
                // The database may have been emptied outside the installer since the warning was shown.
                $credentials = $request->session()->only(['db_host', 'db_port', 'db_name', 'db_username', 'db_password']);
                try {
                    if (!$this->databaseHasTables($credentials, $connector)) {
                        $request->session()->forget('installer_database_warning');
                    }
                } catch (RuntimeException $exception) {
                    // An unavailable database is not an empty database; keep confirmation pending.
                    $request->session()->flash('error', $exception->getMessage());
                }
            }

            return view('installer.database');
        } elseif ($step == 3) {
            if ($request->session()->has('installer_database_warning')) {
                return Redirect::to(route('installer.install').'?step=2');
            }

            if (!$request->session()->has('installer_start_token')) {
                $request->session()->put('installer_start_token', Str::random(64));
            }

            return view('installer.admin');
        }

        return Redirect::to(route('installer.install').'?step=1');
    }

    /**
     * Checks the connection and requires confirmation before using a non-empty database.
     *
     * @param InstallerSaveDBInfoRequest $request
     * @param Connector $connector
     * @return \Illuminate\Http\RedirectResponse
     */
    public function testAndSaveDBInfo(InstallerSaveDBInfoRequest $request, Connector $connector)
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return Redirect::to(route('home'));
        }

        $db_host = $request->get('db_host');
        $db_port = $request->get('db_port') ? $request->get('db_port') : config('database.connections.mysql.port');
        $db_name = $request->get('db_name');
        $db_username = $request->get('db_username');
        $db_password = $request->get('db_password');
        $credentials = [
            'db_host' => $db_host,
            'db_port' => $db_port,
            'db_name' => $db_name,
            'db_username' => $db_username,
            'db_password' => $db_password,
        ];
        $fingerprint = hash('sha256', serialize($credentials));
        $previousWarning = (string) $request->session()->get('installer_database_warning', '');
        // Failed checks must not leave a previously approved connection available to setup.
        $request->session()->forget(array_merge(array_keys($credentials), ['installer_database_warning']));
        try {
            $hasTables = $this->databaseHasTables($credentials, $connector);
        } catch (RuntimeException $exception) {
            return Redirect::to(route('installer.install').'?step=2')
                ->withInput($request->only(['db_host', 'db_port', 'db_name', 'db_username']))
                ->with('error', $exception->getMessage());
        }

        $request->session()->put($credentials);
        if ($hasTables && (!$request->boolean('confirm_non_empty_database') || !hash_equals($fingerprint, $previousWarning))) {
            $request->session()->put('installer_database_warning', $fingerprint);
            $response = Redirect::to(route('installer.install').'?step=2');
            if (hash_equals($fingerprint, $previousWarning)) {
                $response->withErrors([
                    'confirm_non_empty_database' => __('Confirm that you want to continue with this database.'),
                ]);
            }

            return $response;
        }

        return Redirect::to(route('installer.install').'?step=3');
    }

    private function databaseHasTables(array $credentials, Connector $connector): bool
    {
        $errorMessage = __('Database connection could not be established:');
        try {
            $connection = $connector->createConnection(
                "mysql:host={$credentials['db_host']};port={$credentials['db_port']};dbname={$credentials['db_name']}",
                ['username' => $credentials['db_username'], 'password' => $credentials['db_password'] ?? null],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $errorMessage = __('The database tables could not be checked:');

            return $connection->query('SHOW TABLES')->fetchColumn() !== false;
        } catch (\PDOException $exception) {
            throw new RuntimeException($errorMessage.' '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Starts the resumable installation process.
     *
     * @param InstallerSaveAdminInfoRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function beginInstall(InstallerSaveAdminInfoRequest $request, InstallationState $installationState)
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return Redirect::to(route('home'));
        }

        $existingToken = (string) $request->session()->get('installer.token', '');
        if ($installationState->owns($existingToken)) {
            return redirect()->route('installer.progress');
        }

        if ($installationState->isActive()) {
            return response()->view('installer.recovery', [], 409);
        }

        if ($request->session()->has('installer_database_warning')) {
            return Redirect::to(route('installer.install').'?step=2');
        }

        $request->session()->forget([
            'installer',
            'installer_recovery',
            'license',
            'licenseCode',
            InstallerServiceProvider::$lockCode,
        ]);

        if (!$request->session()->get('db_host')) {
            return Redirect::to(route('installer.install').'?step=3')
                ->with('error', __('Database connection could not be established. Go back to previous step.'));
        }

        $site_title = $request->get('site_title');
        $app_url = $request->get('app_url');
        $email = $request->get('email');
        $password = $request->get('password');
        $licenseCode = $request->get('license');

        $token = (string) $request->session()->get('installer_start_token', '');
        if ($token === '') {
            $token = Str::random(64);
            $request->session()->put('installer_start_token', $token);
        }

        $request->session()->put('installer', [
            'token' => $token,
            'payload' => [
                'site_title' => $site_title,
                'app_url' => $app_url,
                'email' => $email,
                'password' => $password,
                'license_code' => $licenseCode,
            ],
        ]);
        $request->session()->save();

        try {
            $installationState->start($token);
        } catch (Throwable $exception) {
            $request->session()->forget([
                'installer',
                'installer_start_token',
                'license',
                'licenseCode',
                InstallerServiceProvider::$lockCode,
            ]);

            return Redirect::to(route('installer.install').'?step=3')
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('installer.progress');
    }

    /**
     * Backwards-compatible redirect for installers that still reach the old URL.
     */
    public function finishInstall(Request $request, InstallationState $installationState)
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return Redirect::to(route('home'));
        }

        $token = (string) $request->session()->get('installer.token', '');
        if ($installationState->owns($token)) {
            return redirect()->route('installer.progress');
        }

        return Redirect::to(route('installer.install').'?step=3')
            ->with('error', __('No active installation was found. Please submit the installation form again.'));
    }

    public function installationProgress(Request $request, InstallationState $installationState)
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return Redirect::to(route('home'));
        }

        $token = (string) $request->session()->get('installer.token', '');

        try {
            $state = $installationState->status($token);
        } catch (Throwable $exception) {
            if ($exception->getCode() === 403) {
                return response()->view('installer.recovery', [], 409);
            }

            return Redirect::to(route('installer.install').'?step=3')
                ->with('error', $exception->getMessage());
        }

        if (!is_array($request->session()->get('installer.payload'))) {
            return response()->view('installer.recovery', [], 409);
        }

        return response()->view('installer.progress', [
            'installationToken' => $token,
            'installationState' => $state,
            'installationSteps' => $this->installationSteps(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function cancelInstallation(Request $request, InstallationState $installationState)
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return Redirect::to(route('home'));
        }

        $sessionToken = (string) $request->session()->get('installer.token', '');
        $providedToken = (string) $request->input('installation_token', '');

        if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
            abort(403, __('The installation session is invalid.'));
        }

        try {
            $state = $installationState->status($sessionToken);

            if (($state['completed'] ?? []) !== [] || ($state['next_step'] ?? null) !== 'license') {
                return redirect()->route('installer.progress')
                    ->with('error', __('Installation can only be restarted before the license phase completes.'));
            }

            $payload = $request->session()->get('installer.payload', []);
            $installationState->clear($sessionToken);
            $request->session()->forget([
                'installer',
                'installer_start_token',
                'license',
                'licenseCode',
                InstallerServiceProvider::$lockCode,
            ]);

            return Redirect::to(route('installer.install').'?step=3')->withInput([
                'site_title' => $payload['site_title'] ?? null,
                'app_url' => $payload['app_url'] ?? null,
                'email' => $payload['email'] ?? null,
                'license' => $payload['license_code'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return redirect()->route('installer.progress')->with('error', $exception->getMessage());
        }
    }

    public function restartInstallation(Request $request, InstallationState $installationState)
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return redirect()->route('home');
        }

        $sessionToken = (string) $request->session()->get('installer.token', '');
        $providedToken = (string) $request->input('installation_token', '');
        if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
            return response()->view('installer.recovery', [], 403);
        }

        $request->validate(['confirm_restart' => 'accepted']);

        try {
            $installationState->restart($sessionToken);
        } catch (Throwable $exception) {
            return redirect()->route('installer.progress')->with('error', $exception->getMessage());
        }

        $this->clearInstallationSession($request);

        return Redirect::to(route('installer.install').'?step=2')
            ->with('status', __('Setup reset. Database unchanged.'));
    }

    public function advanceInstallation(Request $request, InstallationState $installationState)
    {
        $sessionToken = (string) $request->session()->get('installer.token', '');
        $providedToken = (string) $request->header('X-Installer-Token', '');
        $payload = $request->session()->get('installer.payload');
        $recovery = $request->session()->get('installer_recovery');
        $activeSessionAuthorized = $sessionToken !== ''
            && $providedToken !== ''
            && hash_equals($sessionToken, $providedToken);
        $recoverySessionAuthorized = is_array($recovery)
            && !empty($recovery['token'])
            && $providedToken !== ''
            && hash_equals((string) $recovery['token'], $providedToken);

        // The final response may be lost after the installed marker is written.
        // Let the owning installer session recover by returning the intended redirect.
        if (InstallerServiceProvider::checkIfInstalled()) {
            $redirectUrl = $recoverySessionAuthorized
                ? (string) ($recovery['redirect'] ?? '')
                : (string) (is_array($payload) ? ($payload['app_url'] ?? '') : '');

            if (($activeSessionAuthorized || $recoverySessionAuthorized) && $redirectUrl !== '') {
                try {
                    if ($activeSessionAuthorized) {
                        $installationState->clear($sessionToken);
                    }
                } catch (Throwable $exception) {
                    logger()->warning('installer.state.cleanup-failed', ['error' => $exception->getMessage()]);
                }

                $this->storeInstallationRecovery($request, $providedToken, $redirectUrl);

                return response()->json([
                    'success' => true,
                    'completed' => 'finalize',
                    'completed_steps' => InstallationState::STEPS,
                    'next_step' => null,
                    'finished' => true,
                    'redirect' => $redirectUrl,
                ]);
            }

            return response()->json(['success' => false, 'message' => __('The application is already installed.')], 404);
        }

        if (!$activeSessionAuthorized) {
            return response()->json(['success' => false, 'message' => __('The installation session is invalid.')], 403);
        }

        if (!is_array($payload)) {
            return response()->json(['success' => false, 'message' => __('The installation details are missing. Please restart the installer.')], 409);
        }

        foreach (['site_title', 'app_url', 'email', 'password', 'license_code'] as $requiredValue) {
            if (!array_key_exists($requiredValue, $payload)) {
                return response()->json(['success' => false, 'message' => __('The installation details are incomplete. Please restart the installer.')], 409);
            }
        }

        try {
            $state = $installationState->advance(
                $sessionToken,
                fn (string $step) => $this->executeInstallationStep($step, $request, $payload)
            );
        } catch (Throwable $exception) {
            $status = (int) $exception->getCode();
            if (!in_array($status, [403, 404, 423], true)) {
                $status = 422;
            }

            $failedStep = null;
            try {
                $failedStep = $installationState->status($sessionToken)['failed_step'] ?? null;
            } catch (Throwable) {
            }

            logger()->error('installer.step.failed', [
                'step' => $failedStep,
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'success' => false,
                'retryable' => $status === 423,
                'failed_step' => $failedStep,
                'message' => $exception->getMessage(),
            ], $status);
        }

        $completedSteps = $state['completed'] ?? [];
        $completedStep = $completedSteps !== [] ? $completedSteps[array_key_last($completedSteps)] : null;
        $finished = (bool) ($state['finished'] ?? false);
        $redirectUrl = (string) $payload['app_url'];

        if ($finished) {
            try {
                $installationState->clear($sessionToken);
            } catch (Throwable $exception) {
                logger()->warning('installer.state.cleanup-failed', ['error' => $exception->getMessage()]);
            }

            $this->storeInstallationRecovery($request, $sessionToken, $redirectUrl);
        }

        return response()->json([
            'success' => true,
            'completed' => $completedStep,
            'completed_steps' => $completedSteps,
            'next_step' => $state['next_step'],
            'finished' => $finished,
            'redirect' => $finished ? $redirectUrl : null,
        ]);
    }

    private function executeInstallationStep(string $step, Request $request, array $payload): void
    {
        switch ($step) {
            case 'license':
                $this->validateInstallationLicense($request, $payload);
                break;
            case 'configuration':
                $this->configureInstallationEnvironment($request);
                break;
            case 'migrations':
                $this->runInstallationMigrations();
                break;
            case 'seeders':
                $this->runInstallationSeeders();
                break;
            case 'settings':
                $this->configureInstallationSettings($payload);
                break;
            case 'administrator':
                $this->createInstallationAdministrator($payload);
                break;
            case 'finalize':
                $this->finalizeInstallation();
                break;
            default:
                throw new RuntimeException(__('Invalid installation step.'));
        }
    }

    private function validateInstallationLicense(Request $request, array $payload): void
    {
        $licenseCode = (string) ($payload['license_code'] ?? '');
        if ($licenseCode === '') {
            throw new RuntimeException(__('The license code is missing. Please restart the installer.'));
        }

        $storedLicense = $request->session()->get('license');
        $storedLicenseCode = (string) $request->session()->get('licenseCode', '');
        if ($storedLicense && $storedLicenseCode !== '' && hash_equals($storedLicenseCode, $licenseCode)) {
            return;
        }

        $license = InstallerServiceProvider::gld($licenseCode);
        if (isset($license->error)) {
            throw new RuntimeException($license->error);
        }

        $request->session()->put('license', json_encode(array_merge((array) $license, ['code' => $licenseCode])));
        $request->session()->put('licenseCode', $licenseCode);

        // Persist verification before the state machine marks this phase complete.
        $request->session()->save();
    }

    private function configureInstallationEnvironment(Request $request): void
    {
        if (!$this->saveEnvValues($request)) {
            throw new RuntimeException(InstallerServiceProvider::$acError);
        }
    }

    private function runInstallationMigrations(): void
    {
        Schema::defaultStringLength(191);
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        if ($exitCode !== 0) {
            logger()->error('installer.migrations.failed', [
                'exit_code' => $exitCode,
                'output' => Artisan::output(),
            ]);

            throw new RuntimeException(__('Database setup failed. Check the application logs before retrying.'));
        }
    }

    private function runInstallationSeeders(): void
    {
        $exitCode = Artisan::call('db:seed', ['--force' => true]);
        if ($exitCode !== 0) {
            logger()->error('installer.seeders.failed', [
                'exit_code' => $exitCode,
                'output' => Artisan::output(),
            ]);

            throw new RuntimeException(__('Importing default data failed. Check the application logs before retrying.'));
        }
    }

    private function configureInstallationSettings(array $payload): void
    {
        $this->configureFreshInstallationSettingsRepository();

        $licenseCode = session()->get('licenseCode');
        if (!$licenseCode) {
            throw new RuntimeException(__('The validated license information is missing. Please restart the installer.'));
        }

        DB::statement("UPDATE settings SET `payload` = :title WHERE `name` = 'name' and `group` = 'site'", [
            'title' => json_encode($payload['site_title']),
        ]);

        DB::statement("UPDATE settings SET `payload` = :url WHERE `name` = 'app_url' and `group` = 'site'", [
            'url' => json_encode($payload['app_url']),
        ]);

        DB::statement("UPDATE settings SET `payload` = :val WHERE `name` = 'product_license_key' and `group` = 'license'", [
            'val' => json_encode($licenseCode),
        ]);

        $host = parse_url($payload['app_url'], PHP_URL_HOST)
            ?: parse_url('http://'.$payload['app_url'], PHP_URL_HOST);
        $host = $host ? preg_replace('/^www\./i', '', strtolower($host)) : null;

        $from = ($host && !filter_var($host, FILTER_VALIDATE_IP) && $host !== 'localhost')
            ? "no-reply@{$host}"
            : 'no-reply@qdev.tech';

        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $from = 'no-reply@qdev.tech';
        }

        DB::table('settings')->updateOrInsert(
            ['group' => 'emails', 'name' => 'from_address'],
            ['payload' => json_encode($from)]
        );

        DB::table('settings')->updateOrInsert(
            ['group' => 'emails', 'name' => 'from_name'],
            ['payload' => json_encode(trim($payload['site_title']).' Admin')]
        );
    }

    private function createInstallationAdministrator(array $payload): void
    {
        $this->configureFreshInstallationSettingsRepository();

        DB::transaction(function () use ($payload) {
            $user = User::query()->where('email', $payload['email'])->first();

            if (!$user) {
                $user = AuthServiceProvider::createUser([
                    'name' => 'Admin',
                    'email' => $payload['email'],
                    'password' => $payload['password'],
                    'email_verified_at' => Carbon::now(),
                ]);
            }

            Artisan::call('make-admin', ['email' => $payload['email']]);
            GenericHelperServiceProvider::createUserWallet($user);
            ListsHelperServiceProvider::createUserDefaultLists($user->id);
        });
    }

    private function finalizeInstallation(): void
    {
        $license = session()->get('license');
        if (!$license) {
            throw new RuntimeException(__('The validated license information is missing. Please restart the installer.'));
        }

        try {
            $exitCode = Artisan::call('storage:link');

            if ($exitCode !== 0) {
                logger()->warning('storage:link returned non-zero during install', [
                    'exit_code' => $exitCode,
                    'output' => Artisan::output(),
                ]);
            }
        } catch (Throwable $exception) {
            logger()->warning('storage:link threw during install', [
                'error' => $exception->getMessage(),
            ]);
        }

        if (!Storage::disk('local')->put('installed', $license)) {
            throw new RuntimeException(__('The installation marker could not be created.'));
        }
    }

    private function configureFreshInstallationSettingsRepository(): void
    {
        Config::set('settings.admin_version', 'v2');
        Config::set('settings.repositories.database.table', 'settings');
    }

    private function installationSteps(): array
    {
        return [
            'license' => [
                'label' => __('License'),
                'description' => __('Verify your purchase code.'),
            ],
            'configuration' => [
                'label' => __('Configuration'),
                'description' => __('Save database connection details.'),
            ],
            'migrations' => [
                'label' => __('Database'),
                'description' => __('Create the required tables.'),
            ],
            'seeders' => [
                'label' => __('Default data'),
                'description' => __('Import initial settings and records.'),
            ],
            'settings' => [
                'label' => __('Site settings'),
                'description' => __('Apply your site name and URL.'),
            ],
            'administrator' => [
                'label' => __('Administrator'),
                'description' => __('Create your administrator account.'),
            ],
            'finalize' => [
                'label' => __('Finish'),
                'description' => __('Complete setup and enable the site.'),
            ],
        ];
    }

    private function clearInstallationSession(Request $request): void
    {
        $request->session()->forget([
            'installer',
            'installer_recovery',
            'installer_start_token',
            'db_host',
            'db_port',
            'db_name',
            'db_username',
            'db_password',
            'installer_database_warning',
            'license',
            'licenseCode',
            InstallerServiceProvider::$lockCode,
        ]);
    }

    private function storeInstallationRecovery(Request $request, string $token, string $redirectUrl): void
    {
        $this->clearInstallationSession($request);
        $request->session()->flash('installer_recovery', [
            'token' => $token,
            'redirect' => $redirectUrl,
        ]);
    }

    /**
     * Renders upgrade view.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function upgrade()
    {
        app()->instance('pretend_migration', true); // Register a flag
        $canMigrate = InstallerServiceProvider::hasAvailableMigrations();

        return view('installer.upgrade', ['canMigrate'=>$canMigrate]);
    }

    /**
     * Runs the update.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doUpgrade()
    {
        try {
            app(\App\Services\SiteMaintenanceService::class)->upgrade();
        } catch (Throwable $e) {
            // Never reopen the site or report success with a partially migrated schema.
            return Redirect::to(route('installer.update'))->with('error', $e->getMessage());
        }

        return Redirect::to(route('installer.update'))->with('success', __('Platform updated successfully.'));
    }

    /**
     * Saves db info to env file.
     * @param $request
     */
    public function saveEnvValues($request) {
        if(InstallerServiceProvider::getLockCode()){
            $lines = [
                'DB_HOST='.'"'.$request->session()->get('db_host').'"',
                'DB_DATABASE='.'"'.$request->session()->get('db_name').'"',
                'DB_USERNAME='.'"'.$request->session()->get('db_username').'"',
                'DB_PORT='.'"'.$request->session()->get('db_port').'"',
                'DB_PASSWORD='.'"'.$request->session()->get('db_password').'"',
            ];

            $environmentLines = preg_split('/\R/', (string) file_get_contents(base_path('.env')));
            foreach ($lines as $line) {
                if (!in_array($line, $environmentLines, true)) {
                    InstallerServiceProvider::appendToEnv($line);
                    $environmentLines[] = $line;
                }
            }

            return true;
        }
        else{
            return false;
        }
    }
}
