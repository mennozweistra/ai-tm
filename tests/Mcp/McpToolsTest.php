<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;

final class McpToolsTest extends BaseMcpTest
{
    private function scaffold(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->phaseTools->add(ticket: 1, name: 'Phase A');
        $this->taskTools->add(phase: 1, name: 'Task 1', model: 'sonnet');
    }

    #[Test]
    public function it_creates_and_lists_a_project(): void
    {
        $result = $this->projectTools->add(name: 'myproject', path: '/tmp/myproject');
        $data = $this->data($result);
        $this->assertSame('myproject', $data['name']);

        $list = $this->data($this->projectTools->list());
        $projects = $list['projects'];
        $this->assertIsArray($projects);
        $this->assertCount(1, $projects);
    }

    #[Test]
    public function it_returns_not_found_for_missing_project(): void
    {
        $this->assertFailed($this->projectTools->show(project: 999), 'not_found');
    }

    #[Test]
    public function it_creates_and_lists_a_ticket(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $result = $this->ticketTools->add(project: 1, name: 'my ticket', template: self::SCAFFOLD_TEMPLATE);
        $data = $this->data($result);
        $this->assertSame('my ticket', $data['name']);
        $this->assertSame('pending', $data['status']);

        $list = $this->data($this->ticketTools->list(project: 1));
        $tickets = $list['tickets'];
        $this->assertIsArray($tickets);
        $this->assertCount(1, $tickets);
    }

    #[Test]
    public function it_creates_and_moves_phases(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);

        $a = $this->data($this->phaseTools->add(ticket: 1, name: 'A'));
        $b = $this->data($this->phaseTools->add(ticket: 1, name: 'B'));

        $aId = $a['id'];
        $bId = $b['id'];
        $this->assertIsInt($aId);
        $this->assertIsInt($bId);

        $this->phaseTools->move(phase: $bId, before: $aId);

        $list = $this->data($this->phaseTools->list(ticket: 1));
        $phases = $list['phases'];
        $this->assertIsArray($phases);
        $first = $phases[0];
        $this->assertIsArray($first);
        $this->assertSame('B', $first['name']);
    }

    #[Test]
    public function it_allows_pending_task_to_transition_directly_to_done(): void
    {
        $this->scaffold();

        $data = $this->data($this->taskTools->set(task: 1, status: 'done'));
        $this->assertSame('done', $data['status']);
    }

    #[Test]
    public function it_adds_and_lists_log_entries(): void
    {
        $this->scaffold();

        $this->logTools->add(type: 'note', ticket: 1, title: 'Greeting', ai_content: 'Hello');
        $this->logTools->add(type: 'decision', ticket: 1, title: 'a decision', ai_content: 'Decided');

        $list = $this->data($this->logTools->list(ticket: 1));
        $logs = $list['logs'];
        $this->assertIsArray($logs);
        $this->assertCount(2, $logs);
        $first = $logs[0];
        $this->assertIsArray($first);
        $this->assertSame('Greeting', $first['title']);
        $this->assertSame('Hello', $first['ai_content']);
    }

    #[Test]
    public function it_adds_a_ticket_with_priority_low(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $data = $this->data($this->ticketTools->add(project: 1, name: 't', priority: 'low', template: self::SCAFFOLD_TEMPLATE));

        $this->assertSame(1, $data['priority']);
    }

    #[Test]
    public function it_adds_a_ticket_with_priority_medium(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $data = $this->data($this->ticketTools->add(project: 1, name: 't', priority: 'medium', template: self::SCAFFOLD_TEMPLATE));

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_adds_a_ticket_with_priority_high(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $data = $this->data($this->ticketTools->add(project: 1, name: 't', priority: 'high', template: self::SCAFFOLD_TEMPLATE));

        $this->assertSame(3, $data['priority']);
    }

    #[Test]
    public function it_adds_a_ticket_without_priority_defaults_to_null(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $data = $this->data($this->ticketTools->add(project: 1, name: 't', template: self::SCAFFOLD_TEMPLATE));

        $this->assertNull($data['priority']);
    }

    #[Test]
    public function it_rejects_invalid_priority_string(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');

        $this->assertFailed(
            $this->ticketTools->add(project: 1, name: 't', priority: 'urgent', template: self::SCAFFOLD_TEMPLATE),
            'invalid_argument',
        );
    }

    #[Test]
    public function it_sets_ticket_priority_to_medium(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 't', template: self::SCAFFOLD_TEMPLATE);

        $data = $this->data($this->ticketTools->set(ticket: 1, priority: 'medium'));

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_leaves_ticket_priority_unchanged_when_omitted_on_set(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 't', priority: 'high', template: self::SCAFFOLD_TEMPLATE);

        $data = $this->data($this->ticketTools->set(ticket: 1, name: 'renamed'));

        $this->assertSame(3, $data['priority']);
        $this->assertSame('renamed', $data['name']);
    }

    #[Test]
    public function it_rejects_invalid_priority_string_on_set(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 't', template: self::SCAFFOLD_TEMPLATE);

        $this->assertFailed(
            $this->ticketTools->set(ticket: 1, priority: 'urgent'),
            'invalid_argument',
        );
    }

    #[Test]
    public function it_updates_ticket_priority_from_low_to_medium(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 't', priority: 'low', template: self::SCAFFOLD_TEMPLATE);

        $data = $this->data($this->ticketTools->set(ticket: 1, priority: 'medium'));

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_updates_ticket_priority_from_high_to_medium(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 't', priority: 'high', template: self::SCAFFOLD_TEMPLATE);

        $data = $this->data($this->ticketTools->set(ticket: 1, priority: 'medium'));

        $this->assertSame(2, $data['priority']);
    }

    #[Test]
    public function it_leaves_other_fields_unchanged_when_setting_priority(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(
            project: 1,
            name: 'original name',
            description: 'original description',
            ai_description: 'original ai description',
            type: 'bugfix',
            template: self::SCAFFOLD_TEMPLATE,
        );

        $data = $this->data($this->ticketTools->set(ticket: 1, priority: 'medium'));

        $this->assertSame(2, $data['priority']);
        $this->assertSame('original name', $data['name']);
        $this->assertSame('original description', $data['description']);
        $this->assertSame('original ai description', $data['ai_description']);
        $this->assertSame('bugfix', $data['type']);
        $this->assertSame('pending', $data['status']);
    }

    #[Test]
    public function it_shows_config(): void
    {
        $result = $this->configTools->list();
        $data = $this->data($result);

        // The five config lists come straight from Config::default() now that
        // ConfigLoader and config.toml are gone (ticket 172). Ticket, phase and
        // task statuses each hold the same seven canonical values (req 328);
        // log types include the added "assumption" type (req 309/320).
        $sevenStatuses = ['pending', 'active', 'done', 'blocked', 'failed', 'review', 'skipped'];
        $this->assertSame($sevenStatuses, $data['ticket_statuses']);
        $this->assertSame($sevenStatuses, $data['phase_statuses']);
        $this->assertSame($sevenStatuses, $data['task_statuses']);
        $this->assertSame(['progress', 'decision', 'blocker', 'note', 'code_fix', 'assumption'], $data['log_types']);
        $this->assertSame(['unverified', 'met', 'unmet'], $data['requirement_verifications']);
    }

    #[Test]
    public function it_creates_and_lists_a_requirement(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);

        $result = $this->requirementTools->add(ticket: 1, name: 'must login');
        $data = $this->data($result);
        $this->assertSame('must login', $data['name']);
        $this->assertSame('unverified', $data['verification']);

        $list = $this->data($this->requirementTools->list(ticket: 1));
        $requirements = $list['requirements'];
        $this->assertIsArray($requirements);
        $this->assertCount(1, $requirements);
        $first = $requirements[0];
        $this->assertIsArray($first);
        $this->assertSame('must login', $first['name']);
    }

    #[Test]
    public function it_returns_not_found_for_missing_requirement(): void
    {
        $this->assertFailed($this->requirementTools->show(requirement: 999), 'not_found');
    }

    #[Test]
    public function it_creates_and_moves_requirements(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);

        $a = $this->data($this->requirementTools->add(ticket: 1, name: 'A'));
        $b = $this->data($this->requirementTools->add(ticket: 1, name: 'B'));

        $aId = $a['id'];
        $bId = $b['id'];
        $this->assertIsInt($aId);
        $this->assertIsInt($bId);

        $this->requirementTools->move(requirement: $bId, before: $aId);

        $list = $this->data($this->requirementTools->list(ticket: 1));
        $requirements = $list['requirements'];
        $this->assertIsArray($requirements);
        $first = $requirements[0];
        $this->assertIsArray($first);
        $this->assertSame('B', $first['name']);
    }

    #[Test]
    public function it_sets_requirement_name_and_verification(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->requirementTools->add(ticket: 1, name: 'original');

        $data = $this->data($this->requirementTools->set(
            requirement: 1,
            name: 'updated',
            verification: 'met',
        ));

        $this->assertSame('updated', $data['name']);
        $this->assertSame('met', $data['verification']);
    }

    #[Test]
    public function it_deletes_a_requirement(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $this->requirementTools->add(ticket: 1, name: 'to delete');

        $result = $this->requirementTools->delete(requirement: 1);
        $data = $this->data($result);
        $this->assertTrue((bool) $data['deleted']);

        $this->assertFailed($this->requirementTools->show(requirement: 1), 'not_found');
    }

    #[Test]
    public function it_rejects_invalid_verification_on_requirement_add_and_set(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);

        $this->assertFailed(
            $this->requirementTools->add(ticket: 1, name: 'R', verification: 'nonsense'),
            'invalid_verification',
        );

        $this->requirementTools->add(ticket: 1, name: 'R');
        $this->assertFailed(
            $this->requirementTools->set(requirement: 1, verification: 'nonsense'),
            'invalid_verification',
        );
    }

    #[Test]
    public function it_adds_and_lists_a_question(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);

        $result = $this->questionTools->add(
            ticket: 1,
            name: 'naming',
            question: 'Should the column be foo or bar?',
            kind: 'ask',
            model: 'sonnet',
            background: 'Both names appear in the discussion.',
            recommendation: 'Use bar.',
        );
        $data = $this->data($result);
        $this->assertSame('naming', $data['name']);
        $this->assertSame('open', $data['state']);

        $list = $this->data($this->questionTools->list(ticket: 1));
        $questions = $list['questions'];
        $this->assertIsArray($questions);
        $this->assertCount(1, $questions);
    }

    #[Test]
    public function it_filters_questions_by_group(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);

        $open = $this->data($this->questionTools->add(ticket: 1, name: 'open one', question: 'q?', kind: 'ask', model: 'sonnet'));
        $resolved = $this->data($this->questionTools->add(ticket: 1, name: 'resolved one', question: 'q?', kind: 'ask', model: 'sonnet'));
        $openId = $open['id'];
        $resolvedId = $resolved['id'];
        $this->assertIsInt($openId);
        $this->assertIsInt($resolvedId);
        $this->questionTools->resolve(question: $resolvedId, state: 'accepted', resolution_quality: 'direct');

        $openList = $this->data($this->questionTools->list(ticket: 1, group: 'open'));
        $openQuestions = $openList['questions'];
        $this->assertIsArray($openQuestions);
        $this->assertCount(1, $openQuestions);
        $firstOpen = $openQuestions[0];
        $this->assertIsArray($firstOpen);
        $this->assertSame($openId, $firstOpen['id']);

        $unprocessedList = $this->data($this->questionTools->list(ticket: 1, group: 'resolved_unprocessed'));
        $unprocessedQuestions = $unprocessedList['questions'];
        $this->assertIsArray($unprocessedQuestions);
        $this->assertCount(1, $unprocessedQuestions);
        $firstUnprocessed = $unprocessedQuestions[0];
        $this->assertIsArray($firstUnprocessed);
        $this->assertSame($resolvedId, $firstUnprocessed['id']);
    }

    #[Test]
    public function it_resolves_a_question(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $added = $this->data($this->questionTools->add(ticket: 1, name: 'q', question: 'Which approach?', kind: 'ask', model: 'sonnet'));
        $addedId = $added['id'];
        $this->assertIsInt($addedId);

        $resolved = $this->data($this->questionTools->resolve(
            question: $addedId,
            state: 'answered',
            resolution_quality: 'clarified',
            answer: 'Use approach B.',
        ));

        $this->assertSame('answered', $resolved['state']);
        $this->assertSame('Use approach B.', $resolved['answer']);
        $this->assertSame('clarified', $resolved['resolution_quality']);
    }

    #[Test]
    public function it_withdraws_and_processes_a_question(): void
    {
        $this->projectTools->add(name: 'proj', path: '/tmp/proj');
        $this->ticketTools->add(project: 1, name: 'ticket', template: self::SCAFFOLD_TEMPLATE);
        $added = $this->data($this->questionTools->add(ticket: 1, name: 'q', question: 'Still relevant?', kind: 'ask', model: 'sonnet'));
        $addedId = $added['id'];
        $this->assertIsInt($addedId);

        $withdrawn = $this->data($this->questionTools->withdraw(question: $addedId, reason: 'No longer relevant.'));
        $this->assertSame('withdrawn', $withdrawn['state']);
        $this->assertNotNull($withdrawn['processed_at']);

        $added2 = $this->data($this->questionTools->add(ticket: 1, name: 'q2', question: 'Which way?', kind: 'ask', model: 'sonnet'));
        $added2Id = $added2['id'];
        $this->assertIsInt($added2Id);
        $this->questionTools->resolve(question: $added2Id, state: 'accepted', resolution_quality: 'direct');
        $processed = $this->data($this->questionTools->process(question: $added2Id));
        $this->assertNotNull($processed['processed_at']);
    }

    #[Test]
    public function it_returns_not_found_for_missing_question(): void
    {
        $this->assertFailed($this->questionTools->show(question: 999), 'not_found');
    }

    #[Test]
    public function it_response_envelope_matches_cli_format(): void
    {
        $result = $this->projectTools->add(name: 'envtest', path: '/tmp/envtest');
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertTrue((bool) $result['ok']);

        $errorResult = $this->projectTools->show(project: 9999);
        $this->assertArrayHasKey('ok', $errorResult);
        $this->assertArrayHasKey('error', $errorResult);
        $this->assertFalse((bool) $errorResult['ok']);
        $error = $errorResult['error'];
        $this->assertIsArray($error);
        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('message', $error);
    }

}
