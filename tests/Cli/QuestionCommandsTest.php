<?php

declare(strict_types=1);

namespace AiToolset\Tm\Tests\Cli;

use PHPUnit\Framework\Attributes\Test;

final class QuestionCommandsTest extends BaseCliTest
{
    private function addTicket(): int
    {
        $this->ok('project:add', ['--name' => 'proj', '--path' => '/tmp/proj']);
        $data = $this->ok('ticket:add', ['--template' => self::SCAFFOLD_TEMPLATE, '--project' => '1', '--name' => 'my ticket']);

        return $this->intAt($data, 'id');
    }

    #[Test]
    public function it_adds_and_lists_questions(): void
    {
        $ticketId = $this->addTicket();

        $this->ok('question:add', [
            '--ticket' => (string) $ticketId,
            '--name' => 'naming',
            '--question' => 'Should the column be foo or bar?',
            '--kind' => 'ask',
            '--model' => 'sonnet',
            '--recommendation' => 'Use bar.',
        ]);

        $list = $this->ok('question:list', ['--ticket' => (string) $ticketId]);
        $questions = $this->listAt($list, 'questions');
        $this->assertCount(1, $questions);
        $this->assertSame('naming', $this->itemAt($questions, 0)['name']);
        $this->assertSame('open', $this->itemAt($questions, 0)['state']);
    }

    #[Test]
    public function it_shows_a_question(): void
    {
        $ticketId = $this->addTicket();
        $added = $this->ok('question:add', [
            '--ticket' => (string) $ticketId,
            '--name' => 'naming',
            '--question' => 'foo or bar?',
            '--kind' => 'ask',
            '--model' => 'sonnet',
        ]);

        $data = $this->ok('question:show', ['--question' => (string) $this->intAt($added, 'id')]);
        $this->assertSame('naming', $data['name']);
        $this->assertSame('foo or bar?', $data['question']);
    }

    #[Test]
    public function it_lists_questions_filtered_by_group(): void
    {
        $ticketId = $this->addTicket();
        $open = $this->ok('question:add', [
            '--ticket' => (string) $ticketId, '--name' => 'open', '--question' => 'q?', '--kind' => 'ask', '--model' => 'sonnet',
        ]);
        $resolved = $this->ok('question:add', [
            '--ticket' => (string) $ticketId, '--name' => 'resolved', '--question' => 'q?', '--kind' => 'ask', '--model' => 'sonnet',
        ]);
        $this->ok('question:resolve', [
            '--question' => (string) $this->intAt($resolved, 'id'),
            '--state' => 'accepted',
            '--resolution-quality' => 'direct',
        ]);

        $openList = $this->listAt($this->ok('question:list', ['--ticket' => (string) $ticketId, '--group' => 'open']), 'questions');
        $this->assertCount(1, $openList);
        $this->assertSame($this->intAt($open, 'id'), $this->itemAt($openList, 0)['id']);

        $unprocessedList = $this->listAt(
            $this->ok('question:list', ['--ticket' => (string) $ticketId, '--group' => 'resolved_unprocessed']),
            'questions',
        );
        $this->assertCount(1, $unprocessedList);
        $this->assertSame($this->intAt($resolved, 'id'), $this->itemAt($unprocessedList, 0)['id']);
    }

    #[Test]
    public function it_resolves_a_question(): void
    {
        $ticketId = $this->addTicket();
        $added = $this->ok('question:add', [
            '--ticket' => (string) $ticketId, '--name' => 'q', '--question' => 'Which approach?', '--kind' => 'ask', '--model' => 'sonnet',
        ]);

        $data = $this->ok('question:resolve', [
            '--question' => (string) $this->intAt($added, 'id'),
            '--state' => 'answered',
            '--resolution-quality' => 'clarified',
            '--answer' => 'Use approach B.',
        ]);

        $this->assertSame('answered', $data['state']);
        $this->assertSame('Use approach B.', $data['answer']);
        $this->assertSame('clarified', $data['resolution_quality']);
    }

    #[Test]
    public function it_withdraws_a_question(): void
    {
        $ticketId = $this->addTicket();
        $added = $this->ok('question:add', [
            '--ticket' => (string) $ticketId, '--name' => 'q', '--question' => 'Still relevant?', '--kind' => 'ask', '--model' => 'sonnet',
        ]);

        $data = $this->ok('question:withdraw', [
            '--question' => (string) $this->intAt($added, 'id'),
            '--reason' => 'No longer relevant.',
        ]);

        $this->assertSame('withdrawn', $data['state']);
        $this->assertNotNull($data['processed_at']);
    }

    #[Test]
    public function it_marks_a_question_processed(): void
    {
        $ticketId = $this->addTicket();
        $added = $this->ok('question:add', [
            '--ticket' => (string) $ticketId, '--name' => 'q', '--question' => 'Which way?', '--kind' => 'ask', '--model' => 'sonnet',
        ]);
        $this->ok('question:resolve', [
            '--question' => (string) $this->intAt($added, 'id'),
            '--state' => 'accepted',
            '--resolution-quality' => 'direct',
        ]);

        $data = $this->ok('question:process', ['--question' => (string) $this->intAt($added, 'id')]);
        $this->assertNotNull($data['processed_at']);
    }

    #[Test]
    public function it_rejects_invalid_kind_on_add(): void
    {
        $ticketId = $this->addTicket();
        $error = $this->err('question:add', [
            '--ticket' => (string) $ticketId, '--name' => 'q', '--question' => 'q?', '--kind' => 'nonsense', '--model' => 'sonnet',
        ]);
        $this->assertSame('invalid_question_kind', $error['code']);
    }

    #[Test]
    public function it_carries_questions_in_the_deep_ticket_view(): void
    {
        // Guards the bootstrap wiring: Application must pass QuestionRepository
        // into TicketService, or ticket:show --deep silently returns no
        // questions even when they exist.
        $ticketId = $this->addTicket();
        $this->ok('question:add', [
            '--ticket' => (string) $ticketId,
            '--name' => 'naming',
            '--question' => 'foo or bar?',
            '--kind' => 'ask',
            '--model' => 'sonnet',
        ]);

        $deep = $this->ok('ticket:show', ['--ticket' => (string) $ticketId, '--deep' => null]);
        $questions = $this->listAt($deep, 'questions');
        $this->assertCount(1, $questions);
        $this->assertSame('naming', $this->itemAt($questions, 0)['name']);
    }
}
