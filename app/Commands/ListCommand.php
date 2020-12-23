<?php

namespace App\Commands;

use App\InitializesCommands;
use App\Services\Category;
use App\Shell\Docker;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    use InitializesCommands;

    protected $signature = 'list {--json}';
    protected $description = 'List all services enabled by Takeout.';

    public function handle(): void
    {
        $this->initializeCommand();

        $containersCollection = app(Docker::class)->takeoutContainers();

        if ($this->option('json')) {
            $this->line($containersCollection->map(function ($item) {
                $item['category'] = Category::fromContainerName($item['names']);
                return $item;
            })->toJson());
            return;
        }

        if ($containersCollection->isEmpty()) {
            $this->info("No Takeout containers are enabled.\n");
            return;
        }

        $containers = $containersCollection->toArray();
        $columns = array_map('App\title_from_slug', array_keys(reset($containers)));

        $this->line("\n");
        $this->table($columns, $containers);
    }
}
