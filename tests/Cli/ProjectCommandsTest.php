<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class ProjectCommandsTest extends BaseCliTest
{
    #[Test]
    public function it_adds_and_lists_a_project(): void
    {
        $data = $this->ok('project:add', ['--name' => 'myproject', '--path' => '/tmp/myproject']);

        $this->assertSame('myproject', $data['name']);
        $this->assertSame('/tmp/myproject', $data['path']);

        $list = $this->ok('project:list');
        $projects = $this->listAt($list, 'projects');
        $this->assertCount(1, $projects);
        $this->assertSame('myproject', $this->itemAt($projects, 0)['name']);
    }

    #[Test]
    public function it_shows_a_project(): void
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);

        $data = $this->ok('project:show', ['--project' => '1']);
        $this->assertSame(1, $data['id']);
        $this->assertSame('proj', $data['name']);
    }

    #[Test]
    public function it_updates_a_project(): void
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);

        $data = $this->ok('project:set', ['--project' => '1', '--description' => 'Updated description']);
        $this->assertSame('Updated description', $data['description']);
    }

    #[Test]
    public function it_archives_and_restores_a_project(): void
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);

        $archived = $this->ok('project:archive', ['--project' => '1']);
        $this->assertNotNull($archived['archived_at']);

        $restored = $this->ok('project:restore', ['--project' => '1']);
        $this->assertNull($restored['archived_at']);
    }

    #[Test]
    public function it_returns_not_found_for_missing_project(): void
    {
        $error = $this->err('project:show', ['--project' => '999']);
        $this->assertSame('not_found', $error['code']);
    }
}
