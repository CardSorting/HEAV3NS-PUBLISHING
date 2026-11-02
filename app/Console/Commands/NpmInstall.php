<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NpmInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'npm:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Installs locked client dependencies and builds client assets';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Installs client side dependencies within public folder.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('[*]['.date('H:i:s').'] Installing locked frontend dependencies.');

        $npm = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
        passthru($npm.' ci', $status);

        if ($status !== self::SUCCESS) {
            $this->error('[!] Frontend dependency installation failed.');

            return self::FAILURE;
        }

        return $this->call('npm:publish');
    }
}
