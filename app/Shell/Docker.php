<?php

namespace App\Shell;

use Exception;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class Docker
{
    protected $shell;

    public function __construct(Shell $shell)
    {
        $this->shell = $shell;
    }

    public function removeContainer(string $containerId): void
    {
        $this->stopContainer($containerId);

        $process = $this->shell->exec('docker rm ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed removing container ' . $containerId);
        }
    }

    public function stopContainer(string $containerId): void
    {
        $process = $this->shell->exec('docker stop ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed stopping container ' . $containerId);
        }
    }

    public function startContainer(string $containerId): void
    {
        $process = $this->shell->exec('docker start ' . $containerId);

        if (! $process->isSuccessful()) {
            throw new Exception('Failed starting container ' . $containerId);
        }
    }

    public function isInstalled(): bool
    {
        $process = $this->shell->execQuietly('docker --version 2>&1');

        return $process->isSuccessful();
    }

    public function takeoutContainers(): Collection
    {
        return $this->containerRawOutputToCollection($this->takeoutContainersRawOutput());
    }

    public function allContainers(): Collection
    {
        return $this->containerRawOutputToCollection($this->allContainersRawOutput());
    }

    /**
     * Given the raw string of output from Docker, return a collection of
     * associative arrays, with the keys lowercased and slugged using underscores
     *
     * @param  string $output Docker command output
     * @return Collection     Collection of associative arrays
     */
    protected function containerRawOutputToCollection($output): Collection
    {
        $containers = collect(explode("\n", $output))->map(function ($line) {
            return explode('|', $line);
        })->filter();

        $keys = array_map('App\underscore_slug', $containers->shift());

        return $containers->map(function ($container) use ($keys) {
            return array_combine($keys, $container);
        });
    }

    protected function takeoutContainersRawOutput(): string
    {
        $dockerProcessStatusString = 'docker ps -a --filter "name=TO-" --format "table {{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}"';
        return trim($this->shell->execQuietly($dockerProcessStatusString)->getOutput());
    }

    protected function allContainersRawOutput(): string
    {
        $dockerProcessStatusString = 'docker ps --format "table {{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}"';
        return trim($this->shell->execQuietly($dockerProcessStatusString)->getOutput());
    }

    public function imageIsDownloaded(string $organization, string $imageName, ?string $tag): bool
    {
        $process = $this->shell->execQuietly(sprintf(
            'docker image inspect %s/%s:%s',
            $organization,
            $imageName,
            $tag
        ));

        return $process->isSuccessful();
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
        $process = $this->shell->exec('docker run -d --name "${:container_name}" ' . $dockerRunTemplate, $parameters);

        if (! $process->isSuccessful()) {
            throw new Exception("Failed installing " .  $parameters['image_name']);
        }
    }

    public function attachedVolumeName(string $containerId)
    {
        $response = $this->shell->execQuietly("docker inspect --format='{{json .Mounts}}' {$containerId}");
        $jsonResponse = json_decode($response->getOutput());
        return optional($jsonResponse)[0]->Name ?? null;
    }

    public function isDockerServiceRunning(): bool
    {
        $response = $this->shell->execQuietly('docker info');
        return $response->isSuccessful();
    }

    public function stopDockerService(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->shell->execQuietly("wsl -t docker-desktop");
            $this->shell->execQuietly("wsl -t docker-desktop-data");
        } else if (PHP_OS_FAMILY === 'Darwin') {
            $this->shell->execQuietly("test -z $(docker ps -q 2>/dev/null) && osascript -e 'quit app \"Docker\"'");
        } else {
            $this->shell->execQuietly("systemctl stop docker");
        }
    }
}
