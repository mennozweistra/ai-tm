<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;

final class TaskToolsTest extends BaseMcpTest
{
    private function scaffold(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->phaseTools->add(ticket: 1, name: 'Phase A');
    }

    #[Test]
    public function it_adds_a_task_with_max_attempts(): void
    {
        $this->scaffold();
        $data = $this->data($this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet', max_attempts: 3));

        $this->assertSame(3, $data['max_attempts']);
        $this->assertSame(0, $data['attempts']);
    }

    #[Test]
    public function it_sets_max_attempts_on_a_task(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');

        $data = $this->data($this->taskTools->set(task: 1, max_attempts: 5));
        $this->assertSame(5, $data['max_attempts']);
    }

    #[Test]
    public function it_sets_attempts_on_a_task(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');
        $this->taskTools->set(task: 1, status: 'active');

        $data = $this->data($this->taskTools->set(task: 1, attempts: 2));
        $this->assertSame(2, $data['attempts']);
    }

    #[Test]
    public function it_shows_max_attempts_and_attempts_in_task_show(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet', max_attempts: 4);
        $this->taskTools->set(task: 1, status: 'active');
        $this->taskTools->set(task: 1, attempts: 1);

        $data = $this->data($this->taskTools->show(task: 1));
        $this->assertSame(4, $data['max_attempts']);
        $this->assertSame(1, $data['attempts']);
    }

    #[Test]
    public function it_returns_max_attempts_and_attempts_in_task_list(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet', max_attempts: 2);

        $list = $this->data($this->taskTools->list(phase: 1));
        $tasks = $list['tasks'];
        $this->assertIsArray($tasks);
        $this->assertCount(1, $tasks);
        $task = $tasks[0];
        $this->assertIsArray($task);
        $this->assertSame(2, $task['max_attempts']);
        $this->assertSame(0, $task['attempts']);
    }

    #[Test]
    public function it_adds_a_task_supplying_only_phase_name_and_model(): void
    {
        $this->scaffold();
        $data = $this->data($this->taskTools->add(phase: 1, name: 'Task', model: 'haiku'));

        $this->assertSame('haiku', $data['model']);
    }

    #[Test]
    public function it_accepts_a_null_model_on_add(): void
    {
        $this->scaffold();
        $data = $this->data($this->taskTools->add(phase: 1, name: 'Task', model: null, actor: 'human'));

        $this->assertNull($data['model']);
    }

    #[Test]
    public function it_rejects_a_blank_model_on_add(): void
    {
        $this->scaffold();
        $result = $this->taskTools->add(phase: 1, name: 'Task', model: '   ');

        $this->assertFailed($result, 'invalid_argument');
    }

    #[Test]
    public function it_updates_the_model_on_a_task(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');

        $data = $this->data($this->taskTools->set(task: 1, model: 'opus'));
        $this->assertSame('opus', $data['model']);
    }

    #[Test]
    public function it_leaves_the_model_unchanged_when_omitted_on_set(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');

        $data = $this->data($this->taskTools->set(task: 1, status: 'active'));
        $this->assertSame('sonnet', $data['model']);
    }

    #[Test]
    public function it_rejects_a_blank_model_on_set(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');

        $result = $this->taskTools->set(task: 1, model: '   ');
        $this->assertFailed($result, 'invalid_argument');
    }

    #[Test]
    public function it_lists_a_phase_only_with_the_new_fields_always_present(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');

        $tasks = $this->data($this->taskTools->list(phase: 1))['tasks'];
        $this->assertIsArray($tasks);
        $task = $tasks[0];
        $this->assertIsArray($task);
        $this->assertSame(1, $task['ticket_id']);
        $this->assertSame(1, $task['project_id']);
        $this->assertNull($task['started_at']);
        $this->assertNull($task['finished_at']);
    }

    #[Test]
    public function it_filters_across_phases_by_a_finished_date_range(): void
    {
        $this->scaffold();
        $this->phaseTools->add(ticket: 1, name: 'Phase B');

        $this->taskTools->add(phase: 1, name: 'Finished', model: 'sonnet');
        $this->taskTools->set(task: 1, status: 'active');
        $this->taskTools->set(task: 1, status: 'done');

        $this->taskTools->add(phase: 2, name: 'Still pending', model: 'sonnet');

        $now = new \DateTimeImmutable();
        $from = $now->modify('-1 day')->format('Y-m-d');
        $to = $now->modify('+1 day')->format('Y-m-d');

        $tasks = $this->data($this->taskTools->list(finished_from: $from, finished_to: $to))['tasks'];
        $this->assertIsArray($tasks);
        $this->assertCount(1, $tasks);
        $task = $tasks[0];
        $this->assertIsArray($task);
        $this->assertSame('Finished', $task['name']);
        $this->assertSame(1, $task['ticket_id']);
        $this->assertSame(1, $task['project_id']);
        $this->assertIsString($task['finished_at']);
    }

    #[Test]
    public function it_honours_order_by_when_passed_through(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'First', model: 'sonnet');
        $this->taskTools->add(phase: 1, name: 'Second', model: 'sonnet');
        $this->taskTools->move(task: 1, after: 2);

        $byOrder = $this->data($this->taskTools->list(phase: 1, order_by: 'order'))['tasks'];
        $this->assertIsArray($byOrder);
        $this->assertSame(['Second', 'First'], array_column($byOrder, 'name'));

        $byCreated = $this->data($this->taskTools->list(phase: 1, order_by: 'created'))['tasks'];
        $this->assertIsArray($byCreated);
        $this->assertSame(['First', 'Second'], array_column($byCreated, 'name'));
    }

    #[Test]
    public function it_rejects_an_unparseable_date_filter(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');

        $result = $this->taskTools->list(phase: 1, created_from: 'not-a-date');
        $this->assertFailed($result, 'invalid_argument');
    }

    #[Test]
    public function it_rejects_an_unknown_order_by(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');

        $result = $this->taskTools->list(phase: 1, order_by: 'bogus');
        $this->assertFailed($result, 'invalid_argument');
    }

    #[Test]
    public function it_shows_started_and_finished_on_task_show(): void
    {
        $this->scaffold();
        $this->taskTools->add(phase: 1, name: 'Task', model: 'sonnet');
        $this->taskTools->set(task: 1, status: 'active');
        $this->taskTools->set(task: 1, status: 'done');

        $data = $this->data($this->taskTools->show(task: 1));
        $this->assertIsString($data['started_at']);
        $this->assertIsString($data['finished_at']);
    }
}
