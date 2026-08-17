<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\AiLib\Schemas\QuestionIn;
use AiToolset\AiLib\Services\QuestionService;
use AiToolset\Tm\Mcp\BaseTools;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class QuestionTools extends BaseTools
{
    public function __construct(private readonly QuestionService $service)
    {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'tm_question_add',
        description: 'Store a question the moment it arises, for any process that needs the user\'s '
            . 'input: grind subagents, Devil\'s Advocate review, QA, planning, grill, or live conversation. '
            . 'Write it self-contained for a cold reader who was not in the conversation: state the question, '
            . 'the background that produced it, and a concrete recommendation. Do not defer writing it and do '
            . 'not paraphrase it later in a report — reports replay the stored text unchanged.',
    )]
    #[Schema(additionalProperties: false)]
    public function add(
        int $ticket,
        string $name,
        string $question,
        string $kind,
        string $model,
        ?int $task = null,
        string $background = '',
        string $recommendation = '',
    ): array {
        try {
            return $this->success($this->service->add(new QuestionIn(
                ticketId: $ticket,
                name: $name,
                question: $question,
                kind: $kind,
                model: $model,
                taskId: $task,
                background: $background,
                recommendation: $recommendation,
            )));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'tm_question_list',
        description: 'List a ticket\'s questions. Filter by exact state, or by group: "open" (awaiting '
            . 'the user), "resolved_unprocessed" (answered but the consequences are not yet handled), or '
            . '"done" (answered and processed). Use this to find open questions before starting work on a '
            . 'ticket, or to find resolved-unprocessed questions whose answers still need to be acted on.',
    )]
    #[Schema(additionalProperties: false)]
    public function list(int $ticket, ?string $state = null, ?string $group = null): array
    {
        try {
            return $this->success(['questions' => $this->service->list(ticketId: $ticket, state: $state, group: $group)]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_question_show', description: 'Show a single question with its full text and current state.')]
    #[Schema(additionalProperties: false)]
    public function show(int $question): array
    {
        try {
            return $this->success($this->service->show($question));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'tm_question_resolve',
        description: 'Resolve an open question the same turn the user answers it. Use state "accepted" '
            . 'when the user accepts the stored recommendation as-is, or "answered" when the user gives a '
            . 'different or richer answer (pass it in answer). resolution_quality records how much the '
            . 'answer cost to get: "direct" (the recommendation or a plain answer), "clarified" (needed a '
            . 'follow-up), or "deepened" (needed real discussion).',
    )]
    #[Schema(additionalProperties: false)]
    public function resolve(int $question, string $state, string $resolution_quality, ?string $answer = null): array
    {
        try {
            return $this->success($this->service->resolve(
                id: $question,
                state: $state,
                answer: $answer,
                resolutionQuality: $resolution_quality,
            ));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'tm_question_withdraw',
        description: 'Withdraw an open question that no longer needs an answer, for example when its '
            . 'premise disappeared. Pass a reason explaining why it no longer applies. A withdrawn question '
            . 'is immediately final and processed — no follow-up work is expected.',
    )]
    #[Schema(additionalProperties: false)]
    public function withdraw(int $question, string $reason): array
    {
        try {
            return $this->success($this->service->withdraw($question, $reason));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'tm_question_process',
        description: 'Mark a resolved question\'s consequences as handled — call this once the answer has '
            . 'been acted on (code changed, requirement updated, decision recorded). Moves the question from '
            . '"resolved_unprocessed" into "done".',
    )]
    #[Schema(additionalProperties: false)]
    public function process(int $question): array
    {
        try {
            return $this->success($this->service->markProcessed($question));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
