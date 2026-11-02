<?php

namespace App\Console\Commands;

use App\Services\ProductThemeGenerator;
use Illuminate\Console\Command;
use Throwable;

class RollbackProductTheme extends Command
{
    protected $signature = 'theme:rollback {backup? : Backup identifier; omit to list available backups}';

    protected $description = 'List or restore generated theme backups without changing saved colors';

    public function handle(ProductThemeGenerator $generator): int
    {
        try {
            $backup = $this->argument('backup');
            if ($backup === null) {
                $backups = $generator->backups();
                $this->line($backups === [] ? 'No generated theme backups are available.' : implode(PHP_EOL, $backups));

                return self::SUCCESS;
            }

            $result = $generator->rollback((string) $backup);
            $this->info('The theme asset was restored. Saved color settings were not changed.');
            if ($result['backup'] !== null) {
                $this->line('Replacement theme backup: '.$result['backup']);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
