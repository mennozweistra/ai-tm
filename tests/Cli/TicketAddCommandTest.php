<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use AiToolset\AiLib\Repositories\TemplateRepository;
use AiToolset\Tm\Cli\Application;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers `ticket:add`'s mandatory `--template` option specifically: a
 * missing or unknown template is rejected, and a valid one copies its
 * phases and tasks onto the new ticket. The generic add/list/set/archive
 * behaviour of `ticket:add` lives in TicketCommandsTest, scaffolded through
 * the structure-free SCAFFOLD_TEMPLATE from BaseCliTest.
 */
final class TicketAddCommandTest extends BaseCliTest
{
    private const string VALID_TEMPLATE = 'cli_ticket_add_test_template';

    private string $validTemplatePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validTemplatePath = Application::templatesPath() . '/' . self::VALID_TEMPLATE . '.toml';
        if (is_file($this->validTemplatePath)) {
            unlink($this->validTemplatePath);
        }
        new TemplateRepository([Application::templatesPath()], Application::templatesPath())->write(self::VALID_TEMPLATE, [
            'description' => '',
            'ai_description' => '',
            'phases' => [
                ['name' => 'Phase One', 'description' => '', 'ai_description' => '', 'order' => 1, 'tasks' => [
                    ['name' => 'Task A', 'description' => '', 'ai_description' => '', 'actor' => 'agent', 'order' => 1],
                ]],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->validTemplatePath)) {
            unlink($this->validTemplatePath);
        }

        parent::tearDown();
    }

    private function addProject(): int
    {
        $data = $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);

        return $this->intAt($data, 'id');
    }

    #[Test]
    public function it_rejects_ticket_creation_when_template_is_missing(): void
    {
        $projectId = $this->addProject();

        $error = $this->err('ticket:add', ['--project' => (string) $projectId, '--name' => 'no template']);

        $this->assertSame('invalid_argument', $error['code']);
    }

    #[Test]
    public function it_rejects_ticket_creation_when_template_name_is_unknown_and_lists_available_templates(): void
    {
        $projectId = $this->addProject();

        $error = $this->err('ticket:add', [
            '--project' => (string) $projectId,
            '--name' => 'ghost template',
            '--template' => 'ghost',
        ]);

        $this->assertSame('invalid_argument', $error['code']);
        $message = $error['message'];
        $this->assertIsString($message);
        $this->assertStringContainsString(self::VALID_TEMPLATE, $message);
    }

    #[Test]
    public function it_creates_a_ticket_with_phases_and_tasks_from_a_valid_template(): void
    {
        $projectId = $this->addProject();

        $data = $this->ok('ticket:add', [
            '--project' => (string) $projectId,
            '--name' => 'templated ticket',
            '--template' => self::VALID_TEMPLATE,
        ]);
        $this->assertSame('templated ticket', $data['name']);
        $ticketId = $this->intAt($data, 'id');

        $deep = $this->ok('ticket:show', ['--ticket' => (string) $ticketId, '--deep' => null]);
        $phases = $this->listAt($deep, 'phases');
        $this->assertCount(1, $phases);

        $phase = $this->itemAt($phases, 0);
        $this->assertSame('Phase One', $phase['name']);

        $tasks = $this->listAt($phase, 'tasks');
        $this->assertCount(1, $tasks);
        $this->assertSame('Task A', $this->itemAt($tasks, 0)['name']);
    }
}
