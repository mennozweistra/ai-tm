<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class TicketCommandsTest extends BaseCliTest
{
    private function addProject(): int
    {
        $data = $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);

        return $this->intAt($data, 'id');
    }

    #[Test]
    public function it_adds_and_lists_a_ticket(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket']);

        $this->assertSame('my ticket', $data['name']);
        $this->assertSame('pending', $data['status']);

        $list = $this->ok('ticket:list', ['--project' => (string) $projectId]);
        $tickets = $this->listAt($list, 'tickets');
        $this->assertCount(1, $tickets);
    }

    #[Test]
    public function it_shows_a_ticket(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket']);

        $data = $this->ok('ticket:show', ['--ticket' => '1']);
        $this->assertSame(1, $data['id']);
    }

    #[Test]
    public function it_shows_a_ticket_outline(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket', '--description' => 'ticket desc', '--ai-description' => 'ticket ai desc']);
        $this->ok('phase:add', ['--ticket' => '1', '--name' => 'Phase A', '--description' => 'phase desc', '--ai-description' => 'phase ai desc']);
        $this->ok('task:add', ['--phase' => '1', '--name' => 'Task A', '--model' => 'sonnet', '--description' => 'task desc', '--ai-description' => 'task ai desc']);
        $this->ok('requirement:add', ['--ticket' => '1', '--name' => 'Req A', '--description' => 'req desc', '--ai-description' => 'req ai desc']);

        $data = $this->ok('ticket:show', ['--ticket' => '1', '--outline' => null]);

        $this->assertArrayHasKey('phases', $data);
        $this->assertArrayHasKey('requirements', $data);
        $this->assertArrayNotHasKey('logs', $data);
        $this->assertArrayNotHasKey('transitions', $data);

        $phases = $this->listAt($data, 'phases');
        $phase = $this->itemAt($phases, 0);
        $this->assertArrayNotHasKey('description', $phase);
        $this->assertArrayNotHasKey('ai_description', $phase);

        $tasks = $this->listAt($phase, 'tasks');
        $task = $this->itemAt($tasks, 0);
        $this->assertArrayNotHasKey('description', $task);
        $this->assertArrayNotHasKey('ai_description', $task);
        $this->assertArrayNotHasKey('result', $task);
        $this->assertArrayNotHasKey('ai_result', $task);

        $requirements = $this->listAt($data, 'requirements');
        $requirement = $this->itemAt($requirements, 0);
        $this->assertArrayNotHasKey('description', $requirement);
        $this->assertArrayNotHasKey('ai_description', $requirement);
    }

    #[Test]
    public function it_prefers_outline_over_deep_when_both_flags_are_set(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket']);
        $this->ok('phase:add', ['--ticket' => '1', '--name' => 'Phase A', '--description' => 'phase desc', '--ai-description' => 'phase ai desc']);

        $data = $this->ok('ticket:show', ['--ticket' => '1', '--outline' => null, '--deep' => null]);

        $this->assertArrayNotHasKey('logs', $data);
        $this->assertArrayNotHasKey('transitions', $data);

        $phases = $this->listAt($data, 'phases');
        $phase = $this->itemAt($phases, 0);
        $this->assertArrayNotHasKey('description', $phase);
        $this->assertArrayNotHasKey('ai_description', $phase);
    }

    #[Test]
    public function it_updates_a_ticket_status(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket']);

        $data = $this->ok('ticket:set', ['--ticket' => '1', '--status' => 'active']);
        $this->assertSame('active', $data['status']);
    }

    #[Test]
    public function it_archives_and_restores_a_ticket(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket']);

        $archived = $this->ok('ticket:archive', ['--ticket' => '1']);
        $this->assertNotNull($archived['archived_at']);

        $restored = $this->ok('ticket:restore', ['--ticket' => '1']);
        $this->assertNull($restored['archived_at']);
    }

    #[Test]
    public function it_adds_a_ticket_with_default_type_feature(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'feature ticket']);

        $this->assertSame('feature', $data['type']);
    }

    #[Test]
    public function it_adds_a_ticket_with_explicit_type(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'bug ticket', '--type' => 'bugfix']);

        $this->assertSame('bugfix', $data['type']);
    }

    #[Test]
    public function it_updates_ticket_type_via_set(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket']);

        $data = $this->ok('ticket:set', ['--ticket' => '1', '--type' => 'bugfix']);

        $this->assertSame('bugfix', $data['type']);
    }

    #[Test]
    public function it_shows_type_field_in_ticket_show(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 'my ticket', '--type' => 'bugfix']);

        $data = $this->ok('ticket:show', ['--ticket' => '1']);

        $this->assertSame('bugfix', $data['type']);
    }

    #[Test]
    public function it_adds_a_ticket_with_priority_low(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't', '--priority' => 'low']);

        $this->assertSame(1, $data['priority']);
    }

    #[Test]
    public function it_adds_a_ticket_with_priority_medium(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't', '--priority' => 'medium']);

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_adds_a_ticket_with_priority_high(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't', '--priority' => 'high']);

        $this->assertSame(3, $data['priority']);
    }

    #[Test]
    public function it_adds_a_ticket_without_priority_defaults_to_null(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't']);

        $this->assertNull($data['priority']);
    }

    #[Test]
    public function it_rejects_invalid_priority_string_on_add(): void
    {
        $projectId = $this->addProject();

        $error = $this->err('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't', '--priority' => 'urgent']);

        $this->assertSame('invalid_argument', $error['code']);
    }

    #[Test]
    public function it_sets_ticket_priority_to_medium(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't']);

        $data = $this->ok('ticket:set', ['--ticket' => '1', '--priority' => 'medium']);

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_leaves_ticket_priority_unchanged_when_omitted_on_set(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't', '--priority' => 'high']);

        $data = $this->ok('ticket:set', ['--ticket' => '1', '--status' => 'active']);

        $this->assertSame(3, $data['priority']);
    }

    #[Test]
    public function it_rejects_invalid_priority_string_on_set(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't']);

        $error = $this->err('ticket:set', ['--ticket' => '1', '--priority' => 'urgent']);

        $this->assertSame('invalid_argument', $error['code']);
    }

    #[Test]
    public function it_updates_ticket_priority_from_low_to_medium(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't', '--priority' => 'low']);

        $data = $this->ok('ticket:set', ['--ticket' => '1', '--priority' => 'medium']);

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_updates_ticket_priority_from_high_to_medium(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => (string) $projectId, '--name' => 't', '--priority' => 'high']);

        $data = $this->ok('ticket:set', ['--ticket' => '1', '--priority' => 'medium']);

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_leaves_other_fields_unchanged_when_setting_priority(): void
    {
        $projectId = $this->addProject();
        $this->ok('ticket:add', [
            '--template' => self::SCAFFOLD_TEMPLATE,
            '--project' => (string) $projectId,
            '--name' => 'original name',
            '--description' => 'original description',
            '--ai-description' => 'original ai description',
            '--type' => 'bugfix',
        ]);

        $data = $this->ok('ticket:set', ['--ticket' => '1', '--priority' => 'medium']);

        $this->assertSame(2, $data['priority']);
        $this->assertSame('original name', $data['name']);
        $this->assertSame('original description', $data['description']);
        $this->assertSame('original ai description', $data['ai_description']);
        $this->assertSame('bugfix', $data['type']);
        $this->assertSame('pending', $data['status']);
    }
}
