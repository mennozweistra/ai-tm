<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\Tm\Mcp\BaseTools;
use AiToolset\AiLib\Schemas\TicketIn;
use AiToolset\AiLib\Services\TicketService;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class TicketTools extends BaseTools
{
    public function __construct(
        private readonly TicketService $service,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_ticket_add', description: 'Create a ticket in a project from a template. template is required: it names an existing template (see tm_template_list). Its phases and tasks are copied onto the new ticket. Passing no template, an empty string, or an unknown name is rejected with an invalid_argument error.')]
    #[Schema(additionalProperties: false)]
    public function add(
        int $project,
        string $name,
        string $description = '',
        string $ai_description = '',
        string $status = 'pending',
        string $type = 'feature',
        ?string $priority = null,
        ?string $template = null,
    ): array {
        try {
            return $this->success($this->service->addFromTemplate(new TicketIn(
                projectId: $project,
                name: $name,
                description: $description,
                aiDescription: $ai_description,
                status: $status,
                type: $type,
                priority: $this->parsePriority($priority),
            ), $template));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    private function parsePriority(?string $priority): ?int
    {
        if ($priority === null) {
            return null;
        }

        return match ($priority) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            default => throw new \InvalidArgumentException('Parameter priority must be one of: low, medium, high.'),
        };
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_ticket_list', description: 'List tickets in a project.')]
    #[Schema(additionalProperties: false)]
    public function list(int $project, bool $archived = false): array
    {
        try {
            return $this->success(['tickets' => $this->service->list(
                projectId: $project,
                includeArchived: $archived,
            )]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_ticket_show', description: 'Show a ticket. Pass deep=true for the full ticket with phases, tasks, requirements, logs, and status transitions. Pass outline=true for a compact structural tree (ticket, phases, tasks, requirements) with long-form text and history omitted, sized to stay within the MCP output limit; outline takes precedence over deep when both are set.')]
    #[Schema(additionalProperties: false)]
    public function show(int $ticket, bool $deep = false, bool $outline = false): array
    {
        try {
            $out = match (true) {
                $outline => $this->service->showOutline($ticket),
                $deep => $this->service->showDeep($ticket),
                default => $this->service->show($ticket),
            };

            return $this->success($out);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_ticket_set', description: 'Update a ticket.')]
    #[Schema(additionalProperties: false)]
    public function set(
        int $ticket,
        ?string $name = null,
        ?string $description = null,
        ?string $ai_description = null,
        ?string $status = null,
        ?string $type = null,
        ?string $priority = null,
    ): array {
        try {
            return $this->success($this->service->set(
                id: $ticket,
                name: $name,
                description: $description,
                aiDescription: $ai_description,
                status: $status,
                type: $type,
                priority: $this->parsePriority($priority),
            ));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_ticket_archive', description: 'Archive a ticket.')]
    #[Schema(additionalProperties: false)]
    public function archive(int $ticket): array
    {
        try {
            return $this->success($this->service->archive($ticket));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_ticket_restore', description: 'Restore an archived ticket.')]
    #[Schema(additionalProperties: false)]
    public function restore(int $ticket): array
    {
        try {
            return $this->success($this->service->restore($ticket));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
