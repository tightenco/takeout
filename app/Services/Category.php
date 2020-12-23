<?php

namespace App\Services;

use App\Services;

abstract class Category
{
    const CACHE = 'Cache';
    const DATABASE = 'Database';
    const MAIL = 'Mail';
    const SEARCH = 'Search';
    const STORAGE = 'Storage';
    const TOOLS = 'Tools';

    public static function fromServiceName(string $serviceName): string
    {
        $serviceByCategory = (new Services)->allByCategory();
        return array_key_exists($serviceName, $serviceByCategory) ?
            strtolower($serviceByCategory[$serviceName]) :
            'other';
    }

    public static function fromContainerName(string $containerName): string
    {
        $serviceName = array_slice(
            explode('--', $containerName),
            1,
            1
        );
        $serviceName = reset($serviceName);
        return self::fromServiceName($serviceName);
    }
}
