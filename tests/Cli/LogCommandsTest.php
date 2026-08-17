<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class LogCommandsTest extends BaseCliTest
{
    private function scaffold(): void
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => '1', '--name' => 'ticket']);
        $this->ok('phase:add', ['--ticket' => '1', '--name' => 'Phase A']);
        $this->ok('task:add', ['--phase' => '1', '--name' => 'Task 1', '--model' => 'sonnet']);
    }

    #[Test]
    public function it_adds_a_ticket_log_entry(): void
    {
        $this->scaffold();

        $data = $this->ok('log:add', ['--ticket' => '1', '--type' => 'note', '--title' => 'Greeting', '--ai-content' => 'Hello']);
        $this->assertSame('note', $data['log_type']);
        $this->assertSame('Greeting', $data['title']);
        $this->assertSame('Hello', $data['ai_content']);
        $this->assertSame(1, $data['ticket_id']);
        $this->assertNull($data['phase_id']);
    }

    #[Test]
    public function it_adds_a_task_log_entry(): void
    {
        $this->scaffold();

        $data = $this->ok('log:add', ['--task' => '1', '--type' => 'progress', '--title' => 'progress note']);
        $this->assertSame(1, $data['task_id']);
        $this->assertSame(1, $data['phase_id']);
        $this->assertSame(1, $data['ticket_id']);
    }

    #[Test]
    public function it_lists_logs_by_ticket(): void
    {
        $this->scaffold();
        $this->ok('log:add', ['--ticket' => '1', '--type' => 'note', '--title' => 'first note']);
        $this->ok('log:add', ['--ticket' => '1', '--type' => 'decision', '--title' => 'a decision']);

        $data = $this->ok('log:list', ['--ticket' => '1']);
        $this->assertCount(2, $this->listAt($data, 'logs'));
    }

    #[Test]
    public function it_lists_logs_by_task(): void
    {
        $this->scaffold();
        $this->ok('log:add', ['--task' => '1', '--type' => 'progress', '--title' => 'progress note']);

        $data = $this->ok('log:list', ['--task' => '1']);
        $logs = $this->listAt($data, 'logs');
        $this->assertCount(1, $logs);
        $this->assertSame(1, $this->itemAt($logs, 0)['task_id']);
    }
}
