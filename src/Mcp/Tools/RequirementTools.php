<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\AiLib\Schemas\RequirementIn;
use AiToolset\AiLib\Services\RequirementService;
use AiToolset\Tm\Mcp\BaseTools;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class RequirementTools extends BaseTools
{
    public function __construct(private readonly RequirementService $service)
    {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_requirement_add', description: 'Add a requirement to a ticket.')]
    #[Schema(additionalProperties: false)]
    public function add(
        int $ticket,
        string $name,
        string $description = '',
        string $ai_description = '',
        string $verification = 'unverified',
        ?int $before = null,
        ?int $after = null,
    ): array {
        try {
            return $this->success($this->service->add(new RequirementIn(
                ticketId: $ticket,
                name: $name,
                description: $description,
                aiDescription: $ai_description,
                verification: $verification,
                beforeId: $before,
                afterId: $after,
            )));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_requirement_list', description: 'List requirements in a ticket.')]
    #[Schema(additionalProperties: false)]
    public function list(int $ticket): array
    {
        try {
            return $this->success(['requirements' => $this->service->list(ticketId: $ticket)]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_requirement_show', description: 'Show a requirement.')]
    #[Schema(additionalProperties: false)]
    public function show(int $requirement, bool $deep = false): array
    {
        try {
            $out = $deep ? $this->service->showDeep($requirement) : $this->service->show($requirement);

            return $this->success($out);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_requirement_set', description: 'Update a requirement.')]
    #[Schema(additionalProperties: false)]
    public function set(
        int $requirement,
        ?string $name = null,
        ?string $description = null,
        ?string $ai_description = null,
        ?string $verification = null,
    ): array {
        try {
            return $this->success($this->service->set(
                id: $requirement,
                name: $name,
                description: $description,
                aiDescription: $ai_description,
                verification: $verification,
            ));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_requirement_move', description: 'Reorder a requirement relative to a sibling. Provide before or after (not both).')]
    #[Schema(additionalProperties: false)]
    public function move(int $requirement, ?int $before = null, ?int $after = null): array
    {
        try {
            if ($before === null && $after === null) {
                throw new \InvalidArgumentException('Either before or after is required for tm_requirement_move.');
            }

            $movingReq = $this->service->show($requirement);
            $siblingId = $before ?? $after;
            $sibling = $this->service->show((int) $siblingId);

            $adjustedOrder = $sibling->order > $movingReq->order ? $sibling->order - 1 : $sibling->order;
            $toOrder = $before !== null ? $adjustedOrder : $adjustedOrder + 1;

            return $this->success($this->service->move($requirement, $toOrder));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_requirement_delete', description: 'Delete a requirement.')]
    #[Schema(additionalProperties: false)]
    public function delete(int $requirement): array
    {
        try {
            $this->service->delete($requirement);

            return $this->success(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
