<?php

namespace App\Commands;

use App\InitializesCommands;
use App\Shell\Docker;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    use InitializesCommands;

    protected $signature = 'list {--json}{--networking}';
    protected $description = 'List all services enabled by Takeout.';

    public function handle(): void
    {
        $this->initializeCommand();

        $docker = app(Docker::class);

        $containersCollection = $docker->takeoutContainers();

        if ($this->option('json')) {
            $this->line($containersCollection->toJson());
            return;
        }

        if ($containersCollection->isEmpty()) {
            $this->info("No Takeout containers are enabled.\n");
            return;
        }

        if ($this->option('networking')) {
            $takeoutNetworkContainers = $docker->takeoutNetworkContainers();

            $containers = $takeoutNetworkContainers->toArray();
            $columns = array_map('App\title_from_slug', array_keys(reset($containers)));

            $this->table($columns, $containers);
            return;
        }

        $containers = $containersCollection->toArray();
        $columns = array_map('App\title_from_slug', array_keys(reset($containers)));

        $this->line("\n");
        $this->table($columns, $containers);
    }
}
