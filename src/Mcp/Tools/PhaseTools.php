<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\Tm\Mcp\BaseTools;
use AiToolset\AiLib\Schemas\PhaseIn;
use AiToolset\AiLib\Services\PhaseService;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class PhaseTools extends BaseTools
{
    public function __construct(private readonly PhaseService $service)
    {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_phase_add', description: 'Add a phase to a ticket.')]
    #[Schema(additionalProperties: false)]
    public function add(
        int $ticket,
        string $name,
        string $description = '',
        string $ai_description = '',
        string $status = 'pending',
        int $max_attempts = 0,
        ?int $before = null,
        ?int $after = null,
    ): array {
        try {
            return $this->success($this->service->add(new PhaseIn(
                ticketId: $ticket,
                name: $name,
                description: $description,
                aiDescription: $ai_description,
                status: $status,
                maxAttempts: $max_attempts,
                beforeId: $before,
                afterId: $after,
            )));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_phase_list', description: 'List phases in a ticket.')]
    #[Schema(additionalProperties: false)]
    public function list(int $ticket, bool $archived = false): array
    {
        try {
            return $this->success(['phases' => $this->service->list(
                ticketId: $ticket,
                includeArchived: $archived,
            )]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_phase_show', description: 'Show a phase.')]
    #[Schema(additionalProperties: false)]
    public function show(int $phase, bool $deep = false): array
    {
        try {
            $out = $deep ? $this->service->showDeep($phase) : $this->service->show($phase);

            return $this->success($out);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_phase_set', description: 'Update a phase.')]
    #[Schema(additionalProperties: false)]
    public function set(
        int $phase,
        ?string $name = null,
        ?string $description = null,
        ?string $ai_description = null,
        ?string $status = null,
        ?int $max_attempts = null,
        ?int $attempts = null,
    ): array {
        try {
            return $this->success($this->service->set(
                id: $phase,
                name: $name,
                description: $description,
                aiDescription: $ai_description,
                status: $status,
                maxAttempts: $max_attempts,
                attempts: $attempts,
            ));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_phase_move', description: 'Reorder a phase relative to a sibling. Provide before or after (not both).')]
    #[Schema(additionalProperties: false)]
    public function move(int $phase, ?int $before = null, ?int $after = null): array
    {
        try {
            if ($before === null && $after === null) {
                throw new \InvalidArgumentException('Either before or after is required for tm_phase_move.');
            }

            $movingPhase = $this->service->show($phase);
            $siblingId = $before ?? $after;
            $sibling = $this->service->show((int) $siblingId);

            $adjustedOrder = $sibling->order > $movingPhase->order ? $sibling->order - 1 : $sibling->order;
            $toOrder = $before !== null ? $adjustedOrder : $adjustedOrder + 1;

            return $this->success($this->service->move($phase, $toOrder));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_phase_delete', description: 'Delete a phase (must have no tasks or logs).')]
    #[Schema(additionalProperties: false)]
    public function delete(int $phase): array
    {
        try {
            $this->service->delete($phase);

            return $this->success(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
