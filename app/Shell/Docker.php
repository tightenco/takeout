<?php

namespace App\Shell;

use App\Exceptions\DockerContainerMissingException;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Docker
{
    protected $shell;
    protected $formatter;
    protected $networking;

    public function __construct(Shell $shell, DockerFormatter $formatter, DockerNetworking $networking)
    {
        $this->shell = $shell;
        $this->formatter = $formatter;
        $this->networking = $networking;
    }

    public function removeContainer(string $containerId): void
    {
        if ($this->stoppableTakeoutContainers()->contains(function ($container) use ($containerId) {
            return $container['container_id'] === $containerId;
        })) {
            $this->stopContainer($containerId);
        }

        $process = $this->shell->exec('docker rm ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed removing container ' . $containerId);
        }
    }

    public function stopContainer(string $containerId): void
    {
        if (! $this->stoppableTakeoutContainers()->contains(function ($container) use ($containerId) {
            return $container['container_id'] === $containerId;
        })) {
            throw new DockerContainerMissingException($containerId);
        }

        $process = $this->shell->exec('docker stop ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed stopping container ' . $containerId);
        }
    }

    public function logContainer(string $containerId): void
    {
        if (! $this->stoppableTakeoutContainers()->contains(function ($container) use ($containerId) {
            return $container['container_id'] === $containerId;
        })) {
            throw new DockerContainerMissingException($containerId);
        }

        $process = $this->shell->exec('docker logs -f ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed to log container ' . $containerId);
        }
    }

    public function startContainer(string $containerId): void
    {
        if (! $this->startableTakeoutContainers()->contains(function ($container) use ($containerId) {
            return $container['container_id'] === $containerId;
        })) {
            throw new DockerContainerMissingException($containerId);
        }

        $process = $this->shell->exec('docker start ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed starting container ' . $containerId);
        }
    }

    public function isInstalled(): bool
    {
        return $this->shell->execQuietly('docker --version 2>&1')->isSuccessful();
    }

    public function allContainers(): Collection
    {
        return $this->runAndParseTable(
            'docker ps --format "table {{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}"'
        );
    }

    public function takeoutContainers(): Collection
    {
        return $this->runAndParseTable(
            "docker ps -a --filter 'name=TO-' --format 'table {{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}'"
        );
    }

    public function takeoutNetworkContainers(): Collection
    {
        $process = sprintf(
            "docker ps -a --filter 'name=TO-' --format 'table %s|%s'",
            '{{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}',
            '{{.Label "com.tighten.takeout.Base_Alias"}}|{{.Label "com.tighten.takeout.Full_Alias"}}'
        );

        return $this->runAndParseTable($process);
    }

    public function startableTakeoutContainers(): Collection
    {
        return $this->takeoutContainers()->reject(function ($container) {
            return Str::contains($container['status'], 'Up');
        });
    }

    public function stoppableTakeoutContainers(): Collection
    {
        return $this->takeoutContainers()->filter(function ($container) {
            return Str::contains($container['status'], 'Up');
        });
    }

    public function volumeIsAvailable(string $volumeName): bool
    {
        return $this->listMatchingVolumes($volumeName)->isEmpty();
    }

    public function listMatchingVolumes(string $volumeName): Collection
    {
        return $this->runAndParseTable(
            "docker ps -a --filter volume={$volumeName} --format 'table {{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}'"
        );
    }

    public function imageIsDownloaded(string $organization, string $imageName, ?string $tag): bool
    {
        return $this->shell->execQuietly(sprintf(
            'docker image inspect %s/%s:%s',
            $organization,
            $imageName,
            $tag
        ))->isSuccessful();
    }

    public function downloadImage(string $organization, string $imageName, ?string $tag): void
    {
        $this->shell->exec(sprintf(
            'docker pull %s/%s:%s',
            $organization,
            $imageName,
            $tag
        ));
    }

    public function bootContainer(string $dockerRunTemplate, array $parameters): void
    {
        $this->networking->ensureNetworkCreated();

        $command = sprintf(
            'docker run -d --name "${:container_name}" %s %s',
            $this->networking->networkSettings($parameters['alias'], $parameters['image_name']),
            $dockerRunTemplate
        );

        $process = $this->shell->exec($command, $parameters);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed installing ' . $parameters['image_name']);
        }
    }

    public function attachedVolumeName(string $containerId)
    {
        $response = $this->shell->execQuietly("docker inspect --format='{{json .Mounts}}' {$containerId}");

        return optional(json_decode($response->getOutput()))[0]->Name ?? null;
    }

    public function isDockerServiceRunning(): bool
    {
        return $this->shell->execQuietly('docker info')->isSuccessful();
    }

    public function stopDockerService(): void
    {
        $this->shell->execQuietly("test -z $(docker ps -q 2>/dev/null) && osascript -e 'quit app \"Docker\"'");
    }

    protected function runAndParseTable(string $command): Collection
    {
        return $this->formatter->rawTableOutputToCollection(
            $this->shell->execQuietly($command)->getOutput()
        );
    }
}
