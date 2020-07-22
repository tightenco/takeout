<?php

namespace Tests\Feature;

use App\Services\MeiliSearch;
use App\Services\MySql;
use App\Shell\Shell;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Mockery as M;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BaseServiceTest extends TestCase
{
    /** @test */
    function it_generates_shortname()
    {
        $meilisearch = app(MeiliSearch::class);
        $this->assertEquals('meilisearch', $meilisearch->shortName());
    }

    /** @test */
    function it_lists_10_newest_available_tags_for_service()
    {
        $mysql = app(MySql::class);
        $tags = $mysql->getTags();
        $this->assertEquals('latest', $tags[0]);
        $this->assertTrue(in_array('5.7', $tags));
        $this->assertCount(10, $tags);
    }

    /** @test */
    function it_installs_services()
    {
        app()->instance('console', M::mock(Command::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        }));

        $this->mock(Shell::class, function ($mock) {
            $process = M::mock(Process::class);
            $process->shouldReceive('getExitCode')->twice()->andReturn(0);

            $mock->shouldReceive('execQuietly')->once()->andReturn($process);
            $mock->shouldReceive('exec')->once()->with(M::on(function ($arg) {
                return Str::contains($arg, 'meilisearch');
            }))->andReturn($process);
        });

        $this->mock(Docker::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(true);
        });

        $service = app(MeiliSearch::Class); // Extends BaseService
        $service->install();
    }
}
