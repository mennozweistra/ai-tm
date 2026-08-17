<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class TaskCommandsTest extends BaseCliTest
{
    private function addPhase(): int
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);
        $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => '1', '--name' => 'ticket']);
        $data = $this->ok('phase:add', ['--ticket' => '1', '--name' => 'Phase A']);

        return $this->intAt($data, 'id');
    }

    #[Test]
    public function it_adds_and_lists_tasks(): void
    {
        $phaseId = $this->addPhase();

        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task 1', '--model' => 'sonnet']);
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task 2', '--model' => 'sonnet']);

        $list = $this->ok('task:list', ['--phase' => (string) $phaseId]);
        $tasks = $this->listAt($list, 'tasks');
        $this->assertCount(2, $tasks);
        $this->assertSame('Task 1', $this->itemAt($tasks, 0)['name']);
    }

    #[Test]
    public function it_shows_a_task(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task 1', '--model' => 'sonnet']);

        $data = $this->ok('task:show', ['--task' => '1']);
        $this->assertSame('Task 1', $data['name']);
    }

    #[Test]
    public function it_updates_a_task_status(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task 1', '--model' => 'sonnet']);

        $data = $this->ok('task:set', ['--task' => '1', '--status' => 'active']);
        $this->assertSame('active', $data['status']);
    }

    #[Test]
    public function it_allows_pending_to_transition_directly_to_done(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task 1', '--model' => 'sonnet']);

        $data = $this->ok('task:set', ['--task' => '1', '--status' => 'done']);
        $this->assertSame('done', $data['status']);
    }

    #[Test]
    public function it_moves_a_task(): void
    {
        $phaseId = $this->addPhase();
        $a = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'A', '--model' => 'sonnet']);
        $b = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'B', '--model' => 'sonnet']);
        $aId = $this->intAt($a, 'id');
        $bId = $this->intAt($b, 'id');

        $this->ok('task:move', ['--task' => (string) $bId, '--before' => (string) $aId]);

        $list = $this->ok('task:list', ['--phase' => (string) $phaseId]);
        $tasks = $this->listAt($list, 'tasks');
        $this->assertSame('B', $this->itemAt($tasks, 0)['name']);
        $this->assertSame('A', $this->itemAt($tasks, 1)['name']);
    }

    #[Test]
    public function it_deletes_a_task(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task 1', '--model' => 'sonnet']);

        $data = $this->ok('task:delete', ['--task' => '1']);
        $this->assertTrue((bool) $data['deleted']);
    }

    #[Test]
    public function it_adds_a_task_with_max_attempts(): void
    {
        $phaseId = $this->addPhase();
        $data = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet', '--max-attempts' => '3']);
        $this->assertSame(3, $data['max_attempts']);
        $this->assertSame(0, $data['attempts']);
    }

    #[Test]
    public function it_sets_max_attempts_on_a_task(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $data = $this->ok('task:set', ['--task' => '1', '--max-attempts' => '5']);
        $this->assertSame(5, $data['max_attempts']);
    }

    #[Test]
    public function it_sets_attempts_on_a_task(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);
        $this->ok('task:set', ['--task' => '1', '--status' => 'active']);

        $data = $this->ok('task:set', ['--task' => '1', '--attempts' => '2']);
        $this->assertSame(2, $data['attempts']);
    }

    #[Test]
    public function it_shows_max_attempts_and_attempts_in_task_show(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet', '--max-attempts' => '4']);
        $this->ok('task:set', ['--task' => '1', '--attempts' => '1']);

        $data = $this->ok('task:show', ['--task' => '1']);
        $this->assertSame(4, $data['max_attempts']);
        $this->assertSame(1, $data['attempts']);
    }

    #[Test]
    public function it_sets_the_actor_on_a_task(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $data = $this->ok('task:set', ['--task' => '1', '--actor' => 'human']);
        $this->assertSame('human', $data['actor']);
    }

    #[Test]
    public function it_adds_a_task_supplying_only_phase_name_and_model(): void
    {
        $phaseId = $this->addPhase();
        $data = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'haiku']);

        $this->assertSame('haiku', $data['model']);
    }

    #[Test]
    public function it_fails_task_add_when_model_is_omitted(): void
    {
        $phaseId = $this->addPhase();

        $error = $this->err('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task']);
        $this->assertSame('invalid_argument', $error['code']);
    }

    #[Test]
    public function it_treats_the_literal_null_as_no_model_on_task_add(): void
    {
        $phaseId = $this->addPhase();

        $data = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'null']);
        $this->assertNull($data['model']);
    }

    #[Test]
    public function it_treats_the_literal_null_case_insensitively(): void
    {
        $phaseId = $this->addPhase();

        $data = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'NULL']);
        $this->assertNull($data['model']);
    }

    #[Test]
    public function it_rejects_a_blank_model_on_task_add(): void
    {
        $phaseId = $this->addPhase();

        $error = $this->err('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => '   ']);
        $this->assertSame('invalid_argument', $error['code']);
    }

    #[Test]
    public function it_updates_the_model_on_task_set(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $data = $this->ok('task:set', ['--task' => '1', '--model' => 'opus']);
        $this->assertSame('opus', $data['model']);
    }

    #[Test]
    public function it_leaves_the_model_unchanged_when_omitted_on_task_set(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $data = $this->ok('task:set', ['--task' => '1', '--status' => 'active']);
        $this->assertSame('sonnet', $data['model']);
    }

    #[Test]
    public function it_rejects_a_blank_model_on_task_set(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $error = $this->err('task:set', ['--task' => '1', '--model' => '   ']);
        $this->assertSame('invalid_argument', $error['code']);
    }

    #[Test]
    public function it_lists_a_phase_with_the_new_fields_always_present(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $tasks = $this->listAt($this->ok('task:list', ['--phase' => (string) $phaseId]), 'tasks');
        $this->assertCount(1, $tasks);
        $task = $this->itemAt($tasks, 0);
        $this->assertSame(1, $task['ticket_id']);
        $this->assertSame(1, $task['project_id']);
        $this->assertNull($task['started_at']);
        $this->assertNull($task['finished_at']);
    }

    #[Test]
    public function it_filters_across_phases_by_a_finished_date_range(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('phase:add', ['--ticket' => '1', '--name' => 'Phase B']);

        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Finished', '--model' => 'sonnet']);
        $this->ok('task:set', ['--task' => '1', '--status' => 'active']);
        $this->ok('task:set', ['--task' => '1', '--status' => 'done']);

        $this->ok('task:add', ['--phase' => '2', '--name' => 'Still pending', '--model' => 'sonnet']);

        $now = new \DateTimeImmutable();
        $from = $now->modify('-1 day')->format('Y-m-d');
        $to = $now->modify('+1 day')->format('Y-m-d');

        $tasks = $this->listAt(
            $this->ok('task:list', ['--finished-from' => $from, '--finished-to' => $to]),
            'tasks',
        );
        $this->assertCount(1, $tasks);
        $task = $this->itemAt($tasks, 0);
        $this->assertSame('Finished', $task['name']);
        $this->assertSame(1, $task['ticket_id']);
        $this->assertSame(1, $task['project_id']);
        $this->assertIsString($task['finished_at']);
    }

    #[Test]
    public function it_honours_order_by_when_passed_through(): void
    {
        $phaseId = $this->addPhase();
        $a = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'First', '--model' => 'sonnet']);
        $b = $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Second', '--model' => 'sonnet']);
        $this->ok('task:move', ['--task' => (string) $this->intAt($a, 'id'), '--after' => (string) $this->intAt($b, 'id')]);

        $byOrder = $this->listAt(
            $this->ok('task:list', ['--phase' => (string) $phaseId, '--order-by' => 'order']),
            'tasks',
        );
        $this->assertSame(['Second', 'First'], array_column($byOrder, 'name'));

        $byCreated = $this->listAt(
            $this->ok('task:list', ['--phase' => (string) $phaseId, '--order-by' => 'created']),
            'tasks',
        );
        $this->assertSame(['First', 'Second'], array_column($byCreated, 'name'));
    }

    #[Test]
    public function it_rejects_an_unparseable_date_filter(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $error = $this->err('task:list', ['--phase' => (string) $phaseId, '--created-from' => 'not-a-date']);
        $this->assertSame('invalid_argument', $error['code']);
    }

    #[Test]
    public function it_rejects_an_unknown_order_by(): void
    {
        $phaseId = $this->addPhase();
        $this->ok('task:add', ['--phase' => (string) $phaseId, '--name' => 'Task', '--model' => 'sonnet']);

        $error = $this->err('task:list', ['--phase' => (string) $phaseId, '--order-by' => 'bogus']);
        $this->assertSame('invalid_argument', $error['code']);
    }
}
