<?php

declare(strict_types=1);

namespace AiToolset\Tm\Mcp;

use AiToolset\AiLib\Serializer;
use AiToolset\AiLib\Domain\Exception\AlreadyExistsException;
use AiToolset\AiLib\Domain\Exception\ConfigInvalidException;
use AiToolset\AiLib\Domain\Exception\DomainException;
use AiToolset\AiLib\Domain\Exception\ForbiddenAutoStatusTransitionException;
use AiToolset\AiLib\Domain\Exception\ForbiddenQuestionTransitionException;
use AiToolset\AiLib\Domain\Exception\SchemaAheadException;
use AiToolset\AiLib\Domain\Exception\SchemaOutdatedException;
use AiToolset\AiLib\Domain\Exception\HasChildrenException;
use AiToolset\AiLib\Domain\Exception\HasLogsException;
use AiToolset\AiLib\Domain\Exception\InvalidAttemptsException;
use AiToolset\AiLib\Domain\Exception\InvalidLogTypeException;
use AiToolset\AiLib\Domain\Exception\InvalidQuestionKindException;
use AiToolset\AiLib\Domain\Exception\InvalidQuestionStateException;
use AiToolset\AiLib\Domain\Exception\InvalidResolutionQualityException;
use AiToolset\AiLib\Domain\Exception\InvalidStatusException;
use AiToolset\AiLib\Domain\Exception\InvalidTemplateException;
use AiToolset\AiLib\Domain\Exception\InvalidVerificationException;
use AiToolset\AiLib\Domain\Exception\NotFoundException;

abstract class BaseTools
{
    protected Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer();
    }

    /** @return array<string, mixed> */
    protected function success(mixed $data): array
    {
        return ['ok' => true, 'data' => $this->serializer->serialize($data)];
    }

    /** @return array<string, mixed> */
    protected function handleError(\Throwable $e): array
    {
        return ['ok' => false, 'error' => [
            'code' => $this->errorCode($e),
            'message' => $e->getMessage(),
            'type' => $e::class,
        ]];
    }

    private function errorCode(\Throwable $e): string
    {
        return match (true) {
            $e instanceof NotFoundException => 'not_found',
            $e instanceof AlreadyExistsException => 'already_exists',
            $e instanceof ForbiddenAutoStatusTransitionException => 'forbidden_auto_status_transition',
            $e instanceof InvalidStatusException => 'invalid_status',
            $e instanceof InvalidTemplateException => 'invalid_argument',
            $e instanceof InvalidAttemptsException => 'invalid_argument',
            $e instanceof InvalidLogTypeException => 'invalid_log_type',
            $e instanceof InvalidVerificationException => 'invalid_verification',
            $e instanceof InvalidQuestionKindException => 'invalid_question_kind',
            $e instanceof InvalidQuestionStateException => 'invalid_question_state',
            $e instanceof InvalidResolutionQualityException => 'invalid_resolution_quality',
            $e instanceof ForbiddenQuestionTransitionException => 'forbidden_question_transition',
            $e instanceof HasChildrenException => 'has_children',
            $e instanceof HasLogsException => 'has_logs',
            $e instanceof SchemaOutdatedException => 'schema_outdated',
            $e instanceof SchemaAheadException => 'schema_ahead',
            $e instanceof ConfigInvalidException => 'config_invalid',
            $e instanceof DomainException => 'internal',
            $e instanceof \InvalidArgumentException => 'invalid_argument',
            default => 'internal',
        };
    }
}
