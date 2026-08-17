<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\Tm\Mcp\BaseTools;
use AiToolset\AiLib\Schemas\TaskIn;
use AiToolset\AiLib\Services\TaskService;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class TaskTools extends BaseTools
{
    public function __construct(
        private readonly TaskService $service,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_task_add', description: 'Add a task to a phase.')]
    #[Schema(additionalProperties: false)]
    public function add(
        int $phase,
        string $name,
        ?string $model,
        string $description = '',
        string $ai_description = '',
        string $actor = 'agent',
        string $status = 'pending',
        int $max_attempts = 0,
        ?int $before = null,
        ?int $after = null,
    ): array {
        try {
            return $this->success($this->service->add(new TaskIn(
                phaseId: $phase,
                name: $name,
                model: $model,
                description: $description,
                aiDescription: $ai_description,
                status: $status,
                actor: $actor,
                maxAttempts: $max_attempts,
                beforeId: $before,
                afterId: $after,
            )));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'tm_task_list',
        description: 'List tasks. Optionally scope to a phase or project, filter by created/started/finished '
            . 'date ranges (plain YYYY-MM-DD dates, matched against the date part of the stored timestamp; '
            . 'no timezone is involved), and order by order/created/started/finished.',
    )]
    #[Schema(additionalProperties: false)]
    public function list(
        ?int $phase = null,
        bool $archived = false,
        ?int $project = null,
        ?string $created_from = null,
        ?string $created_to = null,
        ?string $started_from = null,
        ?string $started_to = null,
        ?string $finished_from = null,
        ?string $finished_to = null,
        ?string $order_by = null,
    ): array {
        try {
            return $this->success(['tasks' => $this->service->list(
                phaseId: $phase,
                includeArchived: $archived,
                projectId: $project,
                createdFrom: $created_from,
                createdTo: $created_to,
                startedFrom: $started_from,
                startedTo: $started_to,
                finishedFrom: $finished_from,
                finishedTo: $finished_to,
                orderBy: $order_by ?? 'order',
            )]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_task_show', description: 'Show a task.')]
    #[Schema(additionalProperties: false)]
    public function show(int $task, bool $deep = false): array
    {
        try {
            $out = $deep ? $this->service->showDeep($task) : $this->service->show($task);

            return $this->success($out);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_task_set', description: 'Update a task. Provide phase to reassign to a different phase.')]
    #[Schema(additionalProperties: false)]
    public function set(
        int $task,
        ?int $phase = null,
        ?string $name = null,
        ?string $description = null,
        ?string $ai_description = null,
        ?string $status = null,
        ?string $result = null,
        ?string $ai_result = null,
        ?string $actor = null,
        ?int $max_attempts = null,
        ?int $attempts = null,
        ?string $model = null,
    ): array {
        try {
            if ($phase !== null) {
                $this->service->reassign($task, $phase);
            }

            $hasSetFields = $name !== null || $description !== null || $ai_description !== null
                || $result !== null || $ai_result !== null || $status !== null || $actor !== null
                || $max_attempts !== null || $attempts !== null || $model !== null;

            if ($hasSetFields || $phase === null) {
                $out = $this->service->set(
                    id: $task,
                    name: $name,
                    description: $description,
                    aiDescription: $ai_description,
                    status: $status,
                    result: $result,
                    aiResult: $ai_result,
                    actor: $actor,
                    model: $model,
                    maxAttempts: $max_attempts,
                    attempts: $attempts,
                );
            } else {
                $out = $this->service->show($task);
            }

            return $this->success($out);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_task_move', description: 'Reorder a task relative to a sibling. Provide before or after (not both).')]
    #[Schema(additionalProperties: false)]
    public function move(int $task, ?int $before = null, ?int $after = null): array
    {
        try {
            if ($before === null && $after === null) {
                throw new \InvalidArgumentException('Either before or after is required for tm_task_move.');
            }

            $movingTask = $this->service->show($task);
            $siblingId = $before ?? $after;
            $sibling = $this->service->show((int) $siblingId);

            $adjustedOrder = $sibling->order > $movingTask->order ? $sibling->order - 1 : $sibling->order;
            $toOrder = $before !== null ? $adjustedOrder : $adjustedOrder + 1;

            return $this->success($this->service->move($task, $toOrder));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_task_delete', description: 'Delete a task (must have no logs).')]
    #[Schema(additionalProperties: false)]
    public function delete(int $task): array
    {
        try {
            $this->service->delete($task);

            return $this->success(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
