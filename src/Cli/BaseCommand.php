<?php

declare(strict_types=1);

namespace AiToolset\Tm\Cli;

use AiToolset\AiLib\Serializer;
use AiToolset\AiLib\Domain\Exception\AlreadyExistsException;
use AiToolset\AiLib\Domain\Exception\ConfigInvalidException;
use AiToolset\AiLib\Domain\Exception\DomainException;
use AiToolset\AiLib\Domain\Exception\ForbiddenAutoStatusTransitionException;
use AiToolset\AiLib\Domain\Exception\ForbiddenQuestionTransitionException;
use AiToolset\AiLib\Domain\Exception\InvalidAttemptsException;
use AiToolset\AiLib\Domain\Exception\SchemaAheadException;
use AiToolset\AiLib\Domain\Exception\SchemaOutdatedException;
use AiToolset\AiLib\Domain\Exception\HasChildrenException;
use AiToolset\AiLib\Domain\Exception\HasLogsException;
use AiToolset\AiLib\Domain\Exception\InvalidLogTypeException;
use AiToolset\AiLib\Domain\Exception\InvalidQuestionKindException;
use AiToolset\AiLib\Domain\Exception\InvalidQuestionStateException;
use AiToolset\AiLib\Domain\Exception\InvalidResolutionQualityException;
use AiToolset\AiLib\Domain\Exception\InvalidStatusException;
use AiToolset\AiLib\Domain\Exception\InvalidTemplateException;
use AiToolset\AiLib\Domain\Exception\InvalidVerificationException;
use AiToolset\AiLib\Domain\Exception\NotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected Serializer $serializer;

    public function __construct()
    {
        parent::__construct();
        $this->serializer = new Serializer();
    }

    protected function success(OutputInterface $output, mixed $data): int
    {
        $output->writeln((string) json_encode(['ok' => true, 'data' => $this->serializer->serialize($data)], JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }

    protected function handleError(OutputInterface $output, \Throwable $e): int
    {
        $err = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $err->writeln((string) json_encode([
            'ok' => false,
            'error' => [
                'code' => $this->errorCode($e),
                'message' => $e->getMessage(),
                'type' => $e::class,
            ],
        ], JSON_UNESCAPED_UNICODE));

        return Command::FAILURE;
    }

    protected function requireOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Option --{$name} is required.");
        }

        return $value;
    }

    protected function requireIntOption(InputInterface $input, string $name): int
    {
        $value = $input->getOption($name);
        if (!is_string($value) || !ctype_digit($value)) {
            throw new \InvalidArgumentException("Option --{$name} must be an integer.");
        }

        return (int) $value;
    }

    protected function optionalIntOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException("Option --{$name} must be an integer.");
        }

        return (int) $value;
    }

    protected function parseBool(string $raw, string $flag): bool
    {
        if (in_array($raw, ['true', '1'], strict: true)) {
            return true;
        }

        if (in_array($raw, ['false', '0'], strict: true)) {
            return false;
        }

        throw new \InvalidArgumentException("Option --{$flag} must be true, false, 1, or 0.");
    }

    private function errorCode(\Throwable $e): string
    {
        return match (true) {
            $e instanceof NotFoundException => 'not_found',
            $e instanceof AlreadyExistsException => 'already_exists',
            $e instanceof ForbiddenAutoStatusTransitionException => 'forbidden_auto_status_transition',
            $e instanceof InvalidStatusException => 'invalid_status',
            $e instanceof InvalidAttemptsException => 'invalid_argument',
            $e instanceof InvalidLogTypeException => 'invalid_log_type',
            $e instanceof InvalidVerificationException => 'invalid_verification',
            $e instanceof InvalidQuestionKindException => 'invalid_question_kind',
            $e instanceof InvalidQuestionStateException => 'invalid_question_state',
            $e instanceof InvalidResolutionQualityException => 'invalid_resolution_quality',
            $e instanceof ForbiddenQuestionTransitionException => 'forbidden_question_transition',
            $e instanceof InvalidTemplateException => 'invalid_argument',
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
