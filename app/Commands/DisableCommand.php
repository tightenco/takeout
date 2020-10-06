<?php

namespace App\Commands;

use App\InitializesCommands;
use App\Shell\Docker;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class DisableCommand extends Command
{
    use InitializesCommands;

    protected $signature = 'disable {serviceNames?*}';
    protected $description = 'Disable services.';
    protected $disableableServices;
    protected $docker;

    public function handle(Docker $docker)
    {
        $this->docker = $docker;
        $this->initializeCommand();
        $this->disableableServices = $this->disableableServices();

        if (empty($this->disableableServices)) {
            $this->info("There are no containers to disable.\n");
            return;
        }

        if (filled($services = $this->argument('serviceNames'))) {
            foreach ($services as $service) {
                $this->disableByServiceName($service);
            }

            return;
        }

        $this->showDisableServiceMenu();
    }

    public function disableableServices(): array
    {
        return $this->docker->takeoutContainers()->mapWithKeys(function ($container) {
            return [$container['container_id'] => str_replace('TO--', '', $container['names'])];
        })->toArray();
    }

    public function disableByServiceName(string $service): void
    {
        $serviceMatches = collect($this->disableableServices)
            ->filter(function ($containerName) use ($service) {
                return substr($containerName, 0, strlen($service)) === $service;
            });

        switch ($serviceMatches->count()) {
            case 0:
                $this->error("\nCannot find a Takeout-managed instance of {$service}.");
                return;
            case 1:
                $serviceContainerId = $serviceMatches->flip()->first();
                break;
            default: // > 1
                $serviceContainerId = $this->menu('Select which service to disable.', $serviceMatches->toArray())->open();

                if (! $serviceContainerId) {
                    return;
                }
        }

        $this->disableByContainerId($serviceContainerId);
    }

    public function showDisableServiceMenu(): void
    {
        $serviceContainerId = $this->menu('Services to disable', $this->disableableServices)
            ->addLineBreak('', 1)
            ->setPadding(2, 5)
            ->open();

        if ($serviceContainerId) {
            $this->disableByContainerId($serviceContainerId);
        }
    }

    public function disableByContainerId(string $containerId): void
    {
        try {
            $volumeName = $this->docker->attachedVolumeName($containerId);

            $this->task('Disabling Service...', $this->docker->removeContainer($containerId));

            if ($volumeName) {
                $this->info("\nThe disabled service was using a volume named {$volumeName}.");
                $this->info('If you would like to remove this data, run:');
                $this->info("\n docker volume rm {$volumeName}");
            }

            if (count($this->docker->allContainers()) === 0 && in_array(PHP_OS_FAMILY, ['Darwin','Windows','Linux'])) {
                $option = $this->menu('No containers are running. Turn off Docker?', [
                    'Yes',
                    'No',
                ])->disableDefaultItems()->open();

                if ($option === 0) {
                    $this->task('Stopping Docker service ', $this->docker->stopDockerService());
                }
            }
        } catch (Throwable $e) {
            $this->error('Disabling failed!');
        }
    }
}
