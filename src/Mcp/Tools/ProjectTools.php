<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp\Tools;

use AiToolset\Tm\Mcp\BaseTools;
use AiToolset\AiLib\Schemas\ProjectIn;
use AiToolset\AiLib\Services\ProjectService;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

final class ProjectTools extends BaseTools
{
    public function __construct(private readonly ProjectService $service)
    {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_project_add', description: 'Create a new project.')]
    #[Schema(additionalProperties: false)]
    public function add(
        string $name,
        string $path,
        string $description = '',
        string $ai_description = '',
        bool $auto_status = true,
    ): array {
        try {
            return $this->success($this->service->add(new ProjectIn(
                name: $name,
                path: $path,
                description: $description,
                aiDescription: $ai_description,
                autoStatus: $auto_status,
            )));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_project_list', description: 'List all projects.')]
    #[Schema(additionalProperties: false)]
    public function list(bool $archived = false): array
    {
        try {
            return $this->success(['projects' => $this->service->list(includeArchived: $archived)]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_project_show', description: 'Show a project.')]
    #[Schema(additionalProperties: false)]
    public function show(int $project, bool $deep = false): array
    {
        try {
            $out = $deep ? $this->service->showDeep($project) : $this->service->show($project);

            return $this->success($out);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_project_set', description: 'Update a project.')]
    #[Schema(additionalProperties: false)]
    public function set(
        int $project,
        ?string $name = null,
        ?string $path = null,
        ?string $description = null,
        ?string $ai_description = null,
        ?bool $auto_status = null,
        ?string $status = null,
    ): array {
        try {
            return $this->success($this->service->set(
                id: $project,
                name: $name,
                description: $description,
                aiDescription: $ai_description,
                path: $path,
                autoStatus: $auto_status,
                status: $status,
            ));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_project_archive', description: 'Archive a project.')]
    #[Schema(additionalProperties: false)]
    public function archive(int $project): array
    {
        try {
            return $this->success($this->service->archive($project));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'tm_project_restore', description: 'Restore an archived project.')]
    #[Schema(additionalProperties: false)]
    public function restore(int $project): array
    {
        try {
            return $this->success($this->service->restore($project));
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }
}
