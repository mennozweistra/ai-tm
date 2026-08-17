<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\Tm\Mcp\BaseTools;
use AiToolset\AiLib\Schemas\LogEntryIn;
use AiToolset\AiLib\Services\LogService;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class LogTools extends BaseTools
{
    public function __construct(private readonly LogService $service)
    {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_log_add', description: 'Append a log entry to a ticket, phase, or task. Also the end-of-turn logging tool driven by the tm Stop hook. type is one of: progress, decision, blocker, note, code_fix, assumption — pick the one that fits.')]
    #[Schema(additionalProperties: false)]
    public function add(
        string $type,
        ?int $ticket = null,
        ?int $phase = null,
        ?int $task = null,
        string $title = '',
        string $ai_content = '',
    ): array {
        try {
            return $this->success($this->service->add(new LogEntryIn(
                logType: $type,
                title: $title,
                aiContent: $ai_content,
                ticketId: $ticket,
                phaseId: $phase,
                taskId: $task,
            )));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_log_list', description: 'List log entries for a ticket, phase, or task.')]
    #[Schema(additionalProperties: false)]
    public function list(
        ?int $ticket = null,
        ?int $phase = null,
        ?int $task = null,
        string $sort = 'asc',
    ): array {
        try {
            if ($task !== null) {
                $logs = $this->service->listByTask($task, $sort);
            } elseif ($phase !== null) {
                $logs = $this->service->listByPhase($phase, $sort);
            } elseif ($ticket !== null) {
                $logs = $this->service->listByTicket($ticket, $sort);
            } else {
                throw new \InvalidArgumentException('One of ticket, phase, or task is required.');
            }

            return $this->success(['logs' => $logs]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
