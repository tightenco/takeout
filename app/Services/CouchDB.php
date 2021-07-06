<?php

namespace App\Services;

class CouchDB extends BaseService
{
    protected static $category = Category::DATABASE;

    protected $imageName = 'couchdb';
    protected $defaultPort = 5984;
    protected $prompts = [
        [
            'shortname' => 'volume',
            'prompt' => 'What is the Docker volume name?',
            'default' => 'couchdb_data',
        ],
    ];

    protected $dockerRunTemplate = '-p "${:port}":5984 \
        -v "${:volume}":/opt/couchdb/data \
        "${:organization}"/"${:image_name}":"${:tag}"';

    protected static $displayName = 'CouchDB';
}
