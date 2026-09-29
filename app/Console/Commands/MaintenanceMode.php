<?php

namespace App\Console\Commands;

use App\Support\MaintenanceMode as Flag;
use Illuminate\Console\Command;

/**
 * Turn the app's own maintenance switch on or off from the command line — the
 * way back in if a restore left the site closed and nobody can reach the page.
 * (With no shell at all, deleting storage/framework/qrs-maintenance.json does
 * the same thing.)
 */
class MaintenanceMode extends Command
{
    protected $signature = 'maintenance:mode {state : "on" or "off"} {--message= : Shown to visitors while it is on}';

    protected $description = 'Turn maintenance mode on or off';

    public function handle(): int
    {
        $state = strtolower((string) $this->argument('state'));

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('State must be "on" or "off".');

            return self::FAILURE;
        }

        if ($state === 'on') {
            Flag::enable(Flag::MAINTENANCE, $this->option('message'), 'command line');
            $this->info('Maintenance mode is on.');
        } else {
            Flag::disable();
            $this->info('Maintenance mode is off.');
        }

        return self::SUCCESS;
    }
}
